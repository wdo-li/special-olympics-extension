<?php
/**
 * Registers the CPT "contact" (Kontakte).
 *
 * Admin only: visible and editable only for administrators.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'soe_register_post_type_contact', 10 );

/**
 * Registers the "contact" post type.
 */
function soe_register_post_type_contact() {
	$labels = array(
		'name'                  => _x( 'Kontakte', 'post type general name', 'special-olympics-extension' ),
		'singular_name'         => _x( 'Kontakt', 'post type singular name', 'special-olympics-extension' ),
		'menu_name'             => __( 'Kontakte', 'special-olympics-extension' ),
		'name_admin_bar'        => __( 'Kontakt', 'special-olympics-extension' ),
		'add_new'               => __( 'Hinzufügen', 'special-olympics-extension' ),
		'add_new_item'          => __( 'Neuer Kontakt', 'special-olympics-extension' ),
		'new_item'              => __( 'Neuer Kontakt', 'special-olympics-extension' ),
		'edit_item'             => __( 'Kontakt bearbeiten', 'special-olympics-extension' ),
		'view_item'             => __( 'Kontakt anzeigen', 'special-olympics-extension' ),
		'all_items'             => __( 'Alle Kontakte', 'special-olympics-extension' ),
		'search_items'          => __( 'Kontakte suchen', 'special-olympics-extension' ),
		'not_found'             => __( 'Keine Kontakte gefunden.', 'special-olympics-extension' ),
		'not_found_in_trash'    => __( 'Keine Kontakte im Papierkorb.', 'special-olympics-extension' ),
	);

	$args = array(
		'labels'             => $labels,
		'description'        => __( 'Kontakte (nur für Administratoren)', 'special-olympics-extension' ),
		'public'             => false,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'query_var'           => false,
		'rewrite'             => false,
		'capability_type'     => 'post',
		'capabilities'        => array(
			'edit_post'          => 'manage_options',
			'read_post'          => 'manage_options',
			'delete_post'        => 'manage_options',
			'edit_posts'         => 'manage_options',
			'edit_others_posts'  => 'manage_options',
			'publish_posts'      => 'manage_options',
			'read_private_posts' => 'manage_options',
			'delete_posts'       => 'manage_options',
			'create_posts'       => 'manage_options',
		),
		'map_meta_cap'        => false,
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => null,
		'menu_icon'            => 'dashicons-id-alt',
		'supports'            => array( 'title', 'editor', 'custom-fields' ),
		'show_in_rest'        => false,
	);

	register_post_type( 'contact', $args );
}
