<?php
/**
 * CPT "mitglied": capabilities and member status (active/archived).
 *
 * - Ensures capability_type and map_meta_cap so Members plugin can control access.
 * - Non-admins see only their own posts (author = current user); only admins can delete.
 * - Adds member_status (active/archived) via post meta; archive/restore actions.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post meta key for member status: 'active' or 'archived'. */
define( 'SOE_MEMBER_STATUS_META', 'soe_member_status' );

/** Default status when not set. */
define( 'SOE_MEMBER_STATUS_ACTIVE', 'active' );
define( 'SOE_MEMBER_STATUS_ARCHIVED', 'archived' );

/**
 * Checks if user has administrator role without triggering user_can (avoids recursion in map_meta_cap).
 *
 * @param int $user_id User ID.
 * @return bool
 */
function soe_user_is_admin( $user_id ) {
	$user = get_userdata( $user_id );
	return $user && is_array( $user->roles ) && in_array( 'administrator', $user->roles, true );
}

/**
 * Checks if user has ansprechperson role.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function soe_user_is_ansprechperson( $user_id ) {
	$user = get_userdata( $user_id );
	return $user && is_array( $user->roles ) && in_array( 'ansprechperson', $user->roles, true );
}

/**
 * Checks if user can create new mitglied posts (admin or ansprechperson).
 *
 * @param int $user_id User ID.
 * @return bool
 */
function soe_user_can_create_mitglied( $user_id ) {
	return soe_user_is_admin( $user_id ) || soe_user_is_ansprechperson( $user_id );
}

add_action( 'pre_get_posts', 'soe_mitglied_restrict_list_to_own_for_non_admin' );
add_action( 'restrict_manage_posts', 'soe_mitglied_list_add_status_filter' );
add_filter( 'map_meta_cap', 'soe_mitglied_map_meta_cap', 99, 4 );
add_action( 'admin_menu', 'soe_profil_link_to_account', 9999 );
add_action( 'add_meta_boxes', 'soe_mitglied_add_archive_meta_box' );
add_action( 'wp_ajax_soe_archive_member', 'soe_ajax_archive_member' );
add_action( 'wp_ajax_soe_restore_member', 'soe_ajax_restore_member' );
add_action( 'admin_enqueue_scripts', 'soe_mitglied_admin_scripts' );

/**
 * Enqueues admin script for mitglied edit screen (archive/restore buttons).
 *
 * @param string $hook Current admin page hook.
 */
function soe_mitglied_admin_scripts( $hook ) {
	if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'mitglied' ) {
		return;
	}
	wp_enqueue_script(
		'soe-admin-mitglied',
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/admin-mitglied.js',
		array( 'jquery' ),
		'1.0.4',
		true
	);
	// Generate nonces for medical file downloads
	$medical_nonces = array();
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( $post_id && get_post_type( $post_id ) === 'mitglied' ) {
		$datenblatter = get_field( 'medizinische_datenblatter', $post_id, false );
		if ( $datenblatter ) {
			$att_ids = is_array( $datenblatter ) ? $datenblatter : array( $datenblatter );
			foreach ( $att_ids as $att_id ) {
				$att_id = is_array( $att_id ) ? ( isset( $att_id['ID'] ) ? $att_id['ID'] : ( isset( $att_id['id'] ) ? $att_id['id'] : 0 ) ) : (int) $att_id;
				if ( $att_id ) {
					$medical_nonces[ $att_id ] = wp_create_nonce( 'soe_medical_download_' . $att_id );
				}
			}
		}
	}

	wp_localize_script( 'soe-admin-mitglied', 'soeMitgliedAdmin', array(
		'isNonAdmin'          => ! current_user_can( 'manage_options' ),
		'acfTitleReplacement' => current_user_can( 'manage_options' ) ? __( 'Mitglied', 'special-olympics-extension' ) : __( 'Athlet*in', 'special-olympics-extension' ),
		'adminPostUrl'        => admin_url( 'admin-post.php' ),
		'medicalNonces'       => $medical_nonces,
	) );
}

add_action( 'admin_head', 'soe_mitglied_hide_ui_elements' );
/**
 * Hides UI elements on the mitglied edit/new screen:
 * - "Neues Mitglied" button (edit screen only)
 * - Post title field (title is auto-generated from name fields)
 */
function soe_mitglied_hide_ui_elements() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'mitglied' || $screen->base !== 'post' ) {
		return;
	}
	echo '<style>
		.post-type-mitglied .page-title-action { display: none !important; }
		.post-type-mitglied #titlewrap,
		.post-type-mitglied #titlediv,
		.post-type-mitglied #post-body-content > #titlediv,
		.post-type-mitglied .editor-post-title,
		.post-type-mitglied .edit-post-visual-editor__post-title-wrapper { display: none !important; }
	</style>';
}

/**
 * Menü "Benutzer" (mit Profil) nur für Administratoren sichtbar.
 * Nicht-Admins verwalten ihre Daten über "Mein Account"; der gesamte Menüpunkt wird ausgeblendet.
 */
function soe_profil_link_to_account() {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	remove_menu_page( 'users.php' );
	remove_menu_page( 'profile.php' );
}

/**
 * Restricts the members list (edit.php?post_type=mitglied):
 * - Admins: see ALL members
 * - Ansprechpersonen: see only their own posts (where they are the author)
 * - Other roles: no access (handled by capabilities)
 *
 * @param WP_Query $query The main query.
 */
function soe_mitglied_restrict_list_to_own_for_non_admin( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->get( 'post_type' ) !== 'mitglied' ) {
		return;
	}
	// Default sort: by title A–Z (when user has not clicked a column header).
	if ( ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}
	// Filter by member status: default = only active; optional "Archiviert" or "Alle Status".
	$status_filter = isset( $_GET['soe_member_status'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_member_status'] ) ) : '';
	if ( $status_filter === SOE_MEMBER_STATUS_ARCHIVED ) {
		$query->set( 'meta_query', array(
			array(
				'key'   => SOE_MEMBER_STATUS_META,
				'value' => SOE_MEMBER_STATUS_ARCHIVED,
			),
		) );
	} elseif ( $status_filter === 'all' ) {
		// Explicit "Alle Status" – no meta_query
	} else {
		// Default and "Aktiv": only active (or meta not set)
		$query->set( 'meta_query', array(
			'relation' => 'OR',
			array(
				'key'   => SOE_MEMBER_STATUS_META,
				'value' => SOE_MEMBER_STATUS_ACTIVE,
			),
			array(
				'key'     => SOE_MEMBER_STATUS_META,
				'compare' => 'NOT EXISTS',
			),
		) );
	}
	// Admins see all (aside from status filter above)
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	// Ansprechpersonen see only their own posts.
	$query->set( 'author', get_current_user_id() );
}

/**
 * Adds dropdown filter "Status: Alle / Aktiv / Archiviert" on the Mitglieder list screen.
 *
 * @param string $post_type Current post type in admin.
 */
function soe_mitglied_list_add_status_filter( $post_type ) {
	if ( $post_type !== 'mitglied' ) {
		return;
	}
	$current = isset( $_GET['soe_member_status'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_member_status'] ) ) : '';
	?>
	<select name="soe_member_status">
		<option value=""><?php esc_html_e( 'Aktiv (Standard)', 'special-olympics-extension' ); ?></option>
		<option value="all" <?php selected( $current, 'all' ); ?>><?php esc_html_e( 'Alle Status', 'special-olympics-extension' ); ?></option>
		<option value="<?php echo esc_attr( SOE_MEMBER_STATUS_ARCHIVED ); ?>" <?php selected( $current, SOE_MEMBER_STATUS_ARCHIVED ); ?>><?php esc_html_e( 'Nur Archivierte', 'special-olympics-extension' ); ?></option>
	</select>
	<?php
}

/**
 * Gets the WP user ID linked to a mitglied post (ACF/meta user_id). Works early before ACF may be fully loaded.
 *
 * @param int $post_id Mitglied post ID.
 * @return int User ID or 0.
 */
function soe_mitglied_get_linked_user_id( $post_id ) {
	$linked = get_post_meta( (int) $post_id, 'user_id', true );
	if ( $linked !== '' && $linked !== false ) {
		return (int) $linked;
	}
	if ( function_exists( 'get_field' ) ) {
		$linked = get_field( 'user_id', (int) $post_id );
		if ( is_object( $linked ) && isset( $linked->ID ) ) {
			return (int) $linked->ID;
		}
		return (int) $linked;
	}
	return 0;
}

/**
 * Controls mitglied capabilities:
 * - Only admins can delete members.
 * - Only admins and ansprechpersonen can create/edit members.
 * - Ansprechpersonen can only edit their own posts (where they are the author).
 * - All users can edit their own linked mitglied post (Mein Account via user_id meta).
 *
 * @param array  $caps    Required capabilities.
 * @param string $cap     Capability being checked.
 * @param int    $user_id User ID.
 * @param array  $args    Optional args (e.g. post ID for edit_post/delete_post).
 * @return array
 */
function soe_mitglied_map_meta_cap( $caps, $cap, $user_id, $args ) {
	// delete_post for mitglied: only administrators may delete members.
	if ( $cap === 'delete_post' && isset( $args[0] ) ) {
		$post = get_post( (int) $args[0] );
		if ( $post && $post->post_type === 'mitglied' ) {
			if ( soe_user_is_admin( $user_id ) ) {
				return array( 'manage_options' );
			}
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	// edit_post for mitglied:
	// - Admins: can edit all
	// - Ansprechpersonen: can edit only their own (author) or linked (user_id), but not if archived (author loses edit)
	// - All users: can edit their own linked post (Mein Account)
	// - Other roles: cannot edit
	if ( $cap === 'edit_post' && isset( $args[0] ) ) {
		$post_id = (int) $args[0];
		$post    = get_post( $post_id );
		if ( $post && $post->post_type === 'mitglied' ) {
			// Admins can edit all.
			if ( soe_user_is_admin( $user_id ) ) {
				return array( 'manage_options' );
			}
			// All users can edit their own linked mitglied (Mein Account).
			$linked_user_id = soe_mitglied_get_linked_user_id( $post_id );
			if ( $linked_user_id && (int) $linked_user_id === (int) $user_id ) {
				return array( 'read' );
			}
			// Ansprechpersonen can edit posts they authored, unless the member is archived.
			if ( soe_user_is_ansprechperson( $user_id ) ) {
				if ( $post->post_author && (int) $post->post_author === (int) $user_id ) {
					$status = get_post_meta( $post_id, SOE_MEMBER_STATUS_META, true );
					if ( $status === SOE_MEMBER_STATUS_ARCHIVED ) {
						return array( 'do_not_allow' );
					}
					return array( 'read' );
				}
			}
			// All other cases: deny.
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	// publish_mitglieds (create new members): only admins and ansprechperson.
	if ( $cap === 'publish_mitglieds' ) {
		if ( soe_user_can_create_mitglied( $user_id ) ) {
			return array( 'read' );
		}
		return array( 'do_not_allow' );
	}

	// edit_mitglieds (general capability to access the CPT): only admins and ansprechpersonen.
	if ( $cap === 'edit_mitglieds' ) {
		if ( soe_user_is_admin( $user_id ) || soe_user_is_ansprechperson( $user_id ) ) {
			return array( 'read' );
		}
		return array( 'do_not_allow' );
	}

	// Primitive caps for mitglied (edit_others_mitglieds, delete_mitglied, etc.)
	$mitglied_caps = array(
		'edit_mitglied', 'edit_others_mitglieds', 'edit_published_mitglieds',
		'delete_mitglied', 'delete_others_mitglieds', 'delete_published_mitglieds', 'delete_private_mitglieds',
	);
	if ( ! in_array( $cap, $mitglied_caps, true ) ) {
		return $caps;
	}

	if ( soe_user_is_admin( $user_id ) ) {
		return array( 'manage_options' );
	}
	// Delete caps: deny for non-admins.
	if ( in_array( $cap, array( 'delete_mitglied', 'delete_others_mitglieds', 'delete_published_mitglieds', 'delete_private_mitglieds' ), true ) ) {
		return array( 'do_not_allow' );
	}
	// edit_others_mitglieds: deny for non-admins (ansprechpersonen only edit their own).
	if ( $cap === 'edit_others_mitglieds' ) {
		return array( 'do_not_allow' );
	}
	return $caps;
}

/**
 * Adds a meta box with "Person archivieren" / "Person reaktivieren" button.
 * Visible only to administrators.
 */
function soe_mitglied_add_archive_meta_box() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_meta_box(
		'soe_member_status',
		__( 'Status der Person', 'special-olympics-extension' ),
		'soe_mitglied_render_archive_meta_box',
		'mitglied',
		'side',
		'high'
	);
}

/**
 * Renders the archive/restore meta box content.
 *
 * @param WP_Post $post Current post.
 */
function soe_mitglied_render_archive_meta_box( $post ) {
	if ( $post->post_type !== 'mitglied' ) {
		return;
	}
	$status = soe_get_member_status( $post->ID );
	$is_archived = $status === SOE_MEMBER_STATUS_ARCHIVED;
	$archive_nonce = wp_create_nonce( 'soe_archive_member_' . $post->ID );
	$restore_nonce = wp_create_nonce( 'soe_restore_member_' . $post->ID );
	?>
	<p>
		<strong><?php echo $is_archived ? esc_html__( 'Archiviert', 'special-olympics-extension' ) : esc_html__( 'Aktiv', 'special-olympics-extension' ); ?></strong>
	</p>
	<p>
		<?php if ( $is_archived ) : ?>
			<button type="button" class="button soe-restore-member" data-post-id="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( $restore_nonce ); ?>"><?php esc_html_e( 'Person reaktivieren', 'special-olympics-extension' ); ?></button>
		<?php else : ?>
			<button type="button" class="button soe-archive-member" data-post-id="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( $archive_nonce ); ?>"><?php esc_html_e( 'Person archivieren', 'special-olympics-extension' ); ?></button>
		<?php endif; ?>
	</p>
	<p class="soe-member-status-message" style="display:none;"></p>
	<?php
}

/**
 * Returns the member status for a mitglied post.
 *
 * @param int $post_id Member post ID.
 * @return string 'active' or 'archived'
 */
function soe_get_member_status( $post_id ) {
	$status = get_post_meta( $post_id, SOE_MEMBER_STATUS_META, true );
	if ( $status === SOE_MEMBER_STATUS_ARCHIVED ) {
		return SOE_MEMBER_STATUS_ARCHIVED;
	}
	return SOE_MEMBER_STATUS_ACTIVE;
}

/**
 * Checks whether a member is active (not archived).
 *
 * @param int $post_id Member post ID.
 * @return bool
 */
function soe_is_member_active( $post_id ) {
	return soe_get_member_status( $post_id ) === SOE_MEMBER_STATUS_ACTIVE;
}

/**
 * AJAX handler: archive a member (set status to archived).
 */
function soe_ajax_archive_member() {
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'special-olympics-extension' ) ) );
	}
	check_ajax_referer( 'soe_archive_member_' . $post_id, 'nonce' );
	if ( get_post_type( $post_id ) !== 'mitglied' ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'special-olympics-extension' ) ) );
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'special-olympics-extension' ) ) );
	}
	update_post_meta( $post_id, SOE_MEMBER_STATUS_META, SOE_MEMBER_STATUS_ARCHIVED );
	soe_debug_log( 'Member archived', array( 'post_id' => $post_id ) );
	wp_send_json_success( array( 'message' => __( 'Person wurde archiviert.', 'special-olympics-extension' ) ) );
}

/**
 * AJAX handler: restore a member (set status to active).
 */
function soe_ajax_restore_member() {
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'special-olympics-extension' ) ) );
	}
	check_ajax_referer( 'soe_restore_member_' . $post_id, 'nonce' );
	if ( get_post_type( $post_id ) !== 'mitglied' ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'special-olympics-extension' ) ) );
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'special-olympics-extension' ) ) );
	}
	update_post_meta( $post_id, SOE_MEMBER_STATUS_META, SOE_MEMBER_STATUS_ACTIVE );
	soe_debug_log( 'Member restored', array( 'post_id' => $post_id ) );
	wp_send_json_success( array( 'message' => __( 'Person wurde reaktiviert.', 'special-olympics-extension' ) ) );
}
