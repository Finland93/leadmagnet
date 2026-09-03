<?php
/**
 * Email dispatch.
 *
 * The plugin supports a "lead marketplace" style workflow but works equally
 * well for a single business that just wants confirmations and notifications:
 *
 *  - The PARTNER (if partner routing is used) receives the full customer
 *    details by email so they can contact the customer directly.
 *  - The CUSTOMER receives a confirmation that their request was received (and
 *    forwarded to a local partner, if applicable).
 *  - The ADMIN (site owner) gets a minimal, PII-free heads-up and uses the
 *    dashboard to review leads.
 *
 * All customer/partner templates are admin-editable (subject + body) with
 * placeholders, so messages can be added, changed, or disabled without code.
 * Every submitted field is also available as {field_key} and {field_key_label},
 * which keeps the plugin industry-agnostic.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Email
 */
class LMF93_Email {

	/**
	 * Default templates. Used when an admin has not overridden them.
	 * Placeholders are replaced by build_placeholders().
	 *
	 * @return array
	 */
	public static function default_templates() {
		return array(
			'partner_new_lead' => array(
				'enabled' => 1,
				'subject' => __( 'New lead {reference} – {postal_code}', 'leadmagnet' ),
				'body'    =>
					__( "Hello {partner_company},", 'leadmagnet' ) . "\n\n" .
					__( "A new lead from your area has been routed to you. Please contact the customer as soon as possible.", 'leadmagnet' ) . "\n\n" .
					__( "REFERENCE: {reference}", 'leadmagnet' ) . "\n" .
					__( "RECEIVED: {created_at}", 'leadmagnet' ) . "\n\n" .
					__( "— CUSTOMER —", 'leadmagnet' ) . "\n" .
					__( "Name: {first_name} {last_name}", 'leadmagnet' ) . "\n" .
					__( "Phone: {phone}", 'leadmagnet' ) . "\n" .
					__( "Email: {email}", 'leadmagnet' ) . "\n" .
					__( "Postal code: {postal_code}", 'leadmagnet' ) . "\n" .
					__( "City: {city}", 'leadmagnet' ) . "\n\n" .
					__( "— MESSAGE —", 'leadmagnet' ) . "\n" .
					__( "{message}", 'leadmagnet' ) . "\n\n" .
					__( "Please respond promptly – the customer is waiting to be contacted.", 'leadmagnet' ) . "\n\n" .
					"{site_name}",
			),
			'customer_confirmation' => array(
				'enabled' => 1,
				'subject' => __( 'Thank you! Your request has been received ({reference})', 'leadmagnet' ),
				'body'    =>
					__( "Hi {first_name},", 'leadmagnet' ) . "\n\n" .
					__( "thank you for your request. We have received it and will be in touch shortly.", 'leadmagnet' ) . "\n\n" .
					__( "— YOUR REQUEST —", 'leadmagnet' ) . "\n" .
					__( "Reference: {reference}", 'leadmagnet' ) . "\n" .
					__( "Message: {message}", 'leadmagnet' ) . "\n\n" .
					__( "If your details change or you want to cancel your request, just reply to this message.", 'leadmagnet' ) . "\n\n" .
					__( "Best regards,", 'leadmagnet' ) . "\n" .
					"{site_name}",
			),
			'customer_no_partner' => array(
				'enabled' => 1,
				'subject' => __( 'We received your request ({reference})', 'leadmagnet' ),
				'body'    =>
					__( "Hi {first_name},", 'leadmagnet' ) . "\n\n" .
					__( "thank you for your request. We have received it and will be in touch shortly.", 'leadmagnet' ) . "\n\n" .
					__( "— YOUR REQUEST —", 'leadmagnet' ) . "\n" .
					__( "Reference: {reference}", 'leadmagnet' ) . "\n" .
					__( "Message: {message}", 'leadmagnet' ) . "\n\n" .
					__( "Best regards,", 'leadmagnet' ) . "\n" .
					"{site_name}",
			),
		);
	}

	/**
	 * Read a template (admin override merged over defaults).
	 *
	 * @param string $key Template key.
	 * @return array{enabled:int,subject:string,body:string}
	 */
	public static function get_template( $key ) {
		$defaults  = self::default_templates();
		$overrides = get_option( 'lmf93_email_templates', array() );
		$tpl       = isset( $defaults[ $key ] ) ? $defaults[ $key ] : array( 'enabled' => 0, 'subject' => '', 'body' => '' );

		if ( isset( $overrides[ $key ] ) && is_array( $overrides[ $key ] ) ) {
			$tpl = array_merge( $tpl, $overrides[ $key ] );
		}
		return $tpl;
	}

	/**
	 * Build the placeholder map for a lead.
	 *
	 * Besides the standard placeholders (name, email, phone, postal code,
	 * city, reference, etc.), EVERY submitted field is exposed as
	 * {field_key} (raw value) and {field_key_label} (option label if the
	 * field has choices). This keeps templates industry-agnostic.
	 *
	 * @param int         $lead_id Lead id.
	 * @param object|null $partner Optional partner row.
	 * @return array
	 */
	public static function build_placeholders( $lead_id, $partner = null ) {
		$lead = LMF93_Leads::get( $lead_id );
		if ( ! $lead ) {
			return array();
		}

		$fields = json_decode( isset( $lead->payload ) ? $lead->payload : '', true );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}
		$form   = LMF93_Forms::get( (int) $lead->form_id );
		$config = $form ? $form->config : array();

		$label_for = function ( $field_key, $raw_value ) use ( $config ) {
			if ( is_array( $raw_value ) ) {
				$raw_value = implode( ', ', $raw_value );
			}
			if ( empty( $config['fields'] ) ) {
				return $raw_value;
			}
			foreach ( $config['fields'] as $f ) {
				if ( isset( $f['key'] ) && $f['key'] === $field_key && ! empty( $f['options'] ) ) {
					foreach ( LMF93_Security::normalize_options( $f['options'] ) as $opt ) {
						if ( (string) $opt['value'] === (string) $raw_value ) {
							return $opt['label'];
						}
					}
				}
			}
			return $raw_value;
		};

		$get = function ( $key ) use ( $fields ) {
			return isset( $fields[ $key ] ) ? $fields[ $key ] : '';
		};

		// A "message" placeholder that works whether the field is called
		// message, notes, comment or description.
		$message = '';
		foreach ( array( 'message', 'notes', 'comment', 'description' ) as $mk ) {
			if ( ! empty( $fields[ $mk ] ) ) {
				$message = is_array( $fields[ $mk ] ) ? implode( ', ', $fields[ $mk ] ) : $fields[ $mk ];
				break;
			}
		}

		$ph = array(
			'{reference}'   => LMF93_Helpers::lead_reference( $lead_id ),
			'{created_at}'  => isset( $lead->created_at ) ? $lead->created_at : '',
			'{first_name}'  => isset( $lead->first_name ) ? $lead->first_name : '',
			'{last_name}'   => isset( $lead->last_name ) ? $lead->last_name : '',
			'{email}'       => isset( $lead->email ) ? $lead->email : '',
			'{phone}'       => isset( $lead->phone ) ? $lead->phone : '',
			'{postal_code}' => isset( $lead->postal_code ) ? $lead->postal_code : '',
			'{city}'        => ! empty( $lead->city ) ? $lead->city : $get( 'city' ),
			'{message}'     => $message,
			'{site_name}'   => get_bloginfo( 'name' ),
		);

		// Expose every submitted field generically.
		foreach ( $fields as $key => $value ) {
			$raw = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
			$ph[ '{' . $key . '}' ]        = $raw;
			$ph[ '{' . $key . '_label}' ]  = $label_for( $key, $value );
		}

		if ( $partner ) {
			$ph['{partner_company}'] = isset( $partner->company_name ) ? $partner->company_name : '';
			$ph['{partner_email}']   = isset( $partner->email ) ? $partner->email : '';
			$ph['{partner_phone}']   = isset( $partner->phone ) ? $partner->phone : '';
		} else {
			$ph['{partner_company}'] = '';
			$ph['{partner_email}']   = '';
			$ph['{partner_phone}']   = '';
		}

		/**
		 * Filter the placeholder map before templates are rendered.
		 *
		 * @param array  $ph      Placeholder map.
		 * @param int    $lead_id Lead id.
		 * @param object $lead    Lead row.
		 */
		return apply_filters( 'lmf93_email_placeholders', $ph, $lead_id, $lead );
	}

	/**
	 * Render a template with placeholders.
	 *
	 * @param array $tpl          Template (subject/body).
	 * @param array $placeholders Placeholder map.
	 * @return array{subject:string,body:string}
	 */
	protected static function render( $tpl, $placeholders ) {
		$search  = array_keys( $placeholders );
		$replace = array_values( $placeholders );
		return array(
			'subject' => str_replace( $search, $replace, isset( $tpl['subject'] ) ? $tpl['subject'] : '' ),
			'body'    => str_replace( $search, $replace, isset( $tpl['body'] ) ? $tpl['body'] : '' ),
		);
	}

	/**
	 * Notify the assigned partner with FULL customer details.
	 *
	 * @param int $lead_id    Lead id.
	 * @param int $partner_id Partner id.
	 * @return void
	 */
	public static function notify_partner( $lead_id, $partner_id ) {
		$partner = LMF93_Routing::get_partner( $partner_id );
		if ( ! $partner || ! is_email( $partner->email ) ) {
			LMF93_Helpers::log( 'Partner has no valid email; lead ' . $lead_id );
			return;
		}

		$tpl = self::get_template( 'partner_new_lead' );
		if ( empty( $tpl['enabled'] ) ) {
			return;
		}

		$ph  = self::build_placeholders( $lead_id, $partner );
		$msg = self::render( $tpl, $ph );

		// Reply-To customer so the partner can answer straight to them.
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( ! empty( $ph['{email}'] ) && is_email( $ph['{email}'] ) ) {
			$name      = trim( $ph['{first_name}'] . ' ' . $ph['{last_name}'] );
			$headers[] = 'Reply-To: ' . $name . ' <' . $ph['{email}'] . '>';
		}

		self::send( $partner->email, $msg['subject'], $msg['body'], $headers );
		LMF93_Leads::add_event( $lead_id, 'partner_notified', 'Partner emailed full lead' );
	}

	/**
	 * Send the customer their confirmation.
	 *
	 * @param int $lead_id    Lead id.
	 * @param int $partner_id Assigned partner id (0 = none yet).
	 * @return void
	 */
	public static function notify_customer( $lead_id, $partner_id = 0 ) {
		$partner = $partner_id ? LMF93_Routing::get_partner( $partner_id ) : null;
		$key     = $partner ? 'customer_confirmation' : 'customer_no_partner';
		$tpl     = self::get_template( $key );
		if ( empty( $tpl['enabled'] ) ) {
			return;
		}

		$ph = self::build_placeholders( $lead_id, $partner );
		if ( empty( $ph['{email}'] ) || ! is_email( $ph['{email}'] ) ) {
			return;
		}

		$msg     = self::render( $tpl, $ph );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		self::send( $ph['{email}'], $msg['subject'], $msg['body'], $headers );
		LMF93_Leads::add_event( $lead_id, 'customer_notified', 'Customer confirmation sent' );
	}

	/**
	 * Minimal, PII-free heads-up to the site owner.
	 *
	 * @param int $lead_id Lead id.
	 * @return void
	 */
	public static function notify_admin_new_lead( $lead_id ) {
		if ( ! LMF93_Helpers::get_option( 'notify_admin', 1 ) ) {
			return;
		}
		$to = LMF93_Helpers::get_option( 'admin_email', get_option( 'admin_email' ) );
		if ( ! is_email( $to ) ) {
			return;
		}

		$reference = LMF93_Helpers::lead_reference( $lead_id );
		$admin_url = admin_url( 'admin.php?page=lmf93-leads&lead=' . (int) $lead_id );

		/* translators: %s: lead reference */
		$subject = sprintf( __( 'New lead %s', 'leadmagnet' ), $reference );
		$body    = __( 'A new lead has arrived.', 'leadmagnet' ) . "\n\n"
			/* translators: %s: lead reference */
			. sprintf( __( 'Reference: %s', 'leadmagnet' ), $reference ) . "\n\n"
			. __( 'For privacy reasons no personal data is shown in this message. Open the lead in the dashboard:', 'leadmagnet' ) . "\n"
			. $admin_url . "\n";

		self::send( $to, $subject, $body );
	}

	/**
	 * Low-level sender.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 * @param array  $headers Headers.
	 * @return bool
	 */
	public static function send( $to, $subject, $body, $headers = array() ) {
		if ( empty( $headers ) ) {
			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		}
		$sent = wp_mail( $to, $subject, $body, $headers );
		if ( ! $sent ) {
			LMF93_Helpers::log( 'wp_mail returned false for subject: ' . $subject );
		}
		return $sent;
	}

	/**
	 * Fake placeholder data for test sends (no real lead required).
	 *
	 * @return array
	 */
	public static function sample_placeholders() {
		$review_url = LMF93_Helpers::get_option( 'review_page_url', '' );
		if ( $review_url ) {
			$review_url = add_query_arg( 'lmf93_token', LMF93_Review::TEST_TOKEN, $review_url );
		}
		return array(
			'{reference}'       => 'LM-' . gmdate( 'Y' ) . '-000123',
			'{created_at}'      => current_time( 'mysql' ),
			'{first_name}'      => 'Jane',
			'{last_name}'       => 'Doe',
			'{email}'           => 'jane.doe@example.com',
			'{phone}'           => '+1 555 0123',
			'{postal_code}'     => '00100',
			'{city}'            => 'Example City',
			'{message}'         => 'This is an example message from the contact form.',
			'{site_name}'       => get_bloginfo( 'name' ),
			'{partner_company}' => 'Example Partner Ltd',
			'{partner_email}'   => 'partner@example.com',
			'{partner_phone}'   => '+1 555 0456',
			'{review_url}'      => $review_url ? $review_url : home_url( '/review/?lmf93_token=' . LMF93_Review::TEST_TOKEN ),
			'{unsubscribe_url}' => home_url( '/?lmf93_token=' . LMF93_Review::TEST_TOKEN ),
		);
	}

	/**
	 * Send a TEST email to the admin address using fake sample data.
	 *
	 * Supports the built-in messages (review_request, service_reminder,
	 * partner_new_lead, customer_confirmation, customer_no_partner) and any
	 * admin-defined custom follow-up (custom:mN).
	 *
	 * @param string $type Message type/key.
	 * @return bool
	 */
	public static function send_test( $type ) {
		$settings = get_option( 'lmf93_settings', array() );
		$to       = ! empty( $settings['admin_email'] ) ? $settings['admin_email'] : get_option( 'admin_email' );
		if ( ! is_email( $to ) ) {
			return false;
		}

		$ph      = self::sample_placeholders();
		$search  = array_keys( $ph );
		$replace = array_values( $ph );
		$prefix  = '[TEST] ';
		$subject = '';
		$body    = '';

		// Built-in transactional templates (from saved options or defaults).
		$builtin = array( 'partner_new_lead', 'customer_confirmation', 'customer_no_partner' );

		if ( in_array( $type, $builtin, true ) ) {
			$tpl     = self::get_template( $type );
			$subject = str_replace( $search, $replace, isset( $tpl['subject'] ) ? $tpl['subject'] : '' );
			$body    = str_replace( $search, $replace, isset( $tpl['body'] ) ? $tpl['body'] : '' );
		} elseif ( 'review_request' === $type || 'service_reminder' === $type ) {
			$fixed   = LMF93_Followup::fixed_message( $type );
			$subject = str_replace( $search, $replace, isset( $fixed['subject'] ) ? $fixed['subject'] : '' );
			$body    = str_replace( $search, $replace, isset( $fixed['body'] ) ? $fixed['body'] : '' );
		} elseif ( 0 === strpos( (string) $type, 'custom:' ) ) {
			$msg_id = substr( $type, strlen( 'custom:' ) );
			foreach ( LMF93_Followup::messages() as $m ) {
				if ( isset( $m['id'] ) && $m['id'] === $msg_id ) {
					$subject = str_replace( $search, $replace, isset( $m['subject'] ) ? $m['subject'] : '' );
					$body    = str_replace( $search, $replace, isset( $m['body'] ) ? $m['body'] : '' );
					break;
				}
			}
		}

		if ( '' === $subject && '' === $body ) {
			return false;
		}

		$body = $body . "\n\n----\n" . __( 'This is a test message sent from the dashboard with example data.', 'leadmagnet' ) . "\n";

		return self::send( $to, $prefix . $subject, $body );
	}
}
