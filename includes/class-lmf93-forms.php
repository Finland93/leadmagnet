<?php
/**
 * Form definitions: create/read/update forms and render their fields.
 *
 * A "form" is a reusable JSON config describing fields, consent boxes,
 * scoring rules and routing hints. This is what makes the plugin generic:
 * nothing here is heat-pump specific.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Forms
 */
class LMF93_Forms {

	/**
	 * Default starter config (generic contact-style lead form).
	 *
	 * @return array
	 */
	public static function default_config() {
		return array(
			'submit_label'  => __( 'Send request', 'leadmagnet' ),
			'success_message' => __( 'Thank you! We have received your request.', 'leadmagnet' ),
			'fields'        => array(
				array(
					'key'      => 'first_name',
					'type'     => 'text',
					'label'    => __( 'First name', 'leadmagnet' ),
					'required' => true,
					'map'      => 'first_name',
				),
				array(
					'key'      => 'last_name',
					'type'     => 'text',
					'label'    => __( 'Last name', 'leadmagnet' ),
					'required' => false,
					'map'      => 'last_name',
				),
				array(
					'key'      => 'email',
					'type'     => 'email',
					'label'    => __( 'Email', 'leadmagnet' ),
					'required' => true,
					'map'      => 'email',
				),
				array(
					'key'      => 'phone',
					'type'     => 'tel',
					'label'    => __( 'Phone', 'leadmagnet' ),
					'required' => true,
					'map'      => 'phone',
				),
				array(
					'key'      => 'postal_code',
					'type'     => 'text',
					'label'    => __( 'Postal code', 'leadmagnet' ),
					'required' => false,
					'map'      => 'postal_code',
				),
				array(
					'key'      => 'message',
					'type'     => 'textarea',
					'label'    => __( 'How can we help?', 'leadmagnet' ),
					'required' => false,
				),
			),
			// Consent checkboxes: each maps to a consent "purpose".
			'consents'      => array(
				array(
					'purpose'  => 'lead_processing',
					'required' => true,
					'label'    => __( 'I have read the privacy policy and accept the processing of my request.', 'leadmagnet' ),
				),
				array(
					'purpose'  => 'marketing_email',
					'required' => false,
					'label'    => __( 'I want to receive occasional tips and offers by email.', 'leadmagnet' ),
				),
			),
			// Scoring rules: match a field value, add points.
			'scoring'       => array(),
			// Value rules (for lead pricing / partner billing).
			'value_rules'   => array(),
		);
	}

	/**
	 * Create a new form.
	 *
	 * @param array $data name + config.
	 * @return int|false Inserted id or false.
	 */
	public static function create( $data ) {
		global $wpdb;
		$now    = current_time( 'mysql' );
		$config = isset( $data['config'] ) ? $data['config'] : self::default_config();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ok = $wpdb->insert(
			LMF93_Helpers::table( 'forms' ),
			array(
				'name'       => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : 'Untitled',
				'status'     => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
				'config'     => wp_json_encode( $config ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a form.
	 *
	 * @param int   $id   Form id.
	 * @param array $data Fields to update (name/status/config).
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$update = array( 'updated_at' => current_time( 'mysql' ) );
		$format = array( '%s' );

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s';
		}
		if ( isset( $data['status'] ) ) {
			$update['status'] = sanitize_text_field( $data['status'] );
			$format[]         = '%s';
		}
		if ( isset( $data['config'] ) ) {
			$update['config'] = wp_json_encode( $data['config'] );
			$format[]         = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->update(
			LMF93_Helpers::table( 'forms' ),
			$update,
			array( 'id' => (int) $id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Get a form row with decoded config.
	 *
	 * @param int $id Form id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'forms' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
		if ( ! $row ) {
			return null;
		}
		$row->config = json_decode( $row->config, true );
		if ( ! is_array( $row->config ) ) {
			$row->config = self::default_config();
		}
		return $row;
	}

	/**
	 * Get all forms.
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;
		$table = LMF93_Helpers::table( 'forms' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
		return $rows ? $rows : array();
	}

	/**
	 * Delete a form.
	 *
	 * @param int $id Form id.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( LMF93_Helpers::table( 'forms' ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Render the HTML for a form (used by the shortcode).
	 *
	 * @param object $form Form row with decoded config.
	 * @return string
	 */
	public static function render( $form ) {
		$config = $form->config;
		$fields = isset( $config['fields'] ) ? $config['fields'] : array();

		$rest_url = esc_url( rest_url( 'lmf93/v1/lead' ) );
		$nonce    = wp_create_nonce( 'wp_rest' );

		ob_start();
		?>
		<form class="lmf93-form" data-form-id="<?php echo (int) $form->id; ?>" data-endpoint="<?php echo $rest_url; ?>" novalidate>
			<div class="lmf93-messages" role="alert" aria-live="polite"></div>

			<?php foreach ( $fields as $field ) : ?>
				<?php echo self::render_field( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>

			<?php echo self::render_consents( $config ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php echo self::render_captcha(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<?php // Honeypot (visually hidden, not display:none, so bots that skip hidden still fill it). ?>
			<div class="lmf93-hp" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden;">
				<label><?php esc_html_e( 'Leave this field empty', 'leadmagnet' ); ?>
					<input type="text" name="lmf93_hp" tabindex="-1" autocomplete="off" value="">
				</label>
			</div>

			<input type="hidden" name="lmf93_ts" value="<?php echo (int) time(); ?>">
			<input type="hidden" name="lmf93_nonce" value="<?php echo esc_attr( $nonce ); ?>">
			<input type="hidden" name="form_id" value="<?php echo (int) $form->id; ?>">
			<input type="hidden" name="landing_page" value="" data-lmf93-landing>
			<input type="hidden" name="utm_source" value="" data-lmf93-utm="utm_source">
			<input type="hidden" name="utm_medium" value="" data-lmf93-utm="utm_medium">
			<input type="hidden" name="utm_campaign" value="" data-lmf93-utm="utm_campaign">

			<button type="submit" class="lmf93-submit">
				<?php echo esc_html( isset( $config['submit_label'] ) ? $config['submit_label'] : __( 'Send', 'leadmagnet' ) ); ?>
			</button>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render a single field.
	 *
	 * @param array $field Field definition.
	 * @return string
	 */
	protected static function render_field( $field ) {
		$key      = isset( $field['key'] ) ? $field['key'] : '';
		$type     = isset( $field['type'] ) ? $field['type'] : 'text';
		$label    = isset( $field['label'] ) ? $field['label'] : $key;
		$required = ! empty( $field['required'] );
		$name     = 'fields[' . esc_attr( $key ) . ']';
		$id       = 'lmf93_' . esc_attr( $key );
		$req_attr = $required ? ' required aria-required="true"' : '';
		$req_star = $required ? ' <span class="lmf93-req">*</span>' : '';

		if ( '' === $key ) {
			return '';
		}

		ob_start();
		echo '<div class="lmf93-field lmf93-field-' . esc_attr( $type ) . '">';

		switch ( $type ) {
			case 'textarea':
				printf( '<label for="%s">%s%s</label>', esc_attr( $id ), esc_html( $label ), $req_star ); // phpcs:ignore
				printf( '<textarea id="%s" name="%s" rows="4"%s></textarea>', esc_attr( $id ), esc_attr( $name ), $req_attr ); // phpcs:ignore
				break;

			case 'select':
				printf( '<label for="%s">%s%s</label>', esc_attr( $id ), esc_html( $label ), $req_star ); // phpcs:ignore
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $req_attr . '>'; // phpcs:ignore
				echo '<option value="">' . esc_html__( '— Select —', 'leadmagnet' ) . '</option>';
				foreach ( LMF93_Security::normalize_options( isset( $field['options'] ) ? $field['options'] : array() ) as $opt ) {
					printf( '<option value="%s">%s</option>', esc_attr( $opt['value'] ), esc_html( $opt['label'] ) );
				}
				echo '</select>';
				break;

			case 'radio':
			case 'checkbox':
				$multi = ( 'checkbox' === $type );
				echo '<span class="lmf93-group-label">' . esc_html( $label ) . $req_star . '</span>'; // phpcs:ignore
				echo '<div class="lmf93-options">';
				foreach ( LMF93_Security::normalize_options( isset( $field['options'] ) ? $field['options'] : array() ) as $i => $opt ) {
					$oid   = $id . '_' . $i;
					$oname = $multi ? $name . '[]' : $name;
					printf(
						'<label class="lmf93-opt"><input type="%s" id="%s" name="%s" value="%s"%s> %s</label>',
						esc_attr( $multi ? 'checkbox' : 'radio' ),
						esc_attr( $oid ),
						esc_attr( $oname ),
						esc_attr( $opt['value'] ),
						( $required && ! $multi && 0 === $i ) ? ' required' : '',
						esc_html( $opt['label'] )
					);
				}
				echo '</div>';
				break;

			default:
				$input_type  = in_array( $type, array( 'email', 'tel', 'number', 'text' ), true ) ? $type : 'text';
				$is_postcode = ( 'postal_code' === $key );
				$is_city     = ( 'city' === $key );
				$autocity    = (bool) LMF93_Helpers::get_option( 'postal_autocity', 0 );
				$extra_attr  = '';
				if ( $is_postcode ) {
					// Country-agnostic: input mode and length are configurable.
					$mode   = LMF93_Helpers::get_option( 'postal_input_mode', 'any' );
					$maxlen = (int) LMF93_Helpers::get_option( 'postal_max_length', 12 );
					$maxlen = $maxlen > 0 ? $maxlen : 12;
					$extra_attr = ' data-lmf93-postcode maxlength="' . esc_attr( $maxlen ) . '"';
					if ( 'numeric' === $mode ) {
						$extra_attr .= ' inputmode="numeric"';
					}
				} elseif ( $is_city && $autocity ) {
					// Only lock the city field when postal auto-fill is enabled.
					// Otherwise it is a normal, editable field that works
					// in every country.
					$extra_attr = ' data-lmf93-city readonly tabindex="-1" placeholder="' . esc_attr__( 'filled from postal code', 'leadmagnet' ) . '" class="lmf93-city-locked"';
				}
				printf( '<label for="%s">%s%s</label>', esc_attr( $id ), esc_html( $label ), $req_star ); // phpcs:ignore
				printf(
					'<input type="%s" id="%s" name="%s" autocomplete="%s"%s%s>',
					esc_attr( $input_type ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( self::autocomplete_for( $key ) ),
					$extra_attr, // phpcs:ignore
					$req_attr // phpcs:ignore
				);
				break;
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Render consent checkboxes.
	 *
	 * @param array $config Form config.
	 * @return string
	 */
	protected static function render_consents( $config ) {
		$consents = isset( $config['consents'] ) ? $config['consents'] : array();
		if ( empty( $consents ) ) {
			return '';
		}

		$privacy_url = LMF93_Helpers::get_option( 'privacy_page_url', '' );

		ob_start();
		echo '<div class="lmf93-consents">';
		foreach ( $consents as $i => $c ) {
			$purpose  = isset( $c['purpose'] ) ? $c['purpose'] : 'consent_' . $i;
			$required = ! empty( $c['required'] );
			$label    = isset( $c['label'] ) ? $c['label'] : '';
			$id       = 'lmf93_consent_' . esc_attr( $purpose );

			$label_html = wp_kses_post( $label );
			if ( $privacy_url && 'lead_processing' === $purpose ) {
				$label_html .= ' <a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener">' . esc_html__( '(tietosuojaseloste)', 'leadmagnet' ) . '</a>';
			}

			printf(
				'<label class="lmf93-consent"><input type="checkbox" id="%s" name="consents[%s]" value="1"%s> <span>%s%s</span></label>',
				esc_attr( $id ),
				esc_attr( $purpose ),
				$required ? ' required' : '',
				$label_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$required ? ' <span class="lmf93-req">*</span>' : ''
			);
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Render the CAPTCHA widget container based on provider.
	 *
	 * @return string
	 */
	protected static function render_captcha() {
		$provider = LMF93_Helpers::get_option( 'captcha_provider', 'none' );
		$site_key = LMF93_Helpers::get_option( 'captcha_site_key', '' );

		if ( 'none' === $provider || empty( $site_key ) ) {
			return '';
		}

		if ( 'turnstile' === $provider ) {
			return '<div class="lmf93-captcha cf-turnstile" data-sitekey="' . esc_attr( $site_key ) . '" data-callback="lmf93TurnstileCb"></div>'
				. '<input type="hidden" name="lmf93_captcha" value="">';
		}

		if ( 'recaptcha_v2' === $provider ) {
			return '<div class="lmf93-captcha g-recaptcha" data-sitekey="' . esc_attr( $site_key ) . '" data-callback="lmf93RecaptchaCb"></div>'
				. '<input type="hidden" name="lmf93_captcha" value="">';
		}

		// v3: token fetched in JS, stored in hidden input.
		return '<input type="hidden" name="lmf93_captcha" value="" data-lmf93-recaptcha-v3="' . esc_attr( $site_key ) . '">';
	}

	/**
	 * Map a field key to a sensible autocomplete attribute.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	protected static function autocomplete_for( $key ) {
		$map = array(
			'first_name'  => 'given-name',
			'last_name'   => 'family-name',
			'email'       => 'email',
			'phone'       => 'tel',
			'postal_code' => 'postal-code',
			'city'        => 'address-level2',
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : 'on';
	}
}
