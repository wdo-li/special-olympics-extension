<?php
/**
 * Intranet Restriction: Redirect non-logged-in users to login.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', 'soe_intranet_restrict_access', 5 );
function soe_intranet_restrict_access() {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path_normalized = preg_replace( '#\?.*$#', '', $path );

	// Not logged in: redirect to login (except allowed paths).
	if ( ! is_user_logged_in() ) {
		if (
			strpos( $path_normalized, 'wp-login.php' ) !== false ||
			strpos( $path_normalized, 'wp-admin/' ) !== false ||
			strpos( $path_normalized, 'wp-signup.php' ) !== false ||
			strpos( $path_normalized, 'wp-activate.php' ) !== false ||
			strpos( $path_normalized, 'wp-lostpassword.php' ) !== false ||
			strpos( $path_normalized, 'soe-anwesenheit' ) !== false
		) {
			return;
		}
		$requested = home_url( wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/' ) );
		wp_safe_redirect( wp_login_url( $requested ) );
		exit;
	}

	// Logged in on frontend: redirect to backend unless it's the token-based attendance page.
	if ( ! is_admin() && strpos( $path_normalized, 'soe-anwesenheit' ) === false ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
}
