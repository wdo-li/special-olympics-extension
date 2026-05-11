<?php
/**
 * CPT "contact": auto title from Geschäft or Vorname + Name, two-column layout, no editor.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds contact display title from Geschäft or Vorname + Name (no fallback).
 *
 * @param string $geschaeft Business name.
 * @param string $vorname   First name.
 * @param string $name      Last name (ACF field "name").
 * @return string
 */
function soe_contact_build_title_string( $geschaeft, $vorname, $name ) {
	$geschaeft = is_string( $geschaeft ) ? trim( $geschaeft ) : '';
	if ( $geschaeft !== '' ) {
		return $geschaeft;
	}
	$vorname = is_string( $vorname ) ? trim( $vorname ) : '';
	$name    = is_string( $name ) ? trim( $name ) : '';
	$built   = trim( preg_replace( '/\s+/u', ' ', $vorname . ' ' . $name ) );
	return $built;
}

/**
 * Title used when Geschäft and name parts are all empty.
 *
 * @return string
 */
function soe_contact_fallback_title_string() {
	return __( 'Neuer Kontakt', 'special-olympics-extension' );
}

/**
 * Resolves final post title (with fallback).
 *
 * @param string $geschaeft Business name.
 * @param string $vorname   First name.
 * @param string $name      Last name.
 * @return string
 */
function soe_contact_resolve_title_string( $geschaeft, $vorname, $name ) {
	$built = soe_contact_build_title_string( $geschaeft, $vorname, $name );
	return $built !== '' ? $built : soe_contact_fallback_title_string();
}

/**
 * Hides the core #titlediv block on the classic contact edit screen.
 *
 * WordPress prints #titlediv directly in edit-form-advanced.php (not via the meta box API),
 * so remove_meta_box( 'titlediv', … ) has no effect. The native block is hidden with CSS and
 * the core #title input is stripped of name="post_title" in admin JS. A hidden post_title
 * input keeps form submissions valid; the real title is synced on save via ACF fields.
 */
add_action( 'admin_print_styles', 'soe_contact_hide_native_title_print_styles', 99 );
function soe_contact_hide_native_title_print_styles() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'contact' || $screen->base !== 'post' ) {
		return;
	}
	echo '<style id="soe-contact-hide-native-title">#titlediv{display:none!important;}</style>';
}

/**
 * Outputs a hidden post_title field (no visible title UI). Core #title must not submit post_title.
 *
 * @param WP_Post $post Current post.
 */
add_action( 'edit_form_after_title', 'soe_contact_render_hidden_post_title_field', 5 );
function soe_contact_render_hidden_post_title_field( $post ) {
	if ( ! $post instanceof WP_Post || $post->post_type !== 'contact' ) {
		return;
	}
	$geschaeft = function_exists( 'get_field' ) ? get_field( 'geschaeft', $post->ID ) : '';
	$vorname   = function_exists( 'get_field' ) ? get_field( 'vorname', $post->ID ) : '';
	$name      = function_exists( 'get_field' ) ? get_field( 'name', $post->ID ) : '';
	$resolved  = soe_contact_resolve_title_string( $geschaeft, $vorname, $name );
	if ( $post->post_status === 'auto-draft' ) {
		$resolved = soe_contact_fallback_title_string();
	}
	echo '<input type="hidden" name="post_title" id="soe-contact-post-title" value="' . esc_attr( $resolved ) . '" />';
}

/**
 * Sync contact post title after ACF fields are stored.
 */
add_action( 'acf/save_post', 'soe_save_contact_post', 99 );
function soe_save_contact_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || get_post_type( $post_id ) !== 'contact' ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	$geschaeft = function_exists( 'get_field' ) ? get_field( 'geschaeft', $post_id ) : '';
	$vorname   = function_exists( 'get_field' ) ? get_field( 'vorname', $post_id ) : '';
	$name      = function_exists( 'get_field' ) ? get_field( 'name', $post_id ) : '';
	$titel = soe_contact_resolve_title_string( $geschaeft, $vorname, $name );
	soe_update_contact_post_title( $post_id, $titel );
}

/**
 * Updates contact post title and slug. Unhooks soe_save_contact_post to avoid re-entry.
 *
 * @param int    $post_id Post ID.
 * @param string $titel   New title.
 */
function soe_update_contact_post_title( $post_id, $titel ) {
	remove_action( 'acf/save_post', 'soe_save_contact_post', 99 );
	wp_update_post(
		array(
			'ID'         => (int) $post_id,
			'post_title' => $titel,
			'post_name'  => sanitize_title( $titel ),
		)
	);
	add_action( 'acf/save_post', 'soe_save_contact_post', 99 );
}

/**
 * Forces 2-column layout on contact edit screen (same pattern as mitglied).
 */
add_filter( 'screen_layout_columns', 'soe_contact_screen_layout_columns', 10, 3 );
function soe_contact_screen_layout_columns( $columns, $screen_id, $screen ) {
	if ( $screen_id === 'contact' ) {
		$columns['contact'] = 2;
	}
	return $columns;
}

add_filter( 'get_user_option_screen_layout_contact', 'soe_force_contact_two_columns' );
function soe_force_contact_two_columns( $result ) {
	return 2;
}

add_filter( 'admin_body_class', 'soe_contact_admin_body_class' );
function soe_contact_admin_body_class( $classes ) {
	$screen = get_current_screen();
	if ( $screen && $screen->post_type === 'contact' && $screen->get_columns() === 2 ) {
		$classes .= ' has-right-sidebar';
	}
	return $classes;
}

/**
 * Admin script: live preview of generated title from ACF fields.
 *
 * @param string $hook Current admin page hook.
 */
add_action( 'admin_enqueue_scripts', 'soe_contact_admin_enqueue_post_scripts' );
function soe_contact_admin_enqueue_post_scripts( $hook ) {
	if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'contact' ) {
		return;
	}
	$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
	wp_enqueue_script(
		'soe-admin-contact-post',
		$plugin_url . 'assets/js/admin-contact-post.js',
		array( 'jquery' ),
		SOE_PLUGIN_VERSION,
		true
	);
}
