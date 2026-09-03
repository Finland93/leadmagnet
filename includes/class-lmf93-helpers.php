<?php
/**
 * Shared helper utilities.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Helpers
 */
class LMF93_Helpers {

	/**
	 * Return a table name with the WP prefix and our namespace.
	 *
	 * @param string $name Base table name, e.g. 'leads'.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'lmf93_' . $name;
	}

	/**
	 * Generate a cryptographically-random token (URL-safe).
	 *
	 * @param int $bytes Number of random bytes.
	 * @return string
	 */
	public static function random_token( $bytes = 32 ) {
		try {
			$raw = random_bytes( $bytes );
		} catch ( Exception $e ) {
			// Fallback (should never happen on a sane host).
			$raw = wp_generate_password( $bytes, true, true );
		}
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}

	/**
	 * Hash an IP address with a site-specific salt (GDPR: never store raw IP).
	 *
	 * @param string $ip Raw IP.
	 * @return string
	 */
	public static function hash_ip( $ip ) {
		if ( empty( $ip ) ) {
			return '';
		}
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	/**
	 * Best-effort visitor IP (used only hashed, only for rate limiting/consent proof).
	 *
	 * @return string
	 */
	public static function get_ip() {
		// Intentionally only trust REMOTE_ADDR to avoid header spoofing.
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Hash of the user agent (GDPR-friendly consent proof).
	 *
	 * @return string
	 */
	public static function hash_user_agent() {
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		if ( empty( $ua ) ) {
			return '';
		}
		return hash_hmac( 'sha256', $ua, wp_salt( 'auth' ) );
	}

	/**
	 * Generate a human-friendly, sequential-ish lead reference.
	 *
	 * @param int $id Numeric lead id.
	 * @return string e.g. LM-2026-000123
	 */
	public static function lead_reference( $id ) {
		$prefix = self::get_option( 'lead_prefix', 'LM' );
		$prefix = preg_replace( '/[^A-Z0-9]/', '', strtoupper( $prefix ) );
		if ( '' === $prefix ) {
			$prefix = 'LM';
		}
		return sprintf( '%s-%s-%06d', $prefix, gmdate( 'Y' ), (int) $id );
	}

	/**
	 * Read a plugin option from the single options array.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_option( $key, $default = null ) {
		$opts = get_option( 'lmf93_settings', array() );
		return isset( $opts[ $key ] ) ? $opts[ $key ] : $default;
	}

	/**
	 * Persist a single option key inside the options array.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function update_option( $key, $value ) {
		$opts         = get_option( 'lmf93_settings', array() );
		$opts[ $key ] = $value;
		update_option( 'lmf93_settings', $opts );
	}

	/**
	 * Recursively sanitize an arbitrary value.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function deep_sanitize( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( __CLASS__, 'deep_sanitize' ), $value );
		}
		return sanitize_text_field( wp_unslash( $value ) );
	}

	/**
	 * Simple, fast per-key rate limiter backed by transients.
	 *
	 * @param string $key         Unique key (already namespaced by caller).
	 * @param int    $max         Max hits in the window.
	 * @param int    $window_secs Window length in seconds.
	 * @return bool True if allowed, false if the limit is exceeded.
	 */
	public static function rate_limit( $key, $max, $window_secs ) {
		$transient = 'lmf93_rl_' . md5( $key );
		$data      = get_transient( $transient );

		if ( false === $data || ! is_array( $data ) ) {
			set_transient( $transient, array( 'count' => 1 ), $window_secs );
			return true;
		}

		if ( $data['count'] >= $max ) {
			return false;
		}

		$data['count']++;
		set_transient( $transient, $data, $window_secs );
		return true;
	}

	/**
	 * The configured currency symbol (defaults to the euro sign).
	 *
	 * @return string
	 */
	public static function currency_symbol() {
		$symbol = self::get_option( 'currency_symbol', '€' );
		return '' === $symbol ? '€' : $symbol;
	}

	/**
	 * Format a monetary amount using the configured currency symbol and
	 * position. Locale-aware number formatting via number_format_i18n().
	 *
	 * @param float $amount   Amount.
	 * @param int   $decimals Decimal places.
	 * @return string
	 */
	public static function format_price( $amount, $decimals = 2 ) {
		$number   = number_format_i18n( (float) $amount, $decimals );
		$symbol   = self::currency_symbol();
		$position = self::get_option( 'currency_position', 'suffix' );
		return 'prefix' === $position ? $symbol . $number : $number . ' ' . $symbol;
	}

	/**
	 * The configured price note (e.g. "excl. tax"). May be empty.
	 *
	 * @return string
	 */
	public static function price_note() {
		return (string) self::get_option( 'price_note', '' );
	}

	/**
	 * Log a debug line when WP_DEBUG_LOG is enabled. Never logs personal data.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function log( $message ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[LeadMagnet] ' . $message );
		}
	}
}
