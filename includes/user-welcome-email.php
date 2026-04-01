<?php
/**
 * Willkommens-Mail an neu angelegte User.
 *
 * Überschreibt die WordPress-Standard-Benachrichtigung ("Benutzer benachrichtigen")
 * mit dem benutzerdefinierten Text aus den Einstellungen.
 * Der Platzhalter {passwort_setzen_url} enthält den echten Aktivierungslink.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_new_user_notification_email', 'soe_customize_new_user_notification_email', 10, 3 );
add_filter( 'wp_send_new_user_notification_to_user', 'soe_maybe_disable_user_welcome_mail', 10, 2 );

/**
 * Disables the default welcome e-mail to the new user when the category is turned off in settings.
 *
 * @param bool    $send Whether to send.
 * @param WP_User $user User object.
 * @return bool
 */
function soe_maybe_disable_user_welcome_mail( $send, $user ) {
	if ( function_exists( 'soe_is_mail_category_enabled' ) && ! soe_is_mail_category_enabled( SOE_MAIL_CAT_USER_WELCOME ) ) {
		return false;
	}
	return $send;
}

/**
 * Filters the new user notification email sent to the user.
 * Replaces the WordPress default email with custom text from settings.
 *
 * @param array   $wp_new_user_notification_email Array with 'to', 'subject', 'message', 'headers'.
 * @param WP_User $user                           User object for the new user.
 * @param string  $blogname                       The site title.
 * @return array Modified email array.
 */
function soe_customize_new_user_notification_email( $wp_new_user_notification_email, $user, $blogname ) {
	$custom_subject = function_exists( 'soe_get_mail_user_new_subject' ) ? soe_get_mail_user_new_subject() : '';
	$custom_body    = function_exists( 'soe_get_mail_user_new_body' ) ? soe_get_mail_user_new_body() : '';

	if ( $custom_subject === '' && $custom_body === '' ) {
		return $wp_new_user_notification_email;
	}

	$key = get_password_reset_key( $user );
	if ( is_wp_error( $key ) ) {
		$passwort_url = wp_lostpassword_url();
	} else {
		$passwort_url = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
	}

	$replace = array(
		'{vorname}'             => $user->first_name,
		'{nachname}'            => $user->last_name,
		'{login}'               => $user->user_login,
		'{email}'               => $user->user_email,
		'{passwort_setzen_url}' => $passwort_url,
		'{site_name}'           => $blogname,
	);

	if ( $custom_subject !== '' ) {
		$wp_new_user_notification_email['subject'] = str_replace( array_keys( $replace ), array_values( $replace ), $custom_subject );
	}

	if ( $custom_body !== '' ) {
		$wp_new_user_notification_email['message'] = str_replace( array_keys( $replace ), array_values( $replace ), $custom_body );
	}

	return $wp_new_user_notification_email;
}
