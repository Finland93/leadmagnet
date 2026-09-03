<?php
/**
 * Uninstall handler.
 *
 * Only removes data when the site owner explicitly opts in via the
 * 'lmf93_delete_data_on_uninstall' option, so leads are never lost by accident.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'lmf93_settings', array() );
$purge    = ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $purge ) {
	return;
}

global $wpdb;

$tables = array( 'forms', 'leads', 'lead_events', 'consents', 'partners', 'followups', 'feedback' );
foreach ( $tables as $t ) {
	$table = $wpdb->prefix . 'lmf93_' . $t;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'lmf93_settings' );
delete_option( 'lmf93_db_version' );
delete_option( 'lmf93_email_templates' );
delete_option( 'lmf93_followups' );
delete_option( 'lmf93_fixed_followups' );

wp_clear_scheduled_hook( 'lmf93_cron_tick' );
