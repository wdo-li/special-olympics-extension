<?php
/**
 * Floating help button + contact form (logged-in users only).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'soe_help_widget_enqueue', 20 );
add_action( 'admin_enqueue_scripts', 'soe_help_widget_enqueue', 20 );
add_action( 'wp_ajax_soe_submit_help', 'soe_ajax_submit_help' );

/**
 * Enqueues help widget assets for logged-in users (front and admin).
 *
 * @param string|null $hook_suffix Admin hook (unused on frontend).
 */
function soe_help_widget_enqueue( $hook_suffix = null ) {
	if ( ! is_user_logged_in() ) {
		return;
	}
	if ( function_exists( 'soe_is_mail_category_enabled' ) && ! soe_is_mail_category_enabled( SOE_MAIL_CAT_HELP ) ) {
		// If help sending is disabled in settings, do not show the icon/panel at all.
		return;
	}
	$base = plugin_dir_url( dirname( __FILE__ ) );
	wp_enqueue_style( 'soe-help-widget', $base . 'assets/css/help-widget.css', array(), SOE_PLUGIN_VERSION );
	wp_enqueue_script( 'soe-help-widget', $base . 'assets/js/help-widget.js', array( 'jquery' ), SOE_PLUGIN_VERSION, true );
	$mail_to = function_exists( 'soe_get_mail_help_to' ) ? soe_get_mail_help_to() : '';
	wp_localize_script(
		'soe-help-widget',
		'soeHelpWidget',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'soe_help_widget' ),
			'mailTo'  => $mail_to,
			'i18n'    => array(
				'title'    => __( 'Hilfe', 'special-olympics-extension' ),
				'info'     => __( 'Schreib uns kurz, wenn du eine Frage hast oder etwas nicht wie gewünscht funktioniert – wir melden uns zurück.', 'special-olympics-extension' ),
				'subject'  => __( 'Betreff', 'special-olympics-extension' ),
				'message'  => __( 'Nachricht', 'special-olympics-extension' ),
				'send'     => __( 'Senden', 'special-olympics-extension' ),
				'close'    => __( 'Schliessen', 'special-olympics-extension' ),
				'sent'     => __( 'Nachricht wurde gesendet.', 'special-olympics-extension' ),
				'error'    => __( 'Senden fehlgeschlagen.', 'special-olympics-extension' ),
				'wait'     => __( 'Bitte kurz warten, bevor du erneut sendest.', 'special-olympics-extension' ),
				'required' => __( 'Bitte Betreff und Nachricht ausfüllen.', 'special-olympics-extension' ),
			),
		)
	);
}

/**
 * AJAX: submit help form.
 */
function soe_ajax_submit_help() {
	check_ajax_referer( 'soe_help_widget', 'nonce' );
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => __( 'Nicht angemeldet.', 'special-olympics-extension' ) ) );
	}
	if ( function_exists( 'soe_is_mail_category_enabled' ) && ! soe_is_mail_category_enabled( SOE_MAIL_CAT_HELP ) ) {
		wp_send_json_error( array( 'message' => __( 'Hilfe-E-Mails sind in den Einstellungen deaktiviert.', 'special-olympics-extension' ) ) );
	}
	$user = wp_get_current_user();
	$uid  = (int) $user->ID;
	if ( $uid ) {
		$key = 'soe_help_rate_' . $uid;
		if ( get_transient( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Bitte kurz warten, bevor du erneut sendest.', 'special-olympics-extension' ) ) );
		}
	}
	$to = function_exists( 'soe_get_mail_help_to' ) ? soe_get_mail_help_to() : '';
	if ( ! is_email( $to ) ) {
		wp_send_json_error( array( 'message' => __( 'Kein Empfänger konfiguriert.', 'special-olympics-extension' ) ) );
	}
	$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
	$body    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
	if ( $subject === '' || $body === '' ) {
		wp_send_json_error( array( 'message' => __( 'Betreff und Nachricht sind erforderlich.', 'special-olympics-extension' ) ) );
	}
	$page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- UA is not stored raw; used for support context only.
	$ua       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	$lines = array(
		$body,
		'',
		'---',
		sprintf( /* translators: %s: username */ __( 'Benutzer: %s', 'special-olympics-extension' ), $user->user_login ),
		sprintf( /* translators: %s: display name */ __( 'Name: %s', 'special-olympics-extension' ), $user->display_name ),
		sprintf( /* translators: %s: email */ __( 'E-Mail: %s', 'special-olympics-extension' ), $user->user_email ),
		sprintf( /* translators: %s: URL */ __( 'Seite: %s', 'special-olympics-extension' ), $page_url ),
		$ua !== '' ? sprintf( /* translators: %s: user agent */ __( 'User-Agent: %s', 'special-olympics-extension' ), $ua ) : '',
	);
	$mail_body = implode( "\n", array_filter( $lines ) );
	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'From: Special Olympics Liechtenstein <info@specialolympics.li>',
	);
	if ( is_email( $user->user_email ) ) {
		$headers[] = 'Reply-To: ' . $user->user_email;
	}
	$mail_send = wp_mail( $to, wp_specialchars_decode( '[' . get_bloginfo( 'name' ) . '] ' . $subject, ENT_QUOTES ), $mail_body, $headers );
	if ( ! $mail_send ) {
		wp_send_json_error( array( 'message' => __( 'E-Mail konnte nicht versendet werden.', 'special-olympics-extension' ) ) );
	}
	if ( $uid ) {
		set_transient( 'soe_help_rate_' . $uid, 1, 2 * MINUTE_IN_SECONDS );
	}
	wp_send_json_success( array( 'message' => __( 'Nachricht wurde gesendet.', 'special-olympics-extension' ) ) );
}
