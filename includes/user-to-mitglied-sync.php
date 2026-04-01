<?php
/**
 * Sync: WP User → CPT mitglied
 * When user profile is updated, sync identity data to linked mitglied posts.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'profile_update', 'soe_sync_user_to_mitglied', 10, 2 );
add_action( 'profile_update', 'soe_sync_user_roles_to_mitglied', 999, 1 );
add_action( 'set_user_role', 'soe_sync_user_roles_to_mitglied_on_role_change', 10, 3 );
add_action( 'add_user_role', 'soe_sync_user_roles_to_mitglied_by_user_id', 10, 2 );
add_action( 'remove_user_role', 'soe_sync_user_roles_to_mitglied_by_user_id', 10, 2 );
add_action( 'acf/save_post', 'soe_sync_role_from_user_on_mitglied_save', 20, 1 );

/**
 * Syncs the WP User's current roles to the ACF "role" field on all linked mitglied posts.
 * Called when user roles change (profile update with late priority, set_user_role, add_user_role, remove_user_role).
 *
 * @param int $user_id WP User ID.
 */
function soe_sync_user_roles_to_mitglied_linked_posts( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id || ! function_exists( 'soe_get_special_olympics_roles' ) ) {
		return;
	}
	$user = get_user_by( 'ID', $user_id );
	if ( ! $user ) {
		return;
	}
	$posts = get_posts( array(
		'post_type'      => 'mitglied',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => 'user_id',
		'meta_value'     => (string) $user_id,
	) );
	if ( empty( $posts ) ) {
		return;
	}
	$user_roles = array_values( array_intersect( $user->roles, soe_get_special_olympics_roles() ) );
	foreach ( $posts as $post_id ) {
		update_field( 'role', $user_roles, $post_id );
	}
}

/**
 * Runs on profile_update with priority 999 so roles have been saved by other plugins (e.g. Members) first.
 */
function soe_sync_user_roles_to_mitglied( $user_id ) {
	soe_sync_user_roles_to_mitglied_linked_posts( $user_id );
}

/**
 * Runs when a user's role is set (WordPress core user edit).
 */
function soe_sync_user_roles_to_mitglied_on_role_change( $user_id, $role, $old_roles ) {
	soe_sync_user_roles_to_mitglied_linked_posts( $user_id );
}

/**
 * Runs when a role is added or removed (e.g. Members plugin).
 *
 * @param int    $user_id User ID.
 * @param string $role    Role name.
 */
function soe_sync_user_roles_to_mitglied_by_user_id( $user_id, $role ) {
	soe_sync_user_roles_to_mitglied_linked_posts( $user_id );
}

/**
 * When a mitglied post is saved, if it has a linked user_id, overwrite ACF "role" with the WP User's roles.
 * This ensures the role field always reflects all WP roles (e.g. Helfer + Hauptleiter) even if the ACF form only submitted one.
 */
function soe_sync_role_from_user_on_mitglied_save( $post_id ) {
	if ( ! $post_id || get_post_type( $post_id ) !== 'mitglied' ) {
		return;
	}
	$user_id = get_field( 'user_id', $post_id );
	if ( ! $user_id ) {
		return;
	}
	$user = get_user_by( 'ID', (int) $user_id );
	if ( ! $user ) {
		return;
	}
	if ( ! function_exists( 'soe_get_special_olympics_roles' ) ) {
		return;
	}
	$user_roles = array_values( array_intersect( $user->roles, soe_get_special_olympics_roles() ) );
	update_field( 'role', $user_roles, $post_id );
}

/**
 * Syncs WP User data to all mitglied posts linked via user_id.
 *
 * @param int   $user_id       User ID being updated.
 * @param mixed $old_user_data Previous user data (WP_User or array).
 */
function soe_sync_user_to_mitglied( $user_id, $old_user_data = null ) {
	$user = get_user_by( 'ID', $user_id );
	if ( ! $user ) {
		return;
	}

	$posts = get_posts( array(
		'post_type'      => 'mitglied',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => 'user_id',
		'meta_value'     => (string) $user_id,
	) );

	if ( empty( $posts ) ) {
		return;
	}

	$user_roles = array_values( array_intersect( $user->roles, soe_get_special_olympics_roles() ) );
	$firstname  = $user->first_name;
	$lastname   = $user->last_name;
	$email      = $user->user_email;
	$post_title = trim( $firstname . ' ' . $lastname );
	if ( empty( $post_title ) ) {
		$post_title = $user->user_login;
	}

	foreach ( $posts as $post_id ) {
		update_field( 'vorname', $firstname, $post_id );
		update_field( 'nachname', $lastname, $post_id );
		update_field( 'e-mail', $email, $post_id );
		update_field( 'role', $user_roles, $post_id );

		remove_action( 'profile_update', 'soe_sync_user_to_mitglied', 10 );
		wp_update_post( array(
			'ID'         => (int) $post_id,
			'post_title' => $post_title,
			'post_name'  => sanitize_title( $post_title ),
		) );
		add_action( 'profile_update', 'soe_sync_user_to_mitglied', 10, 2 );
	}
}

/** Usermeta key for HL-Stufe (Hauptleiter). */
define( 'SOE_GRADE_HAUPTLEITER_META', 'soe_grade_hauptleiter_in' );

add_action( 'show_user_profile', 'soe_user_profile_hl_stufe_field' );
add_action( 'edit_user_profile', 'soe_user_profile_hl_stufe_field' );
add_action( 'user_new_form', 'soe_user_new_form_hl_stufe_field', 10, 2 );
add_action( 'user_new_form', 'soe_user_new_form_sport_terms_field', 11, 2 );
add_action( 'personal_options_update', 'soe_user_profile_hl_stufe_save' );
add_action( 'edit_user_profile_update', 'soe_user_profile_hl_stufe_save' );
add_action( 'user_register', 'soe_save_hl_stufe_on_user_register', 10, 1 );

/**
 * Adds HL-Stufe field to user profile (admin only; only when role is Hauptleiter).
 */
function soe_user_profile_hl_stufe_field( $user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$user_roles = is_array( $user->roles ) ? $user->roles : array();
	if ( ! in_array( 'hauptleiter_in', $user_roles, true ) ) {
		return;
	}
	$value = get_user_meta( $user->ID, SOE_GRADE_HAUPTLEITER_META, true );
	?>
	<h3><?php esc_html_e( 'HL-Stufe', 'special-olympics-extension' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="soe_grade_hauptleiter_in"><?php esc_html_e( 'Stufe Hauptleitung', 'special-olympics-extension' ); ?></label></th>
			<td>
				<select name="soe_grade_hauptleiter_in" id="soe_grade_hauptleiter_in">
					<option value=""><?php esc_html_e( '— Wählen —', 'special-olympics-extension' ); ?></option>
					<option value="1A" <?php selected( $value, '1A' ); ?>>1A : Hauptleiter*in 1A</option>
					<option value="1B" <?php selected( $value, '1B' ); ?>>1B : Hauptleiter*in 1B</option>
				</select>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Adds HL-Stufe field to the "Add New User" form; visible only when role Hauptleiter*in is selected.
 * WordPress may call this hook with one argument (operation) or two (user, operation).
 *
 * @param WP_User|stdClass|string $user_or_operation User object or operation string, depending on WP version.
 * @param string|null            $operation         Operation string when two args are passed.
 */
function soe_user_new_form_hl_stufe_field( $user_or_operation, $operation = null ) {
	$op = ( $operation !== null ) ? $operation : $user_or_operation;
	if ( ! current_user_can( 'manage_options' ) || $op !== 'add-new-user' ) {
		return;
	}
	?>
	<div id="soe-hl-stufe-wrap" style="display:none;">
		<h3><?php esc_html_e( 'HL-Stufe', 'special-olympics-extension' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="soe_grade_hauptleiter_in_new"><?php esc_html_e( 'Stufe Hauptleitung', 'special-olympics-extension' ); ?></label></th>
				<td>
					<select name="soe_grade_hauptleiter_in" id="soe_grade_hauptleiter_in_new">
						<option value=""><?php esc_html_e( '— Wählen —', 'special-olympics-extension' ); ?></option>
						<option value="1A">1A : Hauptleiter*in 1A</option>
						<option value="1B">1B : Hauptleiter*in 1B</option>
					</select>
				</td>
			</tr>
		</table>
	</div>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var wrap = document.getElementById('soe-hl-stufe-wrap');
		if (!wrap) return;
		function isHauptleiterChecked() {
			// Members plugin: checkboxes name="members_user_roles[]", value="hauptleiter_in"
			var cb = document.querySelector('input[name="members_user_roles[]"][value="hauptleiter_in"]');
			return cb ? cb.checked : false;
		}
		function updateVisibility() {
			wrap.style.display = isHauptleiterChecked() ? 'block' : 'none';
		}
		var roleInputs = document.querySelectorAll('input[name="members_user_roles[]"]');
		if (roleInputs.length) {
			roleInputs.forEach(function(input) {
				input.addEventListener('change', updateVisibility);
			});
		}
		// Fallback: core role dropdown (if Members not used)
		var roleSelect = document.querySelector('select[name="role"]');
		if (roleSelect) {
			roleSelect.addEventListener('change', updateVisibility);
		}
		updateVisibility();
	});
	</script>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		var userLogin = document.getElementById('user_login');
		var firstName = document.getElementById('first_name');
		var lastName = document.getElementById('last_name');
		if (!userLogin || !firstName || !lastName) return;
		userLogin.setAttribute('readonly', 'readonly');
		function sanitizeUsername(s) {
			return (s || '').toString()
				.toLowerCase()
				.replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
				.replace(/[^a-z0-9._-]/g, '')
				.replace(/\.{2,}/g, '.')
				.replace(/^\.|\.$/g, '');
		}
		function updateUserLogin() {
			var first = sanitizeUsername(firstName.value);
			var last = sanitizeUsername(lastName.value);
			if (first && last) {
				userLogin.value = first + '.' + last;
			} else {
				userLogin.value = first || last || '';
			}
		}
		firstName.addEventListener('input', updateUserLogin);
		firstName.addEventListener('change', updateUserLogin);
		lastName.addEventListener('input', updateUserLogin);
		lastName.addEventListener('change', updateUserLogin);
		updateUserLogin();
	});
	</script>
	<?php
}

/**
 * Sportarten (Taxonomie) on Add New User — applied to the created Mitglied post in role-sync.
 *
 * @param string $operation Operation string.
 */
function soe_user_new_form_sport_terms_field( $user_or_operation, $operation = null ) {
	$op = ( $operation !== null ) ? $operation : $user_or_operation;
	if ( ! current_user_can( 'manage_options' ) || $op !== 'add-new-user' ) {
		return;
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'sport',
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}
	?>
	<h3><?php esc_html_e( 'Sportarten (Mitglied)', 'special-olympics-extension' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Wird auf dem angelegten Mitglied-Eintrag (CPT) gespeichert.', 'special-olympics-extension' ); ?></p>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Sportarten', 'special-olympics-extension' ); ?></th>
			<td>
				<fieldset style="max-height:14em;overflow:auto;">
					<?php foreach ( $terms as $term ) : ?>
						<label style="display:block;margin:0.15em 0;">
							<input type="checkbox" name="soe_user_sport_terms[]" value="<?php echo esc_attr( (string) $term->term_id ); ?>" />
							<?php echo esc_html( $term->name ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Saves HL-Stufe when a new user is created (Add New User form).
 *
 * @param int $user_id New user ID.
 */
function soe_save_hl_stufe_on_user_register( $user_id ) {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['soe_grade_hauptleiter_in'] ) ) {
		return;
	}
	$value = sanitize_text_field( wp_unslash( $_POST['soe_grade_hauptleiter_in'] ) );
	if ( in_array( $value, array( '1A', '1B' ), true ) ) {
		update_user_meta( $user_id, SOE_GRADE_HAUPTLEITER_META, $value );
	}
}

/**
 * Saves HL-Stufe on user profile update.
 */
function soe_user_profile_hl_stufe_save( $user_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_POST['soe_grade_hauptleiter_in'] ) ) {
		return;
	}
	$value = sanitize_text_field( wp_unslash( $_POST['soe_grade_hauptleiter_in'] ) );
	if ( in_array( $value, array( '1A', '1B' ), true ) ) {
		update_user_meta( $user_id, SOE_GRADE_HAUPTLEITER_META, $value );
	} else {
		delete_user_meta( $user_id, SOE_GRADE_HAUPTLEITER_META );
	}
}

/**
 * Gets HL-Stufe (grade) for a mitglied post.
 * For members with user_id: from usermeta soe_grade_hauptleiter_in.
 * For members without user: empty.
 *
 * @param int $post_id Mitglied post ID.
 * @return string Grade (e.g. 1A, 1B) or empty.
 */
function soe_get_mitglied_grade_hl( $post_id ) {
	$user_id = get_post_meta( $post_id, 'user_id', true );
	if ( $user_id ) {
		return get_user_meta( $user_id, SOE_GRADE_HAUPTLEITER_META, true );
	}
	return '';
}