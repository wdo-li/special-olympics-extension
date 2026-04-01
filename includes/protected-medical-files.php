<?php
/**
 * Protected medical files (medizinische_datenblatter): upload to private folder, download via proxy.
 *
 * - Uploads for mitglied post type go to uploads/soe-protected/medical/{YEAR}/{nachname}-{vorname}-{ID}/
 * - .htaccess denies direct access; download only via admin-post.php with login + nonce + capability.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Max file size for medical PDF uploads (bytes). */
define( 'SOE_MEDICAL_MAX_UPLOAD_BYTES', 5 * 1024 * 1024 );

/**
 * Force basic uploader (simple file input) for non-admins.
 * This prevents access to the Media Library for users without manage_options.
 * Admins retain full Media Library access.
 */
add_filter( 'acf/settings/uploader', 'soe_acf_uploader_for_non_admins' );
function soe_acf_uploader_for_non_admins( $uploader ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return 'basic';
	}
	return $uploader;
}

/**
 * Builds a filesystem-safe folder slug from mitglied post: nachname-vorname-{POST_ID}.
 *
 * @param int $post_id Mitglied post ID.
 * @return string Slug for use in path (e.g. mueller-max-123).
 */
function soe_medical_folder_slug( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || get_post_type( $post_id ) !== 'mitglied' ) {
		return 'mitglied-' . $post_id;
	}
	$nachname = get_field( 'nachname', $post_id );
	$vorname  = get_field( 'vorname', $post_id );
	$n = is_string( $nachname ) ? trim( $nachname ) : '';
	$v = is_string( $vorname ) ? trim( $vorname ) : '';
	// Replace umlauts for filesystem compatibility.
	$replace = array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss' );
	$n = str_replace( array_keys( $replace ), array_values( $replace ), $n );
	$v = str_replace( array_keys( $replace ), array_values( $replace ), $v );
	$slug = trim( $n . '-' . $v, '-' );
	if ( $slug === '' ) {
		$slug = 'mitglied';
	}
	$slug = sanitize_file_name( $slug );
	$slug = preg_replace( '/[^a-z0-9\-]/i', '-', $slug );
	$slug = trim( $slug, '-' );
	if ( $slug === '' ) {
		$slug = 'mitglied';
	}
	return $slug . '-' . $post_id;
}

/**
 * Ensures soe-protected/medical base dir and .htaccess exist (deny direct access).
 *
 * @param string $dir Full path to a subdir under soe-protected/medical.
 * @return void
 */
function soe_medical_ensure_htaccess( $dir ) {
	$base = dirname( $dir );
	while ( $base && strpos( $base, 'soe-protected' ) !== false ) {
		$ht = $base . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			$content = "# SOE protected: no direct access.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>";
			$bytes = file_put_contents( $ht, $content );
			if ( $bytes === false && function_exists( 'soe_debug_log' ) ) {
				soe_debug_log( 'Medical .htaccess write failed', array( 'path' => $ht ) );
			}
		}
		$base = dirname( $base );
		if ( basename( $base ) === 'uploads' || basename( $base ) === 'wp-content' ) {
			break;
		}
	}
}

/**
 * Extracts post_id from various sources (REQUEST, Referer).
 * Needed because ACF basic uploader doesn't send post_id in REQUEST.
 *
 * @return int Post ID or 0.
 */
function soe_medical_get_upload_post_id() {
	if ( isset( $_REQUEST['post_id'] ) && (int) $_REQUEST['post_id'] > 0 ) {
		return (int) $_REQUEST['post_id'];
	}
	if ( isset( $_REQUEST['post'] ) && (int) $_REQUEST['post'] > 0 ) {
		return (int) $_REQUEST['post'];
	}
	$referer = isset( $_SERVER['HTTP_REFERER'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	if ( $referer ) {
		if ( preg_match( '/[?&]post=(\d+)/', $referer, $m ) ) {
			return (int) $m[1];
		}
		if ( preg_match( '/post\.php\?post=(\d+)/', $referer, $m ) ) {
			return (int) $m[1];
		}
	}
	return 0;
}

/**
 * Redirects uploads for mitglied (medical field) to soe-protected/medical/{YEAR}/{slug}/.
 */
add_filter( 'upload_dir', 'soe_medical_upload_dir', 10, 1 );
function soe_medical_upload_dir( $uploads ) {
	static $running = false;
	if ( $running ) {
		return $uploads;
	}
	$running = true;

	$post_id = soe_medical_get_upload_post_id();
	if ( ! $post_id || get_post_type( $post_id ) !== 'mitglied' ) {
		$running = false;
		return $uploads;
	}
	if ( ! empty( $uploads['error'] ) ) {
		$running = false;
		return $uploads;
	}
	$year   = date( 'Y' );
	$slug   = soe_medical_folder_slug( $post_id );
	$subdir = '/soe-protected/medical/' . $year . '/' . $slug;
	$dir    = $uploads['basedir'] . $subdir;
	if ( ! wp_mkdir_p( $dir ) ) {
		$running = false;
		return $uploads;
	}
	soe_medical_ensure_htaccess( $dir );
	$uploads['subdir'] = $subdir;
	$uploads['path']   = $uploads['basedir'] . $subdir;
	$uploads['url']    = $uploads['baseurl'] . $subdir;

	$running = false;
	return $uploads;
}

/**
 * Prefilter: only PDF, max 5MB, unique filename.
 * Also stores the post_id in a global for use in add_attachment hook.
 */
add_filter( 'wp_handle_upload_prefilter', 'soe_medical_upload_prefilter', 10, 1 );
function soe_medical_upload_prefilter( $file ) {
	global $soe_medical_upload_post_id;
	$post_id = soe_medical_get_upload_post_id();
	if ( ! $post_id || get_post_type( $post_id ) !== 'mitglied' ) {
		$soe_medical_upload_post_id = 0;
		return $file;
	}
	$soe_medical_upload_post_id = $post_id;
	$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
	if ( $ext !== 'pdf' ) {
		return array(
			'error' => __( 'Nur PDF-Dateien sind erlaubt.', 'special-olympics-extension' ),
		);
	}
	if ( isset( $file['type'] ) && $file['type'] !== 'application/pdf' ) {
		return array(
			'error' => __( 'Nur PDF-Dateien sind erlaubt.', 'special-olympics-extension' ),
		);
	}
	if ( isset( $file['size'] ) && $file['size'] > SOE_MEDICAL_MAX_UPLOAD_BYTES ) {
		return array(
			'error' => __( 'Die Datei ist zu groß. Maximal 5 MB.', 'special-olympics-extension' ),
		);
	}
	$base = (int) $post_id . '_' . time() . '_' . wp_generate_password( 8, false, false );
	$file['name'] = sanitize_file_name( $base . '.pdf' );
	return $file;
}

/**
 * After attachment is created, set post_parent to the mitglied post.
 * This ensures the download handler can verify ownership.
 */
add_action( 'add_attachment', 'soe_medical_set_attachment_parent', 10, 1 );
function soe_medical_set_attachment_parent( $attachment_id ) {
	global $soe_medical_upload_post_id;
	if ( empty( $soe_medical_upload_post_id ) ) {
		return;
	}
	$post_id = (int) $soe_medical_upload_post_id;
	if ( ! $post_id || get_post_type( $post_id ) !== 'mitglied' ) {
		return;
	}
	wp_update_post( array(
		'ID'          => $attachment_id,
		'post_parent' => $post_id,
	) );
	$soe_medical_upload_post_id = 0;
}

/**
 * Whether the current user may view medical documents for the given mitglied post.
 *
 * @param int $member_post_id Mitglied post ID.
 * @return bool
 */
function soe_user_can_view_medical( $member_post_id ) {
	$member_post_id = (int) $member_post_id;
	if ( ! $member_post_id || get_post_type( $member_post_id ) !== 'mitglied' ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	$user = wp_get_current_user();
	if ( ! $user || ! $user->ID ) {
		return false;
	}
	$roles = (array) $user->roles;
	if ( in_array( SOE_ROLE_HAUPTLEITER_IN, $roles, true ) || in_array( SOE_ROLE_LEITER_IN, $roles, true ) ) {
		$my_mitglied_id = function_exists( 'soe_get_current_user_mitglied_id' ) ? soe_get_current_user_mitglied_id() : 0;
		if ( ! $my_mitglied_id ) {
			return false;
		}
		$my_terms    = wp_get_object_terms( $my_mitglied_id, 'sport' );
		$member_terms = wp_get_object_terms( $member_post_id, 'sport' );
		$my_ids      = is_array( $my_terms ) ? wp_list_pluck( $my_terms, 'term_id' ) : array();
		$member_ids  = is_array( $member_terms ) ? wp_list_pluck( $member_terms, 'term_id' ) : array();
		$intersect   = array_intersect( $my_ids, $member_ids );
		return ! empty( $intersect );
	}
	return false;
}

/**
 * Download handler: admin-post.php?action=soe_medical_download&id={attachment_id}&_wpnonce=...
 */
add_action( 'admin_post_soe_medical_download', 'soe_medical_download_handler' );
add_action( 'admin_post_nopriv_soe_medical_download', 'soe_medical_download_handler' );
function soe_medical_download_handler() {
	$attachment_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( ! $attachment_id ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Medical download: missing id', array() );
		}
		wp_die( esc_html__( 'Ungültige Anfrage.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	if ( ! is_user_logged_in() ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Medical download: not logged in', array( 'attachment_id' => $attachment_id ) );
		}
		wp_die( esc_html__( 'Du musst angemeldet sein.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'soe_medical_download_' . $attachment_id ) ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Medical download: invalid nonce', array( 'attachment_id' => $attachment_id ) );
		}
		wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$member_post_id = (int) get_post_field( 'post_parent', $attachment_id );
	if ( ! $member_post_id || get_post_type( $member_post_id ) !== 'mitglied' ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Medical download: attachment not attached to mitglied', array( 'attachment_id' => $attachment_id ) );
		}
		wp_die( esc_html__( 'Datei nicht gefunden.', 'special-olympics-extension' ), '', array( 'response' => 404 ) );
	}

	// Allow attachment author (uploader) to download their own files
	$attachment_author = (int) get_post_field( 'post_author', $attachment_id );
	$is_author = $attachment_author && $attachment_author === get_current_user_id();

	if ( ! $is_author && ! soe_user_can_view_medical( $member_post_id ) ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Medical download: permission denied', array( 'attachment_id' => $attachment_id, 'member_post_id' => $member_post_id ) );
		}
		wp_die( esc_html__( 'Du hast keine Berechtigung, diese Datei herunterzuladen.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$path = get_attached_file( $attachment_id );
	if ( ! $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Medical download: file not found or not readable', array( 'attachment_id' => $attachment_id, 'path' => $path ) );
		}
		wp_die( esc_html__( 'Datei konnte nicht geladen werden.', 'special-olympics-extension' ), '', array( 'response' => 404 ) );
	}
	$filename = basename( $path );
	if ( function_exists( 'soe_debug_log' ) ) {
		soe_debug_log( 'Medical download: success', array( 'attachment_id' => $attachment_id ) );
	}
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path );
	exit;
}

/**
 * Replace direct file URL with proxy URL (nonce) for medizinische_datenblatter.
 */
add_filter( 'acf/format_value/name=medizinische_datenblatter', 'soe_medical_format_value', 10, 3 );
function soe_medical_format_value( $value, $post_id, $field ) {
	if ( empty( $value ) ) {
		return $value;
	}

	// Helper to replace URL with proxy URL
	$replace_url = function ( $item ) {
		$id = null;
		if ( is_numeric( $item ) ) {
			$id = (int) $item;
		} elseif ( is_array( $item ) && isset( $item['ID'] ) && is_numeric( $item['ID'] ) ) {
			$id = (int) $item['ID'];
		} elseif ( is_array( $item ) && isset( $item['id'] ) && is_numeric( $item['id'] ) ) {
			$id = (int) $item['id'];
		}
		if ( ! $id ) {
			return $item;
		}
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=soe_medical_download&id=' . $id ),
			'soe_medical_download_' . $id
		);
		if ( is_array( $item ) ) {
			$item['url'] = $url;
			return $item;
		}
		return array( 'id' => $id, 'url' => $url );
	};

	// Single file array from ACF (has 'ID' or 'id' key with numeric value)
	if ( is_array( $value ) && ( ( isset( $value['ID'] ) && is_numeric( $value['ID'] ) ) || ( isset( $value['id'] ) && is_numeric( $value['id'] ) ) ) ) {
		return $replace_url( $value );
	}

	// Single numeric ID
	if ( is_numeric( $value ) ) {
		return $replace_url( $value );
	}

	// Multiple files: array of IDs or array of file arrays
	if ( is_array( $value ) && ! empty( $value ) ) {
		return array_map( $replace_url, $value );
	}

	return $value;
}

/**
 * Auto-delete attachments when removed from medizinische_datenblatter field.
 * Compares old and new values; deletes attachments that are no longer referenced.
 */
add_filter( 'acf/update_value/name=medizinische_datenblatter', 'soe_medical_auto_delete_removed_attachments', 10, 4 );
function soe_medical_auto_delete_removed_attachments( $value, $post_id, $field, $original ) {
	$old_value = get_field( 'medizinische_datenblatter', $post_id, false );

	$extract_ids = function ( $val ) {
		$ids = array();
		if ( empty( $val ) ) {
			return $ids;
		}
		if ( is_numeric( $val ) ) {
			$ids[] = (int) $val;
		} elseif ( is_array( $val ) ) {
			foreach ( $val as $item ) {
				if ( is_numeric( $item ) ) {
					$ids[] = (int) $item;
				} elseif ( is_array( $item ) && isset( $item['ID'] ) ) {
					$ids[] = (int) $item['ID'];
				} elseif ( is_array( $item ) && isset( $item['id'] ) ) {
					$ids[] = (int) $item['id'];
				}
			}
			if ( empty( $ids ) && isset( $val['ID'] ) ) {
				$ids[] = (int) $val['ID'];
			} elseif ( empty( $ids ) && isset( $val['id'] ) ) {
				$ids[] = (int) $val['id'];
			}
		}
		return array_filter( $ids );
	};

	$old_ids = $extract_ids( $old_value );
	$new_ids = $extract_ids( $value );

	$removed_ids = array_diff( $old_ids, $new_ids );

	foreach ( $removed_ids as $attachment_id ) {
		$parent = (int) get_post_field( 'post_parent', $attachment_id );
		if ( $parent === (int) $post_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
	}

	return $value;
}

/**
 * AJAX endpoint to get a download nonce for a newly uploaded medical file.
 * This allows the ACF editor to replace direct links with proxy links
 * for files uploaded in the current session (before page reload).
 */
add_action( 'wp_ajax_soe_get_medical_nonce', 'soe_ajax_get_medical_nonce' );
function soe_ajax_get_medical_nonce() {
	$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
	$post_id       = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

	if ( ! $attachment_id || ! $post_id ) {
		wp_send_json_error( array( 'message' => 'Missing parameters' ) );
	}

	// Verify the attachment belongs to this post or user has permission
	$attachment_author = (int) get_post_field( 'post_author', $attachment_id );
	$attachment_parent = (int) get_post_field( 'post_parent', $attachment_id );
	$current_user_id   = get_current_user_id();

	$can_get_nonce = current_user_can( 'manage_options' )
		|| $attachment_author === $current_user_id
		|| ( $attachment_parent === $post_id && get_post_type( $post_id ) === 'mitglied' );

	if ( ! $can_get_nonce ) {
		wp_send_json_error( array( 'message' => 'Permission denied' ) );
	}

	$nonce = wp_create_nonce( 'soe_medical_download_' . $attachment_id );
	wp_send_json_success( array(
		'nonce'         => $nonce,
		'attachment_id' => $attachment_id,
	) );
}
