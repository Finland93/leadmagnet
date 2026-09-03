<?php
/**
 * Consent logging (GDPR backbone).
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Consent
 */
class LMF93_Consent {

	/**
	 * Record all consents defined by a form, given the submitted checkbox state.
	 *
	 * @param int    $lead_id    Lead id.
	 * @param array  $config     Form config.
	 * @param array  $submitted  purpose => truthy.
	 * @param string $source_url Source URL.
	 * @return void
	 */
	public static function record_all( $lead_id, $config, $submitted, $source_url = '' ) {
		$consents = isset( $config['consents'] ) ? $config['consents'] : array();
		foreach ( $consents as $c ) {
			$purpose = isset( $c['purpose'] ) ? $c['purpose'] : '';
			if ( '' === $purpose ) {
				continue;
			}
			$granted = ! empty( $submitted[ $purpose ] );
			self::record( $lead_id, $purpose, $granted, isset( $c['label'] ) ? $c['label'] : '', $source_url );
		}
	}

	/**
	 * Record a single consent event.
	 *
	 * @param int    $lead_id      Lead id.
	 * @param string $purpose      Purpose slug.
	 * @param bool   $granted      Whether granted.
	 * @param string $consent_text The exact text shown.
	 * @param string $source_url   Source URL.
	 * @return void
	 */
	public static function record( $lead_id, $purpose, $granted, $consent_text = '', $source_url = '' ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			LMF93_Helpers::table( 'consents' ),
			array(
				'lead_id'         => (int) $lead_id,
				'purpose'         => sanitize_key( $purpose ),
				'granted'         => $granted ? 1 : 0,
				'consent_text'    => wp_strip_all_tags( $consent_text ),
				'privacy_version' => LMF93_Helpers::get_option( 'privacy_version', '1.0' ),
				'source_url'      => esc_url_raw( $source_url ),
				'ip_hash'         => LMF93_Helpers::hash_ip( LMF93_Helpers::get_ip() ),
				'ua_hash'         => LMF93_Helpers::hash_user_agent(),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $granted ) {
			LMF93_Leads::add_event( $lead_id, 'consent', 'Consent recorded: ' . sanitize_key( $purpose ) );
		}
	}

	/**
	 * Fetch consents for a lead (for GDPR export / admin view).
	 *
	 * @param int $lead_id Lead id.
	 * @return array
	 */
	public static function get_for_lead( $lead_id ) {
		global $wpdb;
		$table = LMF93_Helpers::table( 'consents' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT purpose, granted, privacy_version, created_at FROM {$table} WHERE lead_id = %d ORDER BY id ASC", (int) $lead_id ) );
		return $rows ? $rows : array();
	}
}
