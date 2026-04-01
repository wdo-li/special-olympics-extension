<?php
/**
 * Account page: Vorname, Nachname, E-Mail, Passwort.
 * Saves to WP User only; sync to mitglied via profile_update.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'soe_account_add_menu', 999 );
add_action( 'admin_init', 'soe_restrict_profile_pages_for_non_admin' );
add_filter( 'login_redirect', 'soe_login_redirect_to_dashboard', 10, 3 );

/**
 * Returns the first mitglied post ID linked to the current user, or 0 if none.
 *
 * @return int Post ID or 0.
 */
function soe_get_current_user_mitglied_id() {
	$current_user_id = get_current_user_id();
	if ( ! $current_user_id ) {
		return 0;
	}
	$mitglied_posts = get_posts( array(
		'post_type'      => 'mitglied',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => 'user_id',
		'meta_value'     => $current_user_id,
		'orderby'        => 'date',
		'order'          => 'ASC',
	) );
	return empty( $mitglied_posts ) ? 0 : (int) $mitglied_posts[0];
}

/**
 * After login: redirect all users to the SOE dashboard.
 */
function soe_login_redirect_to_dashboard( $redirect_to, $requested_redirect_to, $user ) {
	if ( ! $user || is_wp_error( $user ) ) {
		return $redirect_to;
	}
	return admin_url( 'admin.php?page=soe-dashboard' );
}

/**
 * Restrict profile.php and user-edit.php to admins only.
 * Redirect "Mein Account" (page=soe-account) to mitglied edit in admin_init so headers are not yet sent.
 */
function soe_restrict_profile_pages_for_non_admin() {
	if ( ! is_admin() || ! did_action( 'init' ) ) {
		return;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	global $pagenow;

	// "Mein Account" link: redirect to mitglied edit before any output (avoids "headers already sent").
	if ( $pagenow === 'admin.php' && isset( $_GET['page'] ) ) {
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		if ( $page === 'soe-account' ) {
		$mitglied_id = soe_get_current_user_mitglied_id();
		if ( $mitglied_id ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . $mitglied_id . '&action=edit' ) );
			exit;
		}
		}
	}

	$mitglied_id  = soe_get_current_user_mitglied_id();
	$redirect_url = $mitglied_id
		? admin_url( 'admin.php?page=soe-dashboard' )
		: admin_url( 'admin.php?page=soe-account' );

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && in_array( $screen->id, array( 'profile', 'user-edit' ), true ) ) {
		wp_safe_redirect( $redirect_url );
		exit;
	}
	if ( in_array( $pagenow, array( 'profile.php', 'user-edit.php' ), true ) ) {
		wp_safe_redirect( $redirect_url );
		exit;
	}
}

/**
 * Renders "Mein Account" page content (only shown when user has no linked mitglied; otherwise redirect in admin_init).
 */
function soe_redirect_mein_account_to_mitglied() {
	$mitglied_id = soe_get_current_user_mitglied_id();
	if ( $mitglied_id ) {
		// Should not reach here: redirect happens in admin_init. Fallback link.
		echo '<p><a href="' . esc_url( admin_url( 'post.php?post=' . $mitglied_id . '&action=edit' ) ) . '" class="button button-primary">' . esc_html__( 'Zum Mitglieds-Profil', 'special-olympics-extension' ) . '</a></p>';
		return;
	}
	wp_die( esc_html__( 'Kein Mitglieds-Profil gefunden. Bitte wende dich an den Administrator.', 'special-olympics-extension' ) );
}

/**
 * Adds "Mein Account" as top-level menu item for users with a linked mitglied.
 * Uses capability "read" so any logged-in user with a linked profile can access.
 */
function soe_account_add_menu() {
	if ( ! soe_get_current_user_mitglied_id() ) {
		return;
	}
	add_menu_page(
		__( 'Mein Account', 'special-olympics-extension' ),
		__( 'Mein Account', 'special-olympics-extension' ),
		'read',
		'soe-account',
		'soe_redirect_mein_account_to_mitglied',
		'dashicons-admin-users',
		3
	);
}
