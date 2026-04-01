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
		add_filter( 'acf/load_value', array( $this, 'filter_load_value' ), 10, 3 );
		add_filter( 'acf/format_value', array( $this, 'filter_format_value' ), 10, 3 );

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

		acf_render_field_setting( $field, array(
			'label'         => __( 'Encrypt value in database', 'special-olympics-extension' ),
			'instructions'  => __( 'Speichert den Feldwert verschlüsselt (AES-256-GCM) in der Datenbank. Ausgabe wird automatisch entschlüsselt.', 'special-olympics-extension' ),
			'name'          => 'acfenc_encrypt',
			'type'          => 'true_false',
			'ui'            => 1,
			'default_value' => 0,
		) );
	}

	/** Soll ein Feld verschlüsselt werden? */
	private function should_encrypt( $field ) {
		$flag = ! empty( $field['acfenc_encrypt'] );
		return (bool) apply_filters( 'acfenc/should_encrypt', $flag, $field );
	}

	/** Marker prüfen */
	private function is_encrypted( $value ) {
		return is_string( $value ) && substr( $value, 0, 6 ) === 'ENCv1:';
	}

	private function to_string( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		return (string) $value;
	}

	private function from_string( $raw ) {
		$decoded = json_decode( $raw, true );
		return ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : $raw;
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
			return $this->from_string( (string) $pt );
		}
		return $value;
	}

	public function filter_update_value( $value, $post_id, $field ) {
		if ( ! $this->should_encrypt( $field ) ) {
			return $value;
		}
		if ( is_string( $value ) && $this->is_encrypted( $value ) ) {
			return $value;
		}
		$raw = $this->to_string( $value );
		$enc = $this->encrypt_string( $raw );
		return ( $enc === false ) ? $value : $enc;
	}

	public function filter_load_value( $value, $post_id, $field ) {
		if ( ! $this->should_encrypt( $field ) ) {
			return $value;
		}
		if ( is_string( $value ) && $this->is_encrypted( $value ) ) {
			$pt = $this->decrypt_string( $value );
			return $this->from_string( (string) $pt );
		}
		return $value;
	}

	public function filter_format_value( $value, $post_id, $field ) {
		if ( ! $this->should_encrypt( $field ) ) {
			return $value;
		}
		return $this->decrypt_value( $value );
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

		if ( $field_key ) {
			$field = acf_get_field( $field_key );
			if ( ! $field ) {
				\WP_CLI::error( 'Feld mit field_key nicht gefunden.' );
			}
			$field_name = $field['name'];
		}

		\WP_CLI::log( "Verschlüssele bestehende Werte für Meta-Key '{$field_name}' (Post Type: {$post_type}) ..." );

		$paged  = 1;
		$count  = 0;
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
				if ( ! is_string( $current ) || $this->is_encrypted( $current ) ) {
					$count++;
					continue;
				}

				$enc = $this->encrypt_string( $this->to_string( $current ) );
				if ( $enc !== false ) {
					if ( ! empty( $field_key ) ) {
						update_field( $field_key, $enc, $pid );
					} else {
						update_post_meta( $pid, $field_name, $enc );
					}
					$updated++;
				}
				$count++;
			}

			$paged++;
		} while ( ! empty( $ids ) );

		\WP_CLI::success( "Fertig. Gesehen: {$count}, aktualisiert: {$updated}." );
	}
}

add_action( 'plugins_loaded', function () {
	ACF_Encrypted_Fields::instance();
}, 1 );

endif;
