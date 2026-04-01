<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Removes options and custom tables. Tables contain training, event, and payroll data.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Clear scheduled cron.
wp_clear_scheduled_hook( 'soe_payroll_cleanup_orphaned_pdfs' );

// Options.
delete_option( 'soe_settings' );
delete_option( 'soe_db_version' );

// Custom tables (must match constants in includes/database.php).
$tables = array(
	$wpdb->prefix . 'soe_attendance_ops',
	$wpdb->prefix . 'soe_payroll_manual_adjustments',
	$wpdb->prefix . 'soe_payroll_rows',
	$wpdb->prefix . 'soe_payrolls',
	$wpdb->prefix . 'soe_event_persons',
	$wpdb->prefix . 'soe_events',
	$wpdb->prefix . 'soe_training_attendance',
	$wpdb->prefix . 'soe_training_sessions',
	$wpdb->prefix . 'soe_training_persons',
	$wpdb->prefix . 'soe_trainings',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}
