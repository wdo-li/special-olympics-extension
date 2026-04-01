<?php
/**
 * Registers the CPT "training".
 *
 * Independent of the legacy sportangebot/trainingstermin; clear capabilities
 * for Members plugin. Taxonomy "sport" is attached in taxonomy-sport.php.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'soe_register_post_type_training', 10 );
add_action( 'init', 'soe_grant_training_caps_to_roles', 25 );

/**
 * Grants training capabilities: Administrator full; Hauptleiter limited (see map_meta_cap).
 */
function soe_grant_training_caps_to_roles() {
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$caps = array(
			'edit_training', 'read_training', 'delete_training',
			'edit_trainings', 'edit_others_trainings', 'publish_trainings',
			'read_private_trainings', 'delete_trainings', 'delete_private_trainings',
			'delete_published_trainings', 'delete_others_trainings',
		);
		foreach ( $caps as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}

	$hauptleiter = get_role( 'hauptleiter_in' );
	if ( $hauptleiter ) {
		$caps = array( 'edit_trainings', 'edit_training', 'edit_published_trainings', 'read_training', 'publish_trainings' );
		foreach ( $caps as $cap ) {
			if ( ! $hauptleiter->has_cap( $cap ) ) {
				$hauptleiter->add_cap( $cap );
			}
		}
	}

	$leiter = get_role( 'leiter_in' );
	if ( $leiter ) {
		$caps = array( 'edit_trainings', 'edit_training', 'read_training' );
		foreach ( $caps as $cap ) {
			if ( ! $leiter->has_cap( $cap ) ) {
				$leiter->add_cap( $cap );
			}
		}
	}
}

/**
 * Registers the "training" post type.
 */
function soe_register_post_type_training() {
	$labels = array(
		'name'                  => _x( 'Trainings', 'post type general name', 'special-olympics-extension' ),
		'singular_name'         => _x( 'Training', 'post type singular name', 'special-olympics-extension' ),
		'menu_name'             => __( 'Training', 'special-olympics-extension' ),
		'name_admin_bar'        => __( 'Training', 'special-olympics-extension' ),
		'add_new'               => __( 'Hinzufügen', 'special-olympics-extension' ),
		'add_new_item'          => __( 'Neues Training', 'special-olympics-extension' ),
		'new_item'              => __( 'Neues Training', 'special-olympics-extension' ),
		'edit_item'             => __( 'Training bearbeiten', 'special-olympics-extension' ),
		'view_item'             => __( 'Training anzeigen', 'special-olympics-extension' ),
		'all_items'             => __( 'Alle Trainings', 'special-olympics-extension' ),
		'search_items'          => __( 'Trainings suchen', 'special-olympics-extension' ),
		'parent_item_colon'     => __( 'Übergeordnetes Training:', 'special-olympics-extension' ),
		'not_found'             => __( 'Keine Trainings gefunden.', 'special-olympics-extension' ),
		'not_found_in_trash'    => __( 'Keine Trainings im Papierkorb.', 'special-olympics-extension' ),
		'archives'              => _x( 'Trainings-Archiv', 'post type archive', 'special-olympics-extension' ),
		'filter_items_list'     => _x( 'Trainings filtern', 'screen reader', 'special-olympics-extension' ),
		'items_list_navigation' => _x( 'Trainings-Navigation', 'screen reader', 'special-olympics-extension' ),
		'items_list'            => _x( 'Trainings-Liste', 'screen reader', 'special-olympics-extension' ),
	);

	$args = array(
		'labels'             => $labels,
		'description'        => __( 'Trainings mit Sessions und Anwesenheit', 'special-olympics-extension' ),
		'public'             => false,
		'publicly_queryable'  => false,
		'show_ui'             => false,
		'show_in_menu'        => false,
		'query_var'           => true,
		'rewrite'             => array( 'slug' => 'training' ),
		'capability_type'     => 'training',
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'hierarchical'        => false,
		'menu_position'       => null,
		'menu_icon'            => 'dashicons-calendar-alt',
		'supports'            => array( 'title', 'custom-fields' ),
		'show_in_rest'        => false,
	);

	register_post_type( 'training', $args );
}
