<?php
/**
 * CPT "mitglied": title sync, read-only fields, menu label for non-admins.
 *
 * @package Special_Olympics_Extension
 */

/**
 * Save-Handler für Persönliche Daten (WP User) beim Speichern des CPT mitglied.
 * Re-Entry-Schutz: Verhindert Rekursion, wenn soe_sync_user_to_mitglied wp_update_post auslöst.
 */
add_action( 'save_post_mitglied', 'soe_save_post_mitglied_account_data', 5, 2 );
add_action( 'add_meta_boxes_mitglied', 'soe_mitglied_hide_events_and_sport_for_ansprechperson', 200, 1 );
function soe_save_post_mitglied_account_data( $post_id, $post ) {
	static $running = false;
	if ( $running ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['soe_account_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['soe_account_nonce'] ) ), 'soe_account_save' ) ) {
		return;
	}

	$user_id = get_field( 'user_id', $post_id );
	if ( ! $user_id ) {
		return;
	}
	$current_user_id = get_current_user_id();
	if ( (int) $current_user_id !== (int) $user_id && ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$first_name = isset( $_POST['soe_account_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['soe_account_first_name'] ) ) : '';
	$last_name  = isset( $_POST['soe_account_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['soe_account_last_name'] ) ) : '';
	$user_email = isset( $_POST['soe_account_user_email'] ) ? sanitize_email( wp_unslash( $_POST['soe_account_user_email'] ) ) : '';
	$password   = isset( $_POST['soe_account_password'] ) ? $_POST['soe_account_password'] : '';
	$password_2 = isset( $_POST['soe_account_password_confirmation'] ) ? $_POST['soe_account_password_confirmation'] : '';

	$error = '';
	if ( empty( $user_email ) ) {
		$error = __( 'E-Mail-Adresse ist erforderlich.', 'special-olympics-extension' );
	} elseif ( ! is_email( $user_email ) ) {
		$error = __( 'Ungültige E-Mail-Adresse.', 'special-olympics-extension' );
	} else {
		$existing = get_user_by( 'email', $user_email );
		if ( $existing && (int) $existing->ID !== (int) $user_id ) {
			$error = __( 'Diese E-Mail-Adresse wird bereits von einem anderen Benutzer verwendet.', 'special-olympics-extension' );
		}
	}
	if ( ! empty( $password ) || ! empty( $password_2 ) ) {
		if ( $password !== $password_2 ) {
			$error = __( 'Die Passwörter stimmen nicht überein.', 'special-olympics-extension' );
		} elseif ( strlen( $password ) < 5 ) {
			$error = __( 'Das Passwort muss mindestens 5 Zeichen lang sein.', 'special-olympics-extension' );
		}
	}

	if ( ! empty( $error ) ) {
		set_transient( 'soe_account_error_' . $current_user_id, $error, 30 );
		return;
	}

	$running = true;
	$userdata = array(
		'ID'         => (int) $user_id,
		'first_name' => $first_name,
		'last_name'  => $last_name,
		'user_email' => $user_email,
	);
	$updated = wp_update_user( $userdata );
	if ( is_wp_error( $updated ) ) {
		set_transient( 'soe_account_error_' . $current_user_id, $updated->get_error_message(), 30 );
		$running = false;
		return;
	}
	if ( ! empty( $password ) ) {
		wp_set_password( $password, $user_id );
	}
	set_transient( 'soe_account_updated_' . $current_user_id, 1, 30 );
	$running = false;
}

/**
 * Returns normalized role slugs from ACF field "role" for a mitglied post.
 *
 * @param int $post_id Mitglied post ID.
 * @return array
 */
function soe_mitglied_get_role_slugs( $post_id ) {
	$role_raw = get_field( 'role', $post_id );
	$roles    = is_array( $role_raw ) ? $role_raw : ( $role_raw ? array( $role_raw ) : array() );
	$roles    = array_values( array_filter( array_map( 'strval', $roles ) ) );
	return $roles;
}

/**
 * Returns true when role set contains "ansprechperson".
 *
 * @param array $roles Role slugs from ACF field "role".
 * @return bool
 */
function soe_mitglied_has_ansprechperson_role( $roles ) {
	return in_array( 'ansprechperson', (array) $roles, true );
}

/**
 * Returns true when role set contains "leiter_in" or "hauptleiter_in".
 *
 * @param array $roles Role slugs from ACF field "role".
 * @return bool
 */
function soe_mitglied_has_leadership_role( $roles ) {
	$roles = (array) $roles;
	return in_array( 'leiter_in', $roles, true ) || in_array( 'hauptleiter_in', $roles, true );
}

/**
 * Returns true for "pure" Ansprechperson (without Leiter/Hauptleiter).
 *
 * @param array $roles Role slugs from ACF field "role".
 * @return bool
 */
function soe_mitglied_is_pure_ansprechperson_role_set( $roles ) {
	return soe_mitglied_has_ansprechperson_role( $roles ) && ! soe_mitglied_has_leadership_role( $roles );
}

/**
 * Hides Events and Sportarten boxes for "Ansprechperson" member records.
 *
 * These members do not participate in events and should not be assigned sports.
 *
 * @param WP_Post $post Current mitglied post.
 * @return void
 */
function soe_mitglied_hide_events_and_sport_for_ansprechperson( $post ) {
	if ( ! $post || $post->post_type !== 'mitglied' ) {
		return;
	}
	$roles = soe_mitglied_get_role_slugs( $post->ID );
	if ( ! soe_mitglied_is_pure_ansprechperson_role_set( $roles ) ) {
		return;
	}

	// Events meta box from custom-events.php.
	remove_meta_box( 'soe_mitglied_events', 'mitglied', 'normal' );

	// Taxonomy boxes for "sport" (hierarchical/non-hierarchical variants).
	remove_meta_box( 'sportdiv', 'mitglied', 'side' );
	remove_meta_box( 'tagsdiv-sport', 'mitglied', 'side' );
}

/** Meta key: set when "new member" notification email has been sent (avoid duplicate). */
define( 'SOE_MITGLIED_CREATED_MAIL_SENT_META', '_soe_mitglied_created_notification_sent' );

/** Transient key prefix: marks that this post_id was just created (transition from auto-draft). */
define( 'SOE_MITGLIED_JUST_CREATED_TRANSIENT_PREFIX', 'soe_mitglied_created_' );

add_action( 'transition_post_status', 'soe_mark_mitglied_created_on_transition', 10, 3 );
add_action( 'acf/save_post', 'soe_maybe_send_mitglied_created_notification', 100, 1 );

/**
 * When a mitglied post transitions from auto-draft/new to draft/publish/private, mark it as "just created".
 * So we only send the notification for real new posts, not on updates.
 *
 * @param string  $new_status New post status.
 * @param string  $old_status Old post status.
 * @param WP_Post $post       Post object.
 */
function soe_mark_mitglied_created_on_transition( $new_status, $old_status, $post ) {
	if ( ! $post || $post->post_type !== 'mitglied' ) {
		return;
	}
	if ( $old_status !== 'auto-draft' && $old_status !== 'new' ) {
		return;
	}
	if ( ! in_array( $new_status, array( 'publish', 'draft', 'private' ), true ) ) {
		return;
	}
	set_transient( SOE_MITGLIED_JUST_CREATED_TRANSIENT_PREFIX . $post->ID, '1', 60 );
}

/**
 * Sends a one-time email when a user creates a new CPT "Mitglied" (not on update). Recipient from settings.
 * Only runs if transition_post_status marked this post as just created; runs after ACF save so the title is set.
 *
 * @param int $post_id Post ID.
 */
function soe_maybe_send_mitglied_created_notification( $post_id ) {
	if ( get_post_type( $post_id ) !== 'mitglied' ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	// Only send when the post was just created (transition from auto-draft), not when an existing post is updated.
	if ( get_transient( SOE_MITGLIED_JUST_CREATED_TRANSIENT_PREFIX . $post_id ) !== '1' ) {
		return;
	}
	delete_transient( SOE_MITGLIED_JUST_CREATED_TRANSIENT_PREFIX . $post_id );
	if ( get_post_meta( $post_id, SOE_MITGLIED_CREATED_MAIL_SENT_META, true ) === '1' ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post ) {
		return;
	}
	$to = function_exists( 'soe_get_mail_mitglied_created_to' ) ? soe_get_mail_mitglied_created_to() : '';
	if ( ! is_email( $to ) ) {
		return;
	}
	if ( function_exists( 'soe_is_mail_category_enabled' ) && ! soe_is_mail_category_enabled( SOE_MAIL_CAT_MITGLIED_CREATED ) ) {
		return;
	}
	$creator_id = (int) $post->post_author;
	$creator   = $creator_id ? get_userdata( $creator_id ) : null;
	$creator_name = $creator ? ( $creator->display_name ?: $creator->user_login ) : __( 'Unbekannt', 'special-olympics-extension' );
	$member_title = get_the_title( $post_id );
	if ( trim( $member_title ) === '' ) {
		$member_title = __( '(Ohne Namen)', 'special-olympics-extension' );
	}
	$edit_url = admin_url( 'post.php?post=' . (int) $post_id . '&action=edit' );
	$subject = sprintf(
		/* translators: 1: site name, 2: member name */
		__( '[%1$s] Neues Mitglied angelegt: %2$s', 'special-olympics-extension' ),
		get_bloginfo( 'name' ),
		$member_title
	);
	$body = sprintf(
		/* translators: 1: creator name, 2: member name, 3: edit URL */
		__( "Ein neues Mitglied wurde angelegt.\n\nAngelegt von: %1\$s\nMitglied: %2\$s\n\nBearbeiten: %3\$s", 'special-olympics-extension' ),
		$creator_name,
		$member_title,
		$edit_url
	);
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	$sent = wp_mail( $to, $subject, $body, $headers );
	if ( $sent ) {
		update_post_meta( $post_id, SOE_MITGLIED_CREATED_MAIL_SENT_META, '1' );
	}
}

// Set the post title to first and last name when saving a CPT "mitglied"
add_action( 'acf/save_post', 'soe_save_mitglied_post', 99 );
function soe_save_mitglied_post( $post_id ) {
	if ( get_post_type( $post_id ) !== 'mitglied' ) {
		return;
	}
	$user_id = get_field( 'user_id', $post_id );
	$role    = get_field( 'role', $post_id );
	$needs_default = ( empty( $role ) || ! is_array( $role ) ) && empty( $user_id );
	if ( $needs_default ) {
		update_field( 'role', array( 'athlet_in' ), $post_id );
	}

	$firstname = '';
	$lastname  = '';
	if ( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			$firstname = $user->first_name;
			$lastname  = $user->last_name;
		}
	} else {
		$firstname = get_field( 'vorname', $post_id );
		$lastname  = get_field( 'nachname', $post_id );
	}
	$titel = trim( $firstname . ' ' . $lastname );
	if ( empty( $titel ) && $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		$titel = $user ? $user->user_login : '';
	}
	if ( ! empty( $titel ) ) {
		soe_update_mitglied_post_title( $post_id, $titel );
	}
}

/**
 * Updates mitglied post title and slug. Unhooks soe_save_mitglied_post to avoid re-entry.
 *
 * @param int    $post_id Post ID.
 * @param string $titel   New title.
 */
function soe_update_mitglied_post_title( $post_id, $titel ) {
	remove_action( 'acf/save_post', 'soe_save_mitglied_post', 99 );
	wp_update_post( array(
		'ID'         => (int) $post_id,
		'post_title' => $titel,
		'post_name'  => sanitize_title( $titel ),
	) );
	add_action( 'acf/save_post', 'soe_save_mitglied_post', 99 );
}

/**
 * Read-only block when user_id exists: show identity from WP User + link to Account page.
 */
add_action( 'add_meta_boxes', 'soe_mitglied_add_user_info_meta_box', 5 );
function soe_mitglied_add_user_info_meta_box() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'mitglied' ) {
		return;
	}
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id ) {
		return;
	}
	$user_id = get_field( 'user_id', $post_id );
	if ( ! $user_id ) {
		return;
	}
	add_meta_box(
		'soe_mitglied_user_info',
		__( 'Persönliche Daten', 'special-olympics-extension' ),
		'soe_mitglied_render_user_info_meta_box',
		'mitglied',
		'normal',
		'high'
	);
}

/**
 * Renders the "Persönliche Daten" block: editable Vorname, Nachname, E-Mail, Passwort; read-only Rolle and HL-Stufe.
 * Success/error messages from transient are shown above the form.
 *
 * @param WP_Post $post Current post.
 */
function soe_mitglied_render_user_info_meta_box( $post ) {
	$user_id = get_field( 'user_id', $post->ID );
	if ( ! $user_id ) {
		return;
	}
	$user = get_user_by( 'ID', $user_id );
	if ( ! $user ) {
		echo '<p>' . esc_html__( 'Benutzer nicht gefunden.', 'special-olympics-extension' ) . '</p>';
		return;
	}

	$current_user_id = get_current_user_id();
	$can_edit = ( (int) $current_user_id === (int) $user_id ) || current_user_can( 'manage_options' );

	$first_name = $user->first_name;
	$last_name  = $user->last_name;
	$email      = $user->user_email;
	$roles      = array_values( array_intersect( $user->roles, soe_get_special_olympics_roles() ) );
	$role_labels = array(
		'ansprechperson'     => 'Ansprechperson',
		'athlet_in'          => 'Athlet*in',
		'hauptleiter_in'     => 'Hauptleiter*in',
		'leiter_in'          => 'Leiter*in',
		'unified'            => 'Unified',
		'assistenztrainer_in' => 'Assistenztrainer*in',
		'helfer_in'          => 'Helfer*in',
		'praktikant_in'      => 'Praktikant*in',
		'schueler_in'        => 'Schüler*in',
		'athlete_leader'     => 'Athlete Leader',
	);
	$role_display = array_map( function ( $slug ) use ( $role_labels ) {
		return isset( $role_labels[ $slug ] ) ? $role_labels[ $slug ] : $slug;
	}, $roles );
	$grade_hl = '';
	if ( in_array( 'hauptleiter_in', $roles, true ) && defined( 'SOE_GRADE_HAUPTLEITER_META' ) ) {
		$g = get_user_meta( $user_id, SOE_GRADE_HAUPTLEITER_META, true );
		$grade_hl = ( $g === '1A' || $g === '1a' ) ? '1A : Hauptleiter*in 1A' : ( ( $g === '1B' || $g === '1b' ) ? '1B : Hauptleiter*in 1B' : ( $g ? $g : '–' ) );
	}

	$transient_key = 'soe_account_error_' . $current_user_id;
	$success_key   = 'soe_account_updated_' . $current_user_id;
	$error_message = get_transient( $transient_key );
	$show_success  = get_transient( $success_key );
	if ( $error_message ) {
		delete_transient( $transient_key );
	}
	if ( $show_success ) {
		delete_transient( $success_key );
	}
	?>
	<?php if ( $error_message ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $error_message ); ?></p></div>
	<?php endif; ?>
	<?php if ( $show_success ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'Ihre Daten wurden gespeichert.', 'special-olympics-extension' ); ?></p></div>
	<?php endif; ?>
	<?php wp_nonce_field( 'soe_account_save', 'soe_account_nonce' ); ?>
	<table class="form-table">
		<tr>
			<th><label for="soe_account_first_name"><?php esc_html_e( 'Vorname', 'special-olympics-extension' ); ?></label></th>
			<td>
				<?php if ( $can_edit ) : ?>
					<input type="text" name="soe_account_first_name" id="soe_account_first_name" value="<?php echo esc_attr( $first_name ); ?>" class="regular-text" />
				<?php else : ?>
					<?php echo esc_html( $first_name ); ?>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="soe_account_last_name"><?php esc_html_e( 'Nachname', 'special-olympics-extension' ); ?></label></th>
			<td>
				<?php if ( $can_edit ) : ?>
					<input type="text" name="soe_account_last_name" id="soe_account_last_name" value="<?php echo esc_attr( $last_name ); ?>" class="regular-text" />
				<?php else : ?>
					<?php echo esc_html( $last_name ); ?>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label for="soe_account_user_email"><?php esc_html_e( 'E-Mail-Adresse', 'special-olympics-extension' ); ?></label></th>
			<td>
				<?php if ( $can_edit ) : ?>
					<input type="email" name="soe_account_user_email" id="soe_account_user_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" required />
				<?php else : ?>
					<?php echo esc_html( $email ); ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $can_edit ) : ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Passwort', 'special-olympics-extension' ); ?></th>
			<td>
				<div class="soe-password-fields" style="display: flex; gap: 1.5em; flex-wrap: wrap; align-items: flex-start;">
					<div>
						<label for="soe_account_password"><?php esc_html_e( 'Neues Passwort', 'special-olympics-extension' ); ?></label><br />
						<input type="password" name="soe_account_password" id="soe_account_password" class="regular-text" autocomplete="new-password" />
					</div>
					<div>
						<label for="soe_account_password_confirmation"><?php esc_html_e( 'Passwort bestätigen', 'special-olympics-extension' ); ?></label><br />
						<input type="password" name="soe_account_password_confirmation" id="soe_account_password_confirmation" class="regular-text" autocomplete="new-password" />
					</div>
				</div>
				<p class="description"><?php esc_html_e( 'Mindestens 5 Zeichen.', 'special-olympics-extension' ); ?><br /><?php esc_html_e( 'Leer lassen, um das Passwort beizubehalten.', 'special-olympics-extension' ); ?></p>
			</td>
		</tr>
		<?php endif; ?>
		<tr><th><?php esc_html_e( 'Rolle', 'special-olympics-extension' ); ?></th><td><?php echo esc_html( implode( ', ', $role_display ) ?: '–' ); ?>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<br /><a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $user_id ) ); ?>" class="button button-small" style="margin-top:6px;"><?php esc_html_e( 'Rolle bearbeiten', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
		</td></tr>
		<?php if ( in_array( 'hauptleiter_in', $roles, true ) ) : ?>
		<tr><th><?php esc_html_e( 'HL-Stufe', 'special-olympics-extension' ); ?></th><td><?php echo esc_html( $grade_hl ); ?></td></tr>
		<?php endif; ?>
	</table>
	<?php
}

/**
 * Returns role label(s) for a mitglied post from the ACF field "role" (not WP User roles).
 *
 * @param int $post_id Mitglied post ID.
 * @return string Comma-separated role labels or '–'.
 */
function soe_mitglied_get_role_display( $post_id ) {
	$role_labels = array(
		'ansprechperson'     => 'Ansprechperson',
		'athlet_in'          => 'Athlet*in',
		'hauptleiter_in'     => 'Hauptleiter*in',
		'leiter_in'          => 'Leiter*in',
		'unified'            => 'Unified',
		'assistenztrainer_in' => 'Assistenztrainer*in',
		'helfer_in'          => 'Helfer*in',
		'praktikant_in'      => 'Praktikant*in',
		'schueler_in'        => 'Schüler*in',
		'athlete_leader'     => 'Athlete Leader',
	);
	$role_raw = get_field( 'role', $post_id );
	$roles    = is_array( $role_raw ) ? $role_raw : ( array ) $role_raw;
	$roles    = array_filter( array_map( 'strval', $roles ) );
	$labels   = array_map( function ( $slug ) use ( $role_labels ) {
		return isset( $role_labels[ $slug ] ) ? $role_labels[ $slug ] : $slug;
	}, $roles );
	return implode( ', ', $labels ) ?: '–';
}

/**
 * Add "Funktion" column to mitglied list.
 */
add_filter( 'manage_mitglied_posts_columns', 'soe_mitglied_list_add_role_column' );
function soe_mitglied_list_add_role_column( $columns ) {
	$insert = array( 'soe_role' => __( 'Funktion', 'special-olympics-extension' ) );
	$pos    = array_search( 'title', array_keys( $columns ), true );
	if ( $pos !== false ) {
		$columns = array_slice( $columns, 0, $pos + 1, true ) + $insert + array_slice( $columns, $pos + 1, null, true );
	} else {
		$columns = $insert + $columns;
	}
	return $columns;
}

add_action( 'manage_mitglied_posts_custom_column', 'soe_mitglied_list_render_role_column', 10, 2 );
function soe_mitglied_list_render_role_column( $column, $post_id ) {
	if ( $column === 'soe_role' ) {
		echo esc_html( soe_mitglied_get_role_display( $post_id ) );
	}
}

/**
 * Hide title field on mitglied edit screen (title is auto-generated from name/user).
 */
add_action( 'add_meta_boxes', 'soe_mitglied_hide_title_meta_box', 5 );
function soe_mitglied_hide_title_meta_box() {
	remove_meta_box( 'titlediv', 'mitglied', 'normal' );
}

/**
 * Custom messages after saving mitglied (person).
 */
add_filter( 'post_updated_messages', 'soe_mitglied_updated_messages' );
function soe_mitglied_updated_messages( $messages ) {
	$singular = __( 'Mitglied', 'special-olympics-extension' );
	$messages['mitglied'] = array(
		0  => '',
		1  => sprintf( __( '%s wurde aktualisiert.', 'special-olympics-extension' ), $singular ),
		2  => __( 'Benutzerdefiniertes Feld aktualisiert.', 'special-olympics-extension' ),
		3  => __( 'Benutzerdefiniertes Feld gelöscht.', 'special-olympics-extension' ),
		4  => sprintf( __( '%s wurde aktualisiert.', 'special-olympics-extension' ), $singular ),
		5  => isset( $_GET['revision'] ) ? sprintf( __( '%s wiederhergestellt aus Revision vom %s.', 'special-olympics-extension' ), $singular, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		6  => sprintf( __( '%s wurde angelegt.', 'special-olympics-extension' ), $singular ),
		7  => sprintf( __( '%s wurde gespeichert.', 'special-olympics-extension' ), $singular ),
		8  => sprintf( __( '%s wurde eingereicht.', 'special-olympics-extension' ), $singular ),
		9  => isset( $_GET['post'] ) ? sprintf( __( '%s geplant für: %2$s.', 'special-olympics-extension' ), $singular, date_i18n( __( 'M j, Y @ G:i' ), strtotime( get_post_field( 'post_date', (int) $_GET['post'] ) ) ) ) : sprintf( __( '%s wurde geplant.', 'special-olympics-extension' ), $singular ),
		10 => sprintf( __( 'Entwurf der %s wurde aktualisiert.', 'special-olympics-extension' ), $singular ),
	);
	return $messages;
}

/**
 * Forces 2-column layout on mitglied edit screen (main content + sidebar, not full-width).
 * Screen ID for CPT edit is the post type name ("mitglied"), not "post".
 */
add_filter( 'screen_layout_columns', 'soe_mitglied_screen_layout_columns', 10, 3 );
function soe_mitglied_screen_layout_columns( $columns, $screen_id, $screen ) {
	if ( $screen_id === 'mitglied' ) {
		$columns['mitglied'] = 2;
	}
	return $columns;
}
add_filter( 'get_user_option_screen_layout_mitglied', 'soe_force_mitglied_two_columns' );
function soe_force_mitglied_two_columns( $result ) {
	return 2;
}

/**
 * Adds has-right-sidebar body class for mitglied edit screen.
 * Required for WordPress two-column layout CSS.
 */
add_filter( 'admin_body_class', 'soe_mitglied_admin_body_class' );
function soe_mitglied_admin_body_class( $classes ) {
	$screen = get_current_screen();
	if ( $screen && $screen->post_type === 'mitglied' && get_current_screen()->get_columns() === 2 ) {
		$classes .= ' has-right-sidebar';
	}
	return $classes;
}

/**
 * Replaces Publish meta box with a simplified version (no Preview, Status, Visibility) for non-admins on mitglied edit screen.
 * Uses remove + add so elements are fully removed from DOM, not just hidden.
 */
add_action( 'add_meta_boxes', 'soe_replace_publish_meta_box_for_non_admin', 100 );
function soe_replace_publish_meta_box_for_non_admin() {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'mitglied' ) {
		return;
	}
	remove_meta_box( 'submitdiv', 'mitglied', 'side' );
	add_meta_box(
		'soe_submitdiv',
		__( 'Speichern', 'special-olympics-extension' ),
		'soe_simplified_submit_meta_box',
		'mitglied',
		'side',
		'core'
	);
}

/**
 * Simplified Publish meta box: Save Draft, Publish/Update, Delete only.
 * No Preview, Status, or Visibility. Required hidden inputs preserve form behavior.
 *
 * @param WP_Post $post Current post object.
 */
function soe_simplified_submit_meta_box( $post ) {
	$post_id          = (int) $post->ID;
	$post_type        = $post->post_type;
	$post_type_object = get_post_type_object( $post_type );
	$can_publish      = current_user_can( $post_type_object->cap->publish_posts );
	$current_status   = in_array( $post->post_status, array( 'auto-draft' ), true ) ? 'draft' : $post->post_status;
	?>
<div class="submitbox" id="submitpost">
	<div id="minor-publishing">
		<div style="display:none;">
			<?php submit_button( __( 'Save' ), '', 'save' ); ?>
		</div>
		<div id="minor-publishing-actions">
			<div id="save-action">
				<?php
				if ( ! in_array( $post->post_status, array( 'publish', 'future', 'pending' ), true ) ) {
					$private_style = ( 'private' === $post->post_status ) ? 'style="display:none"' : '';
					?>
					<input <?php echo $private_style; ?> type="submit" name="save" id="save-post" value="<?php esc_attr_e( 'Save Draft' ); ?>" class="button" />
					<span class="spinner"></span>
				<?php } elseif ( 'pending' === $post->post_status && $can_publish ) { ?>
					<input type="submit" name="save" id="save-post" value="<?php esc_attr_e( 'Save as Pending' ); ?>" class="button" />
					<span class="spinner"></span>
				<?php } ?>
			</div>
			<div class="clear"></div>
		</div>
		<?php
		// Hidden inputs so form submits correctly without status/visibility UI.
		?>
		<input type="hidden" name="post_status" value="<?php echo esc_attr( $current_status ); ?>" />
		<input type="hidden" name="visibility" value="public" />
		<input type="hidden" name="hidden_post_visibility" value="public" />
		<input type="hidden" name="hidden_post_password" value="" />
	</div>
	<div id="major-publishing-actions">
		<div id="delete-action">
			<?php
			// Only administrators may delete members; hide "Move to Trash" for non-admins.
			if ( current_user_can( 'manage_options' ) && current_user_can( 'delete_post', $post_id ) ) {
				$delete_text = EMPTY_TRASH_DAYS ? __( 'Move to Trash' ) : __( 'Delete permanently' );
				?>
				<a class="submitdelete deletion" href="<?php echo esc_url( get_delete_post_link( $post_id ) ); ?>"><?php echo esc_html( $delete_text ); ?></a>
				<?php
			}
			?>
		</div>
		<div id="publishing-action">
			<span class="spinner"></span>
			<?php
			if ( ! in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) || 0 === $post_id ) {
				if ( $can_publish ) {
					$publish_label = __( 'Jetzt speichern', 'special-olympics-extension' );
					?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php echo esc_attr( $publish_label ); ?>" />
					<?php submit_button( $publish_label, 'primary large', 'publish', false ); ?>
					<?php
				} else {
					?>
					<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Jetzt speichern', 'special-olympics-extension' ); ?>" />
					<?php submit_button( __( 'Jetzt speichern', 'special-olympics-extension' ), 'primary large', 'publish', false ); ?>
					<?php
				}
			} else {
				?>
				<input name="original_publish" type="hidden" id="original_publish" value="<?php esc_attr_e( 'Jetzt speichern', 'special-olympics-extension' ); ?>" />
				<?php submit_button( __( 'Jetzt speichern', 'special-olympics-extension' ), 'primary large', 'save', false, array( 'id' => 'publish' ) ); ?>
				<?php
			}
			?>
		</div>
		<div class="clear"></div>
	</div>
</div>
	<?php
}

/**
 * Disables Admin Columns for CPT mitglied when the user is not an admin.
 * Non-admins see the default WordPress list columns only.
 */
add_filter( 'ac/post_types', 'soe_mitglied_hide_admin_columns_for_non_admin' );
function soe_mitglied_hide_admin_columns_for_non_admin( $post_types ) {
	if ( ! is_array( $post_types ) ) {
		return $post_types;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		unset( $post_types['mitglied'] );
	}
	return $post_types;
}

/**
 * ACF field "Ich bin" (role): keep visible for conditional logic.
 * For non-admin users on mitglied edit screens it is displayed read-only.
 */
add_filter( 'acf/prepare_field/name=role', 'soe_role_field_hide_on_mitglied' );
function soe_role_field_hide_on_mitglied( $field ) {
	// Guard against invalid legacy values (e.g. boolean true) so ACF checkbox rendering
	// always receives an array and does not fatal in array_map().
	if ( isset( $field['value'] ) && ! is_array( $field['value'] ) ) {
		if ( is_string( $field['value'] ) && $field['value'] !== '' ) {
			$field['value'] = array( $field['value'] );
		} else {
			$field['value'] = array();
		}
	}

	$screen = get_current_screen();
	if ( $screen && $screen->post_type === 'mitglied' ) {
		// For ACF checkbox fields, 'disabled' must be an array of choice values (not boolean true).
		if ( ( $field['type'] ?? '' ) === 'checkbox' ) {
			$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();
			$field['disabled'] = array_values( array_map( 'strval', array_keys( $choices ) ) );
		} else {
			$field['disabled'] = true;
		}
		$field['readonly'] = true;
		$field['instructions'] = __( 'Wird automatisch aus der Benutzerrolle synchronisiert.', 'special-olympics-extension' );
	}
	return $field;
}

add_filter( 'acf/load_value/name=role', 'soe_role_field_normalize_loaded_value', 10, 3 );
function soe_role_field_normalize_loaded_value( $value, $post_id, $field ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( is_string( $value ) && $value !== '' ) {
		return array( $value );
	}
	return array();
}

/**
 * Global safety net: ACF checkbox fields must always have array values.
 * Prevents fatal errors when legacy/broken data stores booleans (e.g. true).
 */
function soe_normalize_acf_checkbox_value( $value ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( is_string( $value ) && $value !== '' ) {
		return array( $value );
	}
	return array();
}

add_filter( 'acf/load_value/type=checkbox', 'soe_acf_checkbox_load_value_normalize', 10, 3 );
function soe_acf_checkbox_load_value_normalize( $value, $post_id, $field ) {
	return soe_normalize_acf_checkbox_value( $value );
}

add_filter( 'acf/prepare_field/type=checkbox', 'soe_acf_checkbox_prepare_field_normalize', 5, 1 );
function soe_acf_checkbox_prepare_field_normalize( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}
	$field['value'] = soe_normalize_acf_checkbox_value( isset( $field['value'] ) ? $field['value'] : array() );
	if ( isset( $field['default_value'] ) ) {
		$field['default_value'] = soe_normalize_acf_checkbox_value( $field['default_value'] );
	}
	return $field;
}

add_filter( 'acf/load_field/type=checkbox', 'soe_acf_checkbox_load_field_normalize', 5, 1 );
function soe_acf_checkbox_load_field_normalize( $field ) {
	if ( ! is_array( $field ) ) {
		return $field;
	}
	if ( isset( $field['default_value'] ) ) {
		$field['default_value'] = soe_normalize_acf_checkbox_value( $field['default_value'] );
	}
	return $field;
}

add_filter( 'acf/prepare_field', 'soe_acf_checkbox_prepare_field_normalize_global', 1, 1 );
function soe_acf_checkbox_prepare_field_normalize_global( $field ) {
	if ( ! is_array( $field ) || ( $field['type'] ?? '' ) !== 'checkbox' ) {
		return $field;
	}
	$field['value'] = soe_normalize_acf_checkbox_value( isset( $field['value'] ) ? $field['value'] : array() );
	if ( isset( $field['disabled'] ) && ! is_array( $field['disabled'] ) ) {
		$field['disabled'] = array();
	}
	if ( isset( $field['default_value'] ) ) {
		$field['default_value'] = soe_normalize_acf_checkbox_value( $field['default_value'] );
	}
	return $field;
}

/**
 * ACF field "Ich bin" (role): default athlet_in for new members; when user_id exists, User is Master (read-only).
 * We avoid prepare_field/load_field as they can break ACF checkbox rendering.
 * Instead: set default on save (new posts), and prevent value changes for non-admins via acf/update_value.
 */
add_filter( 'acf/update_value/name=role', 'soe_role_field_update_value', 10, 3 );
function soe_role_field_update_value( $value, $post_id, $field ) {
	if ( get_post_type( $post_id ) !== 'mitglied' ) {
		return $value;
	}
	$user_id = get_field( 'user_id', $post_id );
	if ( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( $user ) {
			$roles = array_values( array_intersect( $user->roles, soe_get_special_olympics_roles() ) );
			return ! empty( $roles ) ? $roles : $value;
		}
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		$existing = get_field( 'role', $post_id );
		if ( ! empty( $existing ) && is_array( $existing ) ) {
			return $existing;
		}
		return array( 'athlet_in' );
	}
	return $value;
}

// Make ACF field read-only based on field key (user_id)
function make_acf_readonly( $field ) {
	// Skip if field is already hidden
	if ( ! $field || ! is_array( $field ) ) {
		return $field;
	}
	// Replace with your ACF field key
	if ( isset( $field['key'] ) && $field['key'] === 'field_6905405879b9f' ) {
		$field['disabled'] = true; // Disable the field
		$field['readonly'] = true; // Make the field read-only
	}
	return $field;
}
add_filter( 'acf/prepare_field', 'make_acf_readonly' );

/**
 * ============================================================================
 * NOTFALLKONTAKT REFERENZ-LOGIK
 * ============================================================================
 * Wenn eine Ansprechperson ein neues Mitglied erstellt, wird deren Mitglied-ID
 * als Notfallkontakt-Referenz gespeichert. Die Felder werden dann dynamisch
 * aus dem Profil der Ansprechperson geladen.
 */

/** Meta key for storing the Notfallkontakt person reference (mitglied post ID). */
define( 'SOE_NOTFALLKONTAKT_PERSON_ID_META', 'notfallkontakt_person_id' );

/**
 * Get Notfallkontakt data for a Mitglied (name and phone).
 * Uses reference if set, otherwise falls back to direct ACF fields.
 *
 * @param int $mitglied_id Mitglied post ID.
 * @return array Array with [name, phone].
 */
function soe_get_notfallkontakt_data( $mitglied_id ) {
	$ref_id = get_post_meta( $mitglied_id, SOE_NOTFALLKONTAKT_PERSON_ID_META, true );

	if ( $ref_id ) {
		// Load from referenced Mitglied
		$vorname = get_field( 'vorname', $ref_id );
		$nachname = get_field( 'nachname', $ref_id );

		// If referenced person has user_id, get name from WP User instead
		$ref_user_id = get_field( 'user_id', $ref_id );
		if ( $ref_user_id ) {
			$user = get_user_by( 'ID', $ref_user_id );
			if ( $user ) {
				$vorname = $user->first_name;
				$nachname = $user->last_name;
			}
		}

		$name = trim( $vorname . ' ' . $nachname );
		$tel = get_field( 'telefonnummer', $ref_id );

		return array( $name, $tel );
	}

	// Fallback: Direct ACF fields
	$notfall = get_field( 'notfallkontakt', $mitglied_id );
	$name = is_array( $notfall ) && isset( $notfall['name_notfallkontakt'] ) ? $notfall['name_notfallkontakt'] : '';
	$tel = is_array( $notfall ) && isset( $notfall['telefon_notfallkontakt'] ) ? $notfall['telefon_notfallkontakt'] : '';

	return array( $name, $tel );
}

/**
 * Automatically set Notfallkontakt reference when Ansprechperson creates a new Mitglied.
 * Runs after ACF save, checks if user_id is empty (not own profile) and sets reference.
 *
 * @param int $post_id Post ID.
 */
add_action( 'acf/save_post', 'soe_set_notfallkontakt_reference_on_create', 15 );
function soe_set_notfallkontakt_reference_on_create( $post_id ) {
	if ( get_post_type( $post_id ) !== 'mitglied' ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	$current_user_id = get_current_user_id();
	if ( ! $current_user_id ) {
		return;
	}

	// Only for pure Ansprechpersonen (without Leiter/Hauptleiter role).
	$current_roles = soe_mitglied_get_role_slugs( $post_id );
	if ( ! soe_mitglied_is_pure_ansprechperson_role_set( $current_roles ) ) {
		return;
	}

	// Skip if user_id field has a value (this is the person's own profile)
	$user_id_field = get_field( 'user_id', $post_id );
	if ( ! empty( $user_id_field ) ) {
		return;
	}

	// Skip if reference is already set
	$existing_ref = get_post_meta( $post_id, SOE_NOTFALLKONTAKT_PERSON_ID_META, true );
	if ( ! empty( $existing_ref ) ) {
		return;
	}

	// Get the Ansprechperson's own Mitglied post ID
	if ( ! function_exists( 'soe_get_current_user_mitglied_id' ) ) {
		return;
	}
	$ansprechperson_mitglied_id = soe_get_current_user_mitglied_id();
	if ( ! $ansprechperson_mitglied_id ) {
		return;
	}

	// Set the reference
	update_post_meta( $post_id, SOE_NOTFALLKONTAKT_PERSON_ID_META, $ansprechperson_mitglied_id );
}

/**
 * Load Notfallkontakt name dynamically from referenced Mitglied profile.
 *
 * @param mixed $value   Field value.
 * @param int   $post_id Post ID.
 * @param array $field   Field array.
 * @return mixed
 */
add_filter( 'acf/load_value/name=name_notfallkontakt', 'soe_load_notfallkontakt_name', 10, 3 );
function soe_load_notfallkontakt_name( $value, $post_id, $field ) {
	if ( get_post_type( $post_id ) !== 'mitglied' ) {
		return $value;
	}

	// Try to get reference ID
	$ref_id = get_post_meta( $post_id, SOE_NOTFALLKONTAKT_PERSON_ID_META, true );

	// If no reference yet, check if we should pre-fill for Ansprechperson (new post)
	if ( ! $ref_id ) {
		$roles = soe_mitglied_get_role_slugs( $post_id );
		$is_pure_ansprechperson = soe_mitglied_is_pure_ansprechperson_role_set( $roles );

		// Only pre-fill if: pure Ansprechperson AND user_id field is empty (not own profile)
		if ( $is_pure_ansprechperson ) {
			$user_id_field = get_field( 'user_id', $post_id );
			if ( empty( $user_id_field ) && function_exists( 'soe_get_current_user_mitglied_id' ) ) {
				$ref_id = soe_get_current_user_mitglied_id();
			}
		}
	}

	if ( ! $ref_id ) {
		return $value;
	}

	// Load name from referenced Mitglied
	$vorname = get_field( 'vorname', $ref_id );
	$nachname = get_field( 'nachname', $ref_id );

	// If referenced person has user_id, get name from WP User instead
	$ref_user_id = get_field( 'user_id', $ref_id );
	if ( $ref_user_id ) {
		$user = get_user_by( 'ID', $ref_user_id );
		if ( $user ) {
			$vorname = $user->first_name;
			$nachname = $user->last_name;
		}
	}

	return trim( $vorname . ' ' . $nachname );
}

/**
 * Load Notfallkontakt phone dynamically from referenced Mitglied profile.
 *
 * @param mixed $value   Field value.
 * @param int   $post_id Post ID.
 * @param array $field   Field array.
 * @return mixed
 */
add_filter( 'acf/load_value/name=telefon_notfallkontakt', 'soe_load_notfallkontakt_telefon', 10, 3 );
function soe_load_notfallkontakt_telefon( $value, $post_id, $field ) {
	if ( get_post_type( $post_id ) !== 'mitglied' ) {
		return $value;
	}

	// Try to get reference ID
	$ref_id = get_post_meta( $post_id, SOE_NOTFALLKONTAKT_PERSON_ID_META, true );

	// If no reference yet, check if we should pre-fill for Ansprechperson (new post)
	if ( ! $ref_id ) {
		$roles = soe_mitglied_get_role_slugs( $post_id );
		$is_pure_ansprechperson = soe_mitglied_is_pure_ansprechperson_role_set( $roles );

		// Only pre-fill if: pure Ansprechperson AND user_id field is empty (not own profile)
		if ( $is_pure_ansprechperson ) {
			$user_id_field = get_field( 'user_id', $post_id );
			if ( empty( $user_id_field ) && function_exists( 'soe_get_current_user_mitglied_id' ) ) {
				$ref_id = soe_get_current_user_mitglied_id();
			}
		}
	}

	if ( ! $ref_id ) {
		return $value;
	}

	// Load phone from referenced Mitglied (ACF field: telefonnummer)
	return get_field( 'telefonnummer', $ref_id );
}

/**
 * Make Notfallkontakt fields read-only for Ansprechpersonen when reference is set.
 *
 * @param array $field Field array.
 * @return array|false
 */
add_filter( 'acf/prepare_field/name=name_notfallkontakt', 'soe_notfallkontakt_field_readonly' );
add_filter( 'acf/prepare_field/name=telefon_notfallkontakt', 'soe_notfallkontakt_field_readonly' );
function soe_notfallkontakt_field_readonly( $field ) {
	if ( ! $field || ! is_array( $field ) ) {
		return $field;
	}

	// Only on mitglied edit screen
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'mitglied' ) {
		return $field;
	}

	// Get post ID from URL or global
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( ! $post_id ) {
		global $post;
		$post_id = $post ? $post->ID : 0;
	}

	// For new posts (auto-draft), check role set from the current mitglied record.
	$current_user_id = get_current_user_id();
	$is_admin = current_user_can( 'manage_options' );
	$roles = $post_id ? soe_mitglied_get_role_slugs( $post_id ) : array();
	$is_pure_ansprechperson = soe_mitglied_is_pure_ansprechperson_role_set( $roles );

	// Check if this post has a notfallkontakt reference (created by Ansprechperson)
	$has_reference = false;
	if ( $post_id ) {
		$ref_id = get_post_meta( $post_id, SOE_NOTFALLKONTAKT_PERSON_ID_META, true );
		$has_reference = ! empty( $ref_id );
	}

	// If reference exists, make read-only for ALL users (including admins)
	if ( $has_reference ) {
		$field['disabled'] = true;
		$field['readonly'] = true;
		$field['wrapper']['class'] = isset( $field['wrapper']['class'] ) ? $field['wrapper']['class'] . ' soe-readonly-field' : 'soe-readonly-field';
		$field['instructions'] = __( 'Wird automatisch aus dem Profil der Ansprechperson übernommen.', 'special-olympics-extension' );
		return $field;
	}

	// If admin and no reference, allow editing
	if ( $is_admin ) {
		return $field;
	}

	// For pure Ansprechpersonen: make fields read-only (for new posts)
	if ( $is_pure_ansprechperson ) {
		// Check if this post has user_id (own profile) - allow editing own profile
		if ( $post_id ) {
			$user_id_field = get_field( 'user_id', $post_id );
			if ( ! empty( $user_id_field ) && (int) $user_id_field === $current_user_id ) {
				return $field; // Own profile, allow editing
			}
		}

		// For new posts or other members: make read-only
		$field['disabled'] = true;
		$field['readonly'] = true;
		$field['wrapper']['class'] = isset( $field['wrapper']['class'] ) ? $field['wrapper']['class'] . ' soe-readonly-field' : 'soe-readonly-field';
		$field['instructions'] = __( 'Wird automatisch aus deinem Profil übernommen.', 'special-olympics-extension' );
	}

	return $field;
}
