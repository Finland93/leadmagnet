<?php
/**
 * Admin: menus, capabilities, and view routing for the CRM.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Admin
 */
class LMF93_Admin {

	const CAP = 'lmf93_manage_leads';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_grant_cap' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . LMF93_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Ensure administrators hold our capability.
	 *
	 * @return void
	 */
	public static function maybe_grant_cap() {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( self::CAP ) ) {
			$role->add_cap( self::CAP );
		}
	}

	/**
	 * Capability check helper.
	 *
	 * @return bool
	 */
	public static function can() {
		return current_user_can( self::CAP ) || current_user_can( 'manage_options' );
	}

	/**
	 * Add the admin menu.
	 *
	 * @return void
	 */
	public static function menu() {
		add_menu_page(
			__( 'LeadMagnet', 'leadmagnet' ),
			__( 'LeadMagnet', 'leadmagnet' ),
			self::CAP,
			'lmf93-leads',
			array( __CLASS__, 'render_leads' ),
			'dashicons-email-alt',
			26
		);

		add_submenu_page( 'lmf93-leads', __( 'Leads', 'leadmagnet' ), __( 'Leads', 'leadmagnet' ), self::CAP, 'lmf93-leads', array( __CLASS__, 'render_leads' ) );
		add_submenu_page( 'lmf93-leads', __( 'Billing', 'leadmagnet' ), __( 'Billing', 'leadmagnet' ), self::CAP, 'lmf93-billing', array( __CLASS__, 'render_billing' ) );
		add_submenu_page( 'lmf93-leads', __( 'Feedback', 'leadmagnet' ), __( 'Feedback', 'leadmagnet' ), self::CAP, 'lmf93-feedback', array( __CLASS__, 'render_feedback' ) );
		add_submenu_page( 'lmf93-leads', __( 'Forms', 'leadmagnet' ), __( 'Forms', 'leadmagnet' ), self::CAP, 'lmf93-forms', array( __CLASS__, 'render_forms' ) );
		add_submenu_page( 'lmf93-leads', __( 'Partners', 'leadmagnet' ), __( 'Partners', 'leadmagnet' ), self::CAP, 'lmf93-partners', array( __CLASS__, 'render_partners' ) );
		add_submenu_page( 'lmf93-leads', __( 'Emails', 'leadmagnet' ), __( 'Emails', 'leadmagnet' ), self::CAP, 'lmf93-emails', array( __CLASS__, 'render_emails' ) );
		add_submenu_page( 'lmf93-leads', __( 'Settings', 'leadmagnet' ), __( 'Settings', 'leadmagnet' ), self::CAP, 'lmf93-settings', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'lmf93-leads', __( 'Help', 'leadmagnet' ), __( 'Help', 'leadmagnet' ), self::CAP, 'lmf93-help', array( __CLASS__, 'render_help' ) );
	}

	/**
	 * Enqueue admin assets on our pages only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'lmf93' ) ) {
			return;
		}
		wp_enqueue_style( 'lmf93-admin', LMF93_URL . 'admin/admin.css', array(), LMF93_VERSION );
	}

	/**
	 * Add a Settings link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url  = admin_url( 'admin.php?page=lmf93-settings' );
		$link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'leadmagnet' ) . '</a>';
		array_unshift( $links, $link );
		return $links;
	}

	/* ---------------------------------------------------------------------
	 * POST/GET action handling (all nonce + capability guarded).
	 * ------------------------------------------------------------------- */

	/**
	 * Route admin actions.
	 *
	 * @return void
	 */
	public static function handle_actions() {
		if ( ! isset( $_REQUEST['lmf93_action'] ) ) {
			return;
		}
		if ( ! self::can() ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'leadmagnet' ) );
		}

		$action = sanitize_key( wp_unslash( $_REQUEST['lmf93_action'] ) );
		check_admin_referer( 'lmf93_' . $action );

		switch ( $action ) {
			case 'save_settings':
				self::action_save_settings();
				break;
			case 'save_emails':
				self::action_save_emails();
				break;
			case 'save_form':
				self::action_save_form();
				break;
			case 'delete_form':
				self::action_delete_form();
				break;
			case 'save_partner':
				self::action_save_partner();
				break;
			case 'delete_partner':
				self::action_delete_partner();
				break;
			case 'update_lead':
				self::action_update_lead();
				break;
			case 'delete_lead':
				self::action_delete_lead();
				break;
			case 'export_lead':
				self::action_export_lead();
				break;
			case 'mark_billed':
				self::action_mark_billed();
				break;
			case 'update_feedback':
				self::action_update_feedback();
				break;
			case 'send_test_email':
				self::action_send_test_email();
				break;
		}
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	protected static function action_save_settings() {
		$in = isset( $_POST['lmf93'] ) ? (array) wp_unslash( $_POST['lmf93'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$settings = array(
			'lead_prefix'              => isset( $in['lead_prefix'] ) ? sanitize_text_field( $in['lead_prefix'] ) : 'LM',
			'admin_email'              => isset( $in['admin_email'] ) ? sanitize_email( $in['admin_email'] ) : get_option( 'admin_email' ),
			'notify_admin'             => empty( $in['notify_admin'] ) ? 0 : 1,
			'captcha_provider'         => isset( $in['captcha_provider'] ) ? sanitize_text_field( $in['captcha_provider'] ) : 'none',
			'captcha_site_key'         => isset( $in['captcha_site_key'] ) ? sanitize_text_field( $in['captcha_site_key'] ) : '',
			'captcha_secret_key'       => isset( $in['captcha_secret_key'] ) ? sanitize_text_field( $in['captcha_secret_key'] ) : '',
			'min_submit_seconds'       => isset( $in['min_submit_seconds'] ) ? absint( $in['min_submit_seconds'] ) : 3,
			'rate_limit_max'           => isset( $in['rate_limit_max'] ) ? absint( $in['rate_limit_max'] ) : 5,
			'rate_limit_window'        => isset( $in['rate_limit_window'] ) ? absint( $in['rate_limit_window'] ) : 3600,
			'privacy_version'          => isset( $in['privacy_version'] ) ? sanitize_text_field( $in['privacy_version'] ) : '1.0',
			'privacy_page_url'         => isset( $in['privacy_page_url'] ) ? esc_url_raw( $in['privacy_page_url'] ) : '',
			'retention_days'           => isset( $in['retention_days'] ) ? absint( $in['retention_days'] ) : 730,
			'anonymize_after_days'     => isset( $in['anonymize_after_days'] ) ? absint( $in['anonymize_after_days'] ) : 730,
			'unsubscribe_page_url'     => isset( $in['unsubscribe_page_url'] ) ? esc_url_raw( $in['unsubscribe_page_url'] ) : '',
			'review_page_url'          => isset( $in['review_page_url'] ) ? esc_url_raw( $in['review_page_url'] ) : '',
			'business_review_url'      => isset( $in['business_review_url'] ) ? esc_url_raw( $in['business_review_url'] ) : '',
			'review_threshold'         => isset( $in['review_threshold'] ) ? min( 5, max( 2, absint( $in['review_threshold'] ) ) ) : 4,
			'review_notify_email'      => isset( $in['review_notify_email'] ) ? sanitize_email( $in['review_notify_email'] ) : '',
			'enable_low_review_notify' => empty( $in['enable_low_review_notify'] ) ? 0 : 1,
			'postal_input_mode'        => ( isset( $in['postal_input_mode'] ) && 'numeric' === $in['postal_input_mode'] ) ? 'numeric' : 'any',
			'postal_max_length'        => isset( $in['postal_max_length'] ) ? max( 1, min( 32, absint( $in['postal_max_length'] ) ) ) : 12,
			'postal_autocity'          => empty( $in['postal_autocity'] ) ? 0 : 1,
			'postal_data_url'          => isset( $in['postal_data_url'] ) ? esc_url_raw( $in['postal_data_url'] ) : '',
			'currency_symbol'          => isset( $in['currency_symbol'] ) ? sanitize_text_field( $in['currency_symbol'] ) : '€',
			'currency_position'        => ( isset( $in['currency_position'] ) && 'prefix' === $in['currency_position'] ) ? 'prefix' : 'suffix',
			'price_note'               => isset( $in['price_note'] ) ? sanitize_text_field( $in['price_note'] ) : '',
			'delete_data_on_uninstall' => empty( $in['delete_data_on_uninstall'] ) ? 0 : 1,
		);

		update_option( 'lmf93_settings', $settings );
		self::redirect_with_notice( 'lmf93-settings', 'saved' );
	}

	/**
	 * Save (create or update) a form from the raw JSON config editor.
	 *
	 * @return void
	 */
	protected static function action_save_form() {
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$name    = isset( $_POST['form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['form_name'] ) ) : 'Untitled';
		$status  = isset( $_POST['form_status'] ) ? sanitize_key( wp_unslash( $_POST['form_status'] ) ) : 'active';
		$raw     = isset( $_POST['form_config'] ) ? wp_unslash( $_POST['form_config'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$config = json_decode( $raw, true );
		if ( ! is_array( $config ) ) {
			self::redirect_with_notice( 'lmf93-forms', 'json_error', $form_id );
			return;
		}

		$config = self::sanitize_form_config( $config );

		if ( $form_id ) {
			LMF93_Forms::update( $form_id, array( 'name' => $name, 'status' => $status, 'config' => $config ) );
		} else {
			$form_id = LMF93_Forms::create( array( 'name' => $name, 'status' => $status, 'config' => $config ) );
		}

		self::redirect_with_notice( 'lmf93-forms', 'saved', $form_id );
	}

	/**
	 * Delete a form.
	 *
	 * @return void
	 */
	protected static function action_delete_form() {
		$form_id = isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0;
		if ( $form_id ) {
			LMF93_Forms::delete( $form_id );
		}
		self::redirect_with_notice( 'lmf93-forms', 'deleted' );
	}

	/**
	 * Save a partner.
	 *
	 * @return void
	 */
	protected static function action_save_partner() {
		$id   = isset( $_POST['partner_id'] ) ? absint( $_POST['partner_id'] ) : 0;
		$data = array(
			'company_name'        => isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '',
			'active'              => isset( $_POST['active'] ) ? 1 : 0,
			'priority'            => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10,
			'email'               => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
			'phone'               => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
			'postal_codes'        => isset( $_POST['postal_codes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['postal_codes'] ) ) : '',
			'regions'             => isset( $_POST['regions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['regions'] ) ) : '',
			'services'            => isset( $_POST['services'] ) ? sanitize_textarea_field( wp_unslash( $_POST['services'] ) ) : '',
			'max_leads_per_day'   => isset( $_POST['max_leads_per_day'] ) ? absint( $_POST['max_leads_per_day'] ) : 0,
			'max_leads_per_month' => isset( $_POST['max_leads_per_month'] ) ? absint( $_POST['max_leads_per_month'] ) : 0,
			'exclusive'           => isset( $_POST['exclusive'] ) ? 1 : 0,
		);

		if ( $id ) {
			LMF93_Routing::update_partner( $id, $data );
		} else {
			LMF93_Routing::create_partner( $data );
		}
		self::redirect_with_notice( 'lmf93-partners', 'saved' );
	}

	/**
	 * Delete a partner.
	 *
	 * @return void
	 */
	protected static function action_delete_partner() {
		$id = isset( $_REQUEST['partner_id'] ) ? absint( $_REQUEST['partner_id'] ) : 0;
		if ( $id ) {
			LMF93_Routing::delete_partner( $id );
		}
		self::redirect_with_notice( 'lmf93-partners', 'deleted' );
	}

	/**
	 * Update lead status / job status / partner.
	 *
	 * @return void
	 */
	protected static function action_update_lead() {
		$lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
		if ( ! $lead_id ) {
			return;
		}
		if ( isset( $_POST['status'] ) ) {
			LMF93_Leads::set_status( $lead_id, sanitize_key( wp_unslash( $_POST['status'] ) ) );
		}
		if ( isset( $_POST['job_status'] ) ) {
			LMF93_Leads::set_job_status( $lead_id, sanitize_key( wp_unslash( $_POST['job_status'] ) ) );
		}
		if ( isset( $_POST['partner_id'] ) && '' !== $_POST['partner_id'] ) {
			LMF93_Leads::set_partner( $lead_id, absint( $_POST['partner_id'] ) );
		}
		self::redirect_with_notice( 'lmf93-leads', 'saved', 0, array( 'lead' => $lead_id ) );
	}

	/**
	 * GDPR delete a lead.
	 *
	 * @return void
	 */
	protected static function action_delete_lead() {
		$lead_id = isset( $_REQUEST['lead_id'] ) ? absint( $_REQUEST['lead_id'] ) : 0;
		if ( $lead_id ) {
			LMF93_Leads::hard_delete( $lead_id );
		}
		self::redirect_with_notice( 'lmf93-leads', 'deleted' );
	}

	/**
	 * Mark one or more leads as billed (invoiced). Accepts a single lead_id
	 * or an array of lead_ids[].
	 *
	 * @return void
	 */
	protected static function action_mark_billed() {
		$ids = array();
		if ( isset( $_POST['lead_ids'] ) && is_array( $_POST['lead_ids'] ) ) {
			$ids = array_map( 'absint', (array) wp_unslash( $_POST['lead_ids'] ) );
		} elseif ( isset( $_REQUEST['lead_id'] ) ) {
			$ids = array( absint( $_REQUEST['lead_id'] ) );
		}
		$ids = array_filter( array_unique( $ids ) );
		foreach ( $ids as $lead_id ) {
			LMF93_Leads::set_billed( $lead_id, true );
		}
		$extra = array();
		if ( isset( $_REQUEST['partner'] ) ) {
			$extra['partner'] = absint( $_REQUEST['partner'] );
		}
		self::redirect_with_notice( 'lmf93-billing', 'billed', 0, $extra );
	}

	/**
	 * Update a feedback row's handling status (e.g. mark contacted / resolved).
	 *
	 * @return void
	 */
	protected static function action_update_feedback() {
		$fb_id  = isset( $_POST['feedback_id'] ) ? absint( $_POST['feedback_id'] ) : 0;
		$status = isset( $_POST['fb_status'] ) ? sanitize_key( wp_unslash( $_POST['fb_status'] ) ) : '';
		if ( $fb_id && $status ) {
			LMF93_Feedback::set_status( $fb_id, $status );
		}
		self::redirect_with_notice( 'lmf93-feedback', 'fb_saved' );
	}

	/**
	 * Send a test email (with fake sample data) to the admin address.
	 *
	 * @return void
	 */
	protected static function action_send_test_email() {
		$type = isset( $_POST['test_type'] ) ? sanitize_text_field( wp_unslash( $_POST['test_type'] ) ) : '';
		$ok   = $type ? LMF93_Email::send_test( $type ) : false;
		self::redirect_with_notice( 'lmf93-emails', $ok ? 'test_sent' : 'test_failed' );
	}

	/**
	 * GDPR export a lead as JSON download.
	 *
	 * @return void
	 */
	protected static function action_export_lead() {
		$lead_id = isset( $_REQUEST['lead_id'] ) ? absint( $_REQUEST['lead_id'] ) : 0;
		if ( ! $lead_id ) {
			return;
		}
		$data = LMF93_Leads::export( $lead_id );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="lead-' . $lead_id . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Sanitize a decoded form config (deep, whitelist-ish).
	 *
	 * @param array $config Raw config.
	 * @return array
	 */
	protected static function sanitize_form_config( $config ) {
		$clean = array(
			'submit_label'    => isset( $config['submit_label'] ) ? sanitize_text_field( $config['submit_label'] ) : __( 'Send', 'leadmagnet' ),
			'success_message' => isset( $config['success_message'] ) ? wp_kses_post( $config['success_message'] ) : '',
			'fields'          => array(),
			'consents'        => array(),
			'scoring'         => array(),
			'value_rules'     => array(),
		);

		$allowed_types = array( 'text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox' );

		if ( ! empty( $config['fields'] ) && is_array( $config['fields'] ) ) {
			foreach ( $config['fields'] as $f ) {
				if ( empty( $f['key'] ) ) {
					continue;
				}
				$field = array(
					'key'      => sanitize_key( $f['key'] ),
					'type'     => in_array( $f['type'] ?? 'text', $allowed_types, true ) ? $f['type'] : 'text',
					'label'    => isset( $f['label'] ) ? sanitize_text_field( $f['label'] ) : '',
					'required' => ! empty( $f['required'] ),
				);
				if ( ! empty( $f['map'] ) ) {
					$field['map'] = sanitize_key( $f['map'] );
				}
				if ( ! empty( $f['options'] ) && is_array( $f['options'] ) ) {
					$field['options'] = array();
					foreach ( $f['options'] as $opt ) {
						if ( is_array( $opt ) ) {
							$field['options'][] = array(
								'value' => sanitize_text_field( $opt['value'] ?? '' ),
								'label' => sanitize_text_field( $opt['label'] ?? ( $opt['value'] ?? '' ) ),
							);
						} else {
							$field['options'][] = array(
								'value' => sanitize_text_field( $opt ),
								'label' => sanitize_text_field( $opt ),
							);
						}
					}
				}
				$clean['fields'][] = $field;
			}
		}

		if ( ! empty( $config['consents'] ) && is_array( $config['consents'] ) ) {
			foreach ( $config['consents'] as $c ) {
				if ( empty( $c['purpose'] ) ) {
					continue;
				}
				$clean['consents'][] = array(
					'purpose'  => sanitize_key( $c['purpose'] ),
					'required' => ! empty( $c['required'] ),
					'label'    => isset( $c['label'] ) ? wp_kses_post( $c['label'] ) : '',
				);
			}
		}

		foreach ( array( 'scoring', 'value_rules' ) as $bucket ) {
			if ( ! empty( $config[ $bucket ] ) && is_array( $config[ $bucket ] ) ) {
				foreach ( $config[ $bucket ] as $rule ) {
					$r = array(
						'field'    => isset( $rule['field'] ) ? sanitize_key( $rule['field'] ) : '',
						'operator' => isset( $rule['operator'] ) ? sanitize_key( $rule['operator'] ) : 'equals',
						'value'    => isset( $rule['value'] ) ? sanitize_text_field( $rule['value'] ) : '',
					);
					if ( 'scoring' === $bucket ) {
						$r['points'] = isset( $rule['points'] ) ? (int) $rule['points'] : 0;
					} else {
						$r['base_price'] = isset( $rule['base_price'] ) ? (float) $rule['base_price'] : 0;
						if ( ! empty( $rule['multiplier_field'] ) ) {
							$r['multiplier_field'] = sanitize_key( $rule['multiplier_field'] );
							$r['multiplier_step']  = isset( $rule['multiplier_step'] ) ? (float) $rule['multiplier_step'] : 0;
						}
					}
					$clean[ $bucket ][] = $r;
				}
			}
		}

		return $clean;
	}

	/**
	 * Redirect back to an admin page with a notice code.
	 *
	 * @param string $page   Page slug.
	 * @param string $notice Notice code.
	 * @param int    $id     Optional entity id.
	 * @param array  $extra  Extra query args.
	 * @return void
	 */
	protected static function redirect_with_notice( $page, $notice, $id = 0, $extra = array() ) {
		$args = array_merge(
			array(
				'page'         => $page,
				'lmf93_notice' => $notice,
			),
			$extra
		);
		if ( $id ) {
			$args['id'] = $id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * View renderers (delegate to view files).
	 * ------------------------------------------------------------------- */

	/**
	 * Render the leads CRM (list or single).
	 *
	 * @return void
	 */
	public static function render_leads() {
		$lead_id = isset( $_GET['lead'] ) ? absint( $_GET['lead'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $lead_id ) {
			require LMF93_PATH . 'admin/views/lead-single.php';
		} else {
			require LMF93_PATH . 'admin/views/leads-list.php';
		}
	}

	/**
	 * Render forms manager.
	 *
	 * @return void
	 */
	public static function render_forms() {
		require LMF93_PATH . 'admin/views/forms.php';
	}

	/**
	 * Render partners manager.
	 *
	 * @return void
	 */
	public static function render_partners() {
		require LMF93_PATH . 'admin/views/partners.php';
	}

	/**
	 * Render settings.
	 *
	 * @return void
	 */
	public static function render_settings() {
		require LMF93_PATH . 'admin/views/settings.php';
	}

	/**
	 * Render the e-mail templates & follow-ups editor.
	 *
	 * @return void
	 */
	public static function render_emails() {
		require LMF93_PATH . 'admin/views/emails.php';
	}

	/**
	 * Render the per-partner billing page.
	 *
	 * @return void
	 */
	public static function render_billing() {
		require LMF93_PATH . 'admin/views/billing.php';
	}

	/**
	 * Render the customer feedback page.
	 *
	 * @return void
	 */
	public static function render_feedback() {
		require LMF93_PATH . 'admin/views/feedback.php';
	}

	/**
	 * Render the in-admin help / instructions page.
	 *
	 * @return void
	 */
	public static function render_help() {
		require LMF93_PATH . 'admin/views/help.php';
	}

	/**
	 * Save e-mail templates and admin-defined follow-up messages.
	 *
	 * @return void
	 */
	protected static function action_save_emails() {
		$in = isset( $_POST['lmf93'] ) ? (array) wp_unslash( $_POST['lmf93'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		// --- Transactional templates (partner + customer) ---
		$tpl_in    = isset( $in['tpl'] ) && is_array( $in['tpl'] ) ? $in['tpl'] : array();
		$templates = array();
		foreach ( array( 'partner_new_lead', 'customer_confirmation', 'customer_no_partner' ) as $key ) {
			if ( ! isset( $tpl_in[ $key ] ) ) {
				continue;
			}
			$templates[ $key ] = array(
				'enabled' => empty( $tpl_in[ $key ]['enabled'] ) ? 0 : 1,
				'subject' => isset( $tpl_in[ $key ]['subject'] ) ? sanitize_text_field( $tpl_in[ $key ]['subject'] ) : '',
				'body'    => isset( $tpl_in[ $key ]['body'] ) ? sanitize_textarea_field( $tpl_in[ $key ]['body'] ) : '',
			);
		}
		update_option( 'lmf93_email_templates', $templates );

		// --- Fixed follow-ups (review request + service reminder) ---
		$fixed_in  = isset( $in['fixed'] ) && is_array( $in['fixed'] ) ? $in['fixed'] : array();
		$fixed_out = array();
		foreach ( array( 'review_request', 'service_reminder' ) as $fkey ) {
			$row                = isset( $fixed_in[ $fkey ] ) && is_array( $fixed_in[ $fkey ] ) ? $fixed_in[ $fkey ] : array();
			$fixed_out[ $fkey ] = array(
				'enabled'    => empty( $row['enabled'] ) ? 0 : 1,
				'delay_days' => isset( $row['delay_days'] ) ? max( 0, absint( $row['delay_days'] ) ) : 0,
				'subject'    => isset( $row['subject'] ) ? sanitize_text_field( $row['subject'] ) : '',
				'body'       => isset( $row['body'] ) ? sanitize_textarea_field( $row['body'] ) : '',
			);
		}
		update_option( 'lmf93_fixed_followups', $fixed_out );

		// --- Follow-up messages (add / remove / timing) ---
		$fu_in     = isset( $in['followup'] ) && is_array( $in['followup'] ) ? $in['followup'] : array();
		$followups = array();
		$i         = 0;
		foreach ( $fu_in as $row ) {
			// Skip fully empty rows (allows "remove" by clearing subject+body).
			$subject = isset( $row['subject'] ) ? sanitize_text_field( $row['subject'] ) : '';
			$body    = isset( $row['body'] ) ? sanitize_textarea_field( $row['body'] ) : '';
			if ( '' === trim( $subject ) && '' === trim( $body ) ) {
				continue;
			}
			$i++;
			$allowed_triggers = array( 'lead_created', 'lead_assigned', 'status_won', 'job_completed' );
			$trigger          = ( isset( $row['trigger'] ) && in_array( $row['trigger'], $allowed_triggers, true ) ) ? $row['trigger'] : 'job_completed';
			$followups[] = array(
				'id'         => 'm' . $i,
				'enabled'    => empty( $row['enabled'] ) ? 0 : 1,
				'trigger'    => $trigger,
				'delay_days' => isset( $row['delay_days'] ) ? max( 0, absint( $row['delay_days'] ) ) : 0,
				'subject'    => $subject,
				'body'       => $body,
			);
		}
		update_option( 'lmf93_followups', $followups );

		self::redirect_with_notice( 'lmf93-emails', 'saved' );
	}

	/**
	 * Print a notice for a known code.
	 *
	 * @return void
	 */
	public static function print_notice() {
		if ( ! isset( $_GET['lmf93_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$code = sanitize_key( wp_unslash( $_GET['lmf93_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$map  = array(
			'saved'       => array( 'success', __( 'Saved.', 'leadmagnet' ) ),
			'deleted'     => array( 'success', __( 'Deleted.', 'leadmagnet' ) ),
			'json_error'  => array( 'error', __( 'The form settings are not valid JSON. Nothing was saved.', 'leadmagnet' ) ),
			'billed'      => array( 'success', __( 'Leads marked as billed.', 'leadmagnet' ) ),
			'fb_saved'    => array( 'success', __( 'Feedback updated.', 'leadmagnet' ) ),
			'test_sent'   => array( 'success', __( 'Test message sent to the admin email.', 'leadmagnet' ) ),
			'test_failed' => array( 'error', __( 'Sending the test message failed. Check the admin email and the message content.', 'leadmagnet' ) ),
		);
		if ( ! isset( $map[ $code ] ) ) {
			return;
		}
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $map[ $code ][0] ),
			esc_html( $map[ $code ][1] )
		);
	}
}
