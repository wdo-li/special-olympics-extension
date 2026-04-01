<?php
/**
 * Public attendance page: /soe-anwesenheit/?token=... (no login required).
 * Includes PIN protection, session cookie, and rate limiting.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOE_ATTENDANCE_AUTH_COOKIE', 'soe_attendance_auth' );
define( 'SOE_ATTENDANCE_COOKIE_EXPIRY_DEFAULT', 15 * 60 ); // 15 minutes; PIN required on each new visit.
define( 'SOE_ATTENDANCE_MAX_PIN_ATTEMPTS_DEFAULT', 5 );
define( 'SOE_ATTENDANCE_LOCKOUT_DURATION_DEFAULT', 15 * MINUTE_IN_SECONDS );
define( 'SOE_ATTENDANCE_SYNC_MAX_OPERATIONS_DEFAULT', 250 );

add_action( 'init', 'soe_attendance_register_rewrite' );
add_filter( 'query_vars', 'soe_attendance_query_vars' );
add_action( 'template_redirect', 'soe_attendance_maybe_render_page', 5 );
add_action( 'wp_ajax_soe_attendance_sync', 'soe_attendance_ajax_sync' );
add_action( 'wp_ajax_nopriv_soe_attendance_sync', 'soe_attendance_ajax_sync' );

/**
 * Returns attendance auth cookie expiry in seconds.
 *
 * @return int
 */
function soe_attendance_get_cookie_expiry_seconds() {
	$minutes = function_exists( 'soe_get_setting' ) ? (int) soe_get_setting( 'attendance_cookie_minutes' ) : 0;
	if ( $minutes <= 0 ) {
		return SOE_ATTENDANCE_COOKIE_EXPIRY_DEFAULT;
	}
	return $minutes * MINUTE_IN_SECONDS;
}

/**
 * Returns max failed PIN attempts before lockout.
 *
 * @return int
 */
function soe_attendance_get_max_pin_attempts() {
	$attempts = function_exists( 'soe_get_setting' ) ? (int) soe_get_setting( 'attendance_max_pin_attempts' ) : 0;
	return $attempts > 0 ? $attempts : SOE_ATTENDANCE_MAX_PIN_ATTEMPTS_DEFAULT;
}

/**
 * Returns lockout duration in seconds.
 *
 * @return int
 */
function soe_attendance_get_lockout_duration_seconds() {
	$minutes = function_exists( 'soe_get_setting' ) ? (int) soe_get_setting( 'attendance_lockout_minutes' ) : 0;
	if ( $minutes <= 0 ) {
		return SOE_ATTENDANCE_LOCKOUT_DURATION_DEFAULT;
	}
	return $minutes * MINUTE_IN_SECONDS;
}

/**
 * Sends security headers for public attendance pages.
 *
 * @return void
 */
function soe_attendance_send_security_headers() {
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
	header( 'Pragma: no-cache' );
	header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
	header( 'Referrer-Policy: no-referrer' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: DENY' );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	header( 'Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), microphone=()' );
	header( "Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; form-action 'self'" );
}

function soe_attendance_register_rewrite() {
	add_rewrite_rule( '^soe-anwesenheit/?$', 'index.php?soe_attendance=1', 'top' );
}

function soe_attendance_query_vars( $vars ) {
	$vars[] = 'soe_attendance';
	return $vars;
}

/**
 * Whether the current request is for the public attendance page (by rewrite or by path).
 *
 * @return bool
 */
function soe_attendance_is_request() {
	if ( get_query_var( 'soe_attendance' ) ) {
		return true;
	}
	$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = preg_replace( '#\?.*$#', '', $path );
	return ( strpos( $path, 'soe-anwesenheit' ) !== false );
}

/**
 * Renders the public attendance page or processes POST save; exits if handled.
 * Includes PIN verification, cookie handling, and rate limiting.
 */
function soe_attendance_maybe_render_page() {
	if ( ! soe_attendance_is_request() ) {
		return;
	}

	// 1. Get token from URL or POST.
	$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
	if ( $token === '' && isset( $_POST['soe_attendance_token'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_POST['soe_attendance_token'] ) );
	}

	if ( $token === '' ) {
		soe_attendance_render_error( __( 'Ungültiger oder fehlender Link.', 'special-olympics-extension' ) );
		exit;
	}

	// 2. Validate token and get user.
	$user_id = function_exists( 'soe_attendance_get_user_by_token' ) ? soe_attendance_get_user_by_token( $token ) : null;
	if ( ! $user_id ) {
		soe_attendance_render_error( __( 'Ungültiger Link.', 'special-olympics-extension' ) );
		exit;
	}

	// 3. Check token expiry.
	if ( function_exists( 'soe_attendance_is_token_expired' ) && soe_attendance_is_token_expired( $user_id ) ) {
		soe_attendance_render_error( __( 'Dieser Link ist abgelaufen. Bitte generiere im Backend einen neuen Link.', 'special-olympics-extension' ) );
		exit;
	}

	// 4. Get mitglied_id.
	$mitglied_id = function_exists( 'soe_attendance_get_mitglied_id_for_token' ) ? soe_attendance_get_mitglied_id_for_token( $token ) : 0;
	if ( $mitglied_id === 0 && user_can( $user_id, 'manage_options' ) ) {
		$mitglied_id = -1; // Admin without mitglied profile.
	}
	if ( $mitglied_id === 0 ) {
		soe_attendance_render_error( __( 'Ungültiger oder abgelaufener Link.', 'special-olympics-extension' ) );
		exit;
	}

	// 5. Check if PIN is set.
	$has_pin = function_exists( 'soe_attendance_has_pin' ) && soe_attendance_has_pin( $user_id );
	if ( ! $has_pin ) {
		soe_attendance_render_no_pin_page( $token );
		exit;
	}

	// 6. Check rate limiting.
	if ( ! soe_attendance_check_rate_limit( $token ) ) {
		soe_attendance_render_locked_page( $token );
		exit;
	}

	// 7. Check auth cookie.
	$cookie_valid = soe_attendance_verify_auth_cookie( $token, $user_id );

	// 8. Handle PIN form submission.
	if ( isset( $_POST['soe_pin_submit'] ) && isset( $_POST['soe_pin'] ) ) {
		$pin_nonce = isset( $_POST['soe_attendance_pin_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['soe_attendance_pin_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $pin_nonce, 'soe_attendance_pin_' . $token ) ) {
			soe_attendance_render_error( __( 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.', 'special-olympics-extension' ) );
			exit;
		}
		$entered_pin = sanitize_text_field( wp_unslash( $_POST['soe_pin'] ) );
		if ( function_exists( 'soe_attendance_verify_pin' ) && soe_attendance_verify_pin( $user_id, $entered_pin ) ) {
			soe_attendance_reset_attempts( $token );
			soe_attendance_set_auth_cookie( $token, $user_id );
			$redirect_url = home_url( '/soe-anwesenheit/?token=' . rawurlencode( $token ) );
			wp_safe_redirect( $redirect_url );
			exit;
		} else {
			soe_attendance_increment_attempts( $token );
			if ( ! soe_attendance_check_rate_limit( $token ) ) {
				soe_attendance_render_locked_page( $token );
				exit;
			}
			soe_attendance_render_pin_page( $token, true );
			exit;
		}
	}

	// 9. If no valid cookie, show PIN page.
	if ( ! $cookie_valid ) {
		soe_attendance_render_pin_page( $token, false );
		exit;
	}

	// 10. POST: save attendance.
	if ( isset( $_POST['soe_attendance_save'] ) && isset( $_POST['training_id'] ) && isset( $_POST['session_date'] ) ) {
		$nonce = isset( $_POST['soe_attendance_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['soe_attendance_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'soe_attendance_save_' . $token ) ) {
			soe_attendance_render_error( __( 'Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.', 'special-olympics-extension' ) );
			exit;
		}
		$save_result = soe_attendance_handle_save( $token, $mitglied_id );
		if ( is_wp_error( $save_result ) ) {
			soe_attendance_render_error( $save_result->get_error_message() );
			exit;
		}
		$redirect_url = home_url( '/soe-anwesenheit/?token=' . rawurlencode( $token ) . '&saved=1' );
		if ( isset( $_POST['training_id'] ) ) {
			$redirect_url .= '&tid=' . (int) $_POST['training_id'];
		}
		wp_safe_redirect( $redirect_url );
		exit;
	}

	// 11. Render attendance page.
	soe_attendance_render_page( $token, $mitglied_id );
	exit;
}

/**
 * Returns list of training IDs the user is allowed to manage (Hauptleiter/Leiter or all for admin).
 *
 * @param int $mitglied_id Mitglied post ID, or -1 for admin.
 * @return array List of training IDs.
 */
function soe_attendance_get_allowed_training_ids( $mitglied_id ) {
	if ( $mitglied_id === -1 ) {
		$trainings = soe_db_training_list( array( 'completed' => 0, 'limit' => 500 ) );
		return array_map( function ( $t ) { return (int) $t['id']; }, $trainings );
	}
	$trainings = soe_db_training_list( array(
		'completed'       => 0,
		'limit'           => 500,
		'person_id_roles' => array( 'person_id' => $mitglied_id, 'roles' => array( 'hauptleiter', 'leiter' ) ),
	) );
	return array_map( function ( $t ) { return (int) $t['id']; }, $trainings );
}

/**
 * Gets person labels for a training (person_id => display name).
 *
 * @param int $training_id Training ID.
 * @return array
 */
function soe_attendance_get_person_labels( $training_id ) {
	$persons = soe_db_training_get_persons( $training_id );
	$ids = array();
	foreach ( $persons as $role => $list ) {
		foreach ( (array) $list as $pid ) {
			$ids[ (int) $pid ] = true;
		}
	}
	$out = array();
	foreach ( array_keys( $ids ) as $pid ) {
		$post = get_post( $pid );
		$out[ $pid ] = $post && $post->post_title ? $post->post_title : (string) $pid;
	}
	return $out;
}

/**
 * Gets all valid person IDs assigned to a training.
 *
 * @param int $training_id Training ID.
 * @return int[]
 */
function soe_attendance_get_training_person_ids( $training_id ) {
	$persons = soe_db_training_get_persons( (int) $training_id );
	$ids = array();
	foreach ( $persons as $role => $list ) {
		foreach ( (array) $list as $pid ) {
			$ids[ (int) $pid ] = true;
		}
	}
	return array_keys( $ids );
}

/**
 * Validates strict Y-m-d date format.
 *
 * @param string $date Date string.
 * @return bool
 */
function soe_attendance_is_valid_ymd_date( $date ) {
	if ( ! is_string( $date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return false;
	}
	$dt = DateTime::createFromFormat( 'Y-m-d', $date );
	return $dt && $dt->format( 'Y-m-d' ) === $date;
}

/**
 * Validates that a person belongs to a training.
 *
 * @param int $training_id Training ID.
 * @param int $person_id   Person ID.
 * @return bool
 */
function soe_attendance_is_valid_person_for_training( $training_id, $person_id ) {
	$training_id = (int) $training_id;
	$person_id = (int) $person_id;
	if ( ! $training_id || ! $person_id ) {
		return false;
	}
	$person_ids = soe_attendance_get_training_person_ids( $training_id );
	return in_array( $person_id, $person_ids, true );
}

/**
 * Whether a session date belongs to the given training.
 *
 * @param int    $training_id  Training ID.
 * @param string $session_date Session date Y-m-d.
 * @return bool
 */
function soe_attendance_is_valid_session_for_training( $training_id, $session_date ) {
	$training_id = (int) $training_id;
	$session_date = is_string( $session_date ) ? $session_date : '';
	if ( ! $training_id || ! soe_attendance_is_valid_ymd_date( $session_date ) ) {
		return false;
	}
	$sessions = soe_db_training_get_sessions( $training_id );
	return in_array( $session_date, $sessions, true );
}

/**
 * Validates attendance write context (training/session/person + permissions).
 *
 * @param int      $training_id           Training ID.
 * @param string   $session_date          Session date.
 * @param int      $person_id             Optional person ID.
 * @param int[]|null $allowed_training_ids Optional allowed training IDs.
 * @return true|WP_Error
 */
function soe_attendance_validate_write_context( $training_id, $session_date, $person_id = 0, $allowed_training_ids = null ) {
	$training_id = (int) $training_id;
	$person_id = (int) $person_id;
	$session_date = is_string( $session_date ) ? $session_date : '';

	if ( ! $training_id || ! soe_db_training_get( $training_id ) ) {
		return new WP_Error( 'invalid_training', __( 'Ungültiges Training.', 'special-olympics-extension' ) );
	}
	if ( is_array( $allowed_training_ids ) && ! in_array( $training_id, $allowed_training_ids, true ) ) {
		return new WP_Error( 'forbidden_training', __( 'Keine Berechtigung für dieses Training.', 'special-olympics-extension' ) );
	}
	if ( ! soe_attendance_is_valid_ymd_date( $session_date ) || ! soe_attendance_is_valid_session_for_training( $training_id, $session_date ) ) {
		return new WP_Error( 'invalid_session_date', __( 'Ungültiges Trainingsdatum.', 'special-olympics-extension' ) );
	}
	if ( $person_id > 0 && ! soe_attendance_is_valid_person_for_training( $training_id, $person_id ) ) {
		return new WP_Error( 'invalid_person', __( 'Ungültige Person für dieses Training.', 'special-olympics-extension' ) );
	}

	return true;
}

function soe_attendance_handle_save( $token, $mitglied_id ) {
	$training_id = isset( $_POST['training_id'] ) ? (int) $_POST['training_id'] : 0;
	$session_date = isset( $_POST['session_date'] ) ? sanitize_text_field( wp_unslash( $_POST['session_date'] ) ) : '';
	if ( ! $training_id || ! soe_attendance_is_valid_ymd_date( $session_date ) ) {
		return new WP_Error( 'invalid_operation', __( 'Ungültige Eingabedaten.', 'special-olympics-extension' ) );
	}
	$allowed = soe_attendance_get_allowed_training_ids( $mitglied_id );
	$context_valid = soe_attendance_validate_write_context( $training_id, $session_date, 0, $allowed );
	if ( is_wp_error( $context_valid ) ) {
		return $context_valid;
	}
	$attendances = isset( $_POST['attended'] ) && is_array( $_POST['attended'] ) ? $_POST['attended'] : array();
	$person_ids = soe_attendance_get_training_person_ids( $training_id );
	foreach ( $person_ids as $person_id ) {
		$attended = isset( $attendances[ $person_id ] ) && $attendances[ $person_id ] ? 1 : 0;
		$saved = soe_db_training_set_attendance( $training_id, $session_date, (int) $person_id, $attended );
		if ( ! $saved ) {
			return new WP_Error( 'db_write_failed', __( 'Speichern fehlgeschlagen. Bitte erneut versuchen.', 'special-olympics-extension' ) );
		}
	}
	return true;
}

/**
 * Resolves and validates attendance context from token for sync requests.
 *
 * @param string $token Attendance token.
 * @return array|WP_Error
 */
function soe_attendance_resolve_context_from_token( $token ) {
	$token = is_string( $token ) ? trim( $token ) : '';
	if ( $token === '' ) {
		return new WP_Error( 'invalid_token', __( 'Ungültiger oder fehlender Link.', 'special-olympics-extension' ) );
	}
	$user_id = function_exists( 'soe_attendance_get_user_by_token' ) ? soe_attendance_get_user_by_token( $token ) : null;
	if ( ! $user_id ) {
		return new WP_Error( 'invalid_token', __( 'Ungültiger Link.', 'special-olympics-extension' ) );
	}
	if ( function_exists( 'soe_attendance_is_token_expired' ) && soe_attendance_is_token_expired( $user_id ) ) {
		return new WP_Error( 'token_expired', __( 'Dieser Link ist abgelaufen. Bitte neu anmelden.', 'special-olympics-extension' ) );
	}
	$mitglied_id = function_exists( 'soe_attendance_get_mitglied_id_for_token' ) ? soe_attendance_get_mitglied_id_for_token( $token ) : 0;
	if ( $mitglied_id === 0 && user_can( $user_id, 'manage_options' ) ) {
		$mitglied_id = -1;
	}
	if ( $mitglied_id === 0 ) {
		return new WP_Error( 'invalid_link', __( 'Ungültiger oder abgelaufener Link.', 'special-olympics-extension' ) );
	}
	return array(
		'user_id'     => (int) $user_id,
		'mitglied_id' => (int) $mitglied_id,
	);
}

/**
 * AJAX sync endpoint for offline attendance queue.
 *
 * Last-write-wins is applied by writing each operation in order.
 * Duplicate operation IDs are ignored (idempotent sync).
 */
function soe_attendance_ajax_sync() {
	$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( $token === '' || ! wp_verify_nonce( $nonce, 'soe_attendance_sync_' . $token ) ) {
		wp_send_json_error(
			array(
				'code'    => 'invalid_nonce',
				'message' => __( 'Sicherheitsprüfung fehlgeschlagen.', 'special-olympics-extension' ),
			),
			403
		);
	}

	$context = soe_attendance_resolve_context_from_token( $token );
	if ( is_wp_error( $context ) ) {
		$code = $context->get_error_code();
		$http_code = $code === 'token_expired' ? 401 : 403;
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $context->get_error_message(),
			),
			$http_code
		);
	}

	$user_id = (int) $context['user_id'];
	$mitglied_id = (int) $context['mitglied_id'];
	$has_pin = function_exists( 'soe_attendance_has_pin' ) && soe_attendance_has_pin( $user_id );
	if ( ! $has_pin || ! soe_attendance_verify_auth_cookie( $token, $user_id ) ) {
		wp_send_json_error(
			array(
				'code'    => 'auth_required',
				'message' => __( 'PIN-Session abgelaufen. Bitte erneut PIN eingeben.', 'special-olympics-extension' ),
			),
			401
		);
	}

	$ops_raw = isset( $_POST['operations'] ) ? wp_unslash( $_POST['operations'] ) : '';
	$operations = is_string( $ops_raw ) ? json_decode( $ops_raw, true ) : array();
	if ( ! is_array( $operations ) ) {
		wp_send_json_error(
			array(
				'code'    => 'invalid_payload',
				'message' => __( 'Ungültige Synchronisierungsdaten.', 'special-olympics-extension' ),
			),
			400
		);
	}
	$max_ops = SOE_ATTENDANCE_SYNC_MAX_OPERATIONS_DEFAULT;
	if ( count( $operations ) > $max_ops ) {
		wp_send_json_error(
			array(
				'code'    => 'payload_too_large',
				'message' => sprintf(
					/* translators: %d: max operations per request */
					__( 'Zu viele Änderungen in einem Request. Maximal %d pro Synchronisierung.', 'special-olympics-extension' ),
					$max_ops
				),
			),
			413
		);
	}
	if ( empty( $operations ) ) {
		wp_send_json_success(
			array(
				'results'     => array(),
				'server_time' => current_time( 'mysql' ),
			)
		);
	}

	$allowed_training_ids = soe_attendance_get_allowed_training_ids( $mitglied_id );
	$results = array();

	foreach ( $operations as $op ) {
		$op_id = isset( $op['opId'] ) ? sanitize_key( (string) $op['opId'] ) : '';
		$training_id = isset( $op['trainingId'] ) ? (int) $op['trainingId'] : 0;
		$session_date = isset( $op['sessionDate'] ) ? sanitize_text_field( (string) $op['sessionDate'] ) : '';
		$person_id = isset( $op['personId'] ) ? (int) $op['personId'] : 0;
		$attended = isset( $op['attended'] ) && (int) $op['attended'] === 1 ? 1 : 0;

		if ( $op_id === '' || ! $training_id || strlen( $session_date ) !== 10 || ! $person_id ) {
			$results[] = array(
				'opId'    => $op_id,
				'status'  => 'rejected',
				'reason'  => 'invalid_operation',
			);
			continue;
		}

		if ( ! in_array( $training_id, $allowed_training_ids, true ) ) {
			$results[] = array(
				'opId'    => $op_id,
				'status'  => 'rejected',
				'reason'  => 'forbidden_training',
			);
			continue;
		}

		$context_valid = soe_attendance_validate_write_context( $training_id, $session_date, $person_id, $allowed_training_ids );
		if ( is_wp_error( $context_valid ) ) {
			$reason = $context_valid->get_error_code();
			if ( ! in_array( $reason, array( 'invalid_training', 'forbidden_training', 'invalid_session_date', 'invalid_person' ), true ) ) {
				$reason = 'invalid_operation';
			}
			$results[] = array(
				'opId'    => $op_id,
				'status'  => 'rejected',
				'reason'  => $reason,
			);
			continue;
		}

		if ( function_exists( 'soe_db_attendance_op_exists' ) && soe_db_attendance_op_exists( $op_id ) ) {
			$results[] = array(
				'opId'   => $op_id,
				'status' => 'duplicate',
			);
			continue;
		}

		$saved = soe_db_training_set_attendance( $training_id, $session_date, $person_id, $attended );
		if ( ! $saved ) {
			$results[] = array(
				'opId'   => $op_id,
				'status' => 'rejected',
				'reason' => 'db_write_failed',
			);
			continue;
		}

		$marked = function_exists( 'soe_db_attendance_op_mark_processed' )
			? soe_db_attendance_op_mark_processed( $op_id, $user_id, $training_id, $session_date, $person_id )
			: true;
		if ( ! $marked && function_exists( 'soe_db_attendance_op_exists' ) && soe_db_attendance_op_exists( $op_id ) ) {
			$results[] = array(
				'opId'   => $op_id,
				'status' => 'duplicate',
			);
			continue;
		}
		if ( ! $marked ) {
			$results[] = array(
				'opId'   => $op_id,
				'status' => 'rejected',
				'reason' => 'idempotency_log_failed',
			);
			continue;
		}

		$results[] = array(
			'opId'   => $op_id,
			'status' => 'applied',
		);
	}

	wp_send_json_success(
		array(
			'results'     => $results,
			'server_time' => current_time( 'mysql' ),
		)
	);
}

function soe_attendance_render_error( $message ) {
	soe_attendance_send_security_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . esc_html__( 'Anwesenheit', 'special-olympics-extension' ) . '</title></head><body><p>' . esc_html( $message ) . '</p></body></html>';
}

/**
 * Sets the authentication cookie after successful PIN entry.
 *
 * @param string $token   The attendance token.
 * @param int    $user_id The WordPress user ID.
 */
function soe_attendance_set_auth_cookie( $token, $user_id ) {
	$value = hash( 'sha256', $token . $user_id . wp_salt() );
	$expire = time() + soe_attendance_get_cookie_expiry_seconds();
	$secure = is_ssl();
	setcookie(
		SOE_ATTENDANCE_AUTH_COOKIE,
		$value,
		array(
			'expires'  => $expire,
			'path'     => '/',
			'domain'   => '',
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Strict',
		)
	);
}

/**
 * Verifies the authentication cookie.
 *
 * @param string $token   The attendance token.
 * @param int    $user_id The WordPress user ID.
 * @return bool True if cookie is valid, false otherwise.
 */
function soe_attendance_verify_auth_cookie( $token, $user_id ) {
	if ( ! isset( $_COOKIE[ SOE_ATTENDANCE_AUTH_COOKIE ] ) ) {
		return false;
	}
	$expected = hash( 'sha256', $token . $user_id . wp_salt() );
	return hash_equals( $expected, $_COOKIE[ SOE_ATTENDANCE_AUTH_COOKIE ] );
}

/**
 * Checks if the user is rate-limited (too many failed PIN attempts).
 *
 * @param string $token The attendance token.
 * @return bool True if allowed to try, false if locked out.
 */
function soe_attendance_check_rate_limit( $token ) {
	$key = 'soe_pin_attempts_' . md5( $token );
	$attempts = (int) get_transient( $key );
	return $attempts < soe_attendance_get_max_pin_attempts();
}

/**
 * Increments the failed PIN attempts counter.
 *
 * @param string $token The attendance token.
 */
function soe_attendance_increment_attempts( $token ) {
	$key = 'soe_pin_attempts_' . md5( $token );
	$attempts = (int) get_transient( $key );
	set_transient( $key, $attempts + 1, soe_attendance_get_lockout_duration_seconds() );
}

/**
 * Resets the failed PIN attempts counter.
 *
 * @param string $token The attendance token.
 */
function soe_attendance_reset_attempts( $token ) {
	delete_transient( 'soe_pin_attempts_' . md5( $token ) );
}

/**
 * Renders the PIN entry page.
 *
 * @param string $token The attendance token.
 * @param bool   $error Whether to show an error message.
 */
function soe_attendance_render_pin_page( $token, $error = false ) {
	$logo_url = function_exists( 'soe_get_attendance_public_logo_url' ) ? soe_get_attendance_public_logo_url() : '';
	$sync_nonce = wp_create_nonce( 'soe_attendance_sync_' . $token );
	$sync_url   = admin_url( 'admin-ajax.php' );
	$token_hash = hash( 'sha256', $token );

	soe_attendance_send_security_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'PIN eingeben', 'special-olympics-extension' ); ?></title>
	<style>
		<?php echo soe_attendance_get_base_styles(); ?>
		.soe-pin-input {
			width: 100%;
			padding: 0.85rem 1rem;
			font-size: 1.5rem;
			text-align: center;
			letter-spacing: 0.5rem;
			border: 1px solid #ccc;
			border-radius: 8px;
			background: #fff;
			color: #333;
		}
		.soe-pin-input:focus {
			outline: none;
			border-color: #c41e3a;
			box-shadow: 0 0 0 2px rgba(196, 30, 58, 0.15);
		}
		.soe-error {
			padding: 0.75rem 1rem;
			margin-bottom: 1rem;
			background: #f8d7da;
			color: #721c24;
			border-radius: 8px;
			font-size: 0.95rem;
		}
	</style>
</head>
<body>
	<div class="soe-attendance-header">
		<?php if ( $logo_url ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Special Olympics Liechtenstein', 'special-olympics-extension' ); ?>" />
		<?php endif; ?>
		<h1><?php esc_html_e( 'PIN eingeben', 'special-olympics-extension' ); ?></h1>
	</div>
	<div class="soe-attendance-card">
		<?php if ( $error ) : ?>
			<p class="soe-error"><?php esc_html_e( 'Falscher PIN. Bitte versuche es erneut.', 'special-olympics-extension' ); ?></p>
		<?php endif; ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'soe_attendance_pin_' . $token, 'soe_attendance_pin_nonce' ); ?>
			<input type="hidden" name="soe_attendance_token" value="<?php echo esc_attr( $token ); ?>" />
			<div class="soe-form-group">
				<label for="soe-pin"><?php esc_html_e( 'Ihr persönlicher PIN', 'special-olympics-extension' ); ?></label>
				<input type="password" id="soe-pin" name="soe_pin" class="soe-pin-input" inputmode="numeric" pattern="[0-9]*" maxlength="6" minlength="4" required autocomplete="off" autofocus />
			</div>
			<button type="submit" name="soe_pin_submit" value="1"><?php esc_html_e( 'Weiter', 'special-olympics-extension' ); ?></button>
		</form>
	</div>
</body>
</html>
	<?php
}

/**
 * Renders the "no PIN set" page.
 *
 * @param string $token The attendance token.
 */
function soe_attendance_render_no_pin_page( $token ) {
	$logo_url = function_exists( 'soe_get_attendance_public_logo_url' ) ? soe_get_attendance_public_logo_url() : '';
	$sync_nonce = wp_create_nonce( 'soe_attendance_sync_' . $token );
	$sync_url   = admin_url( 'admin-ajax.php' );
	$token_hash = hash( 'sha256', $token );
	$trainings_url = admin_url( 'admin.php?page=soe-trainings-list' );

	soe_attendance_send_security_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'PIN erforderlich', 'special-olympics-extension' ); ?></title>
	<style>
		<?php echo soe_attendance_get_base_styles(); ?>
		.soe-info {
			padding: 0.75rem 1rem;
			margin-bottom: 1rem;
			background: #fff3cd;
			color: #856404;
			border-radius: 8px;
			font-size: 0.95rem;
		}
		.soe-link-btn {
			display: block;
			padding: 0.75rem 1.75rem;
			font-size: 1rem;
			font-weight: 600;
			margin-top: 1rem;
			background: #c41e3a;
			color: #fff;
			border: none;
			border-radius: 8px;
			text-align: center;
			text-decoration: none;
			transition: background 0.2s;
		}
		.soe-link-btn:hover {
			background: #a01830;
			color: #fff;
		}
	</style>
</head>
<body>
	<div class="soe-attendance-header">
		<?php if ( $logo_url ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Special Olympics Liechtenstein', 'special-olympics-extension' ); ?>" />
		<?php endif; ?>
		<h1><?php esc_html_e( 'PIN erforderlich', 'special-olympics-extension' ); ?></h1>
	</div>
	<div class="soe-attendance-card">
		<p class="soe-info"><?php esc_html_e( 'Für den Zugriff auf die Anwesenheitserfassung musst du zuerst einen PIN im Backend setzen.', 'special-olympics-extension' ); ?></p>
		<p><?php esc_html_e( 'Bitte melde dich im Backend an und setze deinen persönlichen PIN auf der Trainings-Übersichtsseite.', 'special-olympics-extension' ); ?></p>
		<a href="<?php echo esc_url( $trainings_url ); ?>" class="soe-link-btn"><?php esc_html_e( 'Zum Backend', 'special-olympics-extension' ); ?></a>
	</div>
</body>
</html>
	<?php
}

/**
 * Renders the "locked out" page.
 *
 * @param string $token The attendance token.
 */
function soe_attendance_render_locked_page( $token ) {
	$logo_url = function_exists( 'soe_get_attendance_public_logo_url' ) ? soe_get_attendance_public_logo_url() : '';
	$sync_nonce = wp_create_nonce( 'soe_attendance_sync_' . $token );
	$sync_url   = admin_url( 'admin-ajax.php' );
	$token_hash = hash( 'sha256', $token );
	$lockout_minutes = max( 1, (int) ceil( soe_attendance_get_lockout_duration_seconds() / MINUTE_IN_SECONDS ) );

	soe_attendance_send_security_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Zugriff gesperrt', 'special-olympics-extension' ); ?></title>
	<style>
		<?php echo soe_attendance_get_base_styles(); ?>
		.soe-error {
			padding: 0.75rem 1rem;
			margin-bottom: 1rem;
			background: #f8d7da;
			color: #721c24;
			border-radius: 8px;
			font-size: 0.95rem;
		}
	</style>
</head>
<body>
	<div class="soe-attendance-header">
		<?php if ( $logo_url ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Special Olympics Liechtenstein', 'special-olympics-extension' ); ?>" />
		<?php endif; ?>
		<h1><?php esc_html_e( 'Zugriff gesperrt', 'special-olympics-extension' ); ?></h1>
	</div>
	<div class="soe-attendance-card">
		<p class="soe-error">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: lockout duration in minutes */
					__( 'Zu viele fehlgeschlagene PIN-Versuche. Bitte warte %d Minuten und versuche es erneut.', 'special-olympics-extension' ),
					$lockout_minutes
				)
			);
			?>
		</p>
	</div>
</body>
</html>
	<?php
}

/**
 * Returns the base CSS styles used by all attendance pages.
 *
 * @return string CSS styles.
 */
function soe_attendance_get_base_styles() {
	$body_bg = 'background: #f5f5f5;';
	if ( function_exists( 'soe_get_attendance_public_body_background_css' ) ) {
		$custom = soe_get_attendance_public_body_background_css();
		if ( $custom !== '' ) {
			$body_bg = $custom;
		}
	}
	return '
		* { box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
			margin: 0;
			padding: 1.5rem 1rem 2rem;
			max-width: 520px;
			margin-left: auto;
			margin-right: auto;
			' . $body_bg . '
			color: #333;
			line-height: 1.5;
			min-height: 100vh;
		}
		.soe-attendance-header {
			text-align: center;
			margin-bottom: 1.5rem;
		}
		.soe-attendance-header img {
			max-width: 200px;
			max-height: 72px;
			width: auto;
			height: auto;
			display: block;
			margin: 0 auto 1rem;
			object-fit: contain;
		}
		.soe-attendance-header h1 {
			font-size: 1.35rem;
			font-weight: 600;
			margin: 0;
			color: #222;
		}
		.soe-attendance-card {
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.08);
			padding: 1.5rem;
			margin-bottom: 1rem;
		}
		.soe-form-group { margin-bottom: 1.25rem; }
		.soe-form-group label {
			display: block;
			font-weight: 600;
			margin-bottom: 0.35rem;
			color: #444;
			font-size: 0.95rem;
		}
		button[type="submit"] {
			padding: 0.75rem 1.75rem;
			font-size: 1rem;
			font-weight: 600;
			margin-top: 1rem;
			background: #c41e3a;
			color: #fff;
			border: none;
			border-radius: 8px;
			cursor: pointer;
			width: 100%;
			transition: background 0.2s;
		}
		button[type="submit"]:hover {
			background: #a01830;
		}
		button[type="submit"]:active {
			transform: scale(0.99);
		}
	';
}

function soe_attendance_render_page( $token, $mitglied_id ) {
	$allowed_ids = soe_attendance_get_allowed_training_ids( $mitglied_id );
	$trainings = array();
	foreach ( $allowed_ids as $tid ) {
		$t = soe_db_training_get( $tid );
		if ( $t ) {
			$trainings[ $tid ] = $t;
		}
	}

	$selected_tid = isset( $_GET['tid'] ) ? (int) $_GET['tid'] : 0;
	if ( $selected_tid && ! isset( $trainings[ $selected_tid ] ) ) {
		$selected_tid = 0;
	}
	if ( ! $selected_tid && ! empty( $trainings ) ) {
		$selected_tid = (int) array_key_first( $trainings );
	}

	$sessions = array();
	$persons = array();
	$attendance = array();
	$default_session = '';
	$today = current_time( 'Y-m-d' );

	if ( $selected_tid ) {
		$sessions = soe_db_training_get_sessions( $selected_tid );
		$persons = soe_attendance_get_person_labels( $selected_tid );
		$attendance = soe_db_training_get_attendance( $selected_tid );
		if ( ! empty( $sessions ) ) {
			if ( in_array( $today, $sessions, true ) ) {
				$default_session = $today;
			} else {
				foreach ( $sessions as $d ) {
					if ( $d >= $today ) {
						$default_session = $d;
						break;
					}
				}
				if ( $default_session === '' ) {
					$default_session = $sessions[ count( $sessions ) - 1 ];
				}
			}
		}
	}

	$session_param = isset( $_GET['session'] ) ? sanitize_text_field( wp_unslash( $_GET['session'] ) ) : $default_session;
	if ( $session_param && ! in_array( $session_param, $sessions, true ) ) {
		$session_param = $default_session;
	}
	$saved = isset( $_GET['saved'] ) && $_GET['saved'];

	$logo_url = function_exists( 'soe_get_attendance_public_logo_url' ) ? soe_get_attendance_public_logo_url() : '';
	$body_bg_inline = 'background: #f5f5f5;';
	if ( function_exists( 'soe_get_attendance_public_body_background_css' ) ) {
		$custom_bg = soe_get_attendance_public_body_background_css();
		if ( $custom_bg !== '' ) {
			$body_bg_inline = $custom_bg;
		}
	}
	$sync_nonce = wp_create_nonce( 'soe_attendance_sync_' . $token );
	$sync_url   = admin_url( 'admin-ajax.php' );
	$token_hash = hash( 'sha256', $token );

	soe_attendance_send_security_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Anwesenheit erfassen', 'special-olympics-extension' ); ?></title>
	<style>
		* { box-sizing: border-box; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
			margin: 0;
			padding: 1.5rem 1rem 2rem;
			max-width: 520px;
			margin-left: auto;
			margin-right: auto;
			<?php echo $body_bg_inline; ?>
			color: #333;
			line-height: 1.5;
			min-height: 100vh;
		}
		.soe-attendance-header {
			text-align: center;
			margin-bottom: 1.5rem;
		}
		.soe-attendance-header img {
			max-width: 200px;
			max-height: 72px;
			width: auto;
			height: auto;
			display: block;
			margin: 0 auto 1rem;
			object-fit: contain;
		}
		.soe-attendance-card {
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.08);
			padding: 1.5rem;
			margin-bottom: 1rem;
		}
		h1 {
			font-size: 1.35rem;
			font-weight: 600;
			margin: 0 0 1rem;
			color: #222;
		}
		.soe-form-group { margin-bottom: 1.25rem; }
		.soe-form-group label {
			display: block;
			font-weight: 600;
			margin-bottom: 0.35rem;
			color: #444;
			font-size: 0.95rem;
		}
		.soe-form-group select {
			width: 100%;
			padding: 0.65rem 0.75rem;
			font-size: 1rem;
			border: 1px solid #ccc;
			border-radius: 8px;
			background: #fff;
			color: #333;
		}
		.soe-form-group select:focus {
			outline: none;
			border-color: #c41e3a;
			box-shadow: 0 0 0 2px rgba(196, 30, 58, 0.15);
		}
		.soe-person-list {
			background: #fafafa;
			border-radius: 8px;
			padding: 0.5rem 0;
			margin: 1rem 0;
		}
		.soe-person-row {
			display: flex;
			align-items: center;
			padding: 0.85rem 1rem;
			gap: 0.75rem;
			border-bottom: 1px solid #eee;
		}
		.soe-person-row:last-child { border-bottom: none; }
		.soe-person-row input[type="checkbox"] {
			width: 1.55rem;
			height: 1.55rem;
			accent-color: #c41e3a;
			flex-shrink: 0;
		}
		.soe-person-row label {
			margin: 0;
			font-weight: normal;
			cursor: pointer;
			font-size: 1rem;
			display: flex;
			align-items: center;
			width: 100%;
			padding: 0.35rem 0.25rem;
			gap: 0.5rem;
			flex: 1;
		}
		.soe-person-name-wrap {
			display: flex;
			align-items: center;
			flex: 1;
			min-width: 0;
		}
		.soe-person-name-text {
			flex: 1;
			min-width: 0;
		}
		.soe-person-contact-actions {
			display: flex;
			align-items: center;
			justify-content: flex-end;
			gap: 0.25rem;
			flex-shrink: 0;
			margin-left: auto;
		}
		.soe-person-contact-actions a {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 2.25rem;
			height: 2.25rem;
			border-radius: 8px;
			color: #6b737c;
			text-decoration: none;
		}
		.soe-person-contact-actions a:hover {
			background: #f0f0f1;
			color: #c41e3a;
		}
		.soe-person-contact-actions svg {
			width: 1.15rem;
			height: 1.15rem;
			fill: currentColor;
		}
		.soe-row-sync-state {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 0.9rem;
			height: 0.9rem;
			font-size: 0.78rem;
			line-height: 1;
			color: #8c8f94;
			font-weight: 600;
			text-align: center;
			visibility: hidden;
			flex-shrink: 0;
		}
		.soe-row-sync-state.is-pending {
			visibility: visible;
		}
		.soe-row-sync-state.is-pending::before {
			content: "";
			width: 0.65rem;
			height: 0.65rem;
			border: 2px solid #c3c4c7;
			border-top-color: #d63638;
			border-radius: 50%;
			animation: soe-spin 0.8s linear infinite;
		}
		.soe-row-sync-state.is-saved {
			color: #00a32a;
			visibility: visible;
		}
		@keyframes soe-spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		.soe-section-title {
			font-size: 1rem;
			font-weight: 600;
			margin: 0.5rem 0 0.25rem;
			color: #333;
		}
		.soe-description {
			font-size: 0.875rem;
			color: #666;
			margin: 0 0 0.75rem;
		}
		button[type="submit"] {
			padding: 0.75rem 1.75rem;
			font-size: 1rem;
			font-weight: 600;
			margin-top: 1rem;
			background: #c41e3a;
			color: #fff;
			border: none;
			border-radius: 8px;
			cursor: pointer;
			width: 100%;
			transition: background 0.2s;
		}
		button[type="submit"]:hover {
			background: #a01830;
		}
		button[type="submit"]:active {
			transform: scale(0.99);
		}
		.soe-msg {
			padding: 0.75rem 1rem;
			margin-bottom: 1rem;
			background: #d4edda;
			color: #155724;
			border-radius: 8px;
			font-size: 0.95rem;
		}
		.soe-sync-status {
			padding: 0.75rem 1rem;
			margin-bottom: 1rem;
			background: #f6f7f7;
			border: 1px solid #dcdcde;
			border-radius: 8px;
			font-size: 0.92rem;
		}
		.soe-sync-status-row {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 0.75rem;
		}
		.soe-sync-badge {
			display: inline-block;
			padding: 0.2rem 0.55rem;
			border-radius: 999px;
			font-weight: 600;
			font-size: 0.8rem;
			background: #d63638;
			color: #fff;
		}
		.soe-sync-badge.is-online {
			background: #00a32a;
		}
		.soe-sync-meta {
			margin-top: 0.45rem;
			color: #50575e;
		}
		.soe-sync-error {
			margin-top: 0.45rem;
			color: #b32d2e;
			font-weight: 500;
		}
		.soe-sync-actions {
			margin-top: 0.5rem;
			display: flex;
			justify-content: flex-end;
		}
		.soe-sync-actions button {
			width: auto;
			margin-top: 0;
			padding: 0.45rem 0.9rem;
			font-size: 0.88rem;
		}
		.soe-attendance-footer {
			display: flex;
			flex-wrap: wrap;
			justify-content: center;
			align-items: center;
			gap: 0.35rem 0.75rem;
			text-align: center;
			margin-top: 1.5rem;
			padding-top: 1rem;
			border-top: 1px solid #e0e0e0;
		}
		.soe-attendance-footer-sep {
			color: #8c8f94;
			user-select: none;
		}
		.soe-attendance-footer a {
			color: #2271b1;
			text-decoration: none;
			font-size: 0.95rem;
		}
		.soe-attendance-footer a:hover {
			text-decoration: underline;
		}
	</style>
</head>
<body>
	<div class="soe-attendance-header">
		<?php if ( $logo_url ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php esc_attr_e( 'Special Olympics Liechtenstein', 'special-olympics-extension' ); ?>" />
		<?php endif; ?>
		<h1><?php esc_html_e( 'Anwesenheit erfassen', 'special-olympics-extension' ); ?></h1>
	</div>
	<div class="soe-attendance-card">
		<?php if ( $saved ) : ?>
			<p class="soe-msg"><?php esc_html_e( 'Gespeichert.', 'special-olympics-extension' ); ?></p>
		<?php endif; ?>
		<div id="soe-sync-status" class="soe-sync-status" style="display:none;">
			<div class="soe-sync-status-row">
				<strong><?php esc_html_e( 'Synchronisierung', 'special-olympics-extension' ); ?></strong>
				<span id="soe-sync-badge" class="soe-sync-badge"><?php esc_html_e( 'Offline', 'special-olympics-extension' ); ?></span>
			</div>
			<div id="soe-sync-meta" class="soe-sync-meta"><?php esc_html_e( 'Keine ausstehenden Änderungen.', 'special-olympics-extension' ); ?></div>
			<div id="soe-sync-error" class="soe-sync-error" style="display:none;"></div>
			<div class="soe-sync-actions">
				<button id="soe-sync-now" type="button"><?php esc_html_e( 'Jetzt synchronisieren', 'special-olympics-extension' ); ?></button>
			</div>
		</div>

		<?php if ( empty( $trainings ) ) : ?>
			<p><?php esc_html_e( 'Keine laufenden Trainings zugewiesen.', 'special-olympics-extension' ); ?></p>
		<div class="soe-attendance-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-telefonbuch' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Telefonbuch', 'special-olympics-extension' ); ?></a>
			<span class="soe-attendance-footer-sep" aria-hidden="true">·</span>
			<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Dashboard', 'special-olympics-extension' ); ?></a>
		</div>
	</div>
	</body>
</html>
	<?php
		return;
	endif;
	?>

		<form
			id="soe-attendance-form"
			method="post"
			action=""
			data-sync-url="<?php echo esc_url( $sync_url ); ?>"
			data-sync-nonce="<?php echo esc_attr( $sync_nonce ); ?>"
			data-token="<?php echo esc_attr( $token ); ?>"
			data-token-hash="<?php echo esc_attr( $token_hash ); ?>"
		>
		<?php wp_nonce_field( 'soe_attendance_save_' . $token, 'soe_attendance_nonce' ); ?>
		<input type="hidden" name="soe_attendance_token" value="<?php echo esc_attr( $token ); ?>" />
		<input type="hidden" name="soe_attendance_save" value="1" />
		<input type="hidden" name="training_id" value="<?php echo (int) $selected_tid; ?>" />

		<div class="soe-form-group">
			<label for="soe-training"><?php esc_html_e( 'Training', 'special-olympics-extension' ); ?></label>
			<select id="soe-training" style="margin-bottom:0;" onchange="window.location.href = '?token=<?php echo esc_attr( rawurlencode( $token ) ); ?>&tid=' + this.value;">
				<?php foreach ( $trainings as $tid => $t ) : ?>
					<option value="<?php echo (int) $tid; ?>" <?php selected( $selected_tid, $tid ); ?>><?php echo esc_html( $t['title'] . ( $t['sport_slug'] ? ' (' . $t['sport_slug'] . ')' : '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php if ( $selected_tid && ! empty( $sessions ) ) : ?>
			<div class="soe-form-group">
				<label for="soe-session"><?php esc_html_e( 'Datum', 'special-olympics-extension' ); ?></label>
				<select id="soe-session" name="session_date" required onchange="window.location.href = '?token=<?php echo esc_attr( rawurlencode( $token ) ); ?>&tid=<?php echo (int) $selected_tid; ?>&session=' + this.value;">
					<?php foreach ( $sessions as $d ) : ?>
						<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $session_param, $d ); ?>><?php echo esc_html( date_i18n( 'l, d.m.Y', strtotime( $d ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<p class="soe-section-title"><?php esc_html_e( 'Anwesend', 'special-olympics-extension' ); ?></p>
			<p class="soe-description"><?php esc_html_e( 'Bereits erfasste An- und Abwesenheiten sind vorausgefüllt.', 'special-olympics-extension' ); ?></p>
			<div class="soe-person-list">
			<?php foreach ( $persons as $person_id => $label ) :
				$attended_value = isset( $attendance[ $session_param ][ $person_id ] ) ? (int) $attendance[ $session_param ][ $person_id ] : 0;
				$checked = ( $attended_value === 1 );
				$p_tel   = function_exists( 'get_field' ) ? get_field( 'telefonnummer', $person_id ) : '';
				$p_mail  = function_exists( 'get_field' ) ? get_field( 'e-mail', $person_id ) : '';
				$p_tel   = is_string( $p_tel ) ? trim( $p_tel ) : '';
				$p_mail  = is_string( $p_mail ) ? trim( $p_mail ) : '';
				?>
				<div class="soe-person-row">
					<input type="checkbox" name="attended[<?php echo (int) $person_id; ?>]" value="1" id="att-<?php echo (int) $person_id; ?>" <?php checked( $checked ); ?> />
					<div class="soe-person-name-wrap">
						<label for="att-<?php echo (int) $person_id; ?>">
							<span class="soe-person-name-text"><?php echo esc_html( $label ); ?></span>
							<span id="soe-row-sync-<?php echo (int) $person_id; ?>" class="soe-row-sync-state" aria-live="polite"></span>
						</label>
					</div>
					<div class="soe-person-contact-actions">
						<?php if ( $p_tel !== '' ) : ?>
							<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $p_tel ) ); ?>" title="<?php esc_attr_e( 'Anrufen', 'special-olympics-extension' ); ?>" aria-label="<?php esc_attr_e( 'Telefon', 'special-olympics-extension' ); ?>">
								<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
							</a>
						<?php endif; ?>
						<?php if ( $p_mail !== '' && is_email( $p_mail ) ) : ?>
							<a href="mailto:<?php echo esc_attr( $p_mail ); ?>" title="<?php esc_attr_e( 'E-Mail', 'special-olympics-extension' ); ?>" aria-label="<?php esc_attr_e( 'E-Mail', 'special-olympics-extension' ); ?>">
								<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
			</div>

			<button type="submit" id="soe-attendance-save"><?php esc_html_e( 'Speichern', 'special-olympics-extension' ); ?></button>
		<?php elseif ( $selected_tid && empty( $sessions ) ) : ?>
			<p><?php esc_html_e( 'Keine Sessions für dieses Training.', 'special-olympics-extension' ); ?></p>
		<?php endif; ?>
		</form>

		<div class="soe-attendance-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-telefonbuch' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Telefonbuch', 'special-olympics-extension' ); ?></a>
			<span class="soe-attendance-footer-sep" aria-hidden="true">·</span>
			<a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Dashboard', 'special-olympics-extension' ); ?></a>
		</div>
	</div>
	<script>
	(function() {
		"use strict";

		var form = document.getElementById("soe-attendance-form");
		var syncStatusBox = document.getElementById("soe-sync-status");
		var badge = document.getElementById("soe-sync-badge");
		var meta = document.getElementById("soe-sync-meta");
		var errorBox = document.getElementById("soe-sync-error");
		var syncNowButton = document.getElementById("soe-sync-now");
		var manualSaveButton = document.getElementById("soe-attendance-save");
		if (!form) {
			return;
		}

		var DB_NAME = "soeAttendanceOffline";
		var DB_VERSION = 1;
		var STORE_NAME = "operations";
		var MAX_RETRY_COUNT = 8;
		var syncInProgress = false;
		var retryDelay = 2000;
		var retryTimer = null;

		var syncUrl = form.getAttribute("data-sync-url");
		var syncNonce = form.getAttribute("data-sync-nonce");
		var token = form.getAttribute("data-token");
		var tokenHash = form.getAttribute("data-token-hash");
		var localQueueKey = "soeAttendanceQueue_" + tokenHash;
		var useLocalStorageFallback = false;
		var syncConfigValid = !!(syncUrl && syncNonce && token && tokenHash);
		var currentSyncError = "";
		var rowSavedFlashUntil = {};
		var pendingVisibleSince = null;

		if (manualSaveButton) {
			manualSaveButton.style.display = "none";
		}

		function setOnlineBadge() {
			var online = navigator.onLine;
			if (!badge) {
				return;
			}
			badge.classList.toggle("is-online", online);
			badge.textContent = online ? "Online" : "Offline";
		}

		function showError(msg) {
			currentSyncError = msg || "";
			if (!errorBox) {
				return;
			}
			if (!msg) {
				errorBox.style.display = "none";
				errorBox.textContent = "";
				return;
			}
			errorBox.style.display = "block";
			errorBox.textContent = msg;
		}

		function openDb() {
			return new Promise(function(resolve, reject) {
				if (!window.indexedDB) {
					reject(new Error("indexeddb_not_available"));
					return;
				}
				var request = window.indexedDB.open(DB_NAME, DB_VERSION);
				request.onupgradeneeded = function(event) {
					var db = event.target.result;
					if (!db.objectStoreNames.contains(STORE_NAME)) {
						var store = db.createObjectStore(STORE_NAME, { keyPath: "opId" });
						store.createIndex("byTokenHash", "tokenHash", { unique: false });
						store.createIndex("byStatus", "status", { unique: false });
						store.createIndex("byCreatedAt", "createdAt", { unique: false });
					}
				};
				request.onsuccess = function() { resolve(request.result); };
				request.onerror = function() { reject(request.error); };
			});
		}

		if (!window.indexedDB) {
			useLocalStorageFallback = true;
		}

		function getLocalQueue() {
			try {
				var raw = localStorage.getItem(localQueueKey);
				var parsed = raw ? JSON.parse(raw) : [];
				return Array.isArray(parsed) ? parsed : [];
			} catch (e) {
				return [];
			}
		}

		function setLocalQueue(items) {
			try {
				localStorage.setItem(localQueueKey, JSON.stringify(Array.isArray(items) ? items : []));
				return true;
			} catch (e) {
				return false;
			}
		}

		function withStore(mode, callback) {
			return openDb().then(function(db) {
				return new Promise(function(resolve, reject) {
					var tx = db.transaction(STORE_NAME, mode);
					var store = tx.objectStore(STORE_NAME);
					var result = callback(store);
					tx.oncomplete = function() { resolve(result); };
					tx.onerror = function() { reject(tx.error); };
				});
			});
		}

		function generateOpId() {
			if (window.crypto && window.crypto.randomUUID) {
				return window.crypto.randomUUID().toLowerCase();
			}
			return "op_" + Date.now() + "_" + Math.random().toString(36).slice(2, 10);
		}

		function parsePersonId(input) {
			var match = input && input.name ? input.name.match(/^attended\[(\d+)\]$/) : null;
			return match ? parseInt(match[1], 10) : 0;
		}

		function getOperationIdentityKey(op) {
			if (!op) {
				return "";
			}
			return [
				op.tokenHash || "",
				op.trainingId || 0,
				op.sessionDate || "",
				op.personId || 0
			].join("|");
		}

		function hasUnsyncedStatus(op) {
			return !!op && (op.status === "pending" || op.status === "failed" || op.status === "syncing");
		}

		function isRetryableOperation(op) {
			return !!op && ((op.retryCount || 0) < MAX_RETRY_COUNT);
		}

		function coalesceOperationsByIdentity(ops) {
			var latestByKey = {};
			(ops || []).forEach(function(op) {
				if (!op || !op.opId) {
					return;
				}
				latestByKey[getOperationIdentityKey(op)] = op;
			});
			return Object.keys(latestByKey).map(function(key) {
				return latestByKey[key];
			}).sort(function(a, b) {
				return (a.createdAt || 0) - (b.createdAt || 0);
			});
		}

		function getAllOperationsForToken() {
			if (useLocalStorageFallback) {
				return Promise.resolve(
					getLocalQueue().filter(function(item) {
						return item && item.tokenHash === tokenHash;
					}).sort(function(a, b) {
						return (a.createdAt || 0) - (b.createdAt || 0);
					})
				);
			}
			return openDb().then(function(db) {
				return new Promise(function(resolve, reject) {
					var tx = db.transaction(STORE_NAME, "readonly");
					var store = tx.objectStore(STORE_NAME);
					var req = store.getAll();
					req.onsuccess = function() {
						resolve(req.result || []);
					};
					req.onerror = function() {
						reject(req.error || new Error("indexeddb_getall_failed"));
					};
					tx.onerror = function() {
						reject(tx.error || new Error("indexeddb_tx_failed"));
					};
				});
			}).then(function(all) {
				return (all || []).filter(function(item) {
					return item && item.tokenHash === tokenHash;
				}).sort(function(a, b) {
					return (a.createdAt || 0) - (b.createdAt || 0);
				});
			}).catch(function() {
				useLocalStorageFallback = true;
				return getLocalQueue().filter(function(item) {
					return item && item.tokenHash === tokenHash;
				}).sort(function(a, b) {
					return (a.createdAt || 0) - (b.createdAt || 0);
				});
			});
		}

		function queueOperations(ops) {
			if (!ops.length) {
				return Promise.resolve();
			}
			var normalizedOps = coalesceOperationsByIdentity(ops);
			if (!normalizedOps.length) {
				return Promise.resolve();
			}
			if (useLocalStorageFallback) {
				var existing = getLocalQueue();
				var replaceKeys = {};
				normalizedOps.forEach(function(op) {
					replaceKeys[getOperationIdentityKey(op)] = true;
				});
				var byId = {};
				existing.forEach(function(item) {
					if (item && item.opId) {
						var key = getOperationIdentityKey(item);
						if (replaceKeys[key] && hasUnsyncedStatus(item)) {
							return;
						}
						byId[item.opId] = item;
					}
				});
				normalizedOps.forEach(function(op) {
					byId[op.opId] = op;
				});
				var merged = Object.keys(byId).map(function(key) { return byId[key]; });
				if (!setLocalQueue(merged)) {
					return Promise.reject(new Error("localstorage_queue_write_failed"));
				}
				return Promise.resolve();
			}
			return openDb().then(function(db) {
				return new Promise(function(resolve, reject) {
					var tx = db.transaction(STORE_NAME, "readwrite");
					var store = tx.objectStore(STORE_NAME);
					var replaceKeys = {};
					normalizedOps.forEach(function(op) {
						replaceKeys[getOperationIdentityKey(op)] = true;
					});
					var getReq = store.getAll();
					getReq.onsuccess = function() {
						var existing = getReq.result || [];
						existing.forEach(function(item) {
							if (!item || !item.opId || item.tokenHash !== tokenHash) {
								return;
							}
							var key = getOperationIdentityKey(item);
							if (replaceKeys[key] && hasUnsyncedStatus(item)) {
								store.delete(item.opId);
							}
						});
						normalizedOps.forEach(function(op) {
							store.put(op);
						});
					};
					getReq.onerror = function() { reject(getReq.error || new Error("indexeddb_queue_getall_failed")); };
					tx.oncomplete = function() { resolve(); };
					tx.onerror = function() { reject(tx.error || new Error("indexeddb_queue_write_failed")); };
				});
			}).catch(function() {
				useLocalStorageFallback = true;
				return queueOperations(normalizedOps);
			});
		}

		function updateOperationStatus(opId, status, errorMessage) {
			if (useLocalStorageFallback) {
				var items = getLocalQueue();
				items = items.map(function(item) {
					if (!item || item.opId !== opId) {
						return item;
					}
					if (!item) {
						return item;
					}
					item.status = status;
					item.lastError = errorMessage || "";
					if (status === "pending") {
						item.retryCount = (item.retryCount || 0) + 1;
					}
					item.updatedAt = Date.now();
					return item;
				});
				setLocalQueue(items);
				return Promise.resolve();
			}
			return openDb().then(function(db) {
				return new Promise(function(resolve, reject) {
					var tx = db.transaction(STORE_NAME, "readwrite");
					var store = tx.objectStore(STORE_NAME);
					var req = store.get(opId);
					req.onsuccess = function() {
						var item = req.result;
						if (!item) {
							resolve();
							return;
						}
						item.status = status;
						item.lastError = errorMessage || "";
						if (status === "pending") {
							item.retryCount = (item.retryCount || 0) + 1;
						}
						item.updatedAt = Date.now();
						store.put(item);
					};
					req.onerror = function() { reject(req.error || new Error("indexeddb_status_get_failed")); };
					tx.oncomplete = function() { resolve(); };
					tx.onerror = function() { reject(tx.error || new Error("indexeddb_status_tx_failed")); };
				});
			}).catch(function() {
				useLocalStorageFallback = true;
				return updateOperationStatus(opId, status, errorMessage);
			});
		}

		function deleteOperation(opId) {
			if (useLocalStorageFallback) {
				var items = getLocalQueue().filter(function(item) {
					return item && item.opId !== opId;
				});
				setLocalQueue(items);
				return Promise.resolve();
			}
			return openDb().then(function(db) {
				return new Promise(function(resolve, reject) {
					var tx = db.transaction(STORE_NAME, "readwrite");
					var store = tx.objectStore(STORE_NAME);
					store.delete(opId);
					tx.oncomplete = function() { resolve(); };
					tx.onerror = function() { reject(tx.error || new Error("indexeddb_delete_failed")); };
				});
			}).catch(function() {
				useLocalStorageFallback = true;
				return deleteOperation(opId);
			});
		}

		function countPendingOps(allOps) {
			return allOps.filter(function(op) {
				return (op.status === "pending" || op.status === "syncing") && isRetryableOperation(op);
			}).length;
		}

		function countFailedOps(allOps) {
			return allOps.filter(function(op) {
				return op.status === "failed" || ((op.status === "pending" || op.status === "syncing") && !isRetryableOperation(op));
			}).length;
		}

		function hasRetryableUnsyncedOps(allOps) {
			return allOps.some(function(op) {
				return (op.status === "pending" || op.status === "failed" || op.status === "syncing") && isRetryableOperation(op);
			});
		}

		function flashSavedPerson(personId) {
			if (!personId) {
				return;
			}
			rowSavedFlashUntil[String(personId)] = Date.now() + 1500;
		}

		function getCurrentAttendanceContext() {
			var trainingInput = form.querySelector('input[name="training_id"]');
			var sessionInput = form.querySelector("#soe-session");
			return {
				trainingId: trainingInput ? parseInt(trainingInput.value || "0", 10) : 0,
				sessionDate: sessionInput ? (sessionInput.value || "") : ""
			};
		}

		function buildUnsyncedPersonMap(allOps, context) {
			var unsyncedByPerson = {};
			var currentTrainingId = context && context.trainingId ? context.trainingId : 0;
			var currentSessionDate = context && context.sessionDate ? context.sessionDate : "";
			allOps.forEach(function(op) {
				if (!op || !op.personId) {
					return;
				}
				if (currentTrainingId && parseInt(op.trainingId || 0, 10) !== currentTrainingId) {
					return;
				}
				if (currentSessionDate && (op.sessionDate || "") !== currentSessionDate) {
					return;
				}
				// Row spinner indicates in-flight sync only.
				if (op.status === "pending" || op.status === "syncing") {
					unsyncedByPerson[String(op.personId)] = true;
				}
			});
			return unsyncedByPerson;
		}

		function updatePersonSyncIndicators(unsyncedByPerson) {
			var now = Date.now();
			Object.keys(rowSavedFlashUntil).forEach(function(key) {
				if (rowSavedFlashUntil[key] <= now) {
					delete rowSavedFlashUntil[key];
				}
			});
			var indicators = form.querySelectorAll(".soe-row-sync-state");
			indicators.forEach(function(indicator) {
				var id = indicator.id || "";
				var personId = id.replace("soe-row-sync-", "");
				var hasUnsynced = !!unsyncedByPerson[personId];
				if (hasUnsynced) {
					indicator.classList.remove("is-saved");
					indicator.classList.add("is-pending");
					indicator.textContent = "";
					indicator.setAttribute("title", "Noch nicht synchronisiert");
					return;
				}
				indicator.classList.remove("is-pending");
				indicator.removeAttribute("title");
				if (rowSavedFlashUntil[personId]) {
					indicator.classList.remove("is-saved");
					indicator.classList.add("is-saved");
					indicator.textContent = "✓";
					return;
				}
				indicator.classList.remove("is-saved");
				indicator.textContent = "";
			});
		}

		function updateSyncPanel(pending, failed, lastSync) {
			var now = Date.now();
			if (pending > 0) {
				if (pendingVisibleSince === null) {
					pendingVisibleSince = now;
				}
			} else {
				pendingVisibleSince = null;
			}
			var hasUnsynced = pending > 0 || failed > 0;
			var pendingVisible = pending > 0 && pendingVisibleSince !== null && (now - pendingVisibleSince >= 1500);
			var shouldShow = !navigator.onLine || failed > 0 || pendingVisible || !!currentSyncError;
			if (syncStatusBox) {
				syncStatusBox.style.display = shouldShow ? "block" : "none";
			}
			if (syncNowButton) {
				syncNowButton.style.display = hasUnsynced ? "inline-block" : "none";
			}
			if (!meta) {
				return;
			}
			var parts = [];
			if (pending > 0) {
				parts.push(pending + " ausstehend");
			}
			if (failed > 0) {
				parts.push(failed + " fehlgeschlagen");
			}
			if (parts.length === 0) {
				parts.push(navigator.onLine ? "Keine ausstehenden Änderungen" : "Offline, keine ausstehenden Änderungen");
			}
			if (lastSync) {
				parts.push("Letzter Sync: " + lastSync);
			}
			meta.textContent = parts.join(" | ");
		}

		function refreshMeta() {
			return getAllOperationsForToken().then(function(allOps) {
				var pending = countPendingOps(allOps);
				var failed = countFailedOps(allOps);
				var lastSync = localStorage.getItem("soeAttendanceLastSyncAt") || "";
				var context = getCurrentAttendanceContext();
				var unsyncedByPerson = buildUnsyncedPersonMap(allOps, context);
				updatePersonSyncIndicators(unsyncedByPerson);
				updateSyncPanel(pending, failed, lastSync);
				return {
					pending: pending,
					failed: failed,
					unsyncedByPerson: unsyncedByPerson
				};
			});
		}

		function postOperationsToSync(ops) {
			if (!syncConfigValid) {
				return Promise.reject(new Error("sync_config_missing"));
			}
			var payload = new URLSearchParams();
			payload.append("action", "soe_attendance_sync");
			payload.append("token", token);
			payload.append("nonce", syncNonce);
			payload.append("operations", JSON.stringify(ops.map(function(op) {
				return {
					opId: op.opId,
					tokenHash: op.tokenHash,
					trainingId: op.trainingId,
					sessionDate: op.sessionDate,
					personId: op.personId,
					attended: op.attended,
					clientTimestamp: op.clientTimestamp
				};
			})));
			return fetch(syncUrl, {
				method: "POST",
				headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
				body: payload.toString(),
				credentials: "same-origin"
			}).then(function(response) {
				return response.json()
					.catch(function() { return {}; })
					.then(function(json) {
						return { ok: response.ok, json: json || {} };
					});
			});
		}

		function scheduleRetry() {
			if (retryTimer) {
				return;
			}
			retryTimer = window.setTimeout(function() {
				retryTimer = null;
				syncQueue();
			}, retryDelay);
			retryDelay = Math.min(retryDelay * 2, 30000);
		}

		function resetRetry() {
			retryDelay = 2000;
			if (retryTimer) {
				clearTimeout(retryTimer);
				retryTimer = null;
			}
		}

		function syncQueue() {
			if (syncInProgress) {
				return Promise.resolve(false);
			}
			syncInProgress = true;
			showError("");
			return getAllOperationsForToken()
				.then(function(allOps) {
					var retryableOps = allOps.filter(function(op) {
						return (op.status === "pending" || op.status === "failed") && isRetryableOperation(op);
					});
					var ops = coalesceOperationsByIdentity(retryableOps).slice(0, 200);
					if (!ops.length) {
						localStorage.setItem("soeAttendanceLastSyncAt", new Date().toLocaleString());
						resetRetry();
						return false;
					}
					return queueOperations(ops.map(function(op) {
						op.status = "syncing";
						op.updatedAt = Date.now();
						return op;
					})).then(function() {
						return postOperationsToSync(ops);
					});
				})
				.then(function(result) {
					if (!result) {
						return;
					}
					var json = result.json || {};
					if (!result.ok || !json.success) {
						var code = json && json.data && json.data.code ? json.data.code : "";
						var message = json && json.data && json.data.message ? json.data.message : "Synchronisierung fehlgeschlagen.";
						if (code === "auth_required" || code === "token_expired") {
							showError(message + " Bitte Seite neu laden und PIN erneut eingeben.");
						} else {
							showError(message);
						}
						return getAllOperationsForToken().then(function(allOps) {
							var syncingOps = allOps.filter(function(op) { return op.status === "syncing"; });
							return Promise.all(syncingOps.map(function(op) {
								return updateOperationStatus(op.opId, "pending", message);
							}));
						});
					}

					var results = json.data && Array.isArray(json.data.results) ? json.data.results : [];
					var actionPromises = [];
					var permanentReasons = {
						invalid_operation: true,
						invalid_training: true,
						forbidden_training: true,
						invalid_session_date: true,
						invalid_person: true,
						payload_too_large: true
					};
					results.forEach(function(item) {
						if (!item || !item.opId) {
							return;
						}
						if (item.status === "applied" || item.status === "duplicate") {
							actionPromises.push(deleteOperation(item.opId));
						} else if (item.reason && permanentReasons[item.reason]) {
							// Drop non-retryable operations to avoid stale pending indicators.
							actionPromises.push(deleteOperation(item.opId));
						} else {
							actionPromises.push(updateOperationStatus(item.opId, "failed", item.reason || "rejected"));
						}
					});

					return Promise.all(actionPromises).then(function() {
						localStorage.setItem("soeAttendanceLastSyncAt", new Date().toLocaleString());
						resetRetry();
						return getAllOperationsForToken().then(function(allOps) {
							var stillPending = hasRetryableUnsyncedOps(allOps);
							if (stillPending) {
								window.setTimeout(function() {
									syncQueue();
								}, 1000);
							}
						});
					});
				})
				.catch(function() {
					return getAllOperationsForToken().then(function(allOps) {
						var syncingOps = allOps.filter(function(op) { return op.status === "syncing"; });
						return Promise.all(syncingOps.map(function(op) {
							return updateOperationStatus(op.opId, "pending", "network_error");
						})).then(function() {
							showError("Keine Verbindung zum Server. Änderungen bleiben lokal gespeichert.");
						});
					});
				})
				.finally(function() {
					syncInProgress = false;
					setOnlineBadge();
					refreshMeta();
				});
		}

		function buildOperationFromCheckbox(cb) {
			var trainingInput = form.querySelector('input[name="training_id"]');
			var sessionInput = form.querySelector("#soe-session");
			var personId = parsePersonId(cb);
			if (!trainingInput || !sessionInput || !personId) {
				return null;
			}
			return {
				opId: generateOpId(),
				tokenHash: tokenHash,
				trainingId: parseInt(trainingInput.value || "0", 10),
				sessionDate: sessionInput.value || "",
				personId: personId,
				attended: cb.checked ? 1 : 0,
				clientTimestamp: new Date().toISOString(),
				status: "pending",
				retryCount: 0,
				lastError: "",
				createdAt: Date.now(),
				updatedAt: Date.now()
			};
		}

		function persistCheckboxChange(cb) {
			showError("");
			var operation = buildOperationFromCheckbox(cb);
			if (!operation) {
				showError("Speichern fehlgeschlagen. Bitte Seite neu laden.");
				return;
			}
			queueOperations([operation])
				.then(function() {
					return refreshMeta();
				})
				.then(function() {
					return syncQueue();
				})
				.then(function() {
					return refreshMeta();
				})
				.then(function(state) {
					if (!state.unsyncedByPerson[String(operation.personId)]) {
						flashSavedPerson(operation.personId);
						return refreshMeta();
					}
					return state;
				})
				.catch(function(err) {
					console.error("SOE autosave failed", err);
					showError("Keine Verbindung zum Server. Änderungen bleiben lokal gespeichert.");
					refreshMeta();
				});
		}

		form.addEventListener("submit", function(event) {
			// With JS active, attendance is saved per checkbox change.
			event.preventDefault();
		});

		var personCheckboxes = form.querySelectorAll('.soe-person-list input[type="checkbox"][name^="attended["]');
		personCheckboxes.forEach(function(cb) {
			cb.addEventListener("change", function() {
				persistCheckboxChange(cb);
			});
		});

		if (syncNowButton) {
			syncNowButton.addEventListener("click", function() {
				syncQueue();
			});
		}

		window.addEventListener("online", function() {
			setOnlineBadge();
			showError("");
			syncQueue();
		});
		window.addEventListener("offline", function() {
			setOnlineBadge();
			refreshMeta();
		});

		setOnlineBadge();
		if (!syncConfigValid) {
			if (syncStatusBox) {
				syncStatusBox.style.display = "block";
			}
			showError("Synchronisierung ist nicht korrekt konfiguriert. Bitte Seite neu laden.");
			return;
		}
		refreshMeta().then(function() {
			syncQueue();
		});
	})();
	</script>
</body>
</html>
	<?php
}
