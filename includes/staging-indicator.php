<?php
/**
 * Visual staging environment indicator (admin and login only).
 *
 * Active when the current site host matches the staging host in plugin settings.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Default staging hostname when not configured in settings. */
define( 'SOE_STAGING_HOST_DEFAULT', 'staging.specialolympics.li' );

add_action( 'login_enqueue_scripts', 'soe_staging_enqueue_styles' );
add_action( 'admin_enqueue_scripts', 'soe_staging_enqueue_styles' );
add_action( 'login_head', 'soe_staging_print_favicon', 1 );
add_action( 'admin_head', 'soe_staging_print_favicon', 1 );
add_action( 'login_header', 'soe_staging_render_login_banner', 1 );
add_action( 'admin_bar_menu', 'soe_staging_admin_bar_badge', 2 );
add_action( 'in_admin_header', 'soe_staging_render_admin_banner' );

/**
 * Sanitizes a staging host or URL to a lowercase hostname.
 *
 * @param string $value Hostname or URL from settings.
 * @return string Hostname or empty string when invalid/empty.
 */
function soe_sanitize_staging_host( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' ) {
		return '';
	}
	if ( strpos( $value, '://' ) === false ) {
		$value = 'https://' . $value;
	}
	$parsed = wp_parse_url( $value );
	if ( ! empty( $parsed['host'] ) && is_string( $parsed['host'] ) ) {
		return strtolower( $parsed['host'] );
	}
	$fallback = strtolower( preg_replace( '/[^a-z0-9.:-]/', '', $value ) );
	return $fallback !== '' ? $fallback : '';
}

/**
 * Sanitizes a comma-/newline-separated list of staging hosts or URLs.
 *
 * @param string $value Raw list from settings.
 * @return string[] Array of unique, non-empty hostnames.
 */
function soe_sanitize_staging_host_list( $value ) {
	$value = (string) $value;
	if ( $value === '' ) {
		return array();
	}

	$parts = preg_split( '/[\s,]+/', $value );
	if ( ! is_array( $parts ) ) {
		$parts = array( $value );
	}

	$hosts = array();
	foreach ( $parts as $part ) {
		$host = soe_sanitize_staging_host( $part );
		if ( $host === '' ) {
			continue;
		}
		$hosts[ $host ] = true;
	}

	return array_keys( $hosts );
}

/**
 * Returns the configured staging hostnames.
 *
 * @return string[] Empty array when staging indicator is disabled.
 */
function soe_get_staging_hosts() {
	if ( ! function_exists( 'soe_get_setting' ) ) {
		return array();
	}

	$raw = soe_get_setting( 'staging_host' );
	if ( is_array( $raw ) ) {
		$raw = implode( ', ', $raw );
	}

	$hosts = soe_sanitize_staging_host_list( $raw );

	return $hosts;
}

/**
 * Whether the current request is on the configured staging host.
 *
 * @return bool
 */
function soe_is_staging_environment() {
	static $is_staging = null;
	if ( $is_staging !== null ) {
		return $is_staging;
	}
	$configured_hosts = soe_get_staging_hosts();
	if ( empty( $configured_hosts ) ) {
		$is_staging = false;
		return false;
	}
	$current = '';
	if ( isset( $_SERVER['HTTP_HOST'] ) && is_string( $_SERVER['HTTP_HOST'] ) ) {
		$current = strtolower( untrailingslashit( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) );
	}
	$is_staging = ( $current !== '' && in_array( $current, $configured_hosts, true ) );
	return $is_staging;
}

/**
 * Returns an orange circle favicon as a data URI for staging.
 *
 * @return string
 */
function soe_staging_favicon_data_uri() {
	$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><circle cx="16" cy="16" r="15" fill="#e67e22"/></svg>';
	return 'data:image/svg+xml,' . rawurlencode( $svg );
}

/**
 * Prints staging favicon tags in document head.
 *
 * @return void
 */
function soe_staging_print_favicon() {
	if ( ! soe_is_staging_environment() ) {
		return;
	}
	$href = esc_attr( soe_staging_favicon_data_uri() );
	echo '<link rel="icon" href="' . $href . '" sizes="32x32" />' . "\n";
	echo '<link rel="shortcut icon" href="' . $href . '" />' . "\n";
}

/**
 * Enqueues staging indicator styles for login and admin.
 *
 * @return void
 */
function soe_staging_enqueue_styles() {
	if ( ! soe_is_staging_environment() ) {
		return;
	}
	wp_enqueue_style(
		'soe-staging-indicator',
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/staging-indicator.css',
		array(),
		SOE_PLUGIN_VERSION
	);
}

/**
 * Adds a staging badge to the admin bar.
 *
 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
 * @return void
 */
function soe_staging_admin_bar_badge( $wp_admin_bar ) {
	if ( ! soe_is_staging_environment() ) {
		return;
	}
	$wp_admin_bar->add_node(
		array(
			'id'    => 'soe-staging-badge',
			'title' => '<span class="soe-staging-badge">' . esc_html__( 'Testumgebung', 'special-olympics-extension' ) . '</span>',
			'href'  => false,
			'meta'  => array(
				'class' => 'soe-staging-admin-bar-node',
				'title' => esc_attr__( 'Staging – keine Produktivdaten', 'special-olympics-extension' ),
			),
		)
	);
}

/**
 * Renders a compact banner on wp-login.php.
 *
 * @return void
 */
function soe_staging_render_login_banner() {
	if ( ! soe_is_staging_environment() ) {
		return;
	}
	echo '<div class="soe-staging-banner soe-staging-banner--login" aria-hidden="true"></div>';
}

/**
 * Renders a compact banner at the top of wp-admin screens.
 *
 * @return void
 */
function soe_staging_render_admin_banner() {
	if ( ! soe_is_staging_environment() ) {
		return;
	}
	echo '<div class="soe-staging-banner soe-staging-banner--admin" aria-hidden="true"></div>';
}
