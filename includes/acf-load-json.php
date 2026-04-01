<?php
/**
 * Loads ACF field groups from JSON export in /acf.
 * Ensures the plugin's ACF configuration is applied without manual import.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', 'soe_load_acf_field_groups_from_json', 5 );
function soe_load_acf_field_groups_from_json() {
	$dir = dirname( dirname( __FILE__ ) ) . '/acf';
	if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
		return;
	}

	$files = glob( $dir . '/*.json' );
	if ( ! is_array( $files ) || empty( $files ) ) {
		return;
	}

	foreach ( $files as $json_path ) {
		if ( ! file_exists( $json_path ) || ! is_readable( $json_path ) ) {
			continue;
		}
		$json = file_get_contents( $json_path );
		if ( ! $json ) {
			continue;
		}
		$groups = json_decode( $json, true );
		if ( ! is_array( $groups ) ) {
			continue;
		}
		foreach ( $groups as $group ) {
			if ( ! empty( $group['key'] ) && ! empty( $group['title'] ) ) {
				acf_add_local_field_group( $group );
			}
		}
	}
}
