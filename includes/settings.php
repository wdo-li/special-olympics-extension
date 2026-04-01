<?php
/**
 * Plugin settings page and debug helper.
 *
 * Registers an admin menu under Settings and stores: debug mode,
 * duration options, hourly rates, accounting numbers per sport,
 * mail templates for payroll. All options use the Options API with fallbacks.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Option key for the plugin settings (single array). */
define( 'SOE_SETTINGS_OPTION', 'soe_settings' );

/** Mail category keys for soe_is_mail_category_enabled(). */
define( 'SOE_MAIL_CAT_PAYROLL', 'payroll' );
define( 'SOE_MAIL_CAT_MITGLIED_CREATED', 'mitglied_created' );
define( 'SOE_MAIL_CAT_TRAINING_COMPLETED', 'training_completed' );
define( 'SOE_MAIL_CAT_EVENT_CREATED', 'event_created' );
define( 'SOE_MAIL_CAT_USER_WELCOME', 'user_welcome' );
define( 'SOE_MAIL_CAT_HELP', 'help' );

/** Default duration options (labels for dropdowns). */
define( 'SOE_DURATION_DEFAULTS', array( '60', '90', '120', '180', '240', 'HL Pauschale', 'Skitraining' ) );

/** Keys for hourly rates matrix: role_stufe_duration => label. Used for payroll. */
define( 'SOE_HOURLY_RATE_KEYS', array(
	'hauptleiter_in_1a_60'       => 'Hauptleiter*in 1A – 60 Min',
	'hauptleiter_in_1a_90'       => 'Hauptleiter*in 1A – 90 Min',
	'hauptleiter_in_1a_more_2'   => 'Hauptleiter*in 1A – mehr als 2 Std',
	'hauptleiter_in_1a_more_4'   => 'Hauptleiter*in 1A – mehr als 4 Std',
	'hauptleiter_in_1a_hl'       => 'Hauptleiter*in 1A – HL Pauschale',
	'hauptleiter_in_1b_60'       => 'Hauptleiter*in 1B – 60 Min',
	'hauptleiter_in_1b_90'       => 'Hauptleiter*in 1B – 90 Min',
	'hauptleiter_in_1b_more_2'   => 'Hauptleiter*in 1B – mehr als 2 Std',
	'hauptleiter_in_1b_more_4'   => 'Hauptleiter*in 1B – mehr als 4 Std',
	'hauptleiter_in_1b_hl'       => 'Hauptleiter*in 1B – HL Pauschale',
	'leiter_in_2a_60'            => 'Leiter*in 2A – 60 Min',
	'leiter_in_2a_90'            => 'Leiter*in 2A – 90 Min',
	'leiter_in_2a_more_2'       => 'Leiter*in 2A – mehr als 2 Std',
	'leiter_in_2a_more_4'       => 'Leiter*in 2A – mehr als 4 Std',
	'leiter_in_2a_ski'          => 'Leiter*in 2A – Skitraining',
	'assistenztrainer_in_2b_60' => 'Assistenztrainer*in 2B – 60 Min',
	'assistenztrainer_in_2b_90' => 'Assistenztrainer*in 2B – 90 Min',
	'assistenztrainer_in_2b_more_2' => 'Assistenztrainer*in 2B – mehr als 2 Std',
	'assistenztrainer_in_2b_more_4' => 'Assistenztrainer*in 2B – mehr als 4 Std',
	'helfer_in_3a_60'           => 'Helfer*in 3A – 60 Min',
	'helfer_in_3a_90'           => 'Helfer*in 3A – 90 Min',
	'helfer_in_3a_more_2'       => 'Helfer*in 3A – mehr als 2 Std',
	'helfer_in_3a_more_4'       => 'Helfer*in 3A – mehr als 4 Std',
	'praktikant_in_3b_60'       => 'Praktikant*in 3B – 60 Min',
	'praktikant_in_3b_90'       => 'Praktikant*in 3B – 90 Min',
	'praktikant_in_3b_more_2'   => 'Praktikant*in 3B – mehr als 2 Std',
	'praktikant_in_3b_more_4'   => 'Praktikant*in 3B – mehr als 4 Std',
	'schueler_in_3b_60'         => 'Schüler*in 3B – 60 Min',
	'schueler_in_3b_90'         => 'Schüler*in 3B – 90 Min',
	'schueler_in_3b_more_2'     => 'Schüler*in 3B – mehr als 2 Std',
	'schueler_in_3b_more_4'     => 'Schüler*in 3B – mehr als 4 Std',
	'athlet_leader_3c_60'       => 'Athlete Leader 3C – 60 Min',
	'athlet_leader_3c_90'       => 'Athlete Leader 3C – 90 Min',
	'athlet_leader_3c_more_2'   => 'Athlete Leader 3C – mehr als 2 Std',
	'athlet_leader_3c_more_4'   => 'Athlete Leader 3C – mehr als 4 Std',
) );

add_action( 'admin_menu', 'soe_add_settings_page', 20 );
add_action( 'admin_init', 'soe_register_settings' );
add_action( 'admin_enqueue_scripts', 'soe_settings_enqueue_media' );

/**
 * Loads media modal for appearance tab (logo/background pickers).
 *
 * @param string $hook_suffix Current admin page.
 */
function soe_settings_enqueue_media( $hook_suffix ) {
	if ( $hook_suffix !== 'toplevel_page_soe-settings' ) {
		return;
	}
	wp_enqueue_style(
		'soe-settings-modern',
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/admin-settings-modern.css',
		array(),
		SOE_PLUGIN_VERSION
	);
	wp_enqueue_script(
		'soe-settings-preview',
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/admin-settings-preview.js',
		array(),
		SOE_PLUGIN_VERSION,
		true
	);
	wp_enqueue_media();
	wp_enqueue_script(
		'soe-settings-media',
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/admin-settings-media.js',
		array( 'jquery' ),
		SOE_PLUGIN_VERSION,
		true
	);
}

/**
 * Adds the Special Olympics (SOLie Einstellungen) as top-level menu item in the SOE block (after Lohnabrechnung).
 */
function soe_add_settings_page() {
	add_menu_page(
		__( 'Special Olympics', 'special-olympics-extension' ),
		__( 'Einstellungen', 'special-olympics-extension' ),
		'manage_options',
		'soe-settings',
		'soe_render_settings_page',
		'dashicons-admin-generic',
		9.9
	);
}

/**
 * Registers settings and sanitize callbacks.
 */
function soe_register_settings() {
	register_setting( 'soe_settings_group', SOE_SETTINGS_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'soe_sanitize_settings',
	) );
}

/**
 * Sanitizes the settings array before save.
 *
 * @param array $input Raw POST input.
 * @return array Sanitized settings.
 */
function soe_sanitize_settings( $input ) {
	if ( ! is_array( $input ) ) {
		return soe_get_default_settings();
	}
	$prev = get_option( SOE_SETTINGS_OPTION, array() );
	if ( ! is_array( $prev ) ) {
		$prev = array();
	}
	$out = array_merge( soe_get_default_settings(), $prev );
	if ( array_key_exists( 'debug_enabled', $input ) ) {
		$out['debug_enabled'] = ! empty( $input['debug_enabled'] );
	}
	if ( isset( $input['duration_options'] ) && is_string( $input['duration_options'] ) ) {
		$out['duration_options'] = sanitize_textarea_field( $input['duration_options'] );
	}
	if ( isset( $input['hourly_rates'] ) && is_array( $input['hourly_rates'] ) ) {
		$hr = isset( $out['hourly_rates'] ) && is_array( $out['hourly_rates'] ) ? $out['hourly_rates'] : array();
		foreach ( array_keys( SOE_HOURLY_RATE_KEYS ) as $key ) {
			if ( isset( $input['hourly_rates'][ $key ] ) && is_string( $input['hourly_rates'][ $key ] ) ) {
				$val = trim( $input['hourly_rates'][ $key ] );
				$hr[ $key ] = $val === '' ? '' : sanitize_text_field( $val );
			}
		}
		$out['hourly_rates'] = $hr;
	}
	if ( isset( $input['bh_numbers'] ) && is_array( $input['bh_numbers'] ) ) {
		$bh = array();
		foreach ( $input['bh_numbers'] as $slug => $num ) {
			$bh[ sanitize_key( $slug ) ] = sanitize_text_field( $num );
		}
		$out['bh_numbers'] = $bh;
	}
	if ( isset( $input['mail_payroll_subject'] ) ) {
		$out['mail_payroll_subject'] = sanitize_text_field( $input['mail_payroll_subject'] );
	}
	if ( isset( $input['mail_payroll_body'] ) ) {
		$out['mail_payroll_body'] = wp_kses_post( $input['mail_payroll_body'] );
	}
	if ( isset( $input['mail_training_completed_to'] ) ) {
		$out['mail_training_completed_to'] = sanitize_email( $input['mail_training_completed_to'] );
	}
	if ( isset( $input['mail_mitglied_created_to'] ) ) {
		$out['mail_mitglied_created_to'] = sanitize_email( $input['mail_mitglied_created_to'] );
	}
	if ( isset( $input['mail_event_created_to'] ) ) {
		$out['mail_event_created_to'] = sanitize_email( $input['mail_event_created_to'] );
	}
	if ( isset( $input['mail_user_new_subject'] ) ) {
		$out['mail_user_new_subject'] = sanitize_text_field( $input['mail_user_new_subject'] );
	}
	if ( isset( $input['mail_user_new_body'] ) ) {
		$out['mail_user_new_body'] = wp_kses_post( $input['mail_user_new_body'] );
	}
	if ( isset( $input['attendance_max_pin_attempts'] ) ) {
		$out['attendance_max_pin_attempts'] = max( 1, (int) $input['attendance_max_pin_attempts'] );
	}
	if ( isset( $input['attendance_lockout_minutes'] ) ) {
		$out['attendance_lockout_minutes'] = max( 1, (int) $input['attendance_lockout_minutes'] );
	}
	if ( isset( $input['attendance_cookie_minutes'] ) ) {
		$out['attendance_cookie_minutes'] = max( 1, (int) $input['attendance_cookie_minutes'] );
	}
	if ( array_key_exists( 'mail_send_payroll', $input ) ) {
		$out['mail_send_payroll'] = ! empty( $input['mail_send_payroll'] );
	}
	if ( array_key_exists( 'mail_send_mitglied_created', $input ) ) {
		$out['mail_send_mitglied_created'] = ! empty( $input['mail_send_mitglied_created'] );
	}
	if ( array_key_exists( 'mail_send_training_completed', $input ) ) {
		$out['mail_send_training_completed'] = ! empty( $input['mail_send_training_completed'] );
	}
	if ( array_key_exists( 'mail_send_event_created', $input ) ) {
		$out['mail_send_event_created'] = ! empty( $input['mail_send_event_created'] );
	}
	if ( array_key_exists( 'mail_send_user_welcome', $input ) ) {
		$out['mail_send_user_welcome'] = ! empty( $input['mail_send_user_welcome'] );
	}
	if ( array_key_exists( 'mail_send_help', $input ) ) {
		$out['mail_send_help'] = ! empty( $input['mail_send_help'] );
	}
	if ( isset( $input['mail_help_to'] ) ) {
		$out['mail_help_to'] = sanitize_email( $input['mail_help_to'] );
	}
	if ( isset( $input['login_logo_id'] ) ) {
		$out['login_logo_id'] = max( 0, (int) $input['login_logo_id'] );
	}
	if ( isset( $input['attendance_logo_id'] ) ) {
		$out['attendance_logo_id'] = max( 0, (int) $input['attendance_logo_id'] );
	}
	if ( isset( $input['login_bg_id'] ) ) {
		$out['login_bg_id'] = max( 0, (int) $input['login_bg_id'] );
	}
	if ( isset( $input['attendance_bg_id'] ) ) {
		$out['attendance_bg_id'] = max( 0, (int) $input['attendance_bg_id'] );
	}

	return $out;
}

/**
 * Returns default settings values.
 *
 * @return array
 */
function soe_get_default_settings() {
	return array(
		'debug_enabled'           => false,
		'duration_options'        => implode( "\n", SOE_DURATION_DEFAULTS ),
		'hourly_rates'            => array(),
		'bh_numbers'             => array(),
		'mail_payroll_subject'   => '',
		'mail_payroll_body'      => '',
		'mail_training_completed_to' => '',
		'mail_mitglied_created_to'   => '',
		'mail_event_created_to'      => '',
		'mail_user_new_subject'      => '',
		'mail_user_new_body'         => '',
		'attendance_max_pin_attempts' => 5,
		'attendance_lockout_minutes'  => 15,
		'attendance_cookie_minutes'   => 15,
		'mail_send_payroll'            => true,
		'mail_send_mitglied_created'   => true,
		'mail_send_training_completed' => true,
		'mail_send_event_created'      => true,
		'mail_send_user_welcome'       => true,
		'mail_send_help'               => true,
		'mail_help_to'                 => '',
		'login_logo_id'                => 0,
		'attendance_logo_id'         => 0,
		'login_bg_id'                  => 0,
		'attendance_bg_id'             => 0,
	);
}

/**
 * Gets a single setting with fallback to default.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function soe_get_setting( $key ) {
	$options = get_option( SOE_SETTINGS_OPTION, array() );
	if ( ! is_array( $options ) ) {
		$options = array();
	}
	$defaults = soe_get_default_settings();
	return array_key_exists( $key, $options ) ? $options[ $key ] : ( array_key_exists( $key, $defaults ) ? $defaults[ $key ] : null );
}

/**
 * Checks whether debug mode is enabled (for development logging).
 *
 * @return bool
 */
function soe_is_debug_enabled() {
	return (bool) soe_get_setting( 'debug_enabled' );
}

/**
 * Whether sending e-mail for a plugin category is enabled (per-category toggle).
 *
 * @param string $category One of SOE_MAIL_CAT_* constants.
 * @return bool
 */
function soe_is_mail_category_enabled( $category ) {
	$map = array(
		SOE_MAIL_CAT_PAYROLL            => 'mail_send_payroll',
		SOE_MAIL_CAT_MITGLIED_CREATED   => 'mail_send_mitglied_created',
		SOE_MAIL_CAT_TRAINING_COMPLETED => 'mail_send_training_completed',
		SOE_MAIL_CAT_EVENT_CREATED      => 'mail_send_event_created',
		SOE_MAIL_CAT_USER_WELCOME       => 'mail_send_user_welcome',
		SOE_MAIL_CAT_HELP               => 'mail_send_help',
	);
	if ( ! isset( $map[ $category ] ) ) {
		return true;
	}
	$key = $map[ $category ];
	$val = soe_get_setting( $key );
	if ( $val === null ) {
		return true;
	}
	return (bool) $val;
}

/**
 * Recipient for help/support form (from settings).
 *
 * @return string
 */
function soe_get_mail_help_to() {
	$v = soe_get_setting( 'mail_help_to' );
	return is_string( $v ) ? trim( $v ) : '';
}

/**
 * URL for a settings attachment ID, or empty string if invalid.
 *
 * @param int $attachment_id Attachment post ID.
 * @param string $size Image size.
 * @return string
 */
function soe_get_settings_attachment_url( $attachment_id, $size = 'full' ) {
	$attachment_id = (int) $attachment_id;
	if ( $attachment_id <= 0 ) {
		return '';
	}
	if ( ! wp_attachment_is_image( $attachment_id ) ) {
		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) ? $url : '';
	}
	$src = wp_get_attachment_image_src( $attachment_id, $size );
	return is_array( $src ) && ! empty( $src[0] ) ? $src[0] : '';
}

/**
 * Logo URL for wp-login (settings or plugin default).
 *
 * @return string
 */
function soe_get_login_logo_url() {
	$id = (int) soe_get_setting( 'login_logo_id' );
	if ( $id ) {
		$u = soe_get_settings_attachment_url( $id, 'full' );
		if ( $u !== '' ) {
			return $u;
		}
	}
	$plugin_root_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) . '/plugin.php' );
	return $plugin_root_url . 'assets/img/logo/Logo-center.svg';
}

/**
 * Background image URL for wp-login (settings or plugin default).
 *
 * @return string
 */
function soe_get_login_bg_url() {
	$id = (int) soe_get_setting( 'login_bg_id' );
	if ( $id ) {
		$u = soe_get_settings_attachment_url( $id, 'full' );
		if ( $u !== '' ) {
			return $u;
		}
	}
	$plugin_root_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) . '/plugin.php' );
	return $plugin_root_url . 'assets/img/bg-1.webp';
}

/**
 * Logo URL for public attendance pages (settings or plugin default).
 *
 * @return string
 */
function soe_get_attendance_public_logo_url() {
	$id = (int) soe_get_setting( 'attendance_logo_id' );
	if ( $id ) {
		$u = soe_get_settings_attachment_url( $id, 'full' );
		if ( $u !== '' ) {
			return $u;
		}
	}
	$plugin_dir = dirname( dirname( __FILE__ ) );
	$logo_rel   = 'assets/img/logo/Logo-center.svg';
	$logo_path  = $plugin_dir . '/' . $logo_rel;
	return file_exists( $logo_path ) ? plugins_url( $logo_rel, $plugin_dir . '/plugin.php' ) : '';
}

/**
 * Optional CSS for body background on public attendance pages.
 *
 * @return string Fragment to replace default `background: #f5f5f5;` in base styles.
 */
function soe_get_attendance_public_body_background_css() {
	$id = (int) soe_get_setting( 'attendance_bg_id' );
	if ( ! $id ) {
		return '';
	}
	$u = soe_get_settings_attachment_url( $id, 'full' );
	if ( $u === '' ) {
		return '';
	}
	return 'background-color: #e8e8e8; background-image: url(' . esc_url( $u ) . '); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;';
}

/**
 * Logs a message only when debug mode is enabled on the settings page.
 * Writes to WP_DEBUG_LOG when WP_DEBUG_LOG is true.
 *
 * @param string $message Message to log.
 * @param array  $context Optional context (e.g. post_id, user_id).
 */
function soe_debug_log( $message, $context = array() ) {
	if ( ! soe_is_debug_enabled() ) {
		return;
	}
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return;
	}
	$prefix = '[SOE] ';
	$full = $prefix . $message;
	if ( ! empty( $context ) ) {
		$full .= ' ' . wp_json_encode( $context );
	}
	error_log( $full );
}

/**
 * Renders the settings page HTML.
 */
function soe_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$options = get_option( SOE_SETTINGS_OPTION, array() );
	if ( ! is_array( $options ) ) {
		$options = soe_get_default_settings();
	}
	$defaults = soe_get_default_settings();
	$debug_enabled = ! empty( $options['debug_enabled'] );
	$duration_options = isset( $options['duration_options'] ) ? $options['duration_options'] : $defaults['duration_options'];
	$hourly_rates = isset( $options['hourly_rates'] ) && is_array( $options['hourly_rates'] ) ? $options['hourly_rates'] : array();
	$bh_numbers = isset( $options['bh_numbers'] ) && is_array( $options['bh_numbers'] ) ? $options['bh_numbers'] : array();
	$mail_subject = isset( $options['mail_payroll_subject'] ) ? $options['mail_payroll_subject'] : '';
	$mail_body = isset( $options['mail_payroll_body'] ) ? $options['mail_payroll_body'] : '';
	$mail_training_completed_to = isset( $options['mail_training_completed_to'] ) ? $options['mail_training_completed_to'] : '';
	$mail_mitglied_created_to   = isset( $options['mail_mitglied_created_to'] ) ? $options['mail_mitglied_created_to'] : '';
	$mail_event_created_to     = isset( $options['mail_event_created_to'] ) ? $options['mail_event_created_to'] : '';
	$mail_user_new_subject     = isset( $options['mail_user_new_subject'] ) ? $options['mail_user_new_subject'] : '';
	$mail_user_new_body        = isset( $options['mail_user_new_body'] ) ? $options['mail_user_new_body'] : '';
	$attendance_max_pin_attempts = isset( $options['attendance_max_pin_attempts'] ) ? (int) $options['attendance_max_pin_attempts'] : 5;
	$attendance_lockout_minutes  = isset( $options['attendance_lockout_minutes'] ) ? (int) $options['attendance_lockout_minutes'] : 15;
	$attendance_cookie_minutes   = isset( $options['attendance_cookie_minutes'] ) ? (int) $options['attendance_cookie_minutes'] : 15;
	$mail_send_mitglied_created   = ! isset( $options['mail_send_mitglied_created'] ) || ! empty( $options['mail_send_mitglied_created'] );
	$mail_send_training_completed = ! isset( $options['mail_send_training_completed'] ) || ! empty( $options['mail_send_training_completed'] );
	$mail_send_event_created      = ! isset( $options['mail_send_event_created'] ) || ! empty( $options['mail_send_event_created'] );
	$mail_send_user_welcome       = ! isset( $options['mail_send_user_welcome'] ) || ! empty( $options['mail_send_user_welcome'] );
	$mail_send_help               = ! isset( $options['mail_send_help'] ) || ! empty( $options['mail_send_help'] );
	$mail_help_to                 = isset( $options['mail_help_to'] ) ? $options['mail_help_to'] : '';
	$login_logo_id                = isset( $options['login_logo_id'] ) ? (int) $options['login_logo_id'] : 0;
	$attendance_logo_id           = isset( $options['attendance_logo_id'] ) ? (int) $options['attendance_logo_id'] : 0;
	$login_bg_id                  = isset( $options['login_bg_id'] ) ? (int) $options['login_bg_id'] : 0;
	$attendance_bg_id             = isset( $options['attendance_bg_id'] ) ? (int) $options['attendance_bg_id'] : 0;
	$sport_terms = get_terms( array( 'taxonomy' => 'sport', 'hide_empty' => false ) );
	if ( ! is_array( $sport_terms ) ) {
		$sport_terms = array();
	}
	$tabs = array(
		'general'       => __( 'Allgemein', 'special-olympics-extension' ),
		'payroll'       => __( 'Lohnabrechnung', 'special-olympics-extension' ),
		'notifications' => __( 'Benachrichtigungen', 'special-olympics-extension' ),
		'attendance'    => __( 'Mobile-Anwesenheitsmodul', 'special-olympics-extension' ),
	);
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
	if ( ! isset( $tabs[ $active_tab ] ) ) {
		$active_tab = 'general';
	}
	$base_url = admin_url( 'admin.php?page=soe-settings' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<h2 class="nav-tab-wrapper">
			<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
				<?php
				$tab_url = add_query_arg(
					array(
						'page' => 'soe-settings',
						'tab'  => $tab_key,
					),
					$base_url
				);
				?>
				<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_label ); ?></a>
			<?php endforeach; ?>
		</h2>
		<form action="options.php" method="post">
			<?php settings_fields( 'soe_settings_group' ); ?>
		<table class="form-table soe-settings-table" role="presentation">
				<?php if ( $active_tab === 'general' ) : ?>
					<tr class="soe-settings-jump-row">
						<td colspan="2">
							<h3 class="soe-settings-tab-heading"><?php esc_html_e( 'Allgemein', 'special-olympics-extension' ); ?></h3>
							<div class="soe-settings-jump-buttons">
								<a class="button button-small soe-jump-btn" href="#soe-card-general-debug"><?php esc_html_e( 'Debug', 'special-olympics-extension' ); ?></a>
								<a class="button button-small soe-jump-btn" href="#soe-card-general-duration"><?php esc_html_e( 'Dauer', 'special-olympics-extension' ); ?></a>
								<a class="button button-small soe-jump-btn" href="#soe-card-general-login"><?php esc_html_e( 'Login', 'special-olympics-extension' ); ?></a>
							</div>
						</td>
					</tr>
					<tr id="soe-card-general-debug">
						<th scope="row"><?php esc_html_e( 'Debug-Modus', 'special-olympics-extension' ); ?></th>
						<td>
							<label for="soe_debug_enabled">
								<input type="checkbox" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[debug_enabled]" id="soe_debug_enabled" value="1" <?php checked( $debug_enabled ); ?> />
								<?php esc_html_e( 'Debug-Modus aktiv (Logs für Entwicklung)', 'special-olympics-extension' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Wenn aktiv, werden erweiterte Logs (z.B. Sync, Event-Snapshot, Lohnabrechnung Daten-Sammlung) geschrieben. Erfordert in wp-config.php: WP_DEBUG und WP_DEBUG_LOG auf true. Log-Datei: wp-content/debug.log', 'special-olympics-extension' ); ?></p>
						</td>
					</tr>
					<tr id="soe-card-general-duration">
						<th scope="row"><label for="soe_duration_options"><?php esc_html_e( 'Dauer-Optionen', 'special-olympics-extension' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[duration_options]" id="soe_duration_options" rows="6" class="large-text"><?php echo esc_textarea( $duration_options ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Eine Option pro Zeile (z.B. 60, 90, mehr als 2 Stunden). Wird für Training und Event als Dropdown/Radio angeboten.', 'special-olympics-extension' ); ?></p>
						</td>
					</tr>
					<tr id="soe-card-general-login">
						<th scope="row"><?php esc_html_e( 'Login-Seite (wp-login)', 'special-olympics-extension' ); ?></th>
						<td>
							<p>
								<label for="soe_login_logo_id"><?php esc_html_e( 'Logo', 'special-olympics-extension' ); ?></label><br />
								<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[login_logo_id]" id="soe_login_logo_id" value="<?php echo esc_attr( (string) $login_logo_id ); ?>" />
								<button type="button" class="button soe-media-picker" data-target="#soe_login_logo_id" data-preview="#soe_login_logo_preview"><?php esc_html_e( 'Mediathek', 'special-olympics-extension' ); ?></button>
								<button type="button" class="button soe-media-clear" data-target="#soe_login_logo_id" data-preview="#soe_login_logo_preview"><?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?></button>
								<span id="soe_login_logo_preview" class="soe-media-preview"><?php echo $login_logo_id ? '<img src="' . esc_url( wp_get_attachment_image_url( $login_logo_id, 'medium' ) ) . '" alt="" style="max-height:48px;vertical-align:middle;margin-left:8px;" />' : ''; ?></span>
							</p>
							<p>
								<label for="soe_login_bg_id"><?php esc_html_e( 'Hintergrundbild', 'special-olympics-extension' ); ?></label><br />
								<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[login_bg_id]" id="soe_login_bg_id" value="<?php echo esc_attr( (string) $login_bg_id ); ?>" />
								<button type="button" class="button soe-media-picker" data-target="#soe_login_bg_id" data-preview="#soe_login_bg_preview"><?php esc_html_e( 'Mediathek', 'special-olympics-extension' ); ?></button>
								<button type="button" class="button soe-media-clear" data-target="#soe_login_bg_id" data-preview="#soe_login_bg_preview"><?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?></button>
								<span id="soe_login_bg_preview" class="soe-media-preview"><?php echo $login_bg_id ? '<img src="' . esc_url( wp_get_attachment_image_url( $login_bg_id, 'medium' ) ) . '" alt="" style="max-height:48px;vertical-align:middle;margin-left:8px;" />' : ''; ?></span>
							</p>
							<p class="description"><?php esc_html_e( 'Leer lassen für die Standard-Grafiken des Plugins.', 'special-olympics-extension' ); ?></p>
						</td>
					</tr>
				<?php elseif ( $active_tab === 'payroll' ) : ?>
					<tr class="soe-settings-jump-row">
						<td colspan="2">
							<h3 class="soe-settings-tab-heading"><?php esc_html_e( 'Lohnabrechnung', 'special-olympics-extension' ); ?></h3>
							<div class="soe-settings-jump-buttons">
								<a class="button button-small soe-jump-btn" href="#soe-card-payroll-bh"><?php esc_html_e( 'BH-Nummern', 'special-olympics-extension' ); ?></a>
								<a class="button button-small soe-jump-btn" href="#soe-card-payroll-hourly"><?php esc_html_e( 'Stundensätze', 'special-olympics-extension' ); ?></a>
							</div>
						</td>
					</tr>
					<tr id="soe-card-payroll-bh">
						<th scope="row"><?php esc_html_e( 'Buchhaltungsnummern (Sportart → BH-Nr.)', 'special-olympics-extension' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Pro Sportart eine Buchhaltungsnummer für die Lohnabrechnung.', 'special-olympics-extension' ); ?></p>
							<?php if ( empty( $sport_terms ) ) : ?>
								<p><?php esc_html_e( 'Noch keine Sportarten angelegt. Unter Sportarten Terms anlegen.', 'special-olympics-extension' ); ?></p>
							<?php else : ?>
								<table class="widefat striped" style="max-width: 360px; margin-top: 8px;">
									<thead><tr><th><?php esc_html_e( 'Sportart', 'special-olympics-extension' ); ?></th><th>BH-Nr.</th></tr></thead>
									<tbody>
									<?php foreach ( $sport_terms as $term ) : ?>
										<tr>
											<td><label for="soe_bh_<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></label></td>
											<td><input type="text" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[bh_numbers][<?php echo esc_attr( $term->slug ); ?>]" id="soe_bh_<?php echo esc_attr( $term->slug ); ?>" value="<?php echo esc_attr( isset( $bh_numbers[ $term->slug ] ) ? $bh_numbers[ $term->slug ] : '' ); ?>" class="small-text" /></td>
										</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</td>
					</tr>
					<tr id="soe-card-payroll-hourly">
						<th scope="row"><?php esc_html_e( 'Stundensätze (CHF)', 'special-olympics-extension' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Betrag pro Rolle/Stufe/Dauer für die Lohnabrechnung. Leer = nicht verwendet.', 'special-olympics-extension' ); ?></p>
							<table class="widefat striped" style="max-width: 480px; margin-top: 8px;">
								<thead><tr><th><?php esc_html_e( 'Bezeichnung', 'special-olympics-extension' ); ?></th><th>CHF</th></tr></thead>
								<tbody>
								<?php foreach ( SOE_HOURLY_RATE_KEYS as $key => $label ) : ?>
									<tr>
										<td><label for="soe_hr_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></td>
										<td><input type="text" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[hourly_rates][<?php echo esc_attr( $key ); ?>]" id="soe_hr_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( isset( $hourly_rates[ $key ] ) ? $hourly_rates[ $key ] : '' ); ?>" class="small-text" /></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</td>
					</tr>
				<?php elseif ( $active_tab === 'notifications' ) : ?>
					<tr>
						<td colspan="2">
							<h3 class="soe-settings-tab-heading"><?php esc_html_e( 'Benachrichtigungen', 'special-olympics-extension' ); ?></h3>
							<div class="soe-settings-jump-buttons soe-settings-jump-buttons--notifications">
								<a class="button button-small soe-jump-btn" href="#soe-card-notif-lohnabrechnung-mail"><?php esc_html_e( 'Lohnabrechnung Mail', 'special-olympics-extension' ); ?></a>
								<a class="button button-small soe-jump-btn" href="#soe-card-mail_send_mitglied_created"><?php esc_html_e( 'Neues Mitglied', 'special-olympics-extension' ); ?></a>
								<a class="button button-small soe-jump-btn" href="#soe-card-mail_send_training_completed"><?php esc_html_e( 'Training abgeschlossen', 'special-olympics-extension' ); ?></a>
								<a class="button button-small soe-jump-btn" href="#soe-card-mail_send_event_created"><?php esc_html_e( 'Neues Event', 'special-olympics-extension' ); ?></a>
								<a class="button button-small soe-jump-btn" href="#soe-card-mail_send_user_welcome"><?php esc_html_e( 'Willkommens-Mail', 'special-olympics-extension' ); ?></a>
								<a class="button button-small soe-jump-btn" href="#soe-card-mail_send_help"><?php esc_html_e( 'Hilfe-Anfragen', 'special-olympics-extension' ); ?></a>
							</div>

							<p class="description"><?php esc_html_e( 'Pro Kategorie steuern, ob WordPress überhaupt E-Mails versendet. Empfänger- und Textfelder bleiben gespeichert; bei deaktivierter Kategorie wird kein Versand ausgeführt.', 'special-olympics-extension' ); ?></p>

							<div class="soe-mail-template-card" id="soe-card-notif-lohnabrechnung-mail">
								<div class="soe-mail-template-card-header">
									<h4><?php esc_html_e( 'Lohnabrechnung Mail', 'special-olympics-extension' ); ?></h4>
								</div>
								<div class="soe-mail-template-card-body">
									<p class="description" style="margin-top:0;">
										<?php esc_html_e( 'Vorlage für den E-Mail-Text beim Versand der Lohnabrechnung.', 'special-olympics-extension' ); ?>
									</p>
									<p>
										<label for="soe_mail_subject"><?php esc_html_e( 'Lohnabrechnung Mail – Betreff', 'special-olympics-extension' ); ?></label>
										<input type="text" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_payroll_subject]" id="soe_mail_subject" value="<?php echo esc_attr( $mail_subject ); ?>" class="large-text" />
									</p>
									<div class="soe-mail-template-two-col">
										<div class="soe-mail-template-col">
											<label for="soe_mail_body"><?php esc_html_e( 'Lohnabrechnung Mail – Text', 'special-olympics-extension' ); ?></label>
											<textarea name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_payroll_body]" id="soe_mail_body" rows="6" class="large-text"><?php echo esc_textarea( $mail_body ); ?></textarea>
											<p class="description">
												<?php esc_html_e( 'Platzhalter:', 'special-olympics-extension' ); ?> <code>{{vorname}}</code>, <code>{{nachname}}</code>, <code>{{period_label}}</code>, <code>{{betrag_chf}}</code>
											</p>
										</div>
										<div class="soe-mail-template-col soe-mail-template-preview-col">
											<h5><?php esc_html_e( 'Vorschau', 'special-olympics-extension' ); ?></h5>
											<pre id="soe-payroll-mail-preview-subject" class="soe-mail-preview-subject" aria-live="polite"></pre>
											<pre id="soe-payroll-mail-preview-body" class="soe-mail-preview-body" aria-live="polite"></pre>
										</div>
									</div>
								</div>
							</div>

							<div class="soe-mail-categories">
								<details id="soe-card-mail_send_mitglied_created" class="soe-mail-toggle-card" open>
									<summary><?php esc_html_e( 'Neues Mitglied (Admin-Benachrichtigung)', 'special-olympics-extension' ); ?></summary>
									<div class="soe-mail-toggle-card-body">
										<p class="soe-mail-toggle-line">
											<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_mitglied_created]" value="0" />
											<label>
												<input type="checkbox" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_mitglied_created]" value="1" <?php checked( $mail_send_mitglied_created ); ?> />
												<?php esc_html_e( 'Aktiv', 'special-olympics-extension' ); ?>
											</label>
										</p>
										<p>
											<label for="soe_mail_mitglied_created_to"><?php esc_html_e( 'E-Mail-Empfänger', 'special-olympics-extension' ); ?></label><br />
											<input type="email" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_mitglied_created_to]" id="soe_mail_mitglied_created_to" value="<?php echo esc_attr( $mail_mitglied_created_to ); ?>" class="regular-text" />
										</p>
										<p class="description"><?php esc_html_e( 'Betrifft: E-Mail an die konfigurierte Adresse, wenn ein neues Mitglied (CPT) angelegt wurde.', 'special-olympics-extension' ); ?></p>
									</div>
								</details>

								<details id="soe-card-mail_send_training_completed" class="soe-mail-toggle-card">
									<summary><?php esc_html_e( 'Training als abgeschlossen gemeldet', 'special-olympics-extension' ); ?></summary>
									<div class="soe-mail-toggle-card-body">
										<p class="soe-mail-toggle-line">
											<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_training_completed]" value="0" />
											<label>
												<input type="checkbox" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_training_completed]" value="1" <?php checked( $mail_send_training_completed ); ?> />
												<?php esc_html_e( 'Aktiv', 'special-olympics-extension' ); ?>
											</label>
										</p>
										<p>
											<label for="soe_mail_training_completed_to"><?php esc_html_e( 'E-Mail-Empfänger', 'special-olympics-extension' ); ?></label><br />
											<input type="email" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_training_completed_to]" id="soe_mail_training_completed_to" value="<?php echo esc_attr( $mail_training_completed_to ); ?>" class="regular-text" />
										</p>
										<p class="description"><?php esc_html_e( 'Betrifft: E-Mail an die konfigurierte Adresse, wenn eine Hauptleitung „Training als abgeschlossen melden“ auslöst.', 'special-olympics-extension' ); ?></p>
									</div>
								</details>

								<details id="soe-card-mail_send_event_created" class="soe-mail-toggle-card">
									<summary><?php esc_html_e( 'Neues Event angelegt', 'special-olympics-extension' ); ?></summary>
									<div class="soe-mail-toggle-card-body">
										<p class="soe-mail-toggle-line">
											<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_event_created]" value="0" />
											<label>
												<input type="checkbox" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_event_created]" value="1" <?php checked( $mail_send_event_created ); ?> />
												<?php esc_html_e( 'Aktiv', 'special-olympics-extension' ); ?>
											</label>
										</p>
										<p>
											<label for="soe_mail_event_created_to"><?php esc_html_e( 'E-Mail-Empfänger', 'special-olympics-extension' ); ?></label><br />
											<input type="email" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_event_created_to]" id="soe_mail_event_created_to" value="<?php echo esc_attr( $mail_event_created_to ); ?>" class="regular-text" />
										</p>
										<p class="description"><?php esc_html_e( 'Betrifft: E-Mail an die konfigurierte Adresse bei neuem Event.', 'special-olympics-extension' ); ?></p>
									</div>
								</details>

								<details id="soe-card-mail_send_user_welcome" class="soe-mail-toggle-card">
									<summary><?php esc_html_e( 'Willkommens-Mail an neuen Benutzer', 'special-olympics-extension' ); ?></summary>
									<div class="soe-mail-toggle-card-body">
										<p class="soe-mail-toggle-line">
											<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_user_welcome]" value="0" />
											<label>
												<input type="checkbox" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_user_welcome]" value="1" <?php checked( $mail_send_user_welcome ); ?> />
												<?php esc_html_e( 'Aktiv', 'special-olympics-extension' ); ?>
											</label>
										</p>
										<p>
											<label for="soe_mail_user_new_subject"><?php esc_html_e( 'Neuer User – Betreff', 'special-olympics-extension' ); ?></label><br />
											<input type="text" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_user_new_subject]" id="soe_mail_user_new_subject" value="<?php echo esc_attr( $mail_user_new_subject ); ?>" class="large-text" />
										</p>
										<p>
											<label for="soe_mail_user_new_body"><?php esc_html_e( 'Neuer User – Mailtext', 'special-olympics-extension' ); ?></label><br />
											<textarea name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_user_new_body]" id="soe_mail_user_new_body" rows="8" class="large-text"><?php echo esc_textarea( $mail_user_new_body ); ?></textarea>
										</p>
										<p class="description">
											<?php esc_html_e( 'Betrifft: die Benachrichtigung an den neuen Benutzer (inkl. Passwort-Link), wenn beim Anlegen „Benutzer benachrichtigen“ aktiv ist.', 'special-olympics-extension' ); ?>
										</p>
									</div>
								</details>

								<details id="soe-card-mail_send_help" class="soe-mail-toggle-card">
									<summary><?php esc_html_e( 'Hilfe-Anfragen (Kontakt-Widget)', 'special-olympics-extension' ); ?></summary>
									<div class="soe-mail-toggle-card-body">
										<p class="soe-mail-toggle-line">
											<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_help]" value="0" />
											<label>
												<input type="checkbox" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_send_help]" value="1" <?php checked( $mail_send_help ); ?> />
												<?php esc_html_e( 'Aktiv', 'special-olympics-extension' ); ?>
											</label>
										</p>
										<p>
											<label for="soe_mail_help_to"><?php esc_html_e( 'E-Mail-Empfänger', 'special-olympics-extension' ); ?></label><br />
											<input type="email" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[mail_help_to]" id="soe_mail_help_to" value="<?php echo esc_attr( $mail_help_to ); ?>" class="regular-text" />
										</p>
										<p class="description"><?php esc_html_e( 'Betrifft: E-Mails aus dem Hilfe-Formular (schwebendes Icon). Empfänger- und Textfelder bleiben gespeichert; bei deaktivierter Kategorie wird kein Versand ausgeführt.', 'special-olympics-extension' ); ?></p>
									</div>
								</details>
							</div>
						</td>
					</tr>
				<?php elseif ( $active_tab === 'appearance' ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Login-Seite (wp-login)', 'special-olympics-extension' ); ?></th>
						<td>
							<p>
								<label for="soe_login_logo_id"><?php esc_html_e( 'Logo', 'special-olympics-extension' ); ?></label><br />
								<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[login_logo_id]" id="soe_login_logo_id" value="<?php echo esc_attr( (string) $login_logo_id ); ?>" />
								<button type="button" class="button soe-media-picker" data-target="#soe_login_logo_id" data-preview="#soe_login_logo_preview"><?php esc_html_e( 'Mediathek', 'special-olympics-extension' ); ?></button>
								<button type="button" class="button soe-media-clear" data-target="#soe_login_logo_id" data-preview="#soe_login_logo_preview"><?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?></button>
								<span id="soe_login_logo_preview" class="soe-media-preview"><?php echo $login_logo_id ? '<img src="' . esc_url( wp_get_attachment_image_url( $login_logo_id, 'medium' ) ) . '" alt="" style="max-height:48px;vertical-align:middle;margin-left:8px;" />' : ''; ?></span>
							</p>
							<p>
								<label for="soe_login_bg_id"><?php esc_html_e( 'Hintergrundbild', 'special-olympics-extension' ); ?></label><br />
								<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[login_bg_id]" id="soe_login_bg_id" value="<?php echo esc_attr( (string) $login_bg_id ); ?>" />
								<button type="button" class="button soe-media-picker" data-target="#soe_login_bg_id" data-preview="#soe_login_bg_preview"><?php esc_html_e( 'Mediathek', 'special-olympics-extension' ); ?></button>
								<button type="button" class="button soe-media-clear" data-target="#soe_login_bg_id" data-preview="#soe_login_bg_preview"><?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?></button>
								<span id="soe_login_bg_preview" class="soe-media-preview"><?php echo $login_bg_id ? '<img src="' . esc_url( wp_get_attachment_image_url( $login_bg_id, 'medium' ) ) . '" alt="" style="max-height:48px;vertical-align:middle;margin-left:8px;" />' : ''; ?></span>
							</p>
							<p class="description"><?php esc_html_e( 'Leer lassen für die Standard-Grafiken des Plugins.', 'special-olympics-extension' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Öffentliche Anwesenheit', 'special-olympics-extension' ); ?></th>
						<td>
							<p>
								<label for="soe_attendance_logo_id"><?php esc_html_e( 'Logo (Header)', 'special-olympics-extension' ); ?></label><br />
								<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[attendance_logo_id]" id="soe_attendance_logo_id" value="<?php echo esc_attr( (string) $attendance_logo_id ); ?>" />
								<button type="button" class="button soe-media-picker" data-target="#soe_attendance_logo_id" data-preview="#soe_attendance_logo_preview"><?php esc_html_e( 'Mediathek', 'special-olympics-extension' ); ?></button>
								<button type="button" class="button soe-media-clear" data-target="#soe_attendance_logo_id" data-preview="#soe_attendance_logo_preview"><?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?></button>
								<span id="soe_attendance_logo_preview" class="soe-media-preview"><?php echo $attendance_logo_id ? '<img src="' . esc_url( wp_get_attachment_image_url( $attendance_logo_id, 'medium' ) ) . '" alt="" style="max-height:48px;vertical-align:middle;margin-left:8px;" />' : ''; ?></span>
							</p>
							<p>
								<label for="soe_attendance_bg_id"><?php esc_html_e( 'Hintergrundbild', 'special-olympics-extension' ); ?></label><br />
								<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[attendance_bg_id]" id="soe_attendance_bg_id" value="<?php echo esc_attr( (string) $attendance_bg_id ); ?>" />
								<button type="button" class="button soe-media-picker" data-target="#soe_attendance_bg_id" data-preview="#soe_attendance_bg_preview"><?php esc_html_e( 'Mediathek', 'special-olympics-extension' ); ?></button>
								<button type="button" class="button soe-media-clear" data-target="#soe_attendance_bg_id" data-preview="#soe_attendance_bg_preview"><?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?></button>
								<span id="soe_attendance_bg_preview" class="soe-media-preview"><?php echo $attendance_bg_id ? '<img src="' . esc_url( wp_get_attachment_image_url( $attendance_bg_id, 'medium' ) ) . '" alt="" style="max-height:48px;vertical-align:middle;margin-left:8px;" />' : ''; ?></span>
							</p>
							<p class="description"><?php esc_html_e( 'Leer lassen für die Standard-Grafiken des Plugins.', 'special-olympics-extension' ); ?></p>
						</td>
					</tr>
				<?php elseif ( $active_tab === 'attendance' ) : ?>
					<tr class="soe-settings-jump-row">
						<td colspan="2">
							<h3 class="soe-settings-tab-heading"><?php esc_html_e( 'Mobile-Anwesenheitsmodul', 'special-olympics-extension' ); ?></h3>
							<div class="soe-settings-jump-buttons">
								<a class="button button-small soe-jump-btn" href="#soe-card-mobile-attendance-module"><?php esc_html_e( 'Mobile-Anwesenheitsmodul', 'special-olympics-extension' ); ?></a>
								<a class="button button-small soe-jump-btn" href="#soe-card-mobile-attendance-public"><?php esc_html_e( 'Öffentliche Anwesenheit', 'special-olympics-extension' ); ?></a>
							</div>
						</td>
					</tr>
					<tr id="soe-card-mobile-attendance-module">
						<th scope="row"><?php esc_html_e( 'Mobile-Anwesenheitsmodul', 'special-olympics-extension' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Sicherheitsparameter für öffentliche Anwesenheitslinks.', 'special-olympics-extension' ); ?></p>
							<p>
								<label for="soe_attendance_max_pin_attempts"><?php esc_html_e( 'Max. PIN-Fehlversuche', 'special-olympics-extension' ); ?></label><br />
								<input type="number" min="1" step="1" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[attendance_max_pin_attempts]" id="soe_attendance_max_pin_attempts" value="<?php echo esc_attr( (string) $attendance_max_pin_attempts ); ?>" class="small-text" />
							</p>
							<p>
								<label for="soe_attendance_lockout_minutes"><?php esc_html_e( 'Sperrdauer (Minuten)', 'special-olympics-extension' ); ?></label><br />
								<input type="number" min="1" step="1" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[attendance_lockout_minutes]" id="soe_attendance_lockout_minutes" value="<?php echo esc_attr( (string) $attendance_lockout_minutes ); ?>" class="small-text" />
							</p>
							<p>
								<label for="soe_attendance_cookie_minutes"><?php esc_html_e( 'PIN-Session (Minuten)', 'special-olympics-extension' ); ?></label><br />
								<input type="number" min="1" step="1" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[attendance_cookie_minutes]" id="soe_attendance_cookie_minutes" value="<?php echo esc_attr( (string) $attendance_cookie_minutes ); ?>" class="small-text" />
							</p>
						</td>
					</tr>
					<tr id="soe-card-mobile-attendance-public">
						<th scope="row"><?php esc_html_e( 'Öffentliche Anwesenheit', 'special-olympics-extension' ); ?></th>
						<td>
							<p>
								<label for="soe_attendance_logo_id"><?php esc_html_e( 'Logo (Header)', 'special-olympics-extension' ); ?></label><br />
								<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[attendance_logo_id]" id="soe_attendance_logo_id" value="<?php echo esc_attr( (string) $attendance_logo_id ); ?>" />
								<button type="button" class="button soe-media-picker" data-target="#soe_attendance_logo_id" data-preview="#soe_attendance_logo_preview"><?php esc_html_e( 'Mediathek', 'special-olympics-extension' ); ?></button>
								<button type="button" class="button soe-media-clear" data-target="#soe_attendance_logo_id" data-preview="#soe_attendance_logo_preview"><?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?></button>
								<span id="soe_attendance_logo_preview" class="soe-media-preview"><?php echo $attendance_logo_id ? '<img src="' . esc_url( wp_get_attachment_image_url( $attendance_logo_id, 'medium' ) ) . '" alt="" style="max-height:48px;vertical-align:middle;margin-left:8px;" />' : ''; ?></span>
							</p>
							<p>
								<label for="soe_attendance_bg_id"><?php esc_html_e( 'Hintergrundbild', 'special-olympics-extension' ); ?></label><br />
								<input type="hidden" name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[attendance_bg_id]" id="soe_attendance_bg_id" value="<?php echo esc_attr( (string) $attendance_bg_id ); ?>" />
								<button type="button" class="button soe-media-picker" data-target="#soe_attendance_bg_id" data-preview="#soe_attendance_bg_preview"><?php esc_html_e( 'Mediathek', 'special-olympics-extension' ); ?></button>
								<button type="button" class="button soe-media-clear" data-target="#soe_attendance_bg_id" data-preview="#soe_attendance_bg_preview"><?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?></button>
								<span id="soe_attendance_bg_preview" class="soe-media-preview"><?php echo $attendance_bg_id ? '<img src="' . esc_url( wp_get_attachment_image_url( $attendance_bg_id, 'medium' ) ) . '" alt="" style="max-height:48px;vertical-align:middle;margin-left:8px;" />' : ''; ?></span>
							</p>
							<p class="description"><?php esc_html_e( 'Leer lassen für die Standard-Grafiken des Plugins.', 'special-olympics-extension' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Returns duration options as an array of strings (for use in training/event).
 *
 * @return array
 */
function soe_get_duration_options() {
	$raw = soe_get_setting( 'duration_options' );
	if ( empty( $raw ) ) {
		return SOE_DURATION_DEFAULTS;
	}
	$lines = preg_split( '/\r\n|\r|\n/', $raw, -1, PREG_SPLIT_NO_EMPTY );
	$out = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line !== '' ) {
			$out[] = $line;
		}
	}
	return $out ? $out : SOE_DURATION_DEFAULTS;
}

/**
 * Returns hourly rate (CHF) for a given role_stufe_duration key, or empty string.
 *
 * @param string $key Key from SOE_HOURLY_RATE_KEYS (e.g. hauptleiter_in_1a_60).
 * @return string
 */
function soe_get_hourly_rate( $key ) {
	$rates = soe_get_setting( 'hourly_rates' );
	if ( ! is_array( $rates ) || ! array_key_exists( $key, $rates ) ) {
		return '';
	}
	return is_string( $rates[ $key ] ) ? trim( $rates[ $key ] ) : '';
}

/**
 * Returns accounting number (BH-Nr.) for a sport term slug, or empty string.
 *
 * @param string $sport_slug Term slug from taxonomy 'sport'.
 * @return string
 */
function soe_get_bh_number_for_sport( $sport_slug ) {
	$bh = soe_get_setting( 'bh_numbers' );
	if ( ! is_array( $bh ) || ! array_key_exists( $sport_slug, $bh ) ) {
		return '';
	}
	return is_string( $bh[ $sport_slug ] ) ? trim( $bh[ $sport_slug ] ) : '';
}

/**
 * Returns mail subject for payroll email (from settings).
 *
 * @return string
 */
function soe_get_mail_payroll_subject() {
	$v = soe_get_setting( 'mail_payroll_subject' );
	return is_string( $v ) ? trim( $v ) : '';
}

/**
 * Returns mail body for payroll email (from settings).
 *
 * @return string
 */
function soe_get_mail_payroll_body() {
	$v = soe_get_setting( 'mail_payroll_body' );
	return is_string( $v ) ? $v : '';
}

/**
 * Returns email address for "Training als abgeschlossen melden" notification (from settings).
 *
 * @return string
 */
function soe_get_mail_training_completed_to() {
	$v = soe_get_setting( 'mail_training_completed_to' );
	return is_string( $v ) ? trim( $v ) : '';
}

/**
 * Returns email address for "Neues Mitglied angelegt" notification (from settings).
 *
 * @return string
 */
function soe_get_mail_mitglied_created_to() {
	$v = soe_get_setting( 'mail_mitglied_created_to' );
	return is_string( $v ) ? $v : '';
}

/**
 * E-Mail-Empfänger für Benachrichtigung when a Hauptleiter creates a new event.
 *
 * @return string
 */
function soe_get_mail_event_created_to() {
	$v = soe_get_setting( 'mail_event_created_to' );
	return is_string( $v ) ? trim( $v ) : '';
}

/**
 * Betreff für Willkommens-Mail an neu angelegten User (aus Einstellungen).
 *
 * @return string
 */
function soe_get_mail_user_new_subject() {
	$v = soe_get_setting( 'mail_user_new_subject' );
	return is_string( $v ) ? trim( $v ) : '';
}

/**
 * Mailtext-Vorlage für Willkommens-Mail an neu angelegten User (Platzhalter: {vorname}, {nachname}, {login}, {passwort_setzen_url}).
 *
 * @return string
 */
function soe_get_mail_user_new_body() {
	$v = soe_get_setting( 'mail_user_new_body' );
	return is_string( $v ) ? $v : '';
}
