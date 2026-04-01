<?php
/**
 * Plugin Name:     Special Olympics Extension
 * Description:     Erweiterung für Special Olympics, inkl. Trainingsverwaltung und Mitgliederverwaltung.
 * Version:         1.3.36
 * Author:          Special Olympics Entwicklerteam
 */

if (!defined('ABSPATH')) {
	die;
}

/** Plugin version. */
define( 'SOE_PLUGIN_VERSION', '1.3.36' );

register_activation_hook( __FILE__, 'soe_plugin_activation' );
function soe_plugin_activation() {
	require_once __DIR__ . '/includes/database.php';
	soe_create_tables();
	update_option( 'soe_db_version', SOE_DB_VERSION );
	require_once __DIR__ . '/includes/attendance-public.php';
	soe_attendance_register_rewrite();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'soe_plugin_deactivation' );
function soe_plugin_deactivation() {
	wp_clear_scheduled_hook( 'soe_payroll_cleanup_orphaned_pdfs' );
}

// Central role definitions (before database, custom-trainings, etc.).
require_once __DIR__ . '/includes/roles.php';

// Database tables (Training sessions/attendance, Payroll rows) – load first.
require_once __DIR__ . '/includes/database.php';

// ACF required for rest of plugin (role-sync, mitglied, events, payroll, etc.).
if ( ! function_exists( 'get_field' ) ) {
	add_action( 'admin_notices', function () {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Special Olympics Extension benötigt Advanced Custom Fields.', 'special-olympics-extension' ) . '</p></div>';
	} );
	return;
}

add_action( 'init', function () {
	load_plugin_textdomain( 'special-olympics-extension', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}, 0 );

// Load ACF field groups from JSON (must load before other includes that depend on field structure).
require_once __DIR__ . '/includes/acf-load-json.php';

// ACF Encrypted Fields (AES-256-GCM) – encrypt/decrypt ACF field values in DB.
require_once __DIR__ . '/includes/acf-encrypted-fields.php';

// Protected medical files (upload path, download proxy, capability).
require_once __DIR__ . '/includes/protected-medical-files.php';

// Load core functionality first
require_once __DIR__ . '/includes/role-sync.php';
require_once __DIR__ . '/includes/user-to-mitglied-sync.php';
require_once __DIR__ . '/includes/account-page.php';
require_once __DIR__ . '/includes/attendance-token.php';
require_once __DIR__ . '/includes/attendance-public.php';
require_once __DIR__ . '/includes/post-type-mitglied-register.php';
require_once __DIR__ . '/includes/post-type-training-register.php';
require_once __DIR__ . '/includes/post-type-event-register.php';
require_once __DIR__ . '/includes/taxonomy-sport.php';
require_once __DIR__ . '/includes/taxonomy-event-type.php';
require_once __DIR__ . '/includes/taxonomy-solie-status.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/help-widget.php';
require_once __DIR__ . '/includes/user-welcome-email.php';
require_once __DIR__ . '/includes/github-updater.php';
require_once __DIR__ . '/includes/mitglied-capabilities.php';
require_once __DIR__ . '/includes/post-type-mitglied.php';
require_once __DIR__ . '/includes/meine-athleten-page.php';
require_once __DIR__ . '/includes/training-capabilities.php';
require_once __DIR__ . '/includes/event-capabilities.php';
require_once __DIR__ . '/includes/post-type-contact-register.php';
require_once __DIR__ . '/includes/ajax-person-search.php';
require_once __DIR__ . '/includes/intranet-restrict.php';
require_once __DIR__ . '/includes/login-customize.php';
// Admin menu: hide unused items, reorder SOE items.
require_once __DIR__ . '/includes/admin-menu.php';

// XLS exports (admin_post handlers, load early).
require_once __DIR__ . '/includes/export-xls.php';

// Admin-only: Dashboard, Custom Trainings, Events, Payrolls, Telefonbuch.
if ( is_admin() ) {
	require_once __DIR__ . '/includes/dashboard.php';
	require_once __DIR__ . '/includes/custom-trainings.php';
	require_once __DIR__ . '/includes/custom-events.php';
	require_once __DIR__ . '/includes/custom-payrolls.php';
	require_once __DIR__ . '/includes/payroll.php';
	require_once __DIR__ . '/includes/telefonbuch.php';
}