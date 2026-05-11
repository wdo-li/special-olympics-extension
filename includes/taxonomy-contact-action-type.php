<?php
/**
 * Taxonomy "Contact action type" for flexible action type labels.
 *
 * Terms are stored in WordPress; the custom table `soe_contact_actions.action_type`
 * holds the term slug. The taxonomy is attached to the `contact` CPT for admin UI
 * only (no metabox on contact edit — terms are not assigned to posts).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Taxonomy slug for contact action types. */
define( 'SOE_CONTACT_ACTION_TYPE_TAXONOMY', 'soe_contact_action_type' );

add_action( 'init', 'soe_register_taxonomy_contact_action_type', 11 );

/**
 * Registers the contact action type taxonomy on the contact CPT.
 */
function soe_register_taxonomy_contact_action_type() {
	$labels = array(
		'name'                  => _x( 'Aktionstypen', 'taxonomy general name', 'special-olympics-extension' ),
		'singular_name'         => _x( 'Aktionstyp', 'taxonomy singular name', 'special-olympics-extension' ),
		'menu_name'             => __( 'Aktionstypen', 'special-olympics-extension' ),
		'all_items'             => __( 'Alle Aktionstypen', 'special-olympics-extension' ),
		'edit_item'             => __( 'Aktionstyp bearbeiten', 'special-olympics-extension' ),
		'view_item'             => __( 'Aktionstyp anzeigen', 'special-olympics-extension' ),
		'update_item'           => __( 'Aktionstyp aktualisieren', 'special-olympics-extension' ),
		'add_new_item'          => __( 'Neuer Aktionstyp', 'special-olympics-extension' ),
		'new_item_name'         => __( 'Name des Aktionstyps', 'special-olympics-extension' ),
		'search_items'          => __( 'Aktionstypen suchen', 'special-olympics-extension' ),
		'not_found'             => __( 'Keine Aktionstypen gefunden.', 'special-olympics-extension' ),
		'no_terms'              => __( 'Keine Aktionstypen', 'special-olympics-extension' ),
		'items_list_navigation' => __( 'Aktionstypen-Navigation', 'special-olympics-extension' ),
		'items_list'            => __( 'Aktionstypen-Liste', 'special-olympics-extension' ),
		'back_to_items'         => __( '&larr; Zurück zu Aktionstypen', 'special-olympics-extension' ),
	);

	$args = array(
		'labels'             => $labels,
		'description'        => __( 'Typen für Kontakt-Aktionen (Kampagnen).', 'special-olympics-extension' ),
		'public'             => false,
		'publicly_queryable' => false,
		'hierarchical'       => false,
		'show_ui'            => true,
		'show_in_menu'       => 'edit.php?post_type=contact',
		'show_in_nav_menus'  => false,
		'show_in_rest'       => false,
		'show_tagcloud'      => false,
		'show_in_quick_edit' => false,
		'show_admin_column'  => false,
		'meta_box_cb'        => false,
		'rewrite'            => false,
		'query_var'          => false,
		'capabilities'       => array(
			'manage_terms' => 'manage_options',
			'edit_terms'   => 'manage_options',
			'delete_terms' => 'manage_options',
			'assign_terms' => 'manage_options',
		),
	);

	register_taxonomy( SOE_CONTACT_ACTION_TYPE_TAXONOMY, array( 'contact' ), $args );
}
