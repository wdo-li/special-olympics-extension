<?php
/**
 * CPT "event": capabilities and restrictions.
 *
 * - Create/delete only administrators.
 * - Assign persons (Hauptleiter, Leiter, etc.) only Administrator and Hauptleiter.
 * - Hauptleiter can only select/enter persons, no other changes (enforced in UI via readonly fields).
 * - Rest: no access.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'map_meta_cap', 'soe_event_map_meta_cap', 10, 4 );

/**
 * Maps meta caps for event: only admins create/delete; admins and Hauptleiter edit.
 *
 * @param array  $caps    Required capabilities.
 * @param string $cap     Capability being checked.
 * @param int    $user_id User ID.
 * @param array  $args    Optional args (e.g. post ID).
 * @return array
 */
function soe_event_map_meta_cap( $caps, $cap, $user_id, $args ) {
	// Admins have full access (soe_user_is_admin avoids recursion in map_meta_cap).
	if ( soe_user_is_admin( $user_id ) ) {
		if ( in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) && isset( $args[0] ) ) {
			$post = get_post( (int) $args[0] );
			if ( $post && $post->post_type === 'event' ) {
				return array( 'manage_options' );
			}
		}
		if ( in_array( $cap, array( 'edit_event', 'delete_event', 'edit_others_events', 'delete_others_events' ), true ) ) {
			return array( 'manage_options' );
		}
		return $caps;
	}

	// Only administrators can create events (publish_events for new post).
	if ( $cap === 'create_events' || ( $cap === 'publish_events' && empty( $args[0] ) ) ) {
		return array( 'do_not_allow' );
	}

	// Only administrators can delete events.
	if ( in_array( $cap, array( 'delete_event', 'delete_others_events', 'delete_post' ), true ) ) {
		if ( $cap === 'delete_post' && isset( $args[0] ) ) {
			$post = get_post( (int) $args[0] );
			if ( $post && $post->post_type === 'event' ) {
				return array( 'do_not_allow' );
			}
		}
		if ( $cap === 'delete_event' || $cap === 'delete_others_events' ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	// Hauptleiter can edit (assign persons); others cannot.
	$event_caps = array( 'edit_event', 'edit_others_events', 'edit_post', 'read_post' );
	if ( ! in_array( $cap, $event_caps, true ) ) {
		return $caps;
	}
	if ( ( $cap === 'edit_post' || $cap === 'read_post' ) && isset( $args[0] ) ) {
		$post = get_post( (int) $args[0] );
		if ( ! $post || $post->post_type !== 'event' ) {
			return $caps;
		}
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'hauptleiter_in', $user->roles, true ) ) {
			return array( 'do_not_allow' );
		}
		return array( 'edit_events' );
	}

	return $caps;
}
