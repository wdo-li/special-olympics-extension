<?php
/**
 * CPT "contact": archive / restore (post meta), admin list columns/filters, meta box.
 *
 * Mirrors the mitglied member_status pattern. Archived contacts are excluded from
 * Contact Actions search and cannot be added to actions (see contact-actions.php).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post meta key: 'active' or 'archived'. */
define( 'SOE_CONTACT_STATUS_META', 'soe_contact_status' );

define( 'SOE_CONTACT_STATUS_ACTIVE', 'active' );
define( 'SOE_CONTACT_STATUS_ARCHIVED', 'archived' );

/** Checkbox "Beziehung" choice values (must match ACF JSON). */
function soe_contact_beziehung_filter_choices() {
	return array(
		'Sponsor'    => __( 'Sponsor', 'special-olympics-extension' ),
		'Gönner'     => __( 'Gönner', 'special-olympics-extension' ),
		'Land'       => __( 'Land', 'special-olympics-extension' ),
		'Gemeinde'   => __( 'Gemeinde', 'special-olympics-extension' ),
		'Partner'    => __( 'Partner', 'special-olympics-extension' ),
		'Volontaire' => __( 'Volontaire', 'special-olympics-extension' ),
	);
}

add_action( 'pre_get_posts', 'soe_contact_admin_list_pre_get_posts' );
add_action( 'restrict_manage_posts', 'soe_contact_list_add_filters' );
add_filter( 'manage_contact_posts_columns', 'soe_contact_list_columns' );
add_action( 'manage_contact_posts_custom_column', 'soe_contact_list_column_content', 10, 2 );
add_action( 'admin_print_styles-edit.php', 'soe_contact_list_admin_styles' );
add_action( 'add_meta_boxes_contact', 'soe_contact_add_archive_meta_box', 10 );
add_action( 'wp_ajax_soe_archive_contact', 'soe_ajax_archive_contact' );
add_action( 'wp_ajax_soe_restore_contact', 'soe_ajax_restore_contact' );

/**
 * Returns contact archive status.
 *
 * @param int $post_id Contact post ID.
 * @return string 'active' or 'archived'
 */
function soe_get_contact_status( $post_id ) {
	$status = get_post_meta( (int) $post_id, SOE_CONTACT_STATUS_META, true );
	if ( $status === SOE_CONTACT_STATUS_ARCHIVED ) {
		return SOE_CONTACT_STATUS_ARCHIVED;
	}
	return SOE_CONTACT_STATUS_ACTIVE;
}

/**
 * Whether the contact is active (not archived).
 *
 * @param int $post_id Contact post ID.
 * @return bool
 */
function soe_is_contact_active( $post_id ) {
	return soe_get_contact_status( $post_id ) === SOE_CONTACT_STATUS_ACTIVE;
}

/**
 * Contact list query: default sort by title A–Z; status (default active); optional Beziehung / Ort / Land / PLZ filters.
 *
 * @param WP_Query $query Main query in admin.
 */
function soe_contact_admin_list_pre_get_posts( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( $query->get( 'post_type' ) !== 'contact' ) {
		return;
	}

	// Default sort A–Z by post title when the user has not chosen a column sort.
	if ( ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}

	global $wpdb;

	$meta_query = array( 'relation' => 'AND' );

	$status_filter = isset( $_GET['soe_contact_status'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_status'] ) ) : '';
	if ( $status_filter === SOE_CONTACT_STATUS_ARCHIVED ) {
		$meta_query[] = array(
			'key'   => SOE_CONTACT_STATUS_META,
			'value' => SOE_CONTACT_STATUS_ARCHIVED,
		);
	} elseif ( $status_filter === 'all' ) {
		// No status constraint.
	} else {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'   => SOE_CONTACT_STATUS_META,
				'value' => SOE_CONTACT_STATUS_ACTIVE,
			),
			array(
				'key'     => SOE_CONTACT_STATUS_META,
				'compare' => 'NOT EXISTS',
			),
		);
	}

	$beziehung = isset( $_GET['soe_contact_beziehung'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_beziehung'] ) ) : '';
	if ( $beziehung !== '' ) {
		$allowed = array_keys( soe_contact_beziehung_filter_choices() );
		if ( in_array( $beziehung, $allowed, true ) ) {
			$like         = '%' . $wpdb->esc_like( $beziehung ) . '%';
			$meta_query[] = array(
				'key'     => 'beziehung',
				'value'   => $like,
				'compare' => 'LIKE',
			);
		}
	}

	$ort = isset( $_GET['soe_contact_ort'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_ort'] ) ) : '';
	if ( $ort !== '' ) {
		$meta_query[] = array(
			'key'     => 'ort',
			'value'   => '%' . $wpdb->esc_like( $ort ) . '%',
			'compare' => 'LIKE',
		);
	}

	$land = isset( $_GET['soe_contact_land'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_land'] ) ) : '';
	if ( $land !== '' ) {
		$meta_query[] = array(
			'key'     => 'land',
			'value'   => '%' . $wpdb->esc_like( $land ) . '%',
			'compare' => 'LIKE',
		);
	}

	$plz = isset( $_GET['soe_contact_plz'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_plz'] ) ) : '';
	if ( $plz !== '' ) {
		$meta_query[] = array(
			'key'     => 'plz',
			'value'   => '%' . $wpdb->esc_like( $plz ) . '%',
			'compare' => 'LIKE',
		);
	}

	// Drop empty AND wrapper if only relation key (should not happen).
	if ( count( $meta_query ) === 1 ) {
		return;
	}

	$query->set( 'meta_query', $meta_query );
}

/**
 * List filters: status, Beziehung, Ort, Land, PLZ.
 *
 * @param string $post_type Current post type slug.
 */
function soe_contact_list_add_filters( $post_type ) {
	if ( $post_type !== 'contact' ) {
		return;
	}
	$status_cur = isset( $_GET['soe_contact_status'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_status'] ) ) : '';
	$bez_cur    = isset( $_GET['soe_contact_beziehung'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_beziehung'] ) ) : '';
	$ort_cur    = isset( $_GET['soe_contact_ort'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_ort'] ) ) : '';
	$land_cur   = isset( $_GET['soe_contact_land'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_land'] ) ) : '';
	$plz_cur    = isset( $_GET['soe_contact_plz'] ) ? sanitize_text_field( wp_unslash( $_GET['soe_contact_plz'] ) ) : '';
	?>
	<select name="soe_contact_status">
		<option value=""><?php esc_html_e( 'Aktiv (Standard)', 'special-olympics-extension' ); ?></option>
		<option value="all" <?php selected( $status_cur, 'all' ); ?>><?php esc_html_e( 'Alle Status', 'special-olympics-extension' ); ?></option>
		<option value="<?php echo esc_attr( SOE_CONTACT_STATUS_ARCHIVED ); ?>" <?php selected( $status_cur, SOE_CONTACT_STATUS_ARCHIVED ); ?>><?php esc_html_e( 'Nur Archivierte', 'special-olympics-extension' ); ?></option>
	</select>
	<select name="soe_contact_beziehung">
		<option value=""><?php esc_html_e( 'Alle Beziehungen', 'special-olympics-extension' ); ?></option>
		<?php foreach ( soe_contact_beziehung_filter_choices() as $val => $label ) : ?>
			<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $bez_cur, $val ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<label class="screen-reader-text" for="soe-contact-filter-ort"><?php esc_html_e( 'Ort', 'special-olympics-extension' ); ?></label>
	<input type="search" name="soe_contact_ort" id="soe-contact-filter-ort" value="<?php echo esc_attr( $ort_cur ); ?>"
		placeholder="<?php esc_attr_e( 'Ort…', 'special-olympics-extension' ); ?>" style="max-width:9rem;" />
	<label class="screen-reader-text" for="soe-contact-filter-land"><?php esc_html_e( 'Land', 'special-olympics-extension' ); ?></label>
	<input type="search" name="soe_contact_land" id="soe-contact-filter-land" value="<?php echo esc_attr( $land_cur ); ?>"
		placeholder="<?php esc_attr_e( 'Land…', 'special-olympics-extension' ); ?>" style="max-width:9rem;" />
	<label class="screen-reader-text" for="soe-contact-filter-plz"><?php esc_html_e( 'PLZ', 'special-olympics-extension' ); ?></label>
	<input type="search" name="soe_contact_plz" id="soe-contact-filter-plz" value="<?php echo esc_attr( $plz_cur ); ?>"
		placeholder="<?php esc_attr_e( 'PLZ…', 'special-olympics-extension' ); ?>" style="max-width:6rem;" />
	<?php
}

/**
 * Adds columns on the contact list screen.
 *
 * @param array<string, string> $columns Default columns.
 * @return array<string, string>
 */
function soe_contact_list_columns( $columns ) {
	$insert = array(
		'soe_geschaeft' => __( 'Geschäft', 'special-olympics-extension' ),
		'soe_person'    => __( 'Person', 'special-olympics-extension' ),
		'soe_adresse'   => __( 'Adresse', 'special-olympics-extension' ),
		'soe_email'     => __( 'E-Mail', 'special-olympics-extension' ),
		'soe_phone'     => __( 'Telefon', 'special-olympics-extension' ),
		'soe_beziehung' => __( 'Beziehung', 'special-olympics-extension' ),
	);
	$keys   = array_keys( $columns );
	$pos    = array_search( 'title', $keys, true );
	if ( $pos !== false ) {
		return array_slice( $columns, 0, $pos + 1, true ) + $insert + array_slice( $columns, $pos + 1, null, true );
	}
	return $insert + $columns;
}

/**
 * Builds a tel: URI from a phone string (digits, spaces, +, parentheses).
 *
 * @param string $phone Raw phone text.
 * @return string tel: URI or empty string.
 */
function soe_contact_phone_to_tel_uri( $phone ) {
	if ( ! is_string( $phone ) ) {
		return '';
	}
	$trim = trim( $phone );
	if ( $trim === '' ) {
		return '';
	}
	$dial = preg_replace( '/[^\d+]/', '', $trim );
	if ( $dial === '' ) {
		return '';
	}
	return 'tel:' . $dial;
}

/**
 * Renders custom list columns for contacts.
 *
 * @param string $column Column key.
 * @param int    $post_id Post ID.
 */
function soe_contact_list_column_content( $column, $post_id ) {
	$post_id = (int) $post_id;
	if ( ! function_exists( 'get_field' ) ) {
		return;
	}
	switch ( $column ) {
		case 'soe_geschaeft':
			$v = get_field( 'geschaeft', $post_id );
			echo esc_html( is_string( $v ) && $v !== '' ? $v : '–' );
			return;
		case 'soe_person':
			$vn = get_field( 'vorname', $post_id );
			$nn = get_field( 'name', $post_id );
			$vn = is_string( $vn ) ? trim( $vn ) : '';
			$nn = is_string( $nn ) ? trim( $nn ) : '';
			$full = trim( $vn . ' ' . $nn );
			echo esc_html( $full !== '' ? $full : '–' );
			return;
		case 'soe_adresse':
			$street = get_field( 'strassenr', $post_id );
			$plz    = get_field( 'plz', $post_id );
			$ort    = get_field( 'ort', $post_id );
			$street = is_string( $street ) ? trim( $street ) : '';
			$plz    = is_string( $plz ) ? trim( $plz ) : '';
			$ort    = is_string( $ort ) ? trim( $ort ) : '';
			$lines  = array();
			if ( $street !== '' ) {
				$lines[] = $street;
			}
			$plz_ort = trim( $plz . ' ' . $ort );
			if ( $plz_ort !== '' ) {
				$lines[] = $plz_ort;
			}
			if ( empty( $lines ) ) {
				echo '–';
				return;
			}
			echo '<div class="soe-contact-list-stack">';
			foreach ( $lines as $line ) {
				echo '<div class="soe-contact-list-stack__line">' . esc_html( $line ) . '</div>';
			}
			echo '</div>';
			return;
		case 'soe_email':
			$lines = array();
			foreach ( array( 'e-mail_geschaft', 'e-mail_person', 'e-mail_privat' ) as $fname ) {
				$e = get_field( $fname, $post_id );
				if ( is_string( $e ) && trim( $e ) !== '' ) {
					$e = trim( $e );
					$lines[] = '<a class="soe-contact-list-stack__link" href="' . esc_url( 'mailto:' . $e ) . '">' . esc_html( $e ) . '</a>';
				}
			}
			if ( empty( $lines ) ) {
				echo '–';
				return;
			}
			echo '<div class="soe-contact-list-stack">' . implode( '', array_map( static function ( $html ) {
				return '<div class="soe-contact-list-stack__line">' . $html . '</div>';
			}, $lines ) ) . '</div>';
			return;
		case 'soe_phone':
			$lines = array();
			foreach ( array( 'telefon_geschaeft', 'telefon_privat' ) as $fname ) {
				$t = get_field( $fname, $post_id );
				if ( ! is_string( $t ) || trim( $t ) === '' ) {
					continue;
				}
				$t    = trim( $t );
				$href = soe_contact_phone_to_tel_uri( $t );
				if ( $href !== '' ) {
					$lines[] = '<a class="soe-contact-list-stack__link" href="' . esc_url( $href ) . '">' . esc_html( $t ) . '</a>';
				} else {
					$lines[] = esc_html( $t );
				}
			}
			if ( empty( $lines ) ) {
				echo '–';
				return;
			}
			echo '<div class="soe-contact-list-stack">' . implode( '', array_map( static function ( $html ) {
				return '<div class="soe-contact-list-stack__line">' . $html . '</div>';
			}, $lines ) ) . '</div>';
			return;
		case 'soe_beziehung':
			$raw = get_field( 'beziehung', $post_id );
			$arr = is_array( $raw ) ? $raw : array();
			$arr = array_filter( array_map( 'strval', $arr ) );
			if ( empty( $arr ) ) {
				echo '–';
				return;
			}
			echo '<div class="soe-contact-list-stack">';
			foreach ( $arr as $label ) {
				echo '<div class="soe-contact-list-stack__line">' . esc_html( $label ) . '</div>';
			}
			echo '</div>';
			return;
		default:
			return;
	}
}

/**
 * Contact list table: avoid collapsed columns (vertical letter stacking) when many columns exist.
 */
function soe_contact_list_admin_styles() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'contact' || $screen->base !== 'edit' ) {
		return;
	}
	echo '<style id="soe-contact-list-columns">
		/* Core adds .fixed → table-layout:fixed, which squeezes many custom columns to a few px. */
		.post-type-contact .wp-list-table.widefat {
			table-layout: auto;
			min-width: 1180px;
		}
		.post-type-contact .wp-list-table td.column-soe_geschaeft,
		.post-type-contact .wp-list-table th.column-soe_geschaeft,
		.post-type-contact .wp-list-table td.column-soe_person,
		.post-type-contact .wp-list-table th.column-soe_person,
		.post-type-contact .wp-list-table td.column-soe_email,
		.post-type-contact .wp-list-table th.column-soe_email {
			min-width: 9rem;
			max-width: 16rem;
			vertical-align: top;
			word-break: normal;
			overflow-wrap: break-word;
		}
		.post-type-contact .wp-list-table td.column-soe_adresse,
		.post-type-contact .wp-list-table th.column-soe_adresse,
		.post-type-contact .wp-list-table td.column-soe_phone,
		.post-type-contact .wp-list-table th.column-soe_phone {
			min-width: 5.5rem;
			vertical-align: top;
			word-break: normal;
			overflow-wrap: break-word;
		}
		.post-type-contact .wp-list-table td.column-soe_adresse,
		.post-type-contact .wp-list-table th.column-soe_adresse {
			min-width: 10rem;
			max-width: 18rem;
		}
		.post-type-contact .wp-list-table td.column-soe_beziehung,
		.post-type-contact .wp-list-table th.column-soe_beziehung {
			min-width: 8rem;
			max-width: 14rem;
			vertical-align: top;
			word-break: normal;
			overflow-wrap: break-word;
		}
		.post-type-contact .soe-contact-list-stack { line-height: 1.45; }
		.post-type-contact .soe-contact-list-stack__line { margin: 0 0 3px; }
		.post-type-contact .soe-contact-list-stack__line:last-child { margin-bottom: 0; }
		.post-type-contact .soe-contact-list-stack__link { text-decoration: none; }
		.post-type-contact .soe-contact-list-stack__link:hover,
		.post-type-contact .soe-contact-list-stack__link:focus { text-decoration: underline; }
	</style>';
}

/**
 * Side meta box: archive / restore (administrators only).
 */
function soe_contact_add_archive_meta_box() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_meta_box(
		'soe_contact_status',
		__( 'Kontakt-Status', 'special-olympics-extension' ),
		'soe_contact_render_archive_meta_box',
		'contact',
		'side',
		'high'
	);
}

/**
 * @param WP_Post $post Current contact post.
 */
function soe_contact_render_archive_meta_box( $post ) {
	if ( ! $post instanceof WP_Post || $post->post_type !== 'contact' ) {
		return;
	}
	$is_archived   = soe_get_contact_status( $post->ID ) === SOE_CONTACT_STATUS_ARCHIVED;
	$archive_nonce = wp_create_nonce( 'soe_archive_contact_' . $post->ID );
	$restore_nonce = wp_create_nonce( 'soe_restore_contact_' . $post->ID );
	?>
	<p>
		<strong><?php echo $is_archived ? esc_html__( 'Archiviert', 'special-olympics-extension' ) : esc_html__( 'Aktiv', 'special-olympics-extension' ); ?></strong>
	</p>
	<p>
		<?php if ( $is_archived ) : ?>
			<button type="button" class="button soe-restore-contact" data-post-id="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( $restore_nonce ); ?>"><?php esc_html_e( 'Kontakt reaktivieren', 'special-olympics-extension' ); ?></button>
		<?php else : ?>
			<button type="button" class="button soe-archive-contact" data-post-id="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( $archive_nonce ); ?>"><?php esc_html_e( 'Kontakt archivieren', 'special-olympics-extension' ); ?></button>
		<?php endif; ?>
	</p>
	<p class="soe-contact-status-message" style="display:none;"></p>
	<?php
}

/**
 * AJAX: set contact status to archived.
 */
function soe_ajax_archive_contact() {
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'special-olympics-extension' ) ) );
	}
	check_ajax_referer( 'soe_archive_contact_' . $post_id, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'special-olympics-extension' ) ) );
	}
	if ( get_post_type( $post_id ) !== 'contact' ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'special-olympics-extension' ) ) );
	}
	update_post_meta( $post_id, SOE_CONTACT_STATUS_META, SOE_CONTACT_STATUS_ARCHIVED );
	if ( function_exists( 'soe_debug_log' ) ) {
		soe_debug_log( 'Contact archived', array( 'post_id' => $post_id ) );
	}
	wp_send_json_success( array( 'message' => __( 'Kontakt wurde archiviert.', 'special-olympics-extension' ) ) );
}

/**
 * AJAX: set contact status to active.
 */
function soe_ajax_restore_contact() {
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'special-olympics-extension' ) ) );
	}
	check_ajax_referer( 'soe_restore_contact_' . $post_id, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission.', 'special-olympics-extension' ) ) );
	}
	if ( get_post_type( $post_id ) !== 'contact' ) {
		wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'special-olympics-extension' ) ) );
	}
	update_post_meta( $post_id, SOE_CONTACT_STATUS_META, SOE_CONTACT_STATUS_ACTIVE );
	if ( function_exists( 'soe_debug_log' ) ) {
		soe_debug_log( 'Contact restored', array( 'post_id' => $post_id ) );
	}
	wp_send_json_success( array( 'message' => __( 'Kontakt wurde reaktiviert.', 'special-olympics-extension' ) ) );
}

/**
 * Meta query fragment: only contacts that are active (or have no status meta yet).
 *
 * @return array<int, array<string, mixed>>
 */
function soe_contact_meta_query_active_only() {
	return array(
		'relation' => 'OR',
		array(
			'key'   => SOE_CONTACT_STATUS_META,
			'value' => SOE_CONTACT_STATUS_ACTIVE,
		),
		array(
			'key'     => SOE_CONTACT_STATUS_META,
			'compare' => 'NOT EXISTS',
		),
	);
}
