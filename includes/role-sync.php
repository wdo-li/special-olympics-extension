<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Role / User-Mitglied: WP User is Master.
 * - user_register: Create mitglied when admin creates a user.
 * - Sync (User → mitglied) is handled in user-to-mitglied-sync.php via profile_update.
 *
 * @package Special_Olympics_Extension
 */

/**
 * Returns Special Olympics role slugs. Filterable via 'soe_roles'.
 *
 * @return array
 */
function soe_get_special_olympics_roles() {
	$default_roles = array(
		'ansprechperson',
		'athlet_in',
		'hauptleiter_in',
		'leiter_in',
		'unified',
		'assistenztrainer_in',
		'helfer_in',
		'praktikant_in',
		'schueler_in',
		'athlete_leader',
	);
	return apply_filters( 'soe_roles', $default_roles );
}

/**
 * BACKEND USER CREATION -> CPT MITGLIED
 * When an admin creates a user via wp-admin, automatically create a CPT "mitglied"
 * with user_id, vorname, nachname, e-mail, and role (from WP user roles).
 */
add_action( 'user_register', 'soe_create_mitglied_on_user_register', 10, 1 );
function soe_create_mitglied_on_user_register( $user_id ) {
	$user = get_user_by( 'ID', $user_id );
	if ( ! $user ) {
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
	$mitglied = array(
		'post_title'   => $post_title,
		'post_content' => '',
		'post_status'  => 'publish',
		'post_type'    => 'mitglied',
		'post_author'  => $user_id,
	);
	$post_id = wp_insert_post( $mitglied );
	if ( is_wp_error( $post_id ) ) {
		return;
	}
	update_field( 'user_id', $user_id, $post_id );
	update_field( 'vorname', $firstname, $post_id );
	update_field( 'nachname', $lastname, $post_id );
	update_field( 'e-mail', $email, $post_id );
	update_field( 'role', $user_roles, $post_id );

	if ( ! empty( $_POST['soe_user_sport_terms'] ) && is_array( $_POST['soe_user_sport_terms'] ) ) {
		$term_ids = array_filter( array_map( 'intval', $_POST['soe_user_sport_terms'] ) );
		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, 'sport' );
		}
	}
}

//Add validation for PEID Nr field that is unique
add_filter('acf/validate_value/name=peid_nr', 'validate_unique_peid_nr', 10, 4);
function validate_unique_peid_nr($valid, $value, $field, $input)
{
    if (!$valid || empty($value)) return $valid;

    $post_id = 0;
    if ( is_admin() && isset( $_REQUEST['post'] ) && is_numeric( $_REQUEST['post'] ) ) {
        $post_id = (int) $_REQUEST['post'];
    } elseif ( ! empty( $_POST['acf'] ) && isset( $_POST['_acf_post_id'] ) && is_numeric( $_POST['_acf_post_id'] ) ) {
        $post_id = (int) $_POST['_acf_post_id'];
    } elseif ( isset( $_POST['_acf_current_url'] ) ) {
        $parts = wp_parse_url( esc_url_raw( wp_unslash( $_POST['_acf_current_url'] ) ) );
        if ( ! empty( $parts['query'] ) ) {
            parse_str( $parts['query'], $query );
            if ( isset( $query['post_id'] ) && is_numeric( $query['post_id'] ) ) {
                $post_id = (int) $query['post_id'];
            }
        }
    }
    
    $args = array(
        'post_type'      => 'mitglied',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => array(
            array(
                'key'     => 'peid_nr',
                'value'   => $value,
                'compare' => '='
            )
        ),
        'post__not_in'   => array($post_id)
    );

    $query = new WP_Query($args);

    if (!empty($query->posts)) {
        return __('Diese PEID ist bereits vergeben.');
    }

    return $valid;
}
