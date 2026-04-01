<?php
/**
 * CPT "training": capabilities and list restriction.
 *
 * - Administrators: full access to all trainings.
 * - Hauptleiter: only trainings where they are listed as Hauptleiter; can only enter attendance (enforced in UI).
 * - Others: no access to training list/edit.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'map_meta_cap', 'soe_training_map_meta_cap', 10, 4 );

/**
 * Checks whether the current user is listed as Hauptleiter for the given training.
 *
 * @param int $training_id Training post ID.
 * @return bool
 */
function soe_is_current_user_hauptleiter_of_training( $training_id ) {
	$mitglied_id = soe_get_current_user_mitglied_id();
	if ( ! $mitglied_id ) {
		return false;
	}
	$persons = soe_db_training_get_persons( $training_id );
	$hauptleiter_ids = isset( $persons['hauptleiter'] ) ? (array) $persons['hauptleiter'] : array();
	return in_array( $mitglied_id, array_map( 'intval', $hauptleiter_ids ), true );
}

/**
 * Checks whether the current user is assigned to the training in one of the given roles (for custom-table trainings).
 *
 * @param int   $training_id Training ID (soe_trainings.id).
 * @param array $roles       Allowed roles, e.g. array( 'hauptleiter', 'leiter' ).
 * @return bool
 */
function soe_is_current_user_assigned_to_training( $training_id, $roles = array( 'hauptleiter', 'leiter' ) ) {
	$mitglied_id = function_exists( 'soe_get_current_user_mitglied_id' ) ? soe_get_current_user_mitglied_id() : 0;
	if ( ! $mitglied_id ) {
		return false;
	}
	$persons = soe_db_training_get_persons( (int) $training_id );
	foreach ( (array) $roles as $role ) {
		$ids = isset( $persons[ $role ] ) ? (array) $persons[ $role ] : array();
		if ( in_array( $mitglied_id, array_map( 'intval', $ids ), true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Maps meta caps for training: admins full access; Hauptleiter only for trainings where they are Hauptleiter.
 *
 * @param array  $caps    Required capabilities.
 * @param string $cap     Capability being checked.
 * @param int    $user_id User ID.
 * @param array  $args    Optional args (e.g. post ID).
 * @return array
 */
function soe_training_map_meta_cap( $caps, $cap, $user_id, $args ) {
	if ( soe_user_is_admin( $user_id ) ) {
		if ( in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) && isset( $args[0] ) ) {
			$post = get_post( (int) $args[0] );
			if ( $post && $post->post_type === 'training' ) {
				return array( 'manage_options' );
			}
		}
		if ( in_array( $cap, array( 'edit_training', 'edit_others_trainings', 'delete_training', 'delete_others_trainings' ), true ) ) {
			return array( 'manage_options' );
		}
		return $caps;
	}

	$training_caps = array( 'edit_training', 'edit_others_trainings', 'delete_training', 'delete_others_trainings', 'edit_post', 'delete_post', 'read_post' );
	if ( ! in_array( $cap, $training_caps, true ) ) {
		return $caps;
	}

	if ( ( $cap === 'edit_post' || $cap === 'delete_post' || $cap === 'read_post' ) && isset( $args[0] ) ) {
		$training_id = (int) $args[0];
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'hauptleiter_in', $user->roles, true ) ) {
			return array( 'do_not_allow' );
		}
		$persons = soe_db_training_get_persons( $training_id );
		$ids = isset( $persons['hauptleiter'] ) ? (array) $persons['hauptleiter'] : array();
		$mitglied_posts = get_posts( array(
			'post_type'      => 'mitglied',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'user_id',
			'meta_value'     => $user_id,
		) );
		$my_mitglied_id = ! empty( $mitglied_posts ) ? (int) $mitglied_posts[0] : 0;
		if ( $my_mitglied_id && in_array( $my_mitglied_id, $ids, true ) ) {
			return array( 'edit_trainings' );
		}
		return array( 'do_not_allow' );
	}

	if ( in_array( $cap, array( 'delete_training', 'delete_others_trainings' ), true ) ) {
		return array( 'do_not_allow' );
	}

	return $caps;
}
