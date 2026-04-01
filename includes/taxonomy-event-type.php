<?php
/**
 * Taxonomy "event_type" for the event CPT.
 *
 * Used to classify events (e.g. World Games, European Games,
 * National Games, Regional Games). Only used when CPT "event" exists.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'soe_register_taxonomy_event_type', 12 );

/**
 * Registers the "event_type" taxonomy for the event post type.
 */
function soe_register_taxonomy_event_type() {
	if ( ! post_type_exists( 'event' ) ) {
		return;
	}

	$labels = array(
		'name'                       => _x( 'Event-Typen', 'taxonomy general name', 'special-olympics-extension' ),
		'singular_name'              => _x( 'Event-Typ', 'taxonomy singular name', 'special-olympics-extension' ),
		'menu_name'                  => __( 'Event-Typen', 'special-olympics-extension' ),
		'all_items'                  => __( 'Alle Event-Typen', 'special-olympics-extension' ),
		'edit_item'                  => __( 'Event-Typ bearbeiten', 'special-olympics-extension' ),
		'view_item'                  => __( 'Event-Typ anzeigen', 'special-olympics-extension' ),
		'update_item'                => __( 'Event-Typ aktualisieren', 'special-olympics-extension' ),
		'add_new_item'               => __( 'Neuer Event-Typ', 'special-olympics-extension' ),
		'new_item_name'              => __( 'Neuer Event-Typ', 'special-olympics-extension' ),
		'search_items'               => __( 'Event-Typen suchen', 'special-olympics-extension' ),
		'not_found'                  => __( 'Keine Event-Typen gefunden', 'special-olympics-extension' ),
		'no_terms'                   => __( 'Keine Event-Typen', 'special-olympics-extension' ),
		'items_list_navigation'      => __( 'Event-Typen Navigation', 'special-olympics-extension' ),
		'items_list'                 => __( 'Event-Typen Liste', 'special-olympics-extension' ),
		'back_to_items'              => __( '← Zurück zu Event-Typen', 'special-olympics-extension' ),
	);

	$args = array(
		'labels'             => $labels,
		'description'        => __( 'Art des Events (z.B. Weltspiele, Europäische Spiele)', 'special-olympics-extension' ),
		'public'             => true,
		'publicly_queryable' => true,
		'hierarchical'       => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => true,
		'show_in_rest'       => true,
		'show_tagcloud'      => true,
		'show_in_quick_edit' => true,
		'show_admin_column'  => true,
		'rewrite'            => array(
			'slug'         => 'event-type',
			'with_front'   => true,
			'hierarchical' => false,
		),
		'query_var'          => 'event_type',
		'capabilities'       => array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		),
	);

	register_taxonomy( 'event_type', array( 'event' ), $args );
}
