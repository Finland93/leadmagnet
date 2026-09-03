<?php
/**
 * Plugin Name:       LeadMagnet
 * Plugin URI:        https://github.com/finland93/leadmagnet
 * Description:        A self-hosted lead capture and management system for WordPress: spam protection, a private lead database, consent logging, partner routing, per-partner billing, customer feedback and reviews, and automated follow-up messages. Country-agnostic and fully translatable.
 * Version:           2.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            LeadMagnet
 * Author URI:        https://github.com/finland93/leadmagnet
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       leadmagnet
 * Domain Path:       /languages
 *
 * @package LeadMagnet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

/*
 * ---------------------------------------------------------------------------
 * Constants
 * ---------------------------------------------------------------------------
 */
define( 'LMF93_VERSION', '2.0.0' );
define( 'LMF93_DB_VERSION', '1.1.0' );
define( 'LMF93_FILE', __FILE__ );
define( 'LMF93_PATH', plugin_dir_path( __FILE__ ) );
define( 'LMF93_URL', plugin_dir_url( __FILE__ ) );
define( 'LMF93_BASENAME', plugin_basename( __FILE__ ) );
define( 'LMF93_PREFIX', 'lmf93' );

/*
 * ---------------------------------------------------------------------------
 * Autoload-ish require of class files (no Composer dependency on purpose).
 * ---------------------------------------------------------------------------
 */
require_once LMF93_PATH . 'includes/class-lmf93-helpers.php';
require_once LMF93_PATH . 'includes/class-lmf93-database.php';
require_once LMF93_PATH . 'includes/class-lmf93-security.php';
require_once LMF93_PATH . 'includes/class-lmf93-consent.php';
require_once LMF93_PATH . 'includes/class-lmf93-forms.php';
require_once LMF93_PATH . 'includes/class-lmf93-leads.php';
require_once LMF93_PATH . 'includes/class-lmf93-scoring.php';
require_once LMF93_PATH . 'includes/class-lmf93-routing.php';
require_once LMF93_PATH . 'includes/class-lmf93-followup.php';
require_once LMF93_PATH . 'includes/class-lmf93-feedback.php';
require_once LMF93_PATH . 'includes/class-lmf93-review.php';
require_once LMF93_PATH . 'includes/class-lmf93-email.php';
require_once LMF93_PATH . 'includes/class-lmf93-rest.php';
require_once LMF93_PATH . 'includes/class-lmf93-shortcode.php';
require_once LMF93_PATH . 'includes/class-lmf93-cron.php';

if ( is_admin() ) {
	require_once LMF93_PATH . 'admin/class-lmf93-admin.php';
}

/*
 * ---------------------------------------------------------------------------
 * Activation / Deactivation / Uninstall handling.
 * ---------------------------------------------------------------------------
 */
register_activation_hook( __FILE__, array( 'LMF93_Database', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LMF93_Cron', 'deactivate' ) );

/**
 * Boot the plugin.
 *
 * @return void
 */
function lmf93_boot() {
	load_plugin_textdomain( 'leadmagnet', false, dirname( LMF93_BASENAME ) . '/languages' );

	// Ensure DB schema is up to date (handles plugin updates).
	LMF93_Database::maybe_upgrade();

	// Public-facing pieces.
	LMF93_Shortcode::init();
	LMF93_Rest::init();
	LMF93_Cron::init();
	LMF93_Followup::init();
	LMF93_Review::init();

	// Admin.
	if ( is_admin() ) {
		LMF93_Admin::init();
	}
}
add_action( 'plugins_loaded', 'lmf93_boot' );
