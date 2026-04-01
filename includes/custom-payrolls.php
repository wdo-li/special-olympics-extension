<?php
/**
 * Custom Admin UI for Payrolls (no CPT, direct DB).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'soe_custom_payrolls_menu', 5 );
add_action( 'admin_init', 'soe_payroll_process_bulk_send_before_output', 5 );

/**
 * Process bulk send before any output to avoid "headers already sent".
 */
function soe_payroll_process_bulk_send_before_output() {
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( $page !== 'soe-payroll-bulk-send' || ! isset( $_POST['soe_bulk_send'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! check_admin_referer( 'soe_payroll_bulk_send' ) ) {
		return;
	}
	$pending = soe_payroll_get_pending_mail();
	if ( empty( $pending ) ) {
		return;
	}
	$subject = soe_get_mail_payroll_subject();
	$body_template = soe_get_mail_payroll_body();
	$sent = 0;
	$failed = 0;
	foreach ( $pending as $p ) {
		$person_id = (int) ( $p['person_id'] ?? 0 );
		$to = get_field( 'e-mail', $person_id );
		if ( ! is_string( $to ) || trim( $to ) === '' ) {
			$failed++;
			continue;
		}
		$pdf_path = $p['pdf_path'] ?? '';
		if ( ! $pdf_path && function_exists( 'soe_payroll_generate_pdf' ) ) {
			$pdf_path = soe_payroll_generate_pdf( $p['id'] );
			if ( $pdf_path ) {
				soe_db_payroll_update( $p['id'], array( 'pdf_path' => $pdf_path, 'pdf_generated_at' => current_time( 'mysql' ) ) );
			}
		}
		$attachments = array();
		if ( $pdf_path && function_exists( 'soe_payroll_pdf_path_to_local' ) ) {
			$local = soe_payroll_pdf_path_to_local( $pdf_path );
			if ( $local && file_exists( $local ) && is_readable( $local ) ) {
				$attachments[] = $local;
			}
		}
		$body = function_exists( 'soe_payroll_replace_mail_placeholders' ) ? soe_payroll_replace_mail_placeholders( $body_template, $p['id'] ) : $body_template;
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: Special Olympics Liechtenstein <info@specialolympics.li>',
		);
		if ( wp_mail( $to, $subject, $body, $headers, $attachments ) ) {
			$now = current_time( 'mysql' );
			$log_raw = isset( $p['mail_sent_log'] ) ? $p['mail_sent_log'] : '';
			$log = is_string( $log_raw ) && $log_raw !== '' ? json_decode( $log_raw, true ) : array();
			if ( ! is_array( $log ) ) {
				$log = array();
			}
			$log[] = $now;
			soe_db_payroll_update( $p['id'], array( 'mail_sent_at' => $now, 'mail_text_sent' => $body, 'mail_sent_log' => wp_json_encode( $log ) ) );
			$sent++;
		} else {
			$failed++;
		}
	}
	set_transient( 'soe_payroll_bulk_sent', array( 'sent' => $sent, 'failed' => $failed ), 30 );
	wp_safe_redirect( admin_url( 'admin.php?page=soe-payrolls' ) );
	exit;
}

function soe_custom_payrolls_menu() {
	add_menu_page(
		__( 'Lohnabrechnung', 'special-olympics-extension' ),
		__( 'Lohnabrechnung', 'special-olympics-extension' ),
		'manage_options',
		'soe-payrolls',
		'soe_render_payroll_edit_or_redirect',
		'dashicons-money-alt',
		null
	);
	add_submenu_page( 'soe-payrolls', __( 'Neue Lohnabrechnung', 'special-olympics-extension' ), __( 'Neue Lohnabrechnung', 'special-olympics-extension' ), 'manage_options', 'soe-payroll-new', 'soe_render_payroll_new_page' );
	add_submenu_page( 'soe-payrolls', __( 'Historie', 'special-olympics-extension' ), __( 'Historie', 'special-olympics-extension' ), 'manage_options', 'soe-payroll-history', 'soe_render_payroll_history_page' );
	add_submenu_page( 'soe-payrolls', __( 'Bulk-Versand', 'special-olympics-extension' ), __( 'Bulk-Versand', 'special-olympics-extension' ), 'manage_options', 'soe-payroll-bulk-send', 'soe_render_payroll_bulk_send_page' );
	add_submenu_page( null, __( 'Lohnabrechnung bearbeiten', 'special-olympics-extension' ), '', 'manage_options', 'soe-payroll-edit', 'soe_render_payroll_edit_page' );
}

function soe_render_payroll_edit_or_redirect() {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( $id ) {
		soe_render_payroll_edit_page();
	} else {
		soe_render_payroll_open_page();
	}
}

function soe_render_payroll_edit_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! $id ) {
		echo '<p>' . esc_html__( 'Ungültige Lohnabrechnung.', 'special-olympics-extension' ) . '</p>';
		return;
	}
	$payroll = soe_db_payroll_get( $id );
	if ( ! $payroll ) {
		echo '<p>' . esc_html__( 'Lohnabrechnung nicht gefunden.', 'special-olympics-extension' ) . '</p>';
		return;
	}
	$person_post = get_post( $payroll['person_id'] ?? 0 );
	$person_name = $person_post ? $person_post->post_title : (string) ( $payroll['person_id'] ?? '' );
	$start = $payroll['period_start'] ?? '';
	$end = $payroll['period_end'] ?? '';
	$period = ( $start && $end ) ? date_i18n( 'd.m.Y', strtotime( $start ) ) . ' – ' . date_i18n( 'd.m.Y', strtotime( $end ) ) : ( $start . ' – ' . $end );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( sprintf( __( 'Lohnabrechnung: %1$s – %2$s', 'special-olympics-extension' ), $person_name, $period ) ); ?></h1>
		<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payrolls' ) ); ?>">&larr; <?php esc_html_e( 'Offene Lohnabrechnungen', 'special-olympics-extension' ); ?></a> |
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-history' ) ); ?>"><?php esc_html_e( 'Historie', 'special-olympics-extension' ); ?></a> |
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-bulk-send' ) ); ?>"><?php esc_html_e( 'Bulk-Versand', 'special-olympics-extension' ); ?></a></p>
		<div class="postbox" style="margin-top:20px;">
			<div class="inside">
				<?php soe_payroll_render_data_meta_box( $id ); ?>
			</div>
		</div>
	</div>
	<?php
}

function soe_render_payroll_bulk_send_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// Bulk send processing is handled in soe_payroll_process_bulk_send_before_output() to avoid "headers already sent".
	$pending = soe_payroll_get_pending_mail();
	$msg = '';
	if ( isset( $_GET['sent'] ) || isset( $_GET['failed'] ) ) {
		$s = isset( $_GET['sent'] ) ? (int) $_GET['sent'] : 0;
		$f = isset( $_GET['failed'] ) ? (int) $_GET['failed'] : 0;
		$msg = sprintf( __( 'Versand abgeschlossen: %d gesendet, %d fehlgeschlagen.', 'special-olympics-extension' ), $s, $f );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Bulk-Versand', 'special-olympics-extension' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Abgeschlossene Lohnabrechnungen, die noch nicht per Mail versendet wurden. Mit Standard-Text an alle versenden.', 'special-olympics-extension' ); ?></p>
		<?php if ( $msg ) : ?>
			<div class="notice notice-info"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>
		<?php if ( empty( $pending ) ) : ?>
			<p><?php esc_html_e( 'Keine ausstehenden Lohnabrechnungen.', 'special-olympics-extension' ); ?></p>
		<?php else : ?>
			<form method="post">
				<?php wp_nonce_field( 'soe_payroll_bulk_send' ); ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Person', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Zeitraum', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'E-Mail', 'special-olympics-extension' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pending as $p ) :
							$person_post = get_post( $p['person_id'] ?? 0 );
							$person_name = $person_post ? $person_post->post_title : (string) ( $p['person_id'] ?? '' );
							$email = get_field( 'e-mail', $p['person_id'] ?? 0 );
							$has_email = is_string( $email ) && trim( $email ) !== '';
						?>
						<tr>
							<td><?php echo esc_html( $person_name ); ?></td>
							<td><?php
								$ps = $p['period_start'] ?? '';
								$pe = $p['period_end'] ?? '';
								echo esc_html( ( $ps && $pe ) ? date_i18n( 'd.m.Y', strtotime( $ps ) ) . ' – ' . date_i18n( 'd.m.Y', strtotime( $pe ) ) : ( $ps . ' – ' . $pe ) );
							?></td>
							<td><?php echo $has_email ? esc_html( $email ) : '<span style="color:#b32d2e">' . esc_html__( 'Keine E-Mail', 'special-olympics-extension' ) . '</span>'; ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="submit">
					<button type="submit" name="soe_bulk_send" class="button button-primary"><?php esc_html_e( 'An diese Personen versenden', 'special-olympics-extension' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
