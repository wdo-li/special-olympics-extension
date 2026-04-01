<?php
/**
 * GitHub-based plugin updates for public repository releases.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOE_GITHUB_REPO', 'wdo-li/special-olympics-extension' );
define( 'SOE_GITHUB_RELEASE_API', 'https://api.github.com/repos/' . SOE_GITHUB_REPO . '/releases/latest' );
define( 'SOE_GITHUB_RELEASE_ASSET', 'special-olympics-extension.zip' );
define( 'SOE_GITHUB_CACHE_TTL', 900 );

add_filter( 'pre_set_site_transient_update_plugins', 'soe_github_check_for_updates' );
add_filter( 'plugins_api', 'soe_github_plugins_api', 10, 3 );
add_filter( 'upgrader_source_selection', 'soe_github_fix_source_folder', 10, 4 );
add_action( 'admin_notices', 'soe_github_release_version_notice' );

/**
 * Returns plugin basename for update payload.
 *
 * @return string
 */
function soe_github_plugin_basename() {
	return plugin_basename( dirname( __DIR__ ) . '/plugin.php' );
}

/**
 * Returns plugin folder slug.
 *
 * @return string
 */
function soe_github_plugin_slug() {
	$base = soe_github_plugin_basename();
	return dirname( $base );
}

/**
 * Fetches latest GitHub release data (cached).
 *
 * @return array|null
 */
function soe_github_get_latest_release() {
	$cache_key = 'soe_github_latest_release';
	$cached    = get_site_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$response = wp_remote_get(
		SOE_GITHUB_RELEASE_API,
		array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'special-olympics-extension-updater',
			),
		)
	);
	if ( is_wp_error( $response ) ) {
		return null;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return null;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );
	if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
		return null;
	}

	set_site_transient( $cache_key, $data, SOE_GITHUB_CACHE_TTL );
	return $data;
}

/**
 * Normalizes release tag to SemVer-like version string.
 *
 * @param string $tag Raw tag name (e.g. v1.3.33).
 * @return string
 */
function soe_github_normalize_tag_version( $tag ) {
	$tag = is_string( $tag ) ? trim( $tag ) : '';
	return ltrim( $tag, 'vV' );
}

/**
 * Selects the best release package URL.
 * Prefer named release asset zip, fallback to first zip asset, then zipball.
 *
 * @param array $release GitHub release payload.
 * @return string
 */
function soe_github_release_package_url( $release ) {
	if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
		foreach ( $release['assets'] as $asset ) {
			if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
				continue;
			}
			if ( SOE_GITHUB_RELEASE_ASSET === $asset['name'] ) {
				return (string) $asset['browser_download_url'];
			}
		}
		foreach ( $release['assets'] as $asset ) {
			if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
				continue;
			}
			if ( preg_match( '/\.zip$/i', (string) $asset['name'] ) ) {
				return (string) $asset['browser_download_url'];
			}
		}
	}

	return ! empty( $release['zipball_url'] ) ? (string) $release['zipball_url'] : '';
}

/**
 * Inject update data into WordPress update transient.
 *
 * @param object $transient Update transient.
 * @return object
 */
function soe_github_check_for_updates( $transient ) {
	if ( ! is_object( $transient ) || empty( $transient->checked ) ) {
		return $transient;
	}

	$plugin_file = soe_github_plugin_basename();
	if ( empty( $transient->checked[ $plugin_file ] ) ) {
		return $transient;
	}

	$current_version = (string) $transient->checked[ $plugin_file ];
	$release         = soe_github_get_latest_release();
	if ( ! is_array( $release ) ) {
		return $transient;
	}

	$new_version = soe_github_normalize_tag_version( (string) $release['tag_name'] );
	if ( '' === $new_version || ! version_compare( $new_version, $current_version, '>' ) ) {
		return $transient;
	}

	$package = soe_github_release_package_url( $release );
	if ( '' === $package ) {
		return $transient;
	}

	$item = (object) array(
		'slug'        => soe_github_plugin_slug(),
		'plugin'      => $plugin_file,
		'new_version' => $new_version,
		'url'         => ! empty( $release['html_url'] ) ? (string) $release['html_url'] : 'https://github.com/' . SOE_GITHUB_REPO,
		'package'     => $package,
	);

	$transient->response[ $plugin_file ] = $item;
	return $transient;
}

/**
 * Provide plugin information popup for this plugin.
 *
 * @param false|object|array $result Existing result.
 * @param string             $action Action name.
 * @param object             $args   API args.
 * @return false|object|array
 */
function soe_github_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || soe_github_plugin_slug() !== $args->slug ) {
		return $result;
	}

	$release = soe_github_get_latest_release();
	if ( ! is_array( $release ) ) {
		return $result;
	}

	$version     = soe_github_normalize_tag_version( (string) $release['tag_name'] );
	$description = ! empty( $release['body'] ) ? (string) $release['body'] : __( 'Release information from GitHub.', 'special-olympics-extension' );

	return (object) array(
		'name'          => 'Special Olympics Extension',
		'slug'          => soe_github_plugin_slug(),
		'version'       => $version,
		'author'        => '<a href="https://github.com/wdo-li">wdo-li</a>',
		'homepage'      => 'https://github.com/' . SOE_GITHUB_REPO,
		'download_link' => soe_github_release_package_url( $release ),
		'sections'      => array(
			'description' => wp_kses_post( wpautop( $description ) ),
		),
	);
}

/**
 * Ensures extracted update directory matches plugin slug.
 * Needed when package is a GitHub zipball with dynamic folder name.
 *
 * @param string $source        Source path.
 * @param string $remote_source Remote source path.
 * @param object $upgrader      Upgrader instance.
 * @param array  $hook_extra    Hook context.
 * @return string
 */
function soe_github_fix_source_folder( $source, $remote_source, $upgrader, $hook_extra ) {
	if ( empty( $hook_extra['plugin'] ) || soe_github_plugin_basename() !== $hook_extra['plugin'] ) {
		return $source;
	}

	$desired = trailingslashit( $remote_source ) . soe_github_plugin_slug();
	if ( wp_normalize_path( untrailingslashit( $source ) ) === wp_normalize_path( untrailingslashit( $desired ) ) ) {
		return $source;
	}

	if ( ! is_dir( $source ) || is_dir( $desired ) ) {
		return $source;
	}

	$renamed = @rename( $source, $desired );
	return $renamed ? $desired : $source;
}

/**
 * Shows an admin notice when plugin version and latest GitHub release tag differ.
 * Helps maintain release discipline (version bump + tag/release alignment).
 *
 * @return void
 */
function soe_github_release_version_notice() {
	if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && ! in_array( $screen->id, array( 'plugins', 'update-core' ), true ) ) {
		return;
	}

	$release = soe_github_get_latest_release();
	if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
		return;
	}

	$latest_version = soe_github_normalize_tag_version( (string) $release['tag_name'] );
	$current_version = defined( 'SOE_PLUGIN_VERSION' ) ? (string) SOE_PLUGIN_VERSION : '';
	if ( '' === $latest_version || '' === $current_version || $latest_version === $current_version ) {
		return;
	}

	$release_url = ! empty( $release['html_url'] ) ? (string) $release['html_url'] : 'https://github.com/' . SOE_GITHUB_REPO . '/releases';
	?>
	<div class="notice notice-warning">
		<p>
			<?php
			printf(
				/* translators: 1: local version, 2: latest release version */
				esc_html__( 'Special Olympics Extension: Lokale Plugin-Version (%1$s) und neuestes GitHub-Release (%2$s) stimmen nicht überein.', 'special-olympics-extension' ),
				esc_html( $current_version ),
				esc_html( $latest_version )
			);
			?>
			<a href="<?php echo esc_url( $release_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Releases öffnen', 'special-olympics-extension' ); ?></a>
		</p>
	</div>
	<?php
}

