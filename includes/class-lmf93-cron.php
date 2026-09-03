<?php
/**
 * Cron: process follow-up queue and enforce GDPR retention.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Cron
 */
class LMF93_Cron {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( 'lmf93_cron_tick', array( __CLASS__, 'tick' ) );

		// Self-heal: ensure the event exists (e.g. after a missed activation).
		if ( ! wp_next_scheduled( 'lmf93_cron_tick' ) ) {
			wp_schedule_event( time() + 300, 'lmf93_five_minutes', 'lmf93_cron_tick' );
		}
	}

	/**
	 * Add a 5-minute schedule.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_schedule( $schedules ) {
		$schedules['lmf93_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 minutes (LeadMagnet)', 'leadmagnet' ),
		);
		return $schedules;
	}

	/**
	 * Cron tick.
	 *
	 * @return void
	 */
	public static function tick() {
		// 1) Process due follow-ups.
		LMF93_Followup::process_due( 50 );

		// 2) GDPR retention / anonymization.
		self::enforce_retention();
	}

	/**
	 * Anonymize or delete leads older than the configured retention window.
	 *
	 * @return void
	 */
	protected static function enforce_retention() {
		global $wpdb;
		$table = LMF93_Helpers::table( 'leads' );

		$anon_days = (int) LMF93_Helpers::get_option( 'anonymize_after_days', 0 );
		if ( $anon_days > 0 ) {
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $anon_days * DAY_IN_SECONDS ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE created_at < %s AND email IS NOT NULL AND deleted_at IS NULL LIMIT 100",
					$cutoff
				)
			);
			foreach ( (array) $ids as $id ) {
				LMF93_Leads::anonymize( (int) $id );
			}
		}
	}

	/**
	 * Clear scheduled events on deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'lmf93_cron_tick' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'lmf93_cron_tick' );
		}
		wp_clear_scheduled_hook( 'lmf93_cron_tick' );
	}
}
