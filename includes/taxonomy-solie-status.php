<?php
/**
 * Taxonomy "SOLie-Status" for contacts.
 *
 * Used to categorize CPT "contact" entries (e.g. aktiv, inaktiv, Organisation, Behörde).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'soe_register_taxonomy_solie_status', 11 );

/**
 * Registers the "SOLie-Status" taxonomy for contact.
 */
function soe_register_taxonomy_solie_status() {
	$post_types = array( 'contact' );

	$labels = array(
		'name'                       => _x( 'SOLie-Status', 'taxonomy general name', 'special-olympics-extension' ),
		'singular_name'              => _x( 'SOLie-Status', 'taxonomy singular name', 'special-olympics-extension' ),
		'menu_name'                  => __( 'SOLie-Status', 'special-olympics-extension' ),
		'all_items'                  => __( 'Alle SOLie-Status', 'special-olympics-extension' ),
		'edit_item'                  => __( 'SOLie-Status bearbeiten', 'special-olympics-extension' ),
		'view_item'                  => __( 'SOLie-Status anzeigen', 'special-olympics-extension' ),
		'update_item'                => __( 'SOLie-Status aktualisieren', 'special-olympics-extension' ),
		'add_new_item'               => __( 'Neuer SOLie-Status', 'special-olympics-extension' ),
		'new_item_name'              => __( 'Neuer SOLie-Status', 'special-olympics-extension' ),
		'search_items'               => __( 'SOLie-Status suchen', 'special-olympics-extension' ),
		'not_found'                  => __( 'Kein SOLie-Status gefunden', 'special-olympics-extension' ),
		'no_terms'                   => __( 'Keine SOLie-Status', 'special-olympics-extension' ),
		'items_list_navigation'      => __( 'SOLie-Status Navigation', 'special-olympics-extension' ),
		'items_list'                 => __( 'SOLie-Status Liste', 'special-olympics-extension' ),
		'back_to_items'              => __( '← Zurück zu SOLie-Status', 'special-olympics-extension' ),
	);

	$args = array(
		'labels'             => $labels,
		'description'        => __( 'SOLie-Status für Kontakte', 'special-olympics-extension' ),
		'public'             => false,
		'publicly_queryable' => false,
		'hierarchical'       => false,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'show_in_nav_menus'  => false,
		'show_in_rest'       => false,
		'show_tagcloud'      => false,
		'show_in_quick_edit' => true,
		'show_admin_column'  => true,
		'rewrite'            => false,
		'query_var'          => 'solie_status',
		'capabilities'       => array(
			'manage_terms' => 'manage_options',
			'edit_terms'   => 'manage_options',
			'delete_terms' => 'manage_options',
			'assign_terms' => 'manage_options',
		),
	);

	register_taxonomy( 'solie_status', $post_types, $args );
}

