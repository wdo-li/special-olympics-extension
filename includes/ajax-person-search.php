<?php
/**
 * AJAX endpoint for lazy-loading person (Mitglied) search.
 *
 * Used by Training and Event person assignment fields.
 * Returns only matching results (max 25) instead of loading all members.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_ajax_soe_search_persons', 'soe_ajax_search_persons' );

/**
 * Builds one JSON row for person picker (name, optional sport suffix in text, role label).
 *
 * @param int    $post_id         Mitglied post ID.
 * @param string $role_filter     Optional ACF role slug filter.
 * @param bool   $exclude_athletes Exclude athlete-only persons.
 * @return array|null Row or null if filtered out.
 */
function soe_ajax_person_picker_row( $post_id, $role_filter, $exclude_athletes ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'mitglied' ) {
		return null;
	}
	$role_raw = function_exists( 'get_field' ) ? get_field( 'role', $post_id ) : get_post_meta( $post_id, 'role', true );
	$roles    = is_array( $role_raw ) ? $role_raw : (array) $role_raw;
	$roles    = array_filter( array_map( 'strval', $roles ) );
	if ( $role_filter !== '' && ! in_array( $role_filter, $roles, true ) ) {
		return null;
	}
	if ( $exclude_athletes && count( $roles ) === 1 && in_array( 'athlet_in', $roles, true ) ) {
		return null;
	}
	$role_label = implode( ', ', array_map( 'esc_html', $roles ) );
	$sport_objs = wp_get_object_terms( $post_id, 'sport' );
	$sport_names = array();
	if ( is_array( $sport_objs ) && ! is_wp_error( $sport_objs ) ) {
		foreach ( $sport_objs as $t ) {
			$sport_names[] = $t->name;
		}
	}
	$text = $post->post_title;
	if ( ! empty( $sport_names ) ) {
		$text .= ' (' . implode( ', ', array_map( 'esc_html', $sport_names ) ) . ')';
	}
	return array(
		'id'     => $post_id,
		'text'   => $text,
		'role'   => $role_label,
		'sports' => $sport_names,
	);
}

/**
 * AJAX handler: search mitglied posts by name and by sport taxonomy.
 *
 * GET/POST params:
 *   - q: search term (min 2 chars)
 *   - role: optional role filter (athlet_in, hauptleiter_in, etc.)
 *   - exclude: comma-separated IDs to exclude
 *
 * Returns JSON array of { id, text, role, sports }.
 */
function soe_ajax_search_persons() {
	check_ajax_referer( 'soe_person_search', 'nonce' );
	if ( ! current_user_can( 'edit_trainings' ) && ! current_user_can( 'edit_events' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array() );
	}

	$q = isset( $_REQUEST['q'] ) ? sanitize_text_field( $_REQUEST['q'] ) : '';
	$role_filter = isset( $_REQUEST['role'] ) ? sanitize_text_field( $_REQUEST['role'] ) : '';
	$exclude_raw = isset( $_REQUEST['exclude'] ) ? sanitize_text_field( $_REQUEST['exclude'] ) : '';
	$exclude = array_filter( array_map( 'intval', explode( ',', $exclude_raw ) ) );
	$exclude_athletes = isset( $_REQUEST['exclude_athletes'] ) && $_REQUEST['exclude_athletes'] === 'true';

	if ( strlen( $q ) < 2 ) {
		wp_send_json( array() );
	}

	global $wpdb;

	$like = '%' . $wpdb->esc_like( $q ) . '%';

	$exclude_sql = '';
	if ( ! empty( $exclude ) ) {
		$exclude_sql = ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $exclude ) ) . ')';
	}

	$status_key = defined( 'SOE_MEMBER_STATUS_META' ) ? SOE_MEMBER_STATUS_META : 'soe_member_status';
	$archived   = defined( 'SOE_MEMBER_STATUS_ARCHIVED' ) ? SOE_MEMBER_STATUS_ARCHIVED : 'archived';

	$sql = $wpdb->prepare(
		"SELECT DISTINCT p.ID
		 FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = %s
		 WHERE p.post_type = 'mitglied'
		   AND p.post_status = 'publish'
		   AND (pm_status.meta_value IS NULL OR pm_status.meta_value != %s)
		   AND p.post_title LIKE %s
		   {$exclude_sql}
		 ORDER BY p.post_title ASC
		 LIMIT 100",
		$status_key,
		$archived,
		$like
	);

	$ids_title = $wpdb->get_col( $sql );
	if ( ! is_array( $ids_title ) ) {
		$ids_title = array();
	}
	$ids_title = array_map( 'intval', $ids_title );

	$ids_sport = array();
	$term_ids  = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT t.term_id FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id AND tt.taxonomy = 'sport'
			 WHERE t.name LIKE %s OR t.slug LIKE %s",
			$like,
			$like
		)
	);
	if ( is_array( $term_ids ) && ! empty( $term_ids ) ) {
		$term_ids = array_map( 'intval', $term_ids );
		$term_ids = array_filter( array_unique( $term_ids ) );
		$meta_or  = array(
			'relation' => 'OR',
			array(
				'key'     => $status_key,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => $status_key,
				'value'   => $archived,
				'compare' => '!=',
			),
		);
		$qobj = new WP_Query(
			array(
				'post_type'              => 'mitglied',
				'post_status'            => 'publish',
				'posts_per_page'         => 100,
				'post__not_in'            => $exclude,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'tax_query'              => array(
					array(
						'taxonomy' => 'sport',
						'field'    => 'term_id',
						'terms'    => $term_ids,
						'operator' => 'IN',
					),
				),
				'meta_query'             => array( $meta_or ),
			)
		);
		if ( $qobj->have_posts() ) {
			$ids_sport = array_map( 'intval', $qobj->posts );
		}
		wp_reset_postdata();
	}

	$merged = array_unique( array_merge( $ids_title, $ids_sport ) );
	$merged = array_diff( $merged, $exclude );
	$merged = array_values( array_filter( array_map( 'intval', $merged ) ) );

	usort(
		$merged,
		function ( $a, $b ) {
			$ta = get_the_title( $a );
			$tb = get_the_title( $b );
			return strcasecmp( $ta, $tb );
		}
	);

	$merged = array_slice( $merged, 0, 25 );

	$out = array();
	foreach ( $merged as $pid ) {
		$row = soe_ajax_person_picker_row( $pid, $role_filter, $exclude_athletes );
		if ( $row !== null ) {
			$out[] = $row;
		}
	}

	wp_send_json( $out );
}

/**
 * AJAX handler: get person details by IDs (for initial load of selected persons).
 */
add_action( 'wp_ajax_soe_get_persons_by_ids', 'soe_ajax_get_persons_by_ids' );

function soe_ajax_get_persons_by_ids() {
	check_ajax_referer( 'soe_person_search', 'nonce' );
	if ( ! current_user_can( 'edit_trainings' ) && ! current_user_can( 'edit_events' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array() );
	}

	$ids_raw = isset( $_REQUEST['ids'] ) ? sanitize_text_field( $_REQUEST['ids'] ) : '';
	$ids = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );

	if ( empty( $ids ) ) {
		wp_send_json( array() );
	}

	$out = array();
	foreach ( $ids as $id ) {
		$row = soe_ajax_person_picker_row( $id, '', false );
		if ( $row !== null ) {
			$out[] = $row;
		}
	}

	wp_send_json( $out );
}
