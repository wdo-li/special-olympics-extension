<?php
/**
 * Admin menu and toolbar: hide, reorder, and customize.
 *
 * Sidebar menu:
 * - Hides Posts and Comments for all users.
 * - Reorders menu so SOE plugin items appear consecutively.
 *
 * Toolbar (admin bar):
 * - Removes WordPress logo.
 * - Replaces site-name (house + dropdown) with plain site title.
 * - "+ Neu" only for admins; "Seite" removed for admins.
 * - For non-admins: "Willkommen …" has no link, no "Profil bearbeiten".
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'soe_hide_posts_and_comments_menu', 999 );
function soe_hide_posts_and_comments_menu() {
	remove_menu_page( 'edit.php' );           // Beiträge
	remove_menu_page( 'edit-comments.php' );  // Kommentare
}

add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', 'soe_admin_menu_order' );
function soe_admin_menu_order( $menu_order ) {
	$soe_order = array(
		'soe-dashboard',
		'soe-account',
		'edit.php?post_type=mitglied',
		'soe-meine-athleten',
		'soe-trainings',
		'soe-events',
		'edit.php?post_type=contact',
		'soe-telefonbuch',
		'soe-payrolls',
		'soe-settings',
		'users.php',
	);
	$soe_present  = array_intersect( $soe_order, $menu_order );
	$others       = array_diff( $menu_order, $soe_order );
	return array_merge( array_values( $soe_present ), array_values( $others ) );
}

add_action( 'admin_bar_menu', 'soe_admin_bar_add_site_title_first', 1 );
function soe_admin_bar_add_site_title_first( $wp_admin_bar ) {
	$site_title = get_bloginfo( 'name' );
	if ( $site_title ) {
		$wp_admin_bar->add_node( array(
			'id'    => 'soe-site-title',
			'title' => esc_html( $site_title ),
			'href'  => false,
			'meta'  => array( 'tabindex' => 0 ),
		) );
	}
}

add_action( 'admin_bar_menu', 'soe_customize_admin_bar', 999 );
function soe_customize_admin_bar( $wp_admin_bar ) {
	$is_admin = current_user_can( 'manage_options' );

	// 1. WordPress symbol – remove for all.
	$wp_admin_bar->remove_node( 'wp-logo' );

	// 2. Site name (house + dropdown) – remove, replaced by soe-site-title added at priority 1.
	$wp_admin_bar->remove_node( 'site-name' );

	// 4. "+ Neu" only for admins; remove "Seite" for admins.
	$wp_admin_bar->remove_node( 'comments' );
	$wp_admin_bar->remove_node( 'new-post' );
	if ( ! $is_admin ) {
		$wp_admin_bar->remove_node( 'new-content' );
	} else {
		$wp_admin_bar->remove_node( 'new-page' );
	}

	// 5. "Willkommen …" for non-admins: no link, no "Profil bearbeiten".
	if ( ! $is_admin ) {
		$account_node = $wp_admin_bar->get_node( 'my-account' );
		$user_info_node = $wp_admin_bar->get_node( 'user-info' );
		$logout_node = $wp_admin_bar->get_node( 'logout' );
		if ( $account_node ) {
			$wp_admin_bar->remove_node( 'my-account' );
			$wp_admin_bar->add_node( array(
				'id'     => 'my-account',
				'parent' => 'top-secondary',
				'title'  => $account_node->title,
				'href'   => false,
				'meta'   => isset( $account_node->meta ) ? $account_node->meta : array(),
			) );
			$wp_admin_bar->add_group( array( 'parent' => 'my-account', 'id' => 'user-actions' ) );
			if ( $user_info_node ) {
				$title = preg_replace( '/<span class=[\'"]display-name edit-profile[\'"]>.*?<\/span>/', '', $user_info_node->title );
				$wp_admin_bar->add_node( array(
					'parent' => 'user-actions',
					'id'     => 'user-info',
					'title'  => $title,
					'href'   => false,
				) );
			}
			if ( $logout_node ) {
				$wp_admin_bar->add_node( array(
					'parent' => 'user-actions',
					'id'     => 'logout',
					'title'  => $logout_node->title,
					'href'   => $logout_node->href,
				) );
			}
		}
	}
}
