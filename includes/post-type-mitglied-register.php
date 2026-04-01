<?php
/**
 * Registers the CPT "mitglied" (members).
 *
 * Previously registered via ACF Post Types; now owned by this plugin.
 * Capabilities are set for Members plugin compatibility.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Priority 20: run after ACF, so we override if ACF had mitglied (e.g. trashed) and unregistered it.
add_action( 'init', 'soe_register_post_type_mitglied', 20 );
add_action( 'init', 'soe_grant_mitglied_caps_to_roles', 25 );

/**
 * Grants mitglied capabilities:
 * - Administrator: full access
 * - Ansprechperson: can create and edit own members
 * - Other roles: only read_mitglied (for "Mein Account" access via map_meta_cap)
 */
function soe_grant_mitglied_caps_to_roles() {
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin_caps = array(
			'edit_mitglied', 'read_mitglied', 'delete_mitglied',
			'edit_mitglieds', 'edit_others_mitglieds', 'publish_mitglieds',
			'read_private_mitglieds', 'delete_mitglieds', 'delete_private_mitglieds',
			'delete_published_mitglieds', 'delete_others_mitglieds',
		);
		foreach ( $admin_caps as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				$admin->add_cap( $cap );
			}
		}
	}

	// Ansprechperson: can create and edit own members.
	$ansprechperson = get_role( 'ansprechperson' );
	if ( $ansprechperson ) {
		$ansprech_caps = array( 'edit_mitglieds', 'edit_mitglied', 'edit_published_mitglieds', 'read_mitglied', 'publish_mitglieds' );
		foreach ( $ansprech_caps as $cap ) {
			if ( ! $ansprechperson->has_cap( $cap ) ) {
				$ansprechperson->add_cap( $cap );
			}
		}
	}

	// Other SOE roles: only read_mitglied for "Mein Account" access (actual edit handled by map_meta_cap).
	// Remove any previously granted edit capabilities.
	$soe_roles = array( 'hauptleiter_in', 'leiter_in', 'athlet_in', 'unified', 'assistenztrainer_in', 'helfer_in', 'praktikant_in', 'schueler_in', 'athlete_leader' );
	$caps_to_remove = array( 'edit_mitglieds', 'edit_mitglied', 'edit_published_mitglieds', 'publish_mitglieds', 'edit_others_mitglieds' );
	foreach ( $soe_roles as $role_slug ) {
		$role = get_role( $role_slug );
		if ( $role ) {
			// Grant read_mitglied for basic access.
			if ( ! $role->has_cap( 'read_mitglied' ) ) {
				$role->add_cap( 'read_mitglied' );
			}
			// Remove edit capabilities.
			foreach ( $caps_to_remove as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	// Grant view_telefonbuch capability to Hauptleiter and Leiter (for Telefonbuch access).
	$telefonbuch_roles = array( 'hauptleiter_in', 'leiter_in' );
	foreach ( $telefonbuch_roles as $role_slug ) {
		$role = get_role( $role_slug );
		if ( $role && ! $role->has_cap( 'view_telefonbuch' ) ) {
			$role->add_cap( 'view_telefonbuch' );
		}
	}
}

/**
 * Registers the "mitglied" post type.
 */
function soe_register_post_type_mitglied() {
	$labels = array(
		'name'                  => _x( 'Mitglieder', 'post type general name', 'special-olympics-extension' ),
		'singular_name'         => _x( 'Mitglied', 'post type singular name', 'special-olympics-extension' ),
		'menu_name'             => __( 'Mitglieder', 'special-olympics-extension' ),
		'name_admin_bar'        => __( 'Mitglied', 'special-olympics-extension' ),
		'add_new'               => __( 'Hinzufügen', 'special-olympics-extension' ),
		'add_new_item'          => __( 'Neues Mitglied', 'special-olympics-extension' ),
		'new_item'              => __( 'Neues Mitglied', 'special-olympics-extension' ),
		'edit_item'             => __( 'Mitglied bearbeiten', 'special-olympics-extension' ),
		'view_item'             => __( 'Mitglied anzeigen', 'special-olympics-extension' ),
		'all_items'             => __( 'Alle Mitglieder', 'special-olympics-extension' ),
		'search_items'          => __( 'Mitglieder suchen', 'special-olympics-extension' ),
		'parent_item_colon'     => __( 'Übergeordnetes Mitglied:', 'special-olympics-extension' ),
		'not_found'             => __( 'Keine Mitglieder gefunden.', 'special-olympics-extension' ),
		'not_found_in_trash'    => __( 'Keine Mitglieder im Papierkorb.', 'special-olympics-extension' ),
		'featured_image'        => _x( 'Profilbild', 'Overrides the "Featured Image" phrase.', 'special-olympics-extension' ),
		'set_featured_image'    => _x( 'Profilbild festlegen', 'Overrides the "Set featured image" phrase.', 'special-olympics-extension' ),
		'remove_featured_image' => _x( 'Profilbild entfernen', 'Overrides the "Remove featured image" phrase.', 'special-olympics-extension' ),
		'use_featured_image'    => _x( 'Als Profilbild verwenden', 'Overrides the "Use as featured image" phrase.', 'special-olympics-extension' ),
		'archives'              => _x( 'Mitglieder-Archiv', 'The post type archive label.', 'special-olympics-extension' ),
		'insert_into_item'      => _x( 'In Mitglied einfügen', 'Overrides the "Insert into post" phrase.', 'special-olympics-extension' ),
		'uploaded_to_this_item' => _x( 'Zu diesem Mitglied hochgeladen', 'Overrides the "Uploaded to this post" phrase.', 'special-olympics-extension' ),
		'filter_items_list'     => _x( 'Mitglieder filtern', 'Screen reader text.', 'special-olympics-extension' ),
		'items_list_navigation' => _x( 'Mitglieder-Navigation', 'Screen reader text.', 'special-olympics-extension' ),
		'items_list'            => _x( 'Mitglieder-Liste', 'Screen reader text.', 'special-olympics-extension' ),
	);

	$args = array(
		'labels'               => $labels,
		'description'          => __( 'Mitglieder von Special Olympics Liechtenstein', 'special-olympics-extension' ),
		'public'               => false,
		'publicly_queryable'   => false,
		'exclude_from_search'  => true,
		'show_ui'              => true,
		'show_in_menu'         => true,
		'query_var'            => true,
		'rewrite'              => array( 'slug' => 'mitglied' ),
		'capability_type'      => 'mitglied',
		'map_meta_cap'         => true,
		'has_archive'          => false,
		'hierarchical'       => false,
		'menu_position'      => null,
		'menu_icon'          => 'dashicons-groups',
		'supports'           => array( 'title', 'custom-fields' ),
		'show_in_rest'       => false,
	);

	register_post_type( 'mitglied', $args );
}
