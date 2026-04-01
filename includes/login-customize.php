<?php
/**
 * Login page customization.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'login_enqueue_scripts', 'soe_login_enqueue_styles' );
function soe_login_enqueue_styles() {
	$plugin_root_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) . '/plugin.php' );
	wp_enqueue_style(
		'soe-login',
		$plugin_root_url . 'assets/css/login.css',
		array(),
		SOE_PLUGIN_VERSION
	);
	$bg_url   = esc_url( function_exists( 'soe_get_login_bg_url' ) ? soe_get_login_bg_url() : $plugin_root_url . 'assets/img/bg-1.webp' );
	$logo_url = esc_url( function_exists( 'soe_get_login_logo_url' ) ? soe_get_login_logo_url() : $plugin_root_url . 'assets/img/logo/Logo-center.svg' );
	$inline_css = "body.login { background-image: url({$bg_url}); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: 100vh; background-color: #e8e8e8; }";
	$inline_css .= "#login h1 a { background-image: url({$logo_url}) !important; }";
	wp_add_inline_style( 'soe-login', $inline_css );
}

add_filter( 'login_headerurl', 'soe_login_header_url' );
function soe_login_header_url() {
	return home_url();
}

add_filter( 'login_headertext', 'soe_login_header_text' );
function soe_login_header_text() {
	return __( 'Special Olympics Liechtenstein', 'special-olympics-extension' );
}

