<?php
/**
 * REST API endpoints.
 *
 * Only two public routes exist, both POST:
 *   - /lmf93/v1/lead         : submit a lead (write-only).
 *   - /lmf93/v1/preferences  : update message preferences via token.
 *
 * There is deliberately NO public GET route for leads. Any attempt to read
 * lead data through REST returns 404/permission denied.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Rest
 */
class LMF93_Rest {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'lmf93/v1',
			'/lead',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit_lead' ),
				'permission_callback' => '__return_true', // Public, but hardened inside.
			)
		);

		register_rest_route(
			'lmf93/v1',
			'/preferences',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_preferences' ),
				'permission_callback' => '__return_true',
			)
		);

		// Review route registered here too (in the core REST class) so it is
		// always available as long as the plugin's REST layer loads, even if
		// the review class init ordering ever changes. The callback delegates
		// to LMF93_Review when present.
		if ( class_exists( 'LMF93_Review' ) ) {
			register_rest_route(
				'lmf93/v1',
				'/review',
				array(
					'methods'             => 'POST',
					'callback'            => array( 'LMF93_Review', 'submit_review' ),
					'permission_callback' => '__return_true',
				)
			);
		}
	}

	/**
	 * Handle a lead submission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit_lead( WP_REST_Request $request ) {
		$params = $request->get_params();

		// 1) Verify REST nonce (CSRF protection).
		$nonce = isset( $params['lmf93_nonce'] ) ? sanitize_text_field( $params['lmf93_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'lmf93_nonce', __( 'Security check failed. Please reload the page.', 'leadmagnet' ), array( 'status' => 403 ) );
		}

		// 2) Resolve form.
		$form_id = isset( $params['form_id'] ) ? (int) $params['form_id'] : 0;
		$form    = LMF93_Forms::get( $form_id );
		if ( ! $form || 'active' !== $form->status ) {
			return new WP_Error( 'lmf93_form', __( 'This form is not available.', 'leadmagnet' ), array( 'status' => 404 ) );
		}

		// 3) Security chain (honeypot, timing, rate limit, CAPTCHA).
		$guard = LMF93_Security::guard( $params );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		// 4) Sanitize + validate fields against the form definition.
		$submitted = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();
		$submitted = LMF93_Helpers::deep_sanitize( $submitted );

		$clean = LMF93_Security::validate_fields( $form->config['fields'], $submitted );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}

		// 5) Required consents must be granted.
		$consents_in = isset( $params['consents'] ) && is_array( $params['consents'] ) ? $params['consents'] : array();
		$consent_ok  = self::check_required_consents( $form->config, $consents_in );
		if ( is_wp_error( $consent_ok ) ) {
			return $consent_ok;
		}

		// 6) Build the create payload.
		$data = array(
			'form_id'      => $form_id,
			'fields'       => $clean,
			'consents'     => array_map( 'boolval', $consents_in ),
			'source_url'   => wp_get_referer() ? wp_get_referer() : '',
			'landing_page' => isset( $params['landing_page'] ) ? esc_url_raw( $params['landing_page'] ) : '',
			'utm'          => array(
				'utm_source'   => isset( $params['utm_source'] ) ? sanitize_text_field( $params['utm_source'] ) : '',
				'utm_medium'   => isset( $params['utm_medium'] ) ? sanitize_text_field( $params['utm_medium'] ) : '',
				'utm_campaign' => isset( $params['utm_campaign'] ) ? sanitize_text_field( $params['utm_campaign'] ) : '',
			),
		);

		// 7) Persist.
		$result = LMF93_Leads::create( $data, $form );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// 8) Notifications (post-save, non-blocking failures).
		LMF93_Email::notify_admin_new_lead( $result['id'] );
		$lead       = LMF93_Leads::get( $result['id'] );
		$partner_id = ( $lead && $lead->partner_id ) ? (int) $lead->partner_id : 0;
		if ( $partner_id ) {
			// Partner gets the full customer details by email.
			LMF93_Email::notify_partner( $result['id'], $partner_id );
		}
		// Customer always gets a confirmation (forwarded, or received & searching).
		LMF93_Email::notify_customer( $result['id'], $partner_id );

		$message = isset( $form->config['success_message'] )
			? $form->config['success_message']
			: __( 'Thank you! We have received your request.', 'leadmagnet' );

		// 9) Response: only a reference, never partner/PII/scoring data.
		return new WP_REST_Response(
			array(
				'success'   => true,
				'reference' => $result['reference'],
				'message'   => $message,
			),
			200
		);
	}

	/**
	 * Validate that all required consents were granted.
	 *
	 * @param array $config     Form config.
	 * @param array $consents_in purpose => truthy.
	 * @return true|WP_Error
	 */
	protected static function check_required_consents( $config, $consents_in ) {
		$consents = isset( $config['consents'] ) ? $config['consents'] : array();
		foreach ( $consents as $c ) {
			if ( empty( $c['required'] ) ) {
				continue;
			}
			$purpose = isset( $c['purpose'] ) ? $c['purpose'] : '';
			if ( '' === $purpose ) {
				continue;
			}
			if ( empty( $consents_in[ $purpose ] ) ) {
				return new WP_Error( 'lmf93_consent', __( 'Please accept the required consent to continue.', 'leadmagnet' ), array( 'status' => 400 ) );
			}
		}
		return true;
	}

	/**
	 * Update message preferences via unsubscribe token.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_preferences( WP_REST_Request $request ) {
		$token = sanitize_text_field( $request->get_param( 'lmf93_token' ) );
		if ( empty( $token ) ) {
			return new WP_Error( 'lmf93_token', __( 'Invalid link.', 'leadmagnet' ), array( 'status' => 400 ) );
		}

		$lead = LMF93_Leads::get_by_token( $token );
		if ( ! $lead ) {
			return new WP_Error( 'lmf93_token', __( 'This link is no longer valid.', 'leadmagnet' ), array( 'status' => 404 ) );
		}

		global $wpdb;
		$unsub_all = $request->get_param( 'unsubscribe_all' ) ? 1 : 0;

		$update = array(
			'unsubscribe_all'       => $unsub_all,
			'pref_service_reminder' => $request->get_param( 'pref_service_reminder' ) ? 1 : 0,
			'pref_review_request'   => $request->get_param( 'pref_review_request' ) ? 1 : 0,
			'pref_marketing'        => $request->get_param( 'pref_marketing' ) ? 1 : 0,
			'updated_at'            => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			LMF93_Helpers::table( 'leads' ),
			$update,
			array( 'id' => (int) $lead->id ),
			array( '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		LMF93_Leads::add_event( $lead->id, 'preferences', $unsub_all ? 'Unsubscribed from all' : 'Preferences updated' );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Your preferences have been saved.', 'leadmagnet' ),
			),
			200
		);
	}
}
