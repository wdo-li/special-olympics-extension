<?php
/**
 * Shared taxonomy "sport" for members, trainings, and events.
 *
 * Used for filtering (e.g. telephone directory, statistics) and for
 * payroll (e.g. accounting number per sport). Capabilities are set
 * so that the Members plugin can control access.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'soe_register_taxonomy_sport', 11 );

/**
 * Registers the "sport" taxonomy for mitglied (and later training, event).
 */
function soe_register_taxonomy_sport() {
	$post_types = array( 'mitglied', 'training', 'event' );

	$labels = array(
		'name'                       => _x( 'Sportarten', 'taxonomy general name', 'special-olympics-extension' ),
		'singular_name'              => _x( 'Sportart', 'taxonomy singular name', 'special-olympics-extension' ),
		'menu_name'                  => __( 'Sportarten', 'special-olympics-extension' ),
		'all_items'                  => __( 'Alle Sportarten', 'special-olympics-extension' ),
		'edit_item'                  => __( 'Sportart bearbeiten', 'special-olympics-extension' ),
		'view_item'                  => __( 'Sportart anzeigen', 'special-olympics-extension' ),
		'update_item'                => __( 'Sportart aktualisieren', 'special-olympics-extension' ),
		'add_new_item'               => __( 'Neue Sportart', 'special-olympics-extension' ),
		'new_item_name'              => __( 'Neue Sportart', 'special-olympics-extension' ),
		'search_items'               => __( 'Sportarten suchen', 'special-olympics-extension' ),
		'not_found'                  => __( 'Keine Sportarten gefunden', 'special-olympics-extension' ),
		'no_terms'                   => __( 'Keine Sportarten', 'special-olympics-extension' ),
		'items_list_navigation'      => __( 'Sportarten Navigation', 'special-olympics-extension' ),
		'items_list'                 => __( 'Sportarten Liste', 'special-olympics-extension' ),
		'back_to_items'              => __( '← Zurück zu Sportarten', 'special-olympics-extension' ),
	);

	$args = array(
		'labels'             => $labels,
		'description'        => __( 'Sportarten für Mitglieder, Trainings und Events', 'special-olympics-extension' ),
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
			'slug'         => 'sport',
			'with_front'   => true,
			'hierarchical' => false,
		),
		'query_var'          => 'sport',
		'capabilities'       => array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_posts',
		),
	);

	register_taxonomy( 'sport', $post_types, $args );
}
