<?php
/**
 * Registers the CPT "event".
 *
 * Capabilities for Members plugin. Create/delete only administrators;
 * assign persons: administrator and Hauptleiter.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'soe_register_post_type_event', 10 );
add_action( 'init', 'soe_grant_event_caps_to_roles', 25 );

/**
 * Grants event capabilities: Administrator full; Hauptleiter can edit and assign persons.
 */
function soe_grant_event_caps_to_roles() {
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$caps = array(
			'edit_event', 'read_event', 'delete_event',
			'edit_events', 'edit_others_events', 'publish_events',
			'read_private_events', 'delete_events', 'delete_private_events',
			'delete_published_events', 'delete_others_events',
		);
		foreach ( $caps as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}

	$hauptleiter = get_role( 'hauptleiter_in' );
	if ( $hauptleiter ) {
		$caps = array( 'edit_events', 'edit_event', 'edit_published_events', 'read_event', 'publish_events' );
		foreach ( $caps as $cap ) {
			if ( ! $hauptleiter->has_cap( $cap ) ) {
				$hauptleiter->add_cap( $cap );
			}
		}
	}
}

/**
 * Registers the "event" post type.
 */
function soe_register_post_type_event() {
	$labels = array(
		'name'                  => _x( 'Events', 'post type general name', 'special-olympics-extension' ),
		'singular_name'         => _x( 'Event', 'post type singular name', 'special-olympics-extension' ),
		'menu_name'             => __( 'Events', 'special-olympics-extension' ),
		'name_admin_bar'        => __( 'Event', 'special-olympics-extension' ),
		'add_new'               => __( 'Hinzufügen', 'special-olympics-extension' ),
		'add_new_item'          => __( 'Neues Event', 'special-olympics-extension' ),
		'new_item'              => __( 'Neues Event', 'special-olympics-extension' ),
		'edit_item'             => __( 'Event bearbeiten', 'special-olympics-extension' ),
		'view_item'             => __( 'Event anzeigen', 'special-olympics-extension' ),
		'all_items'             => __( 'Alle Events', 'special-olympics-extension' ),
		'search_items'          => __( 'Events suchen', 'special-olympics-extension' ),
		'parent_item_colon'     => __( 'Übergeordnetes Event:', 'special-olympics-extension' ),
		'not_found'             => __( 'Keine Events gefunden.', 'special-olympics-extension' ),
		'not_found_in_trash'    => __( 'Keine Events im Papierkorb.', 'special-olympics-extension' ),
		'archives'              => _x( 'Events-Archiv', 'post type archive', 'special-olympics-extension' ),
		'filter_items_list'     => _x( 'Events filtern', 'screen reader', 'special-olympics-extension' ),
		'items_list_navigation' => _x( 'Events-Navigation', 'screen reader', 'special-olympics-extension' ),
		'items_list'            => _x( 'Events-Liste', 'screen reader', 'special-olympics-extension' ),
	);

	$args = array(
		'labels'             => $labels,
		'description'        => __( 'Events mit Teilnehmern und Snapshot-Sync zu Mitgliedern', 'special-olympics-extension' ),
		'public'             => false,
		'publicly_queryable'  => false,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'query_var'           => true,
		'rewrite'             => array( 'slug' => 'event' ),
		'capability_type'     => 'event',
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => null,
		'menu_icon'            => 'dashicons-tickets-alt',
		'supports'            => array( 'title', 'custom-fields' ),
		'show_in_rest'        => false,
	);

	register_post_type( 'event', $args );
}
