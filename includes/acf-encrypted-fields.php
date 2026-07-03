<?php
/**
 * ACF Encrypted Fields (AES-256-GCM) – integrated into Special Olympics Extension.
 *
 * Adds "Encrypt value in database" checkbox to ACF fields and stores values
 * encrypted (AES-256-GCM) in the database. Decrypts transparently on output.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ACF_Encrypted_Fields' ) ) :

final class ACF_Encrypted_Fields {

	/** @var ACF_Encrypted_Fields */
	private static $instance = null;

	/** @var string OpenSSL Cipher */
	private $cipher = 'aes-256-gcm';

	/** @var string 32-byte bin key */
	private $key;

	/** Nicht-geeignete Feldtypen (IDs/Beziehungen/Dateien), hier KEINE Verschlüsselungs-Option anzeigen */
	private $disallowed_types = array(
		'image', 'file', 'gallery', 'relationship', 'post_object', 'page_link', 'link', 'user', 'taxonomy',
		'google_map', 'wysiwyg', 'oembed', 'message', 'accordion', 'tab', 'flexible_content', 'repeater',
	);

	/** Singleton */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_notices', array( $this, 'admin_requirements_notice' ) );

		$this->key = $this->derive_key();

		add_action( 'acf/render_field_settings', array( $this, 'render_field_setting' ), 20 );
		add_filter( 'acf/update_value', array( $this, 'filter_update_value' ), 10, 3 );
		// Priority 5: decrypt before soe_acf_checkbox_load_value_normalize (priority 10) wraps ENC strings into arrays.
		add_filter( 'acf/load_value', array( $this, 'filter_load_value' ), 5, 3 );
		add_filter( 'acf/load_value/type=checkbox', array( $this, 'filter_load_value' ), 5, 3 );
		add_filter( 'acf/format_value', array( $this, 'filter_format_value' ), 5, 3 );
		add_filter( 'acf/format_value/type=checkbox', array( $this, 'filter_format_value' ), 5, 3 );

		add_action( 'init', function () {
			if ( ! function_exists( 'acfenc_encrypt_value' ) ) {
				function acfenc_encrypt_value( $value ) {
					return ACF_Encrypted_Fields::instance()->encrypt_value( $value );
				}
			}
			if ( ! function_exists( 'acfenc_decrypt_value' ) ) {
				function acfenc_decrypt_value( $value ) {
					return ACF_Encrypted_Fields::instance()->decrypt_value( $value );
				}
			}
		} );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'acfenc', array( $this, 'cli_command' ) );
		}
	}

	/** Admin-Hinweise für Anforderungen */
	public function admin_requirements_notice() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! function_exists( 'acf' ) ) {
			echo '<div class="notice notice-error"><p><strong>ACF Encrypted Fields:</strong> Advanced Custom Fields ist nicht aktiv.</p></div>';
		}
		if ( ! extension_loaded( 'openssl' ) ) {
			echo '<div class="notice notice-error"><p><strong>ACF Encrypted Fields:</strong> Die PHP-OpenSSL-Extension ist erforderlich.</p></div>';
		}
		if ( ! defined( 'ACF_ENCRYPTION_KEY' ) ) {
			echo '<div class="notice notice-warning"><p><strong>ACF Encrypted Fields:</strong> Es wurde keine feste <code>ACF_ENCRYPTION_KEY</code>-Konstante gesetzt. '
				. 'Es wird ein Schlüssel aus WordPress-Salts abgeleitet. Für stabile Backups/Migrationen wird eine feste Konstante empfohlen.</p></div>';
		}
	}

	/** Stabilen 32-Byte-Key ableiten (HKDF-ähnlich aus WP-Salts, oder feste Konstante verwenden) */
	private function derive_key() {
		if ( defined( 'ACF_ENCRYPTION_KEY' ) && ACF_ENCRYPTION_KEY ) {
			return hash( 'sha256', (string) ACF_ENCRYPTION_KEY, true );
		}
		if ( ! function_exists( 'wp_salt' ) ) {
			return hash( 'sha256', 'acfenc-fallback-' . ( defined( 'DB_NAME' ) ? DB_NAME : 'wp' ), true );
		}
		$base = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' );
		$prk  = hash_hmac( 'sha256', $base, 'acfenc-hkdf-salt', true );
		return hash_hmac( 'sha256', 'acfenc-key-material', $prk, true );
	}

	/** Checkbox im ACF-Feldeditor rendern */
	public function render_field_setting( $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : '';
		if ( in_array( $type, $this->disallowed_types, true ) ) {
			return;
		}

		$instructions = __( 'Speichert den Feldwert verschlüsselt (AES-256-GCM) in der Datenbank. Ausgabe wird automatisch entschlüsselt.', 'special-olympics-extension' );
		if ( $this->field_is_repeater_sub_field( $field ) ) {
			$instructions .= ' ' . __( 'Bei Repeater-Subfeldern wird jede Zeile einzeln verschlüsselt; die Zeilenanzahl bleibt unverschlüsselt.', 'special-olympics-extension' );
		}

		acf_render_field_setting( $field, array(
			'label'         => __( 'Encrypt value in database', 'special-olympics-extension' ),
			'instructions'  => $instructions,
			'name'          => 'acfenc_encrypt',
			'type'          => 'true_false',
			'ui'            => 1,
			'default_value' => 0,
		) );
	}

	/**
	 * Whether the field is a sub-field of an ACF repeater.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 * @return bool
	 */
	private function field_is_repeater_sub_field( $field ) {
		if ( ! is_array( $field ) ) {
			return false;
		}
		if ( ! empty( $field['parent_repeater'] ) ) {
			return true;
		}
		if ( empty( $field['parent'] ) || ! function_exists( 'acf_get_field' ) ) {
			return false;
		}
		$parent = acf_get_field( $field['parent'] );
		return is_array( $parent ) && isset( $parent['type'] ) && $parent['type'] === 'repeater';
	}

	/**
	 * Returns the parent repeater field for a repeater sub-field.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 * @return array<string, mixed>|null
	 */
	private function get_repeater_parent_field( $field ) {
		if ( ! is_array( $field ) || ! function_exists( 'acf_get_field' ) ) {
			return null;
		}
		$parent_key = ! empty( $field['parent_repeater'] ) ? $field['parent_repeater'] : ( $field['parent'] ?? '' );
		if ( ! $parent_key ) {
			return null;
		}
		$parent = acf_get_field( $parent_key );
		if ( ! is_array( $parent ) || ( $parent['type'] ?? '' ) !== 'repeater' ) {
			return null;
		}
		return $parent;
	}

	/**
	 * Whether the field is a sub-field of an ACF group.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 * @return bool
	 */
	private function field_is_group_sub_field( $field ) {
		if ( ! is_array( $field ) || empty( $field['parent'] ) || ! function_exists( 'acf_get_field' ) ) {
			return false;
		}
		$parent = acf_get_field( $field['parent'] );
		return is_array( $parent ) && isset( $parent['type'] ) && $parent['type'] === 'group';
	}

	/**
	 * Returns the parent group field for a group sub-field.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 * @return array<string, mixed>|null
	 */
	private function get_group_parent_field( $field ) {
		if ( ! is_array( $field ) || empty( $field['parent'] ) || ! function_exists( 'acf_get_field' ) ) {
			return null;
		}
		$parent = acf_get_field( $field['parent'] );
		if ( ! is_array( $parent ) || ( $parent['type'] ?? '' ) !== 'group' ) {
			return null;
		}
		return $parent;
	}

	/**
	 * Resolves the post meta key used to store a group sub-field value.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 * @return string|null
	 */
	private function get_group_sub_field_meta_key( $field ) {
		$parent = $this->get_group_parent_field( $field );
		if ( ! $parent || empty( $field['name'] ) || empty( $parent['name'] ) ) {
			return null;
		}
		return $parent['name'] . '_' . $field['name'];
	}

	/**
	 * Validates a repeater row meta key for a given sub-field.
	 *
	 * @param string $meta_key      Post meta key.
	 * @param string $repeater_name Parent repeater field name.
	 * @param string $sub_name      Sub-field name.
	 * @return bool
	 */
	private function is_repeater_sub_field_meta_key( $meta_key, $repeater_name, $sub_name ) {
		if ( ! is_string( $meta_key ) || $meta_key === '' || $meta_key[0] === '_' ) {
			return false;
		}
		$pattern = '/^' . preg_quote( (string) $repeater_name, '/' ) . '_\d+_' . preg_quote( (string) $sub_name, '/' ) . '$/';
		return (bool) preg_match( $pattern, $meta_key );
	}

	/**
	 * Ensures encrypt flag and field type are read from the registered ACF field definition.
	 *
	 * @param array<string, mixed> $field ACF field array.
	 * @return array<string, mixed>
	 */
	private function normalize_field_definition( $field ) {
		if ( ! is_array( $field ) || ! function_exists( 'acf_get_field' ) ) {
			return is_array( $field ) ? $field : array();
		}

		$registered = null;
		if ( ! empty( $field['key'] ) ) {
			$registered = acf_get_field( $field['key'] );
		}
		if ( ! is_array( $registered ) && ! empty( $field['name'] ) ) {
			$registered = acf_get_field( $field['name'] );
		}
		if ( is_array( $registered ) ) {
			if ( isset( $registered['acfenc_encrypt'] ) ) {
				$field['acfenc_encrypt'] = $registered['acfenc_encrypt'];
			}
			if ( ! empty( $registered['type'] ) ) {
				$field['type'] = $registered['type'];
			}
		}

		return $field;
	}

	/** Soll ein Feld verschlüsselt werden? */
	private function should_encrypt( $field ) {
		$field = $this->normalize_field_definition( $field );
		$flag = ! empty( $field['acfenc_encrypt'] );
		return (bool) apply_filters( 'acfenc/should_encrypt', $flag, $field );
	}

	/** Marker prüfen */
	private function is_encrypted( $value ) {
		return is_string( $value ) && substr( $value, 0, 6 ) === 'ENCv1:';
	}

	private function to_string( $value ) {
		return $this->value_to_storage_string( $value );
	}

	/**
	 * Converts a field value to the plaintext string stored inside ENCv1 payloads.
	 *
	 * Checkbox values are always JSON arrays (never PHP-serialized) for reliable decryption.
	 *
	 * @param mixed $value Raw field value.
	 * @return string
	 */
	private function value_to_storage_string( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return (string) json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		if ( is_string( $value ) && function_exists( 'is_serialized' ) && is_serialized( $value ) ) {
			$unserialized = maybe_unserialize( $value );
			if ( is_array( $unserialized ) ) {
				return (string) json_encode( $unserialized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			}
		}
		return (string) $value;
	}

	private function from_string( $raw, $field = array() ) {
		if ( ! is_string( $raw ) ) {
			return $raw;
		}

		$decoded = json_decode( $raw, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return $this->coerce_decrypted_value( $decoded, $field );
		}

		if ( function_exists( 'is_serialized' ) && is_serialized( $raw ) ) {
			$unserialized = maybe_unserialize( $raw );
			if ( $unserialized !== false || $raw === 'b:0;' ) {
				return $this->coerce_decrypted_value( $unserialized, $field );
			}
		}

		return $this->coerce_decrypted_value( $raw, $field );
	}

	/**
	 * Normalizes decrypted values to the type ACF expects per field.
	 *
	 * @param mixed                $value Decrypted value.
	 * @param array<string, mixed> $field ACF field array.
	 * @return mixed
	 */
	private function coerce_decrypted_value( $value, $field ) {
		$field = $this->normalize_field_definition( $field );
		$type  = isset( $field['type'] ) ? (string) $field['type'] : '';

		if ( $type === 'checkbox' ) {
			if ( ! is_array( $value ) ) {
				if ( is_string( $value ) && $value !== '' && ! $this->is_encrypted( $value ) ) {
					return array( $value );
				}
				return array();
			}

			$clean = array();
			foreach ( $value as $item ) {
				if ( ! is_string( $item ) || $item === '' || $this->is_encrypted( $item ) ) {
					continue;
				}
				$clean[] = $item;
			}

			return array_values( array_unique( $clean ) );
		}

		return $value;
	}

	/**
	 * Decrypts an ENCv1 payload and returns a value typed for the given ACF field.
	 *
	 * @param string               $encrypted ENCv1 string.
	 * @param array<string, mixed> $field     ACF field array.
	 * @return mixed
	 */
	private function decrypt_for_field( $encrypted, $field ) {
		if ( ! is_string( $encrypted ) || ! $this->is_encrypted( $encrypted ) ) {
			return $encrypted;
		}
		$pt = $this->decrypt_string( $encrypted );
		if ( ! is_string( $pt ) || $this->is_encrypted( $pt ) ) {
			return $this->coerce_decrypted_value( $encrypted, $field );
		}
		return $this->from_string( $pt, $field );
	}

	/** AES-256-GCM Verschlüsselung */
	private function encrypt_string( $plaintext ) {
		if ( $plaintext === '' ) {
			return $plaintext;
		}
		if ( ! extension_loaded( 'openssl' ) ) {
			return false;
		}

		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( $ct === false ) {
			return false;
		}

		$payload = base64_encode( $iv . $tag . $ct );
		return 'ENCv1:' . $payload;
	}

	/** AES-256-GCM Entschlüsselung */
	private function decrypt_string( $encrypted ) {
		if ( ! $this->is_encrypted( $encrypted ) ) {
			return $encrypted;
		}
		if ( ! extension_loaded( 'openssl' ) ) {
			return $encrypted;
		}

		$b64 = substr( $encrypted, 6 );
		$raw = base64_decode( $b64, true );
		if ( $raw === false || strlen( $raw ) < ( 12 + 16 + 1 ) ) {
			return $encrypted;
		}
		$iv  = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$ct  = substr( $raw, 28 );

		$pt = openssl_decrypt( $ct, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag );
		return ( $pt === false ) ? $encrypted : $pt;
	}

	public function encrypt_value( $value ) {
		$raw = $this->to_string( $value );
		$enc = $this->encrypt_string( $raw );
		return ( $enc === false ) ? $value : $enc;
	}

	public function decrypt_value( $value ) {
		if ( is_string( $value ) && $this->is_encrypted( $value ) ) {
			$pt = $this->decrypt_string( $value );
			return $this->from_string( (string) $pt, array() );
		}
		return $value;
	}

	/**
	 * Public decrypt helper with field-aware coercion (checkbox arrays, etc.).
	 *
	 * @param mixed                $value Field value (possibly ENCv1).
	 * @param array<string, mixed> $field ACF field array.
	 * @return mixed
	 */
	public function decrypt_value_for_field( $value, $field ) {
		$field = $this->normalize_field_definition( $field );
		if ( is_string( $value ) && $this->is_encrypted( $value ) ) {
			return $this->decrypt_for_field( $value, $field );
		}
		return $this->coerce_decrypted_value( $value, $field );
	}

	public function filter_update_value( $value, $post_id, $field ) {
		$field = $this->normalize_field_definition( $field );
		if ( ! $this->should_encrypt( $field ) ) {
			return $value;
		}
		if ( is_string( $value ) && $this->is_encrypted( $value ) ) {
			return $value;
		}
		if ( ( $field['type'] ?? '' ) === 'checkbox' && is_array( $value ) ) {
			$value = $this->coerce_decrypted_value( $value, $field );
		}
		$raw = $this->value_to_storage_string( $value );
		$enc = $this->encrypt_string( $raw );
		return ( $enc === false ) ? $value : $enc;
	}

	public function filter_load_value( $value, $post_id, $field ) {
		$field = $this->normalize_field_definition( $field );
		if ( ! $this->should_encrypt( $field ) ) {
			return $value;
		}
		if ( is_string( $value ) && $this->is_encrypted( $value ) ) {
			return $this->decrypt_for_field( $value, $field );
		}
		return $this->coerce_decrypted_value( $value, $field );
	}

	public function filter_format_value( $value, $post_id, $field ) {
		$field = $this->normalize_field_definition( $field );
		if ( ! $this->should_encrypt( $field ) ) {
			return $value;
		}
		return $this->decrypt_value_for_field( $value, $field );
	}

	/** WP-CLI: Migration bestehender Daten */
	public function cli_command( $args, $assoc_args ) {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		$field_key  = $assoc_args['field_key'] ?? null;
		$field_name = $assoc_args['field_name'] ?? null;
		$post_type  = $assoc_args['post_type'] ?? 'any';
		$per_page   = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : 200;

		if ( ! $field_key && ! $field_name ) {
			\WP_CLI::error( 'Bitte --field_key=FIELD_KEY oder --field_name=FIELD_NAME angeben.' );
		}

		$field = null;
		if ( $field_key ) {
			$field = acf_get_field( $field_key );
			if ( ! $field ) {
				\WP_CLI::error( 'Feld mit field_key nicht gefunden.' );
			}
			$field_name = $field['name'];
		}

		if ( is_array( $field ) && $this->field_is_repeater_sub_field( $field ) ) {
			$this->cli_migrate_repeater_sub_field( $field, $post_type );
			return;
		}

		if ( is_array( $field ) && $this->field_is_group_sub_field( $field ) ) {
			$group_meta_key = $this->get_group_sub_field_meta_key( $field );
			if ( $group_meta_key ) {
				$field_name = $group_meta_key;
			}
		}

		\WP_CLI::log( "Verschlüssele bestehende Werte für Meta-Key '{$field_name}' (Post Type: {$post_type}) ..." );

		$paged   = 1;
		$count   = 0;
		$updated = 0;
		do {
			$q = new \WP_Query( array(
				'post_type'      => $post_type,
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'     => $field_name,
						'compare' => 'EXISTS',
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			) );

			$ids = $q->posts;
			foreach ( $ids as $pid ) {
				$current = get_post_meta( $pid, $field_name, true );
				if ( ! is_string( $current ) || $current === '' || $this->is_encrypted( $current ) ) {
					$count++;
					continue;
				}

				$enc = $this->encrypt_string( $this->value_to_storage_string( $current ) );
				if ( $enc !== false ) {
					update_post_meta( $pid, $field_name, $enc );
					$updated++;
				}
				$count++;
			}

			$paged++;
		} while ( ! empty( $ids ) );

		\WP_CLI::success( "Fertig. Gesehen: {$count}, aktualisiert: {$updated}." );
	}

	/**
	 * WP-CLI: Encrypt existing values for all rows of a repeater sub-field.
	 *
	 * @param array<string, mixed> $field     Repeater sub-field definition.
	 * @param string               $post_type Post type slug.
	 * @return void
	 */
	private function cli_migrate_repeater_sub_field( $field, $post_type ) {
		$parent = $this->get_repeater_parent_field( $field );
		if ( ! $parent || empty( $parent['name'] ) || empty( $field['name'] ) ) {
			\WP_CLI::error( 'Repeater-Parent für Subfeld nicht gefunden.' );
		}

		$repeater_name = (string) $parent['name'];
		$sub_name      = (string) $field['name'];

		\WP_CLI::log( "Verschlüssele Repeater-Subfeld '{$repeater_name}_*_{$sub_name}' (Post Type: {$post_type}) ..." );

		global $wpdb;

		$like = $wpdb->esc_like( $repeater_name . '_' ) . '%' . $wpdb->esc_like( '_' . $sub_name );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_id, pm.meta_key, pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
				AND pm.meta_key LIKE %s
				AND LEFT(pm.meta_key, 1) != %s",
				$post_type,
				$like,
				'_'
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			\WP_CLI::error( 'Datenbankabfrage fehlgeschlagen.' );
		}

		$count   = 0;
		$updated = 0;
		foreach ( $rows as $row ) {
			$meta_key = isset( $row['meta_key'] ) ? (string) $row['meta_key'] : '';
			if ( ! $this->is_repeater_sub_field_meta_key( $meta_key, $repeater_name, $sub_name ) ) {
				continue;
			}

			$count++;
			$current = isset( $row['meta_value'] ) ? $row['meta_value'] : '';
			if ( ! is_string( $current ) || $current === '' || $this->is_encrypted( $current ) ) {
				continue;
			}

			$enc = $this->encrypt_string( $this->value_to_storage_string( $current ) );
			if ( $enc === false ) {
				continue;
			}

			$post_id = isset( $row['post_id'] ) ? (int) $row['post_id'] : 0;
			if ( $post_id > 0 ) {
				update_post_meta( $post_id, $meta_key, $enc );
				$updated++;
			}
		}

		\WP_CLI::success( "Fertig. Gesehen: {$count}, aktualisiert: {$updated}." );
	}
}

add_action( 'plugins_loaded', function () {
	ACF_Encrypted_Fields::instance();
}, 1 );

endif;
