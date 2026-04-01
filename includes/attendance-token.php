<?php
/**
 * Token-based attendance URL: one token per user (Hauptleiter/Leiter/Admin).
 * Token stored in user meta; used for public attendance page without login.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOE_ATTENDANCE_TOKEN_META', 'soe_attendance_token' );
define( 'SOE_ATTENDANCE_TOKEN_CREATED_META', 'soe_attendance_token_created' );
define( 'SOE_ATTENDANCE_PIN_META', 'soe_attendance_pin' );
define( 'SOE_ATTENDANCE_TOKEN_ENCRYPTED_META', 'soe_attendance_token_encrypted' );

/**
 * Whether a value looks like a SHA-256 hex hash.
 *
 * @param string $value Candidate value.
 * @return bool
 */
function soe_attendance_is_token_hash( $value ) {
	return is_string( $value ) && (bool) preg_match( '/^[a-f0-9]{64}$/', $value );
}

/**
 * Creates a stable hash for attendance token lookup.
 *
 * @param string $token Raw token.
 * @return string
 */
function soe_attendance_hash_token( $token ) {
	return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
}

/**
 * Encrypts a token for reversible storage.
 *
 * @param string $token Raw token.
 * @return string Encrypted token blob or empty string on failure.
 */
function soe_attendance_encrypt_token_for_storage( $token ) {
	if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_cipher_iv_length' ) || ! function_exists( 'random_bytes' ) ) {
		return '';
	}
	$key = hash( 'sha256', wp_salt( 'auth' ), true );
	$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
	if ( ! is_int( $iv_length ) || $iv_length <= 0 ) {
		return '';
	}
	try {
		$iv = random_bytes( $iv_length );
	} catch ( Exception $e ) {
		return '';
	}
	$ciphertext = openssl_encrypt( (string) $token, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
	if ( ! is_string( $ciphertext ) || $ciphertext === '' ) {
		return '';
	}
	$mac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
	return base64_encode( $iv . $mac . $ciphertext );
}

/**
 * Decrypts a stored token blob.
 *
 * @param string $payload Encrypted token blob.
 * @return string Decrypted token or empty string on failure.
 */
function soe_attendance_decrypt_token_from_storage( $payload ) {
	if ( ! is_string( $payload ) || $payload === '' || ! function_exists( 'openssl_decrypt' ) || ! function_exists( 'openssl_cipher_iv_length' ) ) {
		return '';
	}
	$raw = base64_decode( $payload, true );
	if ( ! is_string( $raw ) || $raw === '' ) {
		return '';
	}
	$key = hash( 'sha256', wp_salt( 'auth' ), true );
	$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
	if ( ! is_int( $iv_length ) || $iv_length <= 0 ) {
		return '';
	}
	$mac_length = 32;
	if ( strlen( $raw ) <= ( $iv_length + $mac_length ) ) {
		return '';
	}
	$iv = substr( $raw, 0, $iv_length );
	$mac = substr( $raw, $iv_length, $mac_length );
	$ciphertext = substr( $raw, $iv_length + $mac_length );
	$expected_mac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
	if ( ! hash_equals( $expected_mac, $mac ) ) {
		return '';
	}
	$token = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
	return is_string( $token ) ? $token : '';
}

/**
 * Migrates a legacy plaintext token to hashed lookup plus encrypted storage.
 *
 * @param int    $user_id User ID.
 * @param string $token   Legacy plaintext token.
 * @return bool
 */
function soe_attendance_migrate_user_token_to_hashed( $user_id, $token ) {
	$user_id = (int) $user_id;
	$token = is_string( $token ) ? $token : '';
	if ( ! $user_id || strlen( $token ) < 24 ) {
		return false;
	}
	$encrypted_token = soe_attendance_encrypt_token_for_storage( $token );
	if ( $encrypted_token === '' ) {
		return false;
	}
	update_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_META, soe_attendance_hash_token( $token ) );
	update_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_ENCRYPTED_META, $encrypted_token );
	$created = get_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META, true );
	if ( ! $created ) {
		update_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META, time() );
	}
	return true;
}

/**
 * Gets or creates the attendance token for a user. Creates on first use.
 * Also stores creation timestamp for expiry check.
 *
 * @param int $user_id WordPress user ID.
 * @return string Token string, or empty if user may not have a token.
 */
function soe_attendance_get_or_create_token( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id || ! soe_attendance_can_user_have_token( $user_id ) ) {
		return '';
	}
	$stored_token = get_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_META, true );
	$encrypted_token = get_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_ENCRYPTED_META, true );

	if ( soe_attendance_is_token_hash( $stored_token ) ) {
		$token = soe_attendance_decrypt_token_from_storage( $encrypted_token );
		if ( is_string( $token ) && strlen( $token ) >= 24 && hash_equals( $stored_token, soe_attendance_hash_token( $token ) ) ) {
			$created = get_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META, true );
			if ( ! $created ) {
				update_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META, time() );
			}
			return $token;
		}
	}

	if ( is_string( $stored_token ) && strlen( $stored_token ) >= 24 && ! soe_attendance_is_token_hash( $stored_token ) ) {
		if ( soe_attendance_migrate_user_token_to_hashed( $user_id, $stored_token ) ) {
			return $stored_token;
		}
		// Fallback when crypto is unavailable: keep legacy behavior.
		$created = get_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META, true );
		if ( ! $created ) {
			update_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META, time() );
		}
		return $stored_token;
	}

	$token = wp_generate_password( 32, true );
	$encrypted_new = soe_attendance_encrypt_token_for_storage( $token );
	if ( $encrypted_new !== '' ) {
		update_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_META, soe_attendance_hash_token( $token ) );
		update_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_ENCRYPTED_META, $encrypted_new );
	} else {
		// Fallback only for environments without crypto support.
		update_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_META, $token );
	}
	update_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META, time() );
	return $token;
}

/**
 * Finds the WordPress user ID for a given token.
 *
 * @param string $token Token string from URL.
 * @return int|null User ID or null if invalid.
 */
function soe_attendance_get_user_by_token( $token ) {
	if ( ! is_string( $token ) || strlen( $token ) < 24 ) {
		return null;
	}
	$token_hash = soe_attendance_hash_token( $token );
	$users = get_users( array(
		'meta_key'   => SOE_ATTENDANCE_TOKEN_META,
		'meta_value' => $token_hash,
		'number'     => 1,
		'fields'     => 'ID',
	) );
	if ( ! empty( $users ) ) {
		return (int) $users[0];
	}
	// Legacy fallback for plaintext tokens; migrate on read when possible.
	$users = get_users( array(
		'meta_key'   => SOE_ATTENDANCE_TOKEN_META,
		'meta_value' => $token,
		'number'     => 1,
		'fields'     => 'ID',
	) );
	if ( empty( $users ) ) {
		return null;
	}
	$user_id = (int) $users[0];
	soe_attendance_migrate_user_token_to_hashed( $user_id, $token );
	return $user_id;
}

/**
 * Gets the mitglied (post) ID for the user that owns the token.
 *
 * @param string $token Token string from URL.
 * @return int Mitglied post ID or 0.
 */
function soe_attendance_get_mitglied_id_for_token( $token ) {
	$user_id = soe_attendance_get_user_by_token( $token );
	if ( ! $user_id ) {
		return 0;
	}
	$mitglied_posts = get_posts( array(
		'post_type'      => 'mitglied',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => 'user_id',
		'meta_value'     => $user_id,
		'orderby'        => 'date',
		'order'          => 'ASC',
	) );
	return empty( $mitglied_posts ) ? 0 : (int) $mitglied_posts[0];
}

/**
 * Whether the user is allowed to have an attendance token (Hauptleiter, Leiter, or Admin).
 *
 * @param int $user_id WordPress user ID.
 * @return bool
 */
function soe_attendance_can_user_have_token( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return true;
	}
	$roles = (array) $user->roles;
	if ( in_array( SOE_ROLE_HAUPTLEITER_IN, $roles, true ) || in_array( SOE_ROLE_LEITER_IN, $roles, true ) ) {
		$mitglied_posts = get_posts( array(
			'post_type'      => 'mitglied',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => 'user_id',
			'meta_value'     => $user_id,
		) );
		return ! empty( $mitglied_posts );
	}
	return false;
}

/**
 * Checks if the user's attendance token is expired (older than 1 year).
 *
 * @param int $user_id WordPress user ID.
 * @return bool True if expired, false otherwise.
 */
function soe_attendance_is_token_expired( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return true;
	}
	$created = get_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META, true );
	if ( ! $created ) {
		return false; // No timestamp = legacy token, treat as valid.
	}
	return ( time() - (int) $created ) > YEAR_IN_SECONDS;
}

/**
 * Gets the token expiry date for a user.
 *
 * @param int $user_id WordPress user ID.
 * @return int Unix timestamp of expiry, or 0 if no token.
 */
function soe_attendance_get_token_expiry( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return 0;
	}
	$created = get_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META, true );
	if ( ! $created ) {
		return 0;
	}
	return (int) $created + YEAR_IN_SECONDS;
}

/**
 * Regenerates the attendance token for a user (invalidates the old one).
 *
 * @param int $user_id WordPress user ID.
 * @return string New token string, or empty if user may not have a token.
 */
function soe_attendance_regenerate_token( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id || ! soe_attendance_can_user_have_token( $user_id ) ) {
		return '';
	}
	delete_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_META );
	delete_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_CREATED_META );
	delete_user_meta( $user_id, SOE_ATTENDANCE_TOKEN_ENCRYPTED_META );
	return soe_attendance_get_or_create_token( $user_id );
}

/**
 * Sets the PIN for attendance access. PIN must be 4-6 digits.
 *
 * @param int    $user_id WordPress user ID.
 * @param string $pin     PIN string (4-6 digits).
 * @return bool True on success, false on invalid PIN.
 */
function soe_attendance_set_pin( $user_id, $pin ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}
	if ( ! preg_match( '/^\d{4,6}$/', $pin ) ) {
		return false;
	}
	$hash = wp_hash_password( $pin );
	update_user_meta( $user_id, SOE_ATTENDANCE_PIN_META, $hash );
	return true;
}

/**
 * Verifies the PIN for attendance access.
 *
 * @param int    $user_id WordPress user ID.
 * @param string $pin     PIN string to verify.
 * @return bool True if PIN is correct, false otherwise.
 */
function soe_attendance_verify_pin( $user_id, $pin ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}
	$hash = get_user_meta( $user_id, SOE_ATTENDANCE_PIN_META, true );
	if ( ! $hash ) {
		return false;
	}
	return wp_check_password( $pin, $hash );
}

/**
 * Checks if the user has set a PIN for attendance access.
 *
 * @param int $user_id WordPress user ID.
 * @return bool True if PIN is set, false otherwise.
 */
function soe_attendance_has_pin( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return false;
	}
	$hash = get_user_meta( $user_id, SOE_ATTENDANCE_PIN_META, true );
	return ! empty( $hash );
}
