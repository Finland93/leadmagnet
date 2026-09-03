<?php
/**
 * Security layer: honeypot, timing gate, rate limiting, CAPTCHA verification
 * and generic input validation.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Security
 */
class LMF93_Security {

	/**
	 * Run all spam / abuse checks before a lead is accepted.
	 *
	 * Returns true when the submission looks legitimate, or a WP_Error
	 * describing the first failed check.
	 *
	 * @param array $request Raw request data (already unslashed by caller where relevant).
	 * @return true|WP_Error
	 */
	public static function guard( $request ) {
		// 1) Honeypot: a hidden field that must stay empty.
		$hp_field = 'lmf93_hp';
		if ( ! empty( $request[ $hp_field ] ) ) {
			LMF93_Helpers::log( 'Honeypot triggered.' );
			return new WP_Error( 'lmf93_spam', __( 'Submission rejected.', 'leadmagnet' ), array( 'status' => 400 ) );
		}

		// 2) Timing gate: form must not be submitted implausibly fast.
		$min_seconds = (int) LMF93_Helpers::get_option( 'min_submit_seconds', 3 );
		$rendered_at = isset( $request['lmf93_ts'] ) ? (int) $request['lmf93_ts'] : 0;
		if ( $min_seconds > 0 && $rendered_at > 0 ) {
			$elapsed = time() - $rendered_at;
			if ( $elapsed < $min_seconds ) {
				LMF93_Helpers::log( 'Timing gate triggered (' . $elapsed . 's).' );
				return new WP_Error( 'lmf93_spam', __( 'Submission rejected.', 'leadmagnet' ), array( 'status' => 400 ) );
			}
			// Also reject forms older than 12h (stale/replayed).
			if ( $elapsed > 12 * HOUR_IN_SECONDS ) {
				return new WP_Error( 'lmf93_expired', __( 'Form expired, please reload the page.', 'leadmagnet' ), array( 'status' => 400 ) );
			}
		}

		// 3) Rate limit per hashed IP.
		$max    = (int) LMF93_Helpers::get_option( 'rate_limit_max', 5 );
		$window = (int) LMF93_Helpers::get_option( 'rate_limit_window', HOUR_IN_SECONDS );
		if ( $max > 0 ) {
			$ip_hash = LMF93_Helpers::hash_ip( LMF93_Helpers::get_ip() );
			if ( $ip_hash && ! LMF93_Helpers::rate_limit( 'ip_' . $ip_hash, $max, $window ) ) {
				return new WP_Error( 'lmf93_rate', __( 'Too many submissions. Please try again later.', 'leadmagnet' ), array( 'status' => 429 ) );
			}
		}

		// 4) CAPTCHA (Turnstile / reCAPTCHA) when configured.
		$captcha = self::verify_captcha( $request );
		if ( is_wp_error( $captcha ) ) {
			return $captcha;
		}

		return true;
	}

	/**
	 * Verify a CAPTCHA token against the configured provider.
	 *
	 * @param array $request Request data.
	 * @return true|WP_Error
	 */
	public static function verify_captcha( $request ) {
		$provider = LMF93_Helpers::get_option( 'captcha_provider', 'none' );

		if ( 'none' === $provider || empty( $provider ) ) {
			return true;
		}

		$secret = LMF93_Helpers::get_option( 'captcha_secret_key', '' );
		if ( empty( $secret ) ) {
			// Misconfigured: fail open would be unsafe, but blocking all leads is worse.
			// We log and allow, so a missing key never silently kills lead flow.
			LMF93_Helpers::log( 'CAPTCHA enabled but secret key missing; skipping verification.' );
			return true;
		}

		$token = isset( $request['lmf93_captcha'] ) ? sanitize_text_field( wp_unslash( $request['lmf93_captcha'] ) ) : '';
		if ( empty( $token ) ) {
			return new WP_Error( 'lmf93_captcha', __( 'Please complete the anti-spam check.', 'leadmagnet' ), array( 'status' => 400 ) );
		}

		if ( 'turnstile' === $provider ) {
			$endpoint = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
		} else {
			$endpoint = 'https://www.google.com/recaptcha/api/siteverify';
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => LMF93_Helpers::get_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			LMF93_Helpers::log( 'CAPTCHA request failed: ' . $response->get_error_message() );
			// Network hiccup shouldn't block a genuine customer; log & allow.
			return true;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['success'] ) ) {
			return new WP_Error( 'lmf93_captcha', __( 'Anti-spam check failed. Please try again.', 'leadmagnet' ), array( 'status' => 400 ) );
		}

		// reCAPTCHA v3 score gate.
		if ( 'recaptcha_v3' === $provider && isset( $body['score'] ) ) {
			$threshold = (float) apply_filters( 'lmf93_recaptcha_v3_threshold', 0.5 );
			if ( (float) $body['score'] < $threshold ) {
				return new WP_Error( 'lmf93_captcha', __( 'Anti-spam check failed. Please try again.', 'leadmagnet' ), array( 'status' => 400 ) );
			}
		}

		return true;
	}

	/**
	 * Validate submitted field values against a form's field definitions.
	 *
	 * @param array $fields    Form field definitions.
	 * @param array $submitted Sanitized submitted values keyed by field key.
	 * @return array|WP_Error  Clean values, or a WP_Error on the first failure.
	 */
	public static function validate_fields( $fields, $submitted ) {
		$clean = array();

		foreach ( $fields as $field ) {
			$key      = isset( $field['key'] ) ? $field['key'] : '';
			$type     = isset( $field['type'] ) ? $field['type'] : 'text';
			$required = ! empty( $field['required'] );
			$label    = isset( $field['label'] ) ? $field['label'] : $key;

			if ( '' === $key ) {
				continue;
			}

			$value = isset( $submitted[ $key ] ) ? $submitted[ $key ] : '';

			// Required check.
			if ( $required && ( '' === $value || array() === $value ) ) {
				/* translators: %s: field label */
				return new WP_Error( 'lmf93_required', sprintf( __( 'Please fill in: %s', 'leadmagnet' ), $label ), array( 'status' => 400 ) );
			}

			if ( '' === $value ) {
				$clean[ $key ] = '';
				continue;
			}

			switch ( $type ) {
				case 'email':
					$value = sanitize_email( $value );
					if ( ! is_email( $value ) ) {
						return new WP_Error( 'lmf93_email', __( 'Please enter a valid email address.', 'leadmagnet' ), array( 'status' => 400 ) );
					}
					break;

				case 'tel':
					$value = preg_replace( '/[^0-9+ ()\-]/', '', $value );
					if ( strlen( preg_replace( '/\D/', '', $value ) ) < 5 ) {
						return new WP_Error( 'lmf93_phone', __( 'Please enter a valid phone number.', 'leadmagnet' ), array( 'status' => 400 ) );
					}
					break;

				case 'number':
					$value = is_numeric( $value ) ? $value + 0 : '';
					break;

				case 'textarea':
					$value = sanitize_textarea_field( $value );
					break;

				case 'select':
				case 'radio':
					$value   = sanitize_text_field( $value );
					$options = isset( $field['options'] ) ? (array) $field['options'] : array();
					$allowed = wp_list_pluck( self::normalize_options( $options ), 'value' );
					if ( ! empty( $allowed ) && ! in_array( $value, $allowed, true ) ) {
						return new WP_Error( 'lmf93_option', __( 'Invalid selection.', 'leadmagnet' ), array( 'status' => 400 ) );
					}
					break;

				case 'checkbox':
					// Multi-value checkbox group.
					$value   = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array( sanitize_text_field( $value ) );
					$options = isset( $field['options'] ) ? (array) $field['options'] : array();
					$allowed = wp_list_pluck( self::normalize_options( $options ), 'value' );
					if ( ! empty( $allowed ) ) {
						$value = array_values( array_intersect( $value, $allowed ) );
					}
					break;

				default:
					$value = sanitize_text_field( $value );
					break;
			}

			// Optional max length guard.
			if ( is_string( $value ) && strlen( $value ) > 2000 ) {
				$value = substr( $value, 0, 2000 );
			}

			$clean[ $key ] = $value;
		}

		return $clean;
	}

	/**
	 * Normalize option definitions to [ ['value'=>..,'label'=>..], ... ].
	 *
	 * @param array $options Raw options.
	 * @return array
	 */
	public static function normalize_options( $options ) {
		$out = array();
		foreach ( $options as $opt ) {
			if ( is_array( $opt ) && isset( $opt['value'] ) ) {
				$out[] = array(
					'value' => (string) $opt['value'],
					'label' => isset( $opt['label'] ) ? (string) $opt['label'] : (string) $opt['value'],
				);
			} else {
				$out[] = array(
					'value' => (string) $opt,
					'label' => (string) $opt,
				);
			}
		}
		return $out;
	}
}
