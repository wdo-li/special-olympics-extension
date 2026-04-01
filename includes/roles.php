<?php
/**
 * Central role definitions for trainings and events.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Role slug: Hauptleiter*in. */
define( 'SOE_ROLE_HAUPTLEITER_IN', 'hauptleiter_in' );
/** Role slug: Leiter*in. */
define( 'SOE_ROLE_LEITER_IN', 'leiter_in' );
/** Role slug: Athlet*in. */
define( 'SOE_ROLE_ATHLET_IN', 'athlet_in' );
/** Role slug: Unified. */
define( 'SOE_ROLE_UNIFIED', 'unified' );
/** Role slug: Assistenztrainer*in. */
define( 'SOE_ROLE_ASSISTENZTRAINER_IN', 'assistenztrainer_in' );
/** Role slug: Helfer*in. */
define( 'SOE_ROLE_HELFER_IN', 'helfer_in' );
/** Role slug: Praktikant*in. */
define( 'SOE_ROLE_PRAKTIKANT_IN', 'praktikant_in' );
/** Role slug: Schüler*in. */
define( 'SOE_ROLE_SCHUELER_IN', 'schueler_in' );
/** Role slug: Athlete Leader. */
define( 'SOE_ROLE_ATHLETE_LEADER', 'athlete_leader' );

/**
 * Returns role keys for trainings (includes athlete_leader).
 *
 * @return array
 */
function soe_get_training_role_keys() {
	return array( 'hauptleiter', 'leiter', 'athleten', 'unified', 'assistenztrainer', 'helfer', 'praktikant', 'schueler', 'athlete_leader' );
}

/**
 * Returns role keys for events (includes athlete_leader).
 *
 * @return array
 */
function soe_get_event_role_keys() {
	return array( 'hauptleiter', 'leiter', 'athleten', 'unified', 'assistenztrainer', 'helfer', 'praktikant', 'schueler', 'athlete_leader' );
}

/**
 * Maps role keys to WP/ACF role slugs (for person picker filter, payroll).
 *
 * @return array
 */
function soe_get_role_filter_map() {
	return array(
		'hauptleiter'      => SOE_ROLE_HAUPTLEITER_IN,
		'leiter'           => SOE_ROLE_LEITER_IN,
		'athleten'         => SOE_ROLE_ATHLET_IN,
		'unified'          => SOE_ROLE_UNIFIED,
		'assistenztrainer' => SOE_ROLE_ASSISTENZTRAINER_IN,
		'helfer'           => SOE_ROLE_HELFER_IN,
		'praktikant'       => SOE_ROLE_PRAKTIKANT_IN,
		'schueler'         => SOE_ROLE_SCHUELER_IN,
		'athlete_leader'   => SOE_ROLE_ATHLETE_LEADER,
	);
}

/**
 * Returns display labels for role keys (person picker).
 *
 * @return array
 */
function soe_get_role_labels() {
	return array(
		'hauptleiter'      => __( 'Hauptleiter*in', 'special-olympics-extension' ),
		'leiter'           => __( 'Leiter*in', 'special-olympics-extension' ),
		'athleten'         => __( 'Athlet*in', 'special-olympics-extension' ),
		'unified'          => __( 'Unified Partner*in', 'special-olympics-extension' ),
		'assistenztrainer' => __( 'Assistenztrainer*in', 'special-olympics-extension' ),
		'helfer'           => __( 'Helfer*in', 'special-olympics-extension' ),
		'praktikant'       => __( 'Praktikant*in', 'special-olympics-extension' ),
		'schueler'         => __( 'Schüler*in', 'special-olympics-extension' ),
		'athlete_leader'   => __( 'Athlete Leader', 'special-olympics-extension' ),
	);
}

/**
 * Parses persons from POST data (comma-separated IDs per role).
 *
 * @param array  $role_keys Role keys to parse.
 * @param string $prefix    POST key prefix (e.g. 'persons_', 'soe_persons_', 'soe_event_persons_').
 * @return array Role => array of person IDs.
 */
function soe_parse_persons_from_post( $role_keys, $prefix ) {
	$out = array();
	foreach ( $role_keys as $key ) {
		$raw  = isset( $_POST[ $prefix . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $prefix . $key ] ) ) : '';
		$out[ $key ] = array_filter( array_map( 'intval', explode( ',', $raw ) ) );
	}
	return $out;
}
