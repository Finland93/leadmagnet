<?php
/**
 * Database schema and lifecycle.
 *
 * All lead data lives in dedicated tables (never wp_posts / wp_postmeta),
 * so it is not queryable through the public REST API or the standard
 * WordPress front-end.
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LMF93_Database
 */
class LMF93_Database {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::seed_defaults();

		// Schedule the housekeeping cron.
		if ( ! wp_next_scheduled( 'lmf93_cron_tick' ) ) {
			wp_schedule_event( time() + 300, 'lmf93_five_minutes', 'lmf93_cron_tick' );
		}

		update_option( 'lmf93_db_version', LMF93_DB_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Upgrade schema if the stored DB version is behind.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$stored = get_option( 'lmf93_db_version', '0' );
		if ( version_compare( $stored, LMF93_DB_VERSION, '<' ) ) {
			self::create_tables();
			self::seed_defaults();
			update_option( 'lmf93_db_version', LMF93_DB_VERSION );
			// New REST routes (e.g. /review) can 404 until rewrite rules are
			// refreshed after an in-place update. Flush on init (not here, as
			// plugins_loaded is too early for a persistent flush).
			add_action( 'init', 'flush_rewrite_rules', 99 );
		}
	}

	/**
	 * Create/upgrade all tables via dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$forms      = LMF93_Helpers::table( 'forms' );
		$leads      = LMF93_Helpers::table( 'leads' );
		$events     = LMF93_Helpers::table( 'lead_events' );
		$consents   = LMF93_Helpers::table( 'consents' );
		$partners   = LMF93_Helpers::table( 'partners' );
		$followups  = LMF93_Helpers::table( 'followups' );
		$feedback   = LMF93_Helpers::table( 'feedback' );

		// Forms: each captured form (reusable across sites/pages).
		$sql_forms = "CREATE TABLE {$forms} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			config LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";

		// Leads: core private table. Field data stored as JSON payload +
		// promoted common columns for indexing/reporting.
		$sql_leads = "CREATE TABLE {$leads} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_uuid CHAR(36) NOT NULL,
			form_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			first_name VARCHAR(191) NULL,
			last_name VARCHAR(191) NULL,
			email VARCHAR(191) NULL,
			phone VARCHAR(64) NULL,
			postal_code VARCHAR(20) NULL,
			city VARCHAR(191) NULL,
			payload LONGTEXT NULL,
			lead_score INT NOT NULL DEFAULT 0,
			lead_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			partner_id BIGINT UNSIGNED NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'new',
			job_status VARCHAR(30) NOT NULL DEFAULT 'new',
			completed_at DATETIME NULL,
			email_verified TINYINT(1) NOT NULL DEFAULT 0,
			marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
			marketing_consent_at DATETIME NULL,
			unsubscribe_token CHAR(64) NULL,
			unsubscribe_all TINYINT(1) NOT NULL DEFAULT 0,
			pref_service_reminder TINYINT(1) NOT NULL DEFAULT 1,
			pref_review_request TINYINT(1) NOT NULL DEFAULT 1,
			pref_marketing TINYINT(1) NOT NULL DEFAULT 0,
			source_url VARCHAR(255) NULL,
			landing_page VARCHAR(255) NULL,
			utm_source VARCHAR(191) NULL,
			utm_medium VARCHAR(191) NULL,
			utm_campaign VARCHAR(191) NULL,
			ip_hash CHAR(64) NULL,
			ua_hash CHAR(64) NULL,
			followup_30_at DATETIME NULL,
			followup_30_sent TINYINT(1) NOT NULL DEFAULT 0,
			followup_365_at DATETIME NULL,
			followup_365_sent TINYINT(1) NOT NULL DEFAULT 0,
			billed TINYINT(1) NOT NULL DEFAULT 0,
			billed_at DATETIME NULL,
			deleted_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY lead_uuid (lead_uuid),
			UNIQUE KEY unsubscribe_token (unsubscribe_token),
			KEY email (email),
			KEY phone (phone),
			KEY status (status),
			KEY partner_id (partner_id),
			KEY form_id (form_id),
			KEY followup_30 (followup_30_sent, followup_30_at),
			KEY followup_365 (followup_365_sent, followup_365_at),
			KEY billed (billed),
			KEY job_status (job_status)
		) {$charset_collate};";

		// Immutable-ish audit trail per lead.
		$sql_events = "CREATE TABLE {$events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_id BIGINT UNSIGNED NOT NULL,
			event_type VARCHAR(50) NOT NULL,
			message VARCHAR(255) NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY event_type (event_type)
		) {$charset_collate};";

		// Consent records (per purpose), the legal backbone.
		$sql_consents = "CREATE TABLE {$consents} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_id BIGINT UNSIGNED NOT NULL,
			purpose VARCHAR(50) NOT NULL,
			granted TINYINT(1) NOT NULL DEFAULT 0,
			consent_text TEXT NULL,
			privacy_version VARCHAR(20) NULL,
			source_url VARCHAR(255) NULL,
			ip_hash CHAR(64) NULL,
			ua_hash CHAR(64) NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY purpose (purpose)
		) {$charset_collate};";

		// Partner network for routing. Never exposed to front-end.
		$sql_partners = "CREATE TABLE {$partners} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			company_name VARCHAR(191) NOT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			priority INT NOT NULL DEFAULT 10,
			email VARCHAR(191) NULL,
			phone VARCHAR(64) NULL,
			postal_codes LONGTEXT NULL,
			regions LONGTEXT NULL,
			services LONGTEXT NULL,
			max_leads_per_day INT NOT NULL DEFAULT 0,
			max_leads_per_month INT NOT NULL DEFAULT 0,
			exclusive TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY active (active)
		) {$charset_collate};";

		// Scheduled follow-up queue (generic, extensible).
		$sql_followups = "CREATE TABLE {$followups} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_id BIGINT UNSIGNED NOT NULL,
			kind VARCHAR(50) NOT NULL,
			scheduled_at DATETIME NOT NULL,
			sent TINYINT(1) NOT NULL DEFAULT 0,
			sent_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY due (sent, scheduled_at)
		) {$charset_collate};";

		// Optional feedback / review loop.
		$sql_feedback = "CREATE TABLE {$feedback} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			lead_id BIGINT UNSIGNED NOT NULL,
			rating TINYINT NOT NULL DEFAULT 0,
			reason VARCHAR(100) NULL,
			comment TEXT NULL,
			status VARCHAR(30) NOT NULL DEFAULT 'new',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY lead_id (lead_id),
			KEY status (status),
			KEY rating (rating)
		) {$charset_collate};";

		dbDelta( $sql_forms );
		dbDelta( $sql_leads );
		dbDelta( $sql_events );
		dbDelta( $sql_consents );
		dbDelta( $sql_partners );
		dbDelta( $sql_followups );
		dbDelta( $sql_feedback );
	}

	/**
	 * Seed default settings and a starter form on first activation.
	 *
	 * @return void
	 */
	public static function seed_defaults() {
		$existing = get_option( 'lmf93_settings', false );
		if ( false === $existing ) {
			add_option(
				'lmf93_settings',
				array(
					'lead_prefix'              => 'LM',
					'admin_email'              => get_option( 'admin_email' ),
					'notify_admin'             => 1,
					'captcha_provider'         => 'none', // none | turnstile | recaptcha_v2 | recaptcha_v3.
					'captcha_site_key'         => '',
					'captcha_secret_key'       => '',
					'min_submit_seconds'       => 3,
					'rate_limit_max'           => 5,
					'rate_limit_window'        => 3600,
					'privacy_version'          => '1.0',
					'privacy_page_url'         => '',
					'retention_days'           => 730, // 24 months, 0 = keep forever.
					'anonymize_after_days'     => 730,
					'unsubscribe_page_url'     => '',
					'review_page_url'          => '',
					'business_review_url'      => '',
					'review_threshold'         => 4,
					'review_notify_email'      => '',
					'enable_low_review_notify' => 1,
					// Localization: postal code / city behaviour (country-agnostic).
					'postal_input_mode'        => 'any', // any | numeric.
					'postal_max_length'        => 12,
					'postal_autocity'          => 0,     // Auto-fill city from a postal dataset.
					'postal_data_url'          => '',    // URL to a { "CODE": "City" } JSON map.
					// Localization: currency for lead value / billing.
					'currency_symbol'          => '€',
					'currency_position'        => 'suffix', // prefix | suffix.
					'price_note'               => 'excl. tax',
				)
			);
		} else {
			// On upgrades, add any missing default keys without touching
			// values the user has already set (purely additive, safe).
			$defaults = array(
				'lead_prefix'              => 'LM',
				'admin_email'              => get_option( 'admin_email' ),
				'notify_admin'             => 1,
				'captcha_provider'         => 'none', // none | turnstile | recaptcha_v2 | recaptcha_v3.
				'captcha_site_key'         => '',
				'captcha_secret_key'       => '',
				'min_submit_seconds'       => 3,
				'rate_limit_max'           => 5,
				'rate_limit_window'        => 3600,
				'privacy_version'          => '1.0',
				'privacy_page_url'         => '',
				'retention_days'           => 730, // 24 months, 0 = keep forever.
				'anonymize_after_days'     => 730,
				'unsubscribe_page_url'     => '',
				'review_page_url'          => '',
				'business_review_url'      => '',
				'review_threshold'         => 4,
				'review_notify_email'      => '',
				'enable_low_review_notify' => 1,
				// Localization: postal code / city behaviour (country-agnostic).
				'postal_input_mode'        => 'any', // any | numeric.
				'postal_max_length'        => 12,
				'postal_autocity'          => 0,     // Auto-fill city from a postal dataset.
				'postal_data_url'          => '',    // URL to a { "CODE": "City" } JSON map.
				// Localization: currency for lead value / billing.
				'currency_symbol'          => '€',
				'currency_position'        => 'suffix', // prefix | suffix.
				'price_note'               => 'excl. tax',
				);
			$merged = array_merge( $defaults, (array) $existing );
			if ( $merged !== (array) $existing ) {
				update_option( 'lmf93_settings', $merged );
			}
		}

		// Create one starter form if none exist.
		global $wpdb;
		$forms_table = LMF93_Helpers::table( 'forms' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$forms_table}" );
		if ( 0 === $count ) {
			LMF93_Forms::create(
				array(
					'name'   => __( 'Default lead form', 'leadmagnet' ),
					'config' => LMF93_Forms::default_config(),
				)
			);
		}
	}
}
