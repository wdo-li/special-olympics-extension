<?php
/**
 * Payroll (Lohnabrechnung): overview, create flow, data collection, edit screen, PDF, mail, history.
 *
 * Admin only. Custom table soe_payrolls with status draft → geprüft → abgeschlossen.
 * Data from training (attendance) and event (participations); two tables; Stundensätze/BH from settings.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Payroll status: draft. */
define( 'SOE_PAYROLL_STATUS_DRAFT', 'draft' );
/** Payroll status: abgeschlossen. */
define( 'SOE_PAYROLL_STATUS_ABGESCHLOSSEN', 'abgeschlossen' );

/** Meta: person (mitglied post ID). */
define( 'SOE_PAYROLL_PERSON_META', 'soe_payroll_person_id' );
/** Meta: period start (Y-m-d). */
define( 'SOE_PAYROLL_PERIOD_START_META', 'soe_payroll_period_start' );
/** Meta: period end (Y-m-d). */
define( 'SOE_PAYROLL_PERIOD_END_META', 'soe_payroll_period_end' );
/** Meta: status. */
define( 'SOE_PAYROLL_STATUS_META', 'soe_payroll_status' );
/** Meta: training table rows (array of row arrays). */
define( 'SOE_PAYROLL_TABLE_TRAINING_META', 'soe_payroll_table_training' );
/** Meta: event table rows. */
define( 'SOE_PAYROLL_TABLE_EVENT_META', 'soe_payroll_table_event' );
/** Meta: PDF file path. */
define( 'SOE_PAYROLL_PDF_PATH_META', 'soe_payroll_pdf_path' );
/** Meta: PDF generated at (datetime). */
define( 'SOE_PAYROLL_PDF_AT_META', 'soe_payroll_pdf_generated_at' );
/** Meta: mail sent at. */
define( 'SOE_PAYROLL_MAIL_AT_META', 'soe_payroll_mail_sent_at' );
/** Meta: mail text sent (for history). */
define( 'SOE_PAYROLL_MAIL_TEXT_META', 'soe_payroll_mail_text_sent' );

// Payroll uses custom tables (soe_payrolls); no CPT.

/**
 * Whether Dompdf is available for PDF generation (Composer vendor/autoload.php loaded).
 *
 * @return bool
 */
function soe_payroll_can_generate_pdf() {
	static $can = null;
	if ( $can === null ) {
		$autoload = dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
		$can = file_exists( $autoload ) && ( require_once $autoload ) && class_exists( '\Dompdf\Dompdf' );
	}
	return $can;
}

/**
 * Converts stored PDF path (URL) to local filesystem path for attachments/download.
 *
 * @param string $path URL or path stored in DB.
 * @return string Empty if not resolvable, else absolute path.
 */
function soe_payroll_pdf_path_to_local( $path ) {
	if ( empty( $path ) ) {
		return '';
	}
	$upload_dir = wp_upload_dir();
	if ( strpos( $path, $upload_dir['baseurl'] ) === 0 ) {
		return $upload_dir['basedir'] . substr( $path, strlen( $upload_dir['baseurl'] ) );
	}
	if ( file_exists( $path ) ) {
		return $path;
	}
	return '';
}

/**
 * Returns a map of person posts by ID for a list of mitglied post IDs.
 *
 * @param int[] $person_ids Person IDs.
 * @return array<int,WP_Post>
 */
function soe_payroll_get_person_posts_map( $person_ids ) {
	$person_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $person_ids ) ) ) );
	if ( empty( $person_ids ) ) {
		return array();
	}
	$posts = get_posts(
		array(
			'post_type'      => 'mitglied',
			'post_status'    => 'any',
			'post__in'       => $person_ids,
			'posts_per_page' => -1,
		)
	);
	$map = array();
	foreach ( $posts as $post ) {
		$map[ (int) $post->ID ] = $post;
	}
	return $map;
}

add_action( 'admin_init', 'soe_payroll_process_create_before_output', 5 );
add_action( 'admin_enqueue_scripts', 'soe_payroll_admin_scripts' );
add_action( 'wp_ajax_soe_payroll_abschliessen', 'soe_ajax_payroll_abschliessen' );
add_action( 'wp_ajax_soe_payroll_download_pdf', 'soe_ajax_payroll_download_pdf' );
add_action( 'wp_ajax_soe_payroll_send_mail', 'soe_ajax_payroll_send_mail' );
add_action( 'wp_ajax_soe_payroll_refresh_data', 'soe_ajax_payroll_refresh_data' );
add_action( 'wp_ajax_soe_payroll_delete', 'soe_ajax_payroll_delete' );
add_action( 'wp_ajax_soe_payroll_add_adjustment', 'soe_ajax_payroll_add_adjustment' );
add_action( 'wp_ajax_soe_payroll_delete_adjustment', 'soe_ajax_payroll_delete_adjustment' );
add_action( 'soe_payroll_cleanup_orphaned_pdfs', 'soe_payroll_cleanup_orphaned_pdfs_callback' );
add_action( 'admin_init', 'soe_payroll_schedule_cleanup_cron', 20 );

/**
 * Schedules daily cron to remove orphaned payroll PDFs (no DB row).
 */
function soe_payroll_schedule_cleanup_cron() {
	if ( ! function_exists( 'soe_table_payrolls' ) ) {
		return;
	}
	if ( wp_next_scheduled( 'soe_payroll_cleanup_orphaned_pdfs' ) ) {
		return;
	}
	wp_schedule_event( time(), 'daily', 'soe_payroll_cleanup_orphaned_pdfs' );
}

/**
 * Cron callback: delete PDF files in payroll-pdfs/ that have no matching soe_payrolls.pdf_path.
 */
function soe_payroll_cleanup_orphaned_pdfs_callback() {
	global $wpdb;
	if ( ! function_exists( 'soe_table_payrolls' ) ) {
		return;
	}
	$table = soe_table_payrolls();
	$rows = $wpdb->get_col( "SELECT pdf_path FROM $table WHERE pdf_path IS NOT NULL AND pdf_path != ''" );
	$referenced = array();
	foreach ( $rows as $path ) {
		$local = function_exists( 'soe_payroll_pdf_path_to_local' ) ? soe_payroll_pdf_path_to_local( $path ) : $path;
		if ( $local && file_exists( $local ) ) {
			$canonical = realpath( $local );
			if ( $canonical ) {
				$referenced[ $canonical ] = true;
			}
		}
	}
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'] . '/payroll-pdfs';
	if ( ! is_dir( $dir ) || ! is_readable( $dir ) ) {
		return;
	}
	$files = array_diff( scandir( $dir ), array( '.', '..', '.htaccess' ) );
	foreach ( $files as $file ) {
		$full = $dir . '/' . $file;
		if ( ! is_file( $full ) ) {
			continue;
		}
		$canonical = realpath( $full );
		if ( $canonical && ! isset( $referenced[ $canonical ] ) && is_writable( $full ) ) {
			@unlink( $full );
		}
	}
}

/**
 * Processes "Lohnabrechnung anlegen" POST before any output so redirects work (avoids "headers already sent").
 */
function soe_payroll_process_create_before_output() {
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	$submitted = isset( $_POST['soe_payroll_create'] ) || isset( $_POST['soe_confirm_overlap_submit'] )
		|| ( isset( $_POST['soe_confirm_overlap'] ) && $_POST['soe_confirm_overlap'] === '1' );
	if ( $page !== 'soe-payroll-new' || ! $submitted || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	check_admin_referer( 'soe_payroll_new' );

	$person_id = isset( $_POST['person_id'] ) ? (int) $_POST['person_id'] : 0;
	$start     = isset( $_POST['period_start'] ) ? sanitize_text_field( wp_unslash( $_POST['period_start'] ) ) : '';
	$end       = isset( $_POST['period_end'] ) ? sanitize_text_field( wp_unslash( $_POST['period_end'] ) ) : '';

	if ( ! $person_id || ! $start || ! $end ) {
		wp_safe_redirect( admin_url( 'admin.php?page=soe-payroll-new&error=' . rawurlencode( __( 'Bitte Person und Zeitraum wählen.', 'special-olympics-extension' ) ) ) );
		exit;
	}

	$overlapping = function_exists( 'soe_db_payroll_find_overlapping' ) ? soe_db_payroll_find_overlapping( $person_id, $start, $end, 0 ) : array();
	$confirm_overlap = ( isset( $_POST['soe_confirm_overlap'] ) && $_POST['soe_confirm_overlap'] === '1' )
		|| ( isset( $_POST['soe_confirm_overlap_submit'] ) && $_POST['soe_confirm_overlap_submit'] === '1' );
	if ( ! empty( $overlapping ) && ! $confirm_overlap ) {
		wp_safe_redirect( admin_url( 'admin.php?page=soe-payroll-new&overlap=1&person_id=' . $person_id . '&period_start=' . rawurlencode( $start ) . '&period_end=' . rawurlencode( $end ) ) );
		exit;
	}

	$new_id = soe_payroll_create_draft( $person_id, $start, $end );
	wp_safe_redirect( admin_url( 'admin.php?page=soe-payroll-edit&id=' . $new_id ) );
	exit;
}

/**
 * Renders the "Neue Lohnabrechnung" page: select person + period, create new payroll.
 */
function soe_render_payroll_new_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$error = '';
	if ( isset( $_GET['error'] ) && is_string( $_GET['error'] ) ) {
		$error = sanitize_text_field( wp_unslash( $_GET['error'] ) );
	}
	$show_overlap_popup = ! empty( $_GET['overlap'] );
	$preselect_person = isset( $_GET['person_id'] ) ? (int) $_GET['person_id'] : 0;
	$preselect_start  = isset( $_GET['period_start'] ) ? sanitize_text_field( wp_unslash( $_GET['period_start'] ) ) : '';
	$preselect_end    = isset( $_GET['period_end'] ) ? sanitize_text_field( wp_unslash( $_GET['period_end'] ) ) : '';
	if ( $show_overlap_popup ) {
		$preselect_person = isset( $_GET['person_id'] ) ? (int) $_GET['person_id'] : $preselect_person;
		$preselect_start  = isset( $_GET['period_start'] ) ? sanitize_text_field( wp_unslash( $_GET['period_start'] ) ) : $preselect_start;
		$preselect_end    = isset( $_GET['period_end'] ) ? sanitize_text_field( wp_unslash( $_GET['period_end'] ) ) : $preselect_end;
	}
	$selected_person = $preselect_person;
	$selected_start  = $preselect_start;
	$selected_end    = $preselect_end;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Neue Lohnabrechnung', 'special-olympics-extension' ); ?></h1>
		<?php if ( $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>
		<form method="post" action="" id="soe-payroll-new-form">
			<?php wp_nonce_field( 'soe_payroll_new' ); ?>
			<?php if ( $show_overlap_popup ) : ?>
				<input type="hidden" name="soe_confirm_overlap" id="soe_confirm_overlap" value="" />
			<?php endif; ?>
			<table class="form-table">
				<tr>
					<th><label><?php esc_html_e( 'Person', 'special-olympics-extension' ); ?></label></th>
					<td>
						<div class="soe-person-picker soe-payroll-person-picker" data-field-name="person_id" data-single="true" data-exclude-athletes="true">
							<input type="text" class="soe-pp-search" placeholder="<?php esc_attr_e( 'Name eingeben...', 'special-olympics-extension' ); ?>" autocomplete="off" />
							<div class="soe-pp-results"></div>
							<div class="soe-pp-selected"></div>
							<input type="hidden" class="soe-pp-hidden" name="person_id" id="person_id" value="<?php echo esc_attr( $selected_person ); ?>" required />
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="period_start"><?php esc_html_e( 'Zeitraum von', 'special-olympics-extension' ); ?></label></th>
					<td><input type="date" name="period_start" id="period_start" value="<?php echo esc_attr( $selected_start ); ?>" required /></td>
				</tr>
				<tr>
					<th><label for="period_end"><?php esc_html_e( 'Zeitraum bis', 'special-olympics-extension' ); ?></label></th>
					<td><input type="date" name="period_end" id="period_end" value="<?php echo esc_attr( $selected_end ); ?>" required /></td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="soe_payroll_create" class="button button-primary"><?php esc_html_e( 'Lohnabrechnung anlegen', 'special-olympics-extension' ); ?></button>
			</p>
		</form>
		<?php if ( $show_overlap_popup ) : ?>
		<script>
		(function() {
			var msg = <?php echo wp_json_encode( __( 'Eine Lohnabrechnung für diese Person überschneidet sich mit dem gewählten Zeitraum. Trotzdem anlegen?', 'special-olympics-extension' ) ); ?>;
			if ( confirm(msg) ) {
				document.getElementById('soe_confirm_overlap').value = '1';
				document.getElementById('soe-payroll-new-form').submit();
			}
		})();
		</script>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Creates a new draft payroll.
 *
 * @param int    $person_id Mitglied post ID.
 * @param string $start     Period start Y-m-d.
 * @param string $end       Period end Y-m-d.
 * @return int New payroll ID.
 */
function soe_payroll_create_draft( $person_id, $start, $end ) {
	$id = soe_db_payroll_insert( array( 'person_id' => $person_id, 'period_start' => $start, 'period_end' => $end, 'status' => SOE_PAYROLL_STATUS_DRAFT ) );
	soe_payroll_collect_and_save_data( $id );
	return $id;
}

/**
 * Returns payroll status.
 *
 * @param int $payroll_id Payroll ID.
 * @return string
 */
function soe_payroll_get_status( $payroll_id ) {
	$row = soe_db_payroll_get( $payroll_id );
	if ( ! $row ) {
		return SOE_PAYROLL_STATUS_DRAFT;
	}
	$s = $row['status'] ?? '';
	if ( $s === SOE_PAYROLL_STATUS_ABGESCHLOSSEN ) {
		return $s;
	}
	return SOE_PAYROLL_STATUS_DRAFT;
}

/**
 * Collects payroll data from training and event and saves to DB.
 *
 * @param int $payroll_id Payroll ID.
 */
function soe_payroll_collect_and_save_data( $payroll_id ) {
	$row = soe_db_payroll_get( $payroll_id );
	if ( ! $row ) {
		return;
	}
	$person_id = (int) ( $row['person_id'] ?? 0 );
	$start = $row['period_start'] ?? '';
	$end   = $row['period_end'] ?? '';
	if ( ! $person_id || ! $start || ! $end ) {
		return;
	}
	$training_rows = soe_payroll_collect_training_rows( $person_id, $start, $end );
	$event_rows = soe_payroll_collect_event_rows( $person_id, $start, $end );
	soe_db_payroll_save_rows( $payroll_id, $training_rows, $event_rows );
}

/**
 * Collects training attendance rows for a person in period.
 *
 * @param int    $person_id Mitglied post ID.
 * @param string $start     Period start Y-m-d.
 * @param string $end       Period end Y-m-d.
 * @return array Array of row arrays (sport, notes, qualification, duration, ref_no, quantity, chf_per_hour, chf_amount, session_dates).
 */
function soe_payroll_collect_training_rows( $person_id, $start, $end ) {
	// Include both running and completed trainings; sessions in period are counted.
	$trainings = soe_db_training_list( array( 'completed' => null, 'limit' => 500 ) );
	$rows = array();
	foreach ( $trainings as $t ) {
		$tid = (int) $t['id'];
		$sessions = soe_db_training_get_sessions( $tid );
		$attendance = soe_db_training_get_attendance( $tid );
		$role = soe_payroll_get_person_role_in_training( $tid, $person_id );
		if ( ! $role ) {
			continue;
		}
		$duration = $t['duration'] ?? '';
		$sport_slug = $t['sport_slug'] ?? '';
		$sport = $sport_slug ? ( get_term_by( 'slug', $sport_slug, 'sport' ) ? get_term_by( 'slug', $sport_slug, 'sport' )->name : $sport_slug ) : '';
		$ref_no = ( ! empty( $t['bh_override'] ) ) ? $t['bh_override'] : ( $sport_slug ? soe_get_bh_number_for_sport( $sport_slug ) : '' );
		$notes = $t['notes'] ?? '';
		$count = 0;
		$session_dates = array();
		foreach ( $sessions as $d ) {
			if ( $d < $start || $d > $end ) {
				continue;
			}
			if ( ! empty( $attendance[ $d ][ $person_id ] ) ) {
				$count++;
				$session_dates[] = $d;
			}
		}
		if ( $count === 0 ) {
			continue;
		}
		$grade_hl = function_exists( 'soe_get_mitglied_grade_hl' ) ? soe_get_mitglied_grade_hl( $person_id ) : '';
		$rate_key = soe_payroll_get_rate_key( $role, $duration, $grade_hl );
		$chf_per_hour = soe_get_hourly_rate( $rate_key );
		$chf_amount = $chf_per_hour !== '' && is_numeric( $chf_per_hour ) ? ( (float) $chf_per_hour * $count ) : 0;
		$rows[] = array(
			'sport'          => $sport,
			'training_id'    => $tid,
			'notes'          => is_string( $notes ) ? $notes : '',
			'qualification'  => $role,
			'duration'       => is_string( $duration ) ? $duration : '',
			'ref_no'         => $ref_no,
			'quantity'       => $count,
			'chf_per_hour'   => $chf_per_hour,
			'chf_amount'     => $chf_amount,
			'session_dates'  => $session_dates,
		);
	}
	return $rows;
}

/**
 * Gets the role of a person in a training (from relationship fields).
 *
 * @param int $training_id Training post ID.
 * @param int $person_id   Mitglied post ID.
 * @return string|null Role slug or null.
 */
function soe_payroll_get_person_role_in_training( $training_id, $person_id ) {
	$persons = soe_db_training_get_persons( $training_id );
	$role_map = soe_get_role_filter_map();
	foreach ( $persons as $role_key => $ids ) {
		if ( in_array( (int) $person_id, array_map( 'intval', (array) $ids ), true ) ) {
			return isset( $role_map[ $role_key ] ) ? $role_map[ $role_key ] : $role_key;
		}
	}
	return null;
}

/**
 * Maps qualification slug to display label (e.g. hauptleiter_in → Hauptleiter*in).
 *
 * @param string $qualification Qualification slug (e.g. hauptleiter_in, leiter_in_2a_60).
 * @return string Human-readable label.
 */
function soe_payroll_qualification_to_label( $qualification ) {
	if ( ! is_string( $qualification ) || $qualification === '' ) {
		return '';
	}
	$keys = defined( 'SOE_HOURLY_RATE_KEYS' ) ? SOE_HOURLY_RATE_KEYS : array();
	if ( isset( $keys[ $qualification ] ) ) {
		return $keys[ $qualification ];
	}
	$short_map = array(
		'hauptleiter_in'     => 'Hauptleiter*in',
		'leiter_in'          => 'Leiter*in',
		'assistenztrainer_in' => 'Assistenztrainer*in',
		'helfer_in'          => 'Helfer*in',
		'praktikant_in'      => 'Praktikant*in',
		'schueler_in'        => 'Schüler*in',
		'athlet_leader'      => 'Athlete Leader',
		'athlet_in'          => 'Athlet*in',
		'unified'            => 'Unified',
	);
	return isset( $short_map[ $qualification ] ) ? $short_map[ $qualification ] : $qualification;
}

/**
 * Builds hourly rate key for payroll (role + stufe + duration).
 *
 * @param string $role_slug Role slug.
 * @param string $duration  Duration label.
 * @param mixed  $grade_hl  grade_hl from member (1A, 1B for Hauptleiter).
 * @return string Key for SOE_HOURLY_RATE_KEYS.
 */
function soe_payroll_get_rate_key( $role_slug, $duration, $grade_hl ) {
	$d = is_string( $duration ) ? $duration : '';
	$g = is_string( $grade_hl ) ? trim( $grade_hl ) : '';
	if ( $role_slug === 'hauptleiter_in' ) {
		$stufe = ( $g === '1A' || $g === '1a' ) ? '1a' : '1b';
		$dur = soe_payroll_duration_to_rate_suffix( $d );
		return 'hauptleiter_in_' . $stufe . '_' . $dur;
	}
	if ( $role_slug === 'leiter_in' ) {
		return 'leiter_in_2a_' . soe_payroll_duration_to_rate_suffix( $d );
	}
	if ( $role_slug === 'assistenztrainer_in' ) {
		return 'assistenztrainer_in_2b_' . soe_payroll_duration_to_rate_suffix( $d );
	}
	if ( $role_slug === 'helfer_in' ) {
		return 'helfer_in_3a_' . soe_payroll_duration_to_rate_suffix( $d );
	}
	if ( $role_slug === 'praktikant_in' ) {
		return 'praktikant_in_3b_' . soe_payroll_duration_to_rate_suffix( $d );
	}
	if ( $role_slug === 'schueler_in' ) {
		return 'schueler_in_3b_' . soe_payroll_duration_to_rate_suffix( $d );
	}
	if ( $role_slug === 'athlet_leader' || $role_slug === 'athlete_leader' ) {
		return 'athlet_leader_3c_' . soe_payroll_duration_to_rate_suffix( $d );
	}
	return '';
}

/**
 * Maps duration label to rate key suffix (60, 90, more_2, more_4, hl, ski).
 *
 * @param string $duration Duration label.
 * @return string
 */
function soe_payroll_duration_to_rate_suffix( $duration ) {
	$d = strtolower( trim( $duration ) );
	if ( $d === '60' ) {
		return '60';
	}
	if ( $d === '90' ) {
		return '90';
	}
	if ( strpos( $d, '2' ) !== false || strpos( $d, 'mehr' ) !== false ) {
		return 'more_2';
	}
	if ( strpos( $d, '4' ) !== false ) {
		return 'more_4';
	}
	if ( strpos( $d, 'hl' ) !== false || strpos( $d, 'pauschale' ) !== false ) {
		return 'hl';
	}
	if ( strpos( $d, 'ski' ) !== false ) {
		return 'ski';
	}
	return '60';
}

/**
 * Collects event participation rows for a person in period.
 *
 * @param int    $person_id Mitglied post ID.
 * @param string $start     Period start Y-m-d.
 * @param string $end       Period end Y-m-d.
 * @return array
 */
function soe_payroll_collect_event_rows( $person_id, $start, $end ) {
	$events = soe_db_event_list( array( 'limit' => 500 ) );
	$rows = array();
	foreach ( $events as $e ) {
		$eid = (int) $e['id'];
		$event_date = $e['event_date'] ?? '';
		if ( ! $event_date || $event_date < $start || $event_date > $end ) {
			continue;
		}
		$persons = soe_db_event_get_persons( $eid );
		$role_map = soe_get_role_filter_map();
		$participant_role = null;
		foreach ( $persons as $role_key => $ids ) {
			if ( in_array( (int) $person_id, array_map( 'intval', (array) $ids ), true ) ) {
				$participant_role = isset( $role_map[ $role_key ] ) ? $role_map[ $role_key ] : $role_key;
				break;
			}
		}
		if ( ! $participant_role ) {
			continue;
		}
		$role = $participant_role;
		$duration = $e['duration'] ?? '';
		$title = $e['title'] ?? '';
		$notes = $e['notes'] ?? '';
		$sport_slug = $e['sport_slug'] ?? '';
		$bh_override = $e['bh_override'] ?? '';
		$ref_no = ! empty( $bh_override ) ? $bh_override : ( $sport_slug ? soe_get_bh_number_for_sport( $sport_slug ) : '' );
		$rate_key = soe_payroll_get_rate_key( $role, $duration, '' );
		$chf_per_hour = soe_get_hourly_rate( $rate_key );
		$count = 1;
		$chf_amount = $chf_per_hour !== '' && is_numeric( $chf_per_hour ) ? (float) $chf_per_hour : 0;
		$rows[] = array(
			'sport'         => 'Event',
			'event_title'   => $title,
			'event_id'      => $eid,
			'notes'         => is_string( $notes ) ? $notes : '',
			'qualification' => $role,
			'duration'      => is_string( $duration ) ? $duration : '',
			'ref_no'        => $ref_no,
			'quantity'      => $count,
			'chf_per_hour'  => $chf_per_hour,
			'chf_amount'    => $chf_amount,
			'session_dates' => array( $event_date ),
		);
	}
	return $rows;
}

/**
 * Renders the open payrolls page (Entwurf + geprüft): landing when clicking Lohnabrechnung.
 */
function soe_render_payroll_open_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$bulk_msg = get_transient( 'soe_payroll_bulk_sent' );
	if ( $bulk_msg && is_array( $bulk_msg ) ) {
		delete_transient( 'soe_payroll_bulk_sent' );
		if ( ! empty( $bulk_msg['disabled'] ) ) {
			$bulk_msg = __( 'Bulk-Versand übersprungen: E-Mail-Versand für Lohnabrechnungen ist in den Einstellungen deaktiviert.', 'special-olympics-extension' );
		} else {
			$s = isset( $bulk_msg['sent'] ) ? (int) $bulk_msg['sent'] : 0;
			$f = isset( $bulk_msg['failed'] ) ? (int) $bulk_msg['failed'] : 0;
			$bulk_msg = sprintf( __( 'Bulk-Versand abgeschlossen: %d gesendet, %d fehlgeschlagen.', 'special-olympics-extension' ), $s, $f );
		}
	}
	$person_filter = isset( $_GET['person_id'] ) ? (int) $_GET['person_id'] : 0;
	$list_args = array( 'limit' => 500, 'status' => SOE_PAYROLL_STATUS_DRAFT );
	if ( $person_filter ) {
		$list_args['person_id'] = $person_filter;
	}
	$payrolls = soe_db_payroll_list( $list_args );
	$all_open = soe_db_payroll_list( array( 'limit' => 500, 'status' => SOE_PAYROLL_STATUS_DRAFT ) );
	$person_ids = array_unique( array_map( function ( $p ) { return (int) ( $p['person_id'] ?? 0 ); }, $all_open ) );
	$person_ids = array_filter( $person_ids );
	$person_posts_map = soe_payroll_get_person_posts_map( $person_ids );
	$persons_for_filter = array();
	foreach ( $person_ids as $pid ) {
		$post = isset( $person_posts_map[ (int) $pid ] ) ? $person_posts_map[ (int) $pid ] : null;
		if ( $post ) {
			$persons_for_filter[] = $post;
		}
	}
	usort( $persons_for_filter, function ( $a, $b ) { return strcasecmp( $a->post_title, $b->post_title ); } );
	$base_url = admin_url( 'admin.php?page=soe-payrolls' );
	?>
	<div class="wrap">
		<?php if ( ! empty( $bulk_msg ) ) : ?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $bulk_msg ); ?></p></div>
		<?php endif; ?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Offene Lohnabrechnungen', 'special-olympics-extension' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Neue Lohnabrechnung', 'special-olympics-extension' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-history' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Historie', 'special-olympics-extension' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-bulk-send' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Bulk-Versand', 'special-olympics-extension' ); ?></a>
		<hr class="wp-header-end">

		<form method="get" class="soe-payroll-filter">
			<input type="hidden" name="page" value="soe-payrolls" />
			<p class="soe-filter-row">
				<select name="person_id">
					<option value=""><?php esc_html_e( 'Alle Personen', 'special-olympics-extension' ); ?></option>
					<?php foreach ( $persons_for_filter as $p ) : ?>
						<option value="<?php echo (int) $p->ID; ?>" <?php selected( $person_filter, $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filtern', 'special-olympics-extension' ); ?>" />
				<?php if ( $person_filter ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Filter zurücksetzen', 'special-olympics-extension' ); ?></a>
				<?php endif; ?>
			</p>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Person', 'special-olympics-extension' ); ?></th>
					<th><?php esc_html_e( 'Zeitraum', 'special-olympics-extension' ); ?></th>
					<th><?php esc_html_e( 'Status', 'special-olympics-extension' ); ?></th>
					<th><?php esc_html_e( 'Erstellt', 'special-olympics-extension' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $payrolls as $p ) :
					$pid = (int) ( $p['person_id'] ?? 0 );
					$person_post = isset( $person_posts_map[ $pid ] ) ? $person_posts_map[ $pid ] : null;
					$person_name = $person_post ? $person_post->post_title : (string) $pid;
					$start = $p['period_start'] ?? '';
					$end = $p['period_end'] ?? '';
					$period_fmt = ( $start && $end ) ? date_i18n( 'd.m.Y', strtotime( $start ) ) . ' – ' . date_i18n( 'd.m.Y', strtotime( $end ) ) : ( $start . ' – ' . $end );
					$created = $p['created_at'] ?? '';
					$status = soe_payroll_get_status( $p['id'] ?? 0 );
					$status_label = $status === SOE_PAYROLL_STATUS_DRAFT ? __( 'Entwurf', 'special-olympics-extension' ) : $status;
				?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-edit&id=' . (int) ( $p['id'] ?? 0 ) ) ); ?>"><?php echo esc_html( $person_name ); ?></a></td>
					<td><?php echo esc_html( $period_fmt ); ?></td>
					<td><?php echo esc_html( $status_label ); ?></td>
					<td><?php echo $created ? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $created ) ) ) : '–'; ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-edit&id=' . (int) ( $p['id'] ?? 0 ) ) ); ?>"><?php esc_html_e( 'Bearbeiten', 'special-olympics-extension' ); ?></a>
						| <a href="#" class="soe-payroll-delete" data-post-id="<?php echo (int) ( $p['id'] ?? 0 ); ?>"><?php esc_html_e( 'Löschen', 'special-olympics-extension' ); ?></a>
					</td>
				</tr>
				<?php endforeach; ?>
				<?php if ( empty( $payrolls ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Keine offenen Lohnabrechnungen.', 'special-olympics-extension' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Renders the Historie page: only abgeschlossen payrolls.
 */
function soe_render_payroll_history_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$person_filter = isset( $_GET['person_id'] ) ? (int) $_GET['person_id'] : 0;
	$list_args = array( 'limit' => 500, 'status' => SOE_PAYROLL_STATUS_ABGESCHLOSSEN );
	if ( $person_filter ) {
		$list_args['person_id'] = $person_filter;
	}
	$payrolls = soe_db_payroll_list( $list_args );
	$all_completed = soe_db_payroll_list( array( 'limit' => 500, 'status' => SOE_PAYROLL_STATUS_ABGESCHLOSSEN ) );
	$person_ids = array_unique( array_map( function ( $p ) { return (int) ( $p['person_id'] ?? 0 ); }, $all_completed ) );
	$person_ids = array_filter( $person_ids );
	$person_posts_map = soe_payroll_get_person_posts_map( $person_ids );
	$persons_for_filter = array();
	foreach ( $person_ids as $pid ) {
		$post = isset( $person_posts_map[ (int) $pid ] ) ? $person_posts_map[ (int) $pid ] : null;
		if ( $post ) {
			$persons_for_filter[] = $post;
		}
	}
	usort( $persons_for_filter, function ( $a, $b ) { return strcasecmp( $a->post_title, $b->post_title ); } );
	$by_person = array();
	foreach ( $payrolls as $p ) {
		$pid = (int) ( $p['person_id'] ?? 0 );
		if ( ! isset( $by_person[ $pid ] ) ) {
			$by_person[ $pid ] = array();
		}
		$by_person[ $pid ][] = $p;
	}
	$history_base = admin_url( 'admin.php?page=soe-payroll-history' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Lohnabrechnung – Historie', 'special-olympics-extension' ); ?></h1>
		<p><?php esc_html_e( 'Abgeschlossene Lohnabrechnungen. Pro Person: Abrechnungen mit Erstellung, Download, Versand.', 'special-olympics-extension' ); ?></p>

		<form method="get" class="soe-payroll-filter">
			<input type="hidden" name="page" value="soe-payroll-history" />
			<p class="soe-filter-row">
				<select name="person_id">
					<option value=""><?php esc_html_e( 'Alle Personen', 'special-olympics-extension' ); ?></option>
					<?php foreach ( $persons_for_filter as $p ) : ?>
						<option value="<?php echo (int) $p->ID; ?>" <?php selected( $person_filter, $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filtern', 'special-olympics-extension' ); ?>" />
				<?php if ( $person_filter ) : ?>
					<a href="<?php echo esc_url( $history_base ); ?>" class="button"><?php esc_html_e( 'Filter zurücksetzen', 'special-olympics-extension' ); ?></a>
				<?php endif; ?>
			</p>
		</form>

		<?php foreach ( $by_person as $person_id => $list ) : ?>
			<?php
			$person_post = isset( $person_posts_map[ (int) $person_id ] ) ? $person_posts_map[ (int) $person_id ] : null;
			$person_name = $person_post ? $person_post->post_title : (string) $person_id;
			?>
			<h2><?php echo esc_html( $person_name ); ?></h2>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Zeitraum', 'special-olympics-extension' ); ?></th>
						<th><?php esc_html_e( 'Status', 'special-olympics-extension' ); ?></th>
						<th><?php esc_html_e( 'Erstellt', 'special-olympics-extension' ); ?></th>
						<th><?php esc_html_e( 'PDF', 'special-olympics-extension' ); ?></th>
						<th><?php esc_html_e( 'Mail versendet', 'special-olympics-extension' ); ?></th>
						<th><?php esc_html_e( 'Aktionen', 'special-olympics-extension' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $list as $payroll ) : ?>
						<?php
						$payroll_id = isset( $payroll['id'] ) ? $payroll['id'] : 0;
						$status = soe_payroll_get_status( $payroll_id );
						$pdf_at = $payroll['pdf_generated_at'] ?? '';
						$pdf_path = $payroll['pdf_path'] ?? '';
						$mail_at = $payroll['mail_sent_at'] ?? '';
						$mail_text = isset( $payroll['mail_text_sent'] ) ? $payroll['mail_text_sent'] : '';
						$mail_log = isset( $payroll['mail_sent_log'] ) && is_string( $payroll['mail_sent_log'] ) && $payroll['mail_sent_log'] !== '' ? json_decode( $payroll['mail_sent_log'], true ) : array();
						if ( ! is_array( $mail_log ) ) {
							$mail_log = array();
						}
						$start = $payroll['period_start'] ?? '';
						$end = $payroll['period_end'] ?? '';
						$created = $payroll['created_at'] ?? '';
						$period_fmt = ( $start && $end ) ? date_i18n( 'd.m.Y', strtotime( $start ) ) . ' – ' . date_i18n( 'd.m.Y', strtotime( $end ) ) : ( $start . ' – ' . $end );
						$mail_dates = ! empty( $mail_log ) ? $mail_log : ( $mail_at ? array( $mail_at ) : array() );
						$mail_cell = ! empty( $mail_dates ) ? esc_html( implode( ', ', array_map( function ( $t ) { return date_i18n( 'd.m.Y H:i', strtotime( $t ) ); }, $mail_dates ) ) ) : '–';
						if ( $mail_text !== '' ) {
							$mail_cell .= ' <a href="#" class="soe-view-mail-text" data-text="' . esc_attr( $mail_text ) . '" title="' . esc_attr__( 'Mail-Text anzeigen', 'special-olympics-extension' ) . '">[' . esc_html__( 'Text', 'special-olympics-extension' ) . ']</a>';
						}
						$pdf_cell = $pdf_at ? date_i18n( 'd.m.Y H:i', strtotime( $pdf_at ) ) : '–';
						$detail_url = admin_url( 'admin.php?page=soe-payroll-edit&id=' . (int) $payroll_id );
						$download_url = wp_nonce_url( admin_url( 'admin-ajax.php?action=soe_payroll_download_pdf&post_id=' . (int) $payroll_id ), 'soe_payroll_admin', 'nonce' );
						?>
						<tr>
							<td><?php echo esc_html( $period_fmt ); ?></td>
							<td><?php echo esc_html( $status ); ?></td>
							<td><?php echo $created ? esc_html( date_i18n( 'd.m.Y H:i', strtotime( $created ) ) ) : '–'; ?></td>
							<td><?php echo esc_html( $pdf_cell ); ?></td>
							<td><?php echo $mail_cell; ?></td>
							<td>
								<a href="<?php echo esc_url( $detail_url ); ?>" title="<?php esc_attr_e( 'Detailansicht', 'special-olympics-extension' ); ?>" class="soe-payroll-action-icon"><span class="dashicons dashicons-visibility"></span></a>
								<?php if ( ! empty( $pdf_path ) ) : ?>
									<a href="<?php echo esc_url( $download_url ); ?>" title="<?php esc_attr_e( 'PDF herunterladen', 'special-olympics-extension' ); ?>" class="soe-payroll-action-icon"><span class="dashicons dashicons-download"></span></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endforeach; ?>
		<?php if ( empty( $by_person ) ) : ?>
			<p><?php esc_html_e( 'Keine Lohnabrechnungen vorhanden.', 'special-olympics-extension' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renders table body rows for payroll training or event rows.
 *
 * @param array  $rows          Array of row data.
 * @param bool   $is_events     True for events (use event_title, event_id link), false for trainings.
 * @param string $edit_page     Admin page for edit link (soe-training-edit or soe-event-edit).
 * @param string $edit_cap      Capability for edit link (edit_trainings or edit_events).
 */
function soe_payroll_render_rows_table_body( $rows, $is_events, $edit_page, $edit_cap ) {
	foreach ( $rows as $r ) {
		if ( $is_events ) {
			$first_label = isset( $r['event_title'] ) ? $r['event_title'] : ( isset( $r['sport'] ) ? $r['sport'] : '' );
			$link_id    = isset( $r['event_id'] ) ? (int) $r['event_id'] : 0;
		} else {
			$first_label = isset( $r['sport'] ) ? $r['sport'] : '';
			$link_id    = isset( $r['training_id'] ) ? (int) $r['training_id'] : 0;
		}
		$quantity_title = '';
		if ( ! $is_events && isset( $r['session_dates'] ) && is_array( $r['session_dates'] ) ) {
			$quantity_title = implode( ', ', array_map( function ( $d ) { return date_i18n( 'd.m.Y', strtotime( $d ) ); }, $r['session_dates'] ) );
		}
		?>
		<tr>
			<td><?php
			if ( $link_id && current_user_can( $edit_cap ) ) {
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $edit_page . '&id=' . $link_id ) ) . '">' . esc_html( $first_label ) . '</a>';
			} else {
				echo esc_html( $first_label );
			}
			?></td>
			<td><?php echo esc_html( isset( $r['notes'] ) ? $r['notes'] : '' ); ?></td>
			<td><?php echo esc_html( soe_payroll_qualification_to_label( isset( $r['qualification'] ) ? $r['qualification'] : '' ) ); ?></td>
			<td><?php echo esc_html( isset( $r['duration'] ) ? $r['duration'] : '' ); ?></td>
			<td><?php echo esc_html( isset( $r['ref_no'] ) ? $r['ref_no'] : '' ); ?></td>
			<td <?php echo $quantity_title ? 'title="' . esc_attr( $quantity_title ) . '"' : ''; ?>><?php echo (int) ( isset( $r['quantity'] ) ? $r['quantity'] : 0 ); ?></td>
			<td><?php echo esc_html( isset( $r['chf_per_hour'] ) ? $r['chf_per_hour'] : '' ); ?></td>
			<td><?php echo esc_html( isset( $r['chf_amount'] ) ? number_format( (float) ( $r['chf_amount'] ?? 0 ), 2 ) : '' ); ?></td>
		</tr>
		<?php
	}
}

/**
 * Renders the payroll data meta box: two tables, status, buttons.
 *
 * @param int|object $post_or_id Payroll ID or object with ->ID.
 */
function soe_payroll_render_data_meta_box( $post_or_id ) {
	$payroll_id = is_object( $post_or_id ) ? (int) $post_or_id->ID : (int) $post_or_id;
	if ( ! $payroll_id ) {
		return;
	}
	$row = soe_db_payroll_get( $payroll_id );
	if ( ! $row ) {
		return;
	}
	$status = soe_payroll_get_status( $payroll_id );
	$all_rows = soe_db_payroll_get_rows( $payroll_id );
	$training_rows = array_filter( $all_rows, function ( $r ) {
		return ! isset( $r['event_title'] ) || $r['event_title'] === '';
	} );
	$event_rows = array_filter( $all_rows, function ( $r ) {
		return isset( $r['event_title'] ) && $r['event_title'] !== '';
	} );
	$person_id = (int) ( $row['person_id'] ?? 0 );
	$period_start = $row['period_start'] ?? '';
	$period_end = $row['period_end'] ?? '';
	$base_total = 0;
	foreach ( array_merge( $training_rows, $event_rows ) as $r ) {
		$base_total += isset( $r['chf_amount'] ) ? (float) $r['chf_amount'] : 0;
	}
	$adjustments = soe_db_payroll_get_adjustments( $payroll_id );
	$adjustments_total = 0;
	foreach ( $adjustments as $a ) {
		$adjustments_total += (float) ( $a['amount'] ?? 0 );
	}
	$grand_total = $base_total + $adjustments_total;
	$readonly = $status === SOE_PAYROLL_STATUS_ABGESCHLOSSEN;
	?>
	<p><strong><?php esc_html_e( 'Status', 'special-olympics-extension' ); ?>:</strong> <?php echo esc_html( $status ); ?></p>
	<?php
	$row_for_buttons = soe_db_payroll_get( $payroll_id );
	$pdf_path = $row_for_buttons['pdf_path'] ?? '';
	$mail_sent_at = $row_for_buttons['mail_sent_at'] ?? '';
	$mail_sent_log_raw = isset( $row_for_buttons['mail_sent_log'] ) ? $row_for_buttons['mail_sent_log'] : '';
	$mail_sent_log = is_string( $mail_sent_log_raw ) && $mail_sent_log_raw !== '' ? json_decode( $mail_sent_log_raw, true ) : array();
	if ( ! is_array( $mail_sent_log ) ) {
		$mail_sent_log = array();
	}
	$mail_dates_display = ! empty( $mail_sent_log ) ? $mail_sent_log : ( $mail_sent_at ? array( $mail_sent_at ) : array() );
	$has_pdf = ! empty( $pdf_path );
	$mail_not_sent = empty( $mail_sent_at );
	?>
	<?php if ( ! empty( $mail_dates_display ) ) : ?>
		<p><strong><?php esc_html_e( 'Versandt am', 'special-olympics-extension' ); ?>:</strong> <?php echo esc_html( implode( ', ', array_map( function ( $t ) { return date_i18n( 'd.m.Y H:i', strtotime( $t ) ); }, $mail_dates_display ) ) ); ?></p>
	<?php endif; ?>
	<?php if ( ! $readonly ) : ?>
		<p>
			<button type="button" class="button soe-payroll-refresh-data" data-post-id="<?php echo (int) $payroll_id; ?>"><?php esc_html_e( 'Daten neu sammeln', 'special-olympics-extension' ); ?></button>
			<button type="button" class="button soe-payroll-download-pdf" data-post-id="<?php echo (int) $payroll_id; ?>"><?php esc_html_e( 'PDF herunterladen', 'special-olympics-extension' ); ?></button>
			<?php if ( function_exists( 'soe_export_can_xls' ) && soe_export_can_xls() ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'soe_export_payroll', 'id' => $payroll_id ), admin_url( 'admin-post.php' ) ), 'soe_export_payroll' ) ); ?>" class="button"><?php esc_html_e( 'Als Excel exportieren', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
			<button type="button" class="button soe-payroll-send-mail" data-post-id="<?php echo (int) $payroll_id; ?>"><?php esc_html_e( 'Mail senden', 'special-olympics-extension' ); ?></button>
			<button type="button" class="button button-primary soe-payroll-abschliessen" data-post-id="<?php echo (int) $payroll_id; ?>"><?php esc_html_e( 'Abschliessen', 'special-olympics-extension' ); ?></button>
			<button type="button" class="button soe-payroll-delete" data-post-id="<?php echo (int) $payroll_id; ?>" style="color:#b32d2e;"><?php esc_html_e( 'Lohnabrechnung löschen', 'special-olympics-extension' ); ?></button>
		</p>
	<?php elseif ( $has_pdf || $mail_not_sent ) : ?>
		<p>
			<?php if ( $has_pdf ) : ?>
				<button type="button" class="button soe-payroll-download-pdf" data-post-id="<?php echo (int) $payroll_id; ?>"><?php esc_html_e( 'PDF herunterladen', 'special-olympics-extension' ); ?></button>
			<?php endif; ?>
			<?php if ( function_exists( 'soe_export_can_xls' ) && soe_export_can_xls() ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'soe_export_payroll', 'id' => $payroll_id ), admin_url( 'admin-post.php' ) ), 'soe_export_payroll' ) ); ?>" class="button"><?php esc_html_e( 'Als Excel exportieren', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
			<?php if ( $mail_not_sent ) : ?>
				<button type="button" class="button soe-payroll-send-mail" data-post-id="<?php echo (int) $payroll_id; ?>"><?php esc_html_e( 'Mail senden', 'special-olympics-extension' ); ?></button>
			<?php endif; ?>
		</p>
	<?php endif; ?>
	<h3><?php esc_html_e( 'Trainingsanwesenheiten', 'special-olympics-extension' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Es werden ausschliesslich abgeschlossene Trainings berücksichtigt.', 'special-olympics-extension' ); ?></p>
	<table class="widefat striped soe-payroll-table" style="table-layout: fixed; width: 100%;">
		<colgroup>
			<col style="width: 12%" />
			<col style="width: 28%" />
			<col style="width: 14%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
			<col style="width: 11%" />
			<col style="width: 11%" />
		</colgroup>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Sportart', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Bemerkungen', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Qualifikation', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Dauer', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'BH-Nr.', 'special-olympics-extension' ); ?></th>
				<th title="<?php esc_attr_e( 'Hover für Datumsliste', 'special-olympics-extension' ); ?>"><?php esc_html_e( 'Anzahl', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'CHF/Std', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'CHF/Bereich', 'special-olympics-extension' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php soe_payroll_render_rows_table_body( $training_rows, false, 'soe-training-edit', 'edit_trainings' ); ?>
		</tbody>
	</table>
	<h3><?php esc_html_e( 'Events', 'special-olympics-extension' ); ?></h3>
	<table class="widefat striped soe-payroll-table" style="table-layout: fixed; width: 100%;">
		<colgroup>
			<col style="width: 12%" />
			<col style="width: 28%" />
			<col style="width: 14%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
			<col style="width: 8%" />
			<col style="width: 11%" />
			<col style="width: 11%" />
		</colgroup>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Event', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Bemerkungen', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Qualifikation', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Dauer', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'BH-Nr.', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Anzahl', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'CHF/Std', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'CHF/Bereich', 'special-olympics-extension' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php soe_payroll_render_rows_table_body( $event_rows, true, 'soe-event-edit', 'edit_events' ); ?>
		</tbody>
	</table>
	<h3><?php esc_html_e( 'Manuelle Änderungen', 'special-olympics-extension' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Zusätzliche Positionen (z. B. Nachzahlungen, Abzüge). Der Betrag kann positiv oder negativ sein. Wird nicht von «Daten neu sammeln» überschrieben.', 'special-olympics-extension' ); ?></p>
	<table class="widefat striped soe-payroll-table soe-payroll-adjustments" style="table-layout: fixed; width: 100%;">
		<colgroup>
			<col style="width: 60%" />
			<col style="width: 20%" />
			<?php if ( ! $readonly ) : ?><col style="width: 20%" /><?php endif; ?>
		</colgroup>
		<thead>
			<tr>
				<th><?php esc_html_e( 'Kommentar', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Betrag (CHF)', 'special-olympics-extension' ); ?></th>
				<?php if ( ! $readonly ) : ?><th></th><?php endif; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $adjustments as $a ) : ?>
				<tr data-adjustment-id="<?php echo (int) ( $a['id'] ?? 0 ); ?>">
					<td><?php echo esc_html( isset( $a['comment'] ) ? $a['comment'] : '' ); ?></td>
					<td><?php
					$amt = (float) ( $a['amount'] ?? 0 );
					echo esc_html( ( $amt >= 0 ? '+' : '' ) . number_format( $amt, 2 ) );
					?></td>
					<?php if ( ! $readonly ) : ?>
						<td>
							<button type="button" class="button button-link-delete soe-payroll-delete-adjustment" data-post-id="<?php echo (int) $payroll_id; ?>" data-adjustment-id="<?php echo (int) ( $a['id'] ?? 0 ); ?>"><?php esc_html_e( 'Löschen', 'special-olympics-extension' ); ?></button>
						</td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php if ( ! $readonly ) : ?>
		<p class="soe-payroll-add-adjustment-form">
			<input type="text" class="soe-adjustment-comment regular-text" placeholder="<?php esc_attr_e( 'Kommentar (z. B. Bonus, Abzug)', 'special-olympics-extension' ); ?>" />
			<input type="number" class="soe-adjustment-amount" step="0.01" placeholder="0.00" title="<?php esc_attr_e( 'Positiv = hinzufügen, negativ = abziehen', 'special-olympics-extension' ); ?>" />
			<button type="button" class="button soe-payroll-add-adjustment" data-post-id="<?php echo (int) $payroll_id; ?>"><?php esc_html_e( 'Hinzufügen', 'special-olympics-extension' ); ?></button>
		</p>
	<?php endif; ?>
	<p style="text-align: right">
		<?php if ( ! empty( $adjustments ) ) : ?>
			<strong><?php esc_html_e( 'Summe Trainings + Events', 'special-olympics-extension' ); ?>:</strong> CHF <?php echo esc_html( number_format( $base_total, 2 ) ); ?>
			<br/>
			<strong><?php esc_html_e( 'Manuelle Änderungen', 'special-olympics-extension' ); ?>:</strong> CHF <?php echo esc_html( ( $adjustments_total >= 0 ? '+' : '' ) . number_format( $adjustments_total, 2 ) ); ?>
			<br/>
		<?php endif; ?>
		<strong><?php esc_html_e( 'Gesamtsumme', 'special-olympics-extension' ); ?>:</strong> CHF <?php echo esc_html( number_format( $grand_total, 2 ) ); ?>
	</p>
	<?php
}

/**
 * Enqueues admin scripts for payroll.
 */
function soe_payroll_admin_scripts( $hook ) {
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	$is_payroll_page = ( strpos( $hook, 'soe-payroll' ) !== false ) || in_array( $page, array( 'soe-payrolls', 'soe-payroll-edit', 'soe-payroll-new', 'soe-payroll-history', 'soe-payroll-bulk-send' ), true );
	if ( ! $is_payroll_page ) {
		return;
	}
	$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
	$flatpickr_version = '4.6.13';
	if ( $page === 'soe-payroll-new' ) {
		wp_enqueue_style( 'flatpickr', $plugin_url . 'assets/vendor/flatpickr/flatpickr.min.css', array(), $flatpickr_version );
		wp_enqueue_style( 'flatpickr-theme', $plugin_url . 'assets/css/flatpickr-theme.css', array( 'flatpickr' ), SOE_PLUGIN_VERSION );
		wp_enqueue_script( 'flatpickr', $plugin_url . 'assets/vendor/flatpickr/flatpickr.min.js', array(), $flatpickr_version, true );
		wp_enqueue_script( 'flatpickr-de', $plugin_url . 'assets/vendor/flatpickr/l10n-de.js', array( 'flatpickr' ), $flatpickr_version, true );
		// Person picker for person selection.
		wp_enqueue_style( 'soe-person-picker', $plugin_url . 'assets/css/person-picker.css', array(), SOE_PLUGIN_VERSION );
		wp_enqueue_script( 'soe-person-picker', $plugin_url . 'assets/js/person-picker.js', array( 'jquery' ), SOE_PLUGIN_VERSION, true );
	}
	$payroll_deps = array( 'jquery' );
	if ( $page === 'soe-payroll-new' ) {
		$payroll_deps[] = 'flatpickr-de';
		$payroll_deps[] = 'soe-person-picker';
	}
	wp_enqueue_script(
		'soe-admin-payroll',
		$plugin_url . 'assets/js/admin-payroll.js',
		$payroll_deps,
		SOE_PLUGIN_VERSION,
		true
	);
	wp_localize_script( 'soe-admin-payroll', 'soePayrollAdmin', array(
		'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
		'nonce'             => wp_create_nonce( 'soe_payroll_admin' ),
		'personSearchNonce' => wp_create_nonce( 'soe_person_search' ),
		'historyUrl'        => admin_url( 'admin.php?page=soe-payroll-history' ),
		'openUrl'           => admin_url( 'admin.php?page=soe-payrolls' ),
		'mailSubject'       => soe_get_mail_payroll_subject(),
		'mailBody'          => soe_get_mail_payroll_body(),
		'i18n'              => array(
			'sendMail'         => __( 'Mail senden', 'special-olympics-extension' ),
			'cancel'           => __( 'Abbrechen', 'special-olympics-extension' ),
			'subject'          => __( 'Betreff', 'special-olympics-extension' ),
			'message'          => __( 'Nachricht', 'special-olympics-extension' ),
			'sent'             => __( 'Mail wurde gesendet.', 'special-olympics-extension' ),
			'error'            => __( 'Fehler beim Senden.', 'special-olympics-extension' ),
			'refreshing'       => __( 'Wird gesammelt…', 'special-olympics-extension' ),
			'deleteConfirm'    => __( 'Lohnabrechnung unwiderruflich löschen?', 'special-olympics-extension' ),
			'addAdjustment'    => __( 'Hinzufügen', 'special-olympics-extension' ),
			'deleteAdjConfirm' => __( 'Position wirklich löschen?', 'special-olympics-extension' ),
		),
	) );
}

/**
 * Returns payrolls (draft or abgeschlossen) that have not been mailed yet (for bulk send).
 *
 * @return array
 */
function soe_payroll_get_pending_mail() {
	global $wpdb;
	$table = soe_table_payrolls();
	return $wpdb->get_results(
		"SELECT * FROM $table WHERE status IN ('draft', 'abgeschlossen') AND (mail_sent_at IS NULL OR mail_sent_at = '') ORDER BY period_end DESC",
		ARRAY_A
	);
}

/**
 * AJAX: mark payroll as abgeschlossen and generate PDF.
 */
function soe_ajax_payroll_abschliessen() {
	check_ajax_referer( 'soe_payroll_admin', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'manage_options' ) || ! soe_db_payroll_get( $post_id ) ) {
		wp_send_json_error();
	}
	$path = soe_payroll_generate_pdf( $post_id );
	soe_db_payroll_update( $post_id, array(
		'status' => SOE_PAYROLL_STATUS_ABGESCHLOSSEN,
		'pdf_path' => $path ?: '',
		'pdf_generated_at' => $path ? current_time( 'mysql' ) : null,
	) );
	wp_send_json_success( array( 'reload' => true ) );
}

/**
 * Sanitizes a name for use in PDF filename (Umlaute, spaces, special chars).
 *
 * @param string $name Name part (e.g. Vorname or Nachname).
 * @return string
 */
function soe_payroll_sanitize_filename_part( $name ) {
	$map = array( 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss' );
	$s   = str_replace( array_keys( $map ), array_values( $map ), $name );
	$s   = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $s );
	$s   = preg_replace( '/_+/', '_', trim( $s, '_' ) );
	return $s !== '' ? $s : 'Person';
}

/**
 * Replaces mail placeholders with payroll-specific values.
 *
 * @param string $body       Mail body template.
 * @param int    $payroll_id Payroll ID.
 * @return string
 */
function soe_payroll_replace_mail_placeholders( $body, $payroll_id ) {
	$row = soe_db_payroll_get( $payroll_id );
	if ( ! $row ) {
		return $body;
	}
	$person_id = (int) ( $row['person_id'] ?? 0 );
	$start     = $row['period_start'] ?? '';
	$end       = $row['period_end'] ?? '';
	$vorname   = is_string( get_field( 'vorname', $person_id ) ) ? get_field( 'vorname', $person_id ) : '';
	$nachname  = is_string( get_field( 'nachname', $person_id ) ) ? get_field( 'nachname', $person_id ) : '';
	$period_label = ( $start && $end ) ? date_i18n( 'd.m.Y', strtotime( $start ) ) . ' – ' . date_i18n( 'd.m.Y', strtotime( $end ) ) : '';
	$all_rows  = soe_db_payroll_get_rows( $payroll_id );
	$grand_total = 0;
	foreach ( $all_rows as $r ) {
		$grand_total += isset( $r['chf_amount'] ) ? (float) $r['chf_amount'] : 0;
	}
	$adjustments = soe_db_payroll_get_adjustments( $payroll_id );
	foreach ( $adjustments as $a ) {
		$grand_total += (float) ( $a['amount'] ?? 0 );
	}
	$betrag_chf = number_format( $grand_total, 2 );
	return str_replace(
		array( '{{vorname}}', '{{nachname}}', '{{period_label}}', '{{betrag_chf}}' ),
		array( $vorname, $nachname, $period_label, $betrag_chf ),
		$body
	);
}

/**
 * Generates PDF (or HTML) for payroll. Returns file path or empty string.
 *
 * @param int  $payroll_id Payroll post ID.
 * @param bool $persist    If true (default), save to payroll-pdfs/; if false, write to temp file (caller must unlink).
 * @return string File path (persist) or temp path (stream-only).
 */
function soe_payroll_generate_pdf( $payroll_id, $persist = true ) {
	$row = soe_db_payroll_get( $payroll_id );
	if ( ! $row ) {
		return '';
	}
	$person_id = (int) ( $row['person_id'] ?? 0 );
	$start = $row['period_start'] ?? '';
	$end = $row['period_end'] ?? '';
	$vorname = get_field( 'vorname', $person_id );
	$nachname = get_field( 'nachname', $person_id );
	$strasse = get_field( 'strasse', $person_id );
	$plz = get_field( 'postleitzahl', $person_id );
	$ort = get_field( 'ort', $person_id );
	// Bank fields are sub-fields of the ACF group 'bank_informationen'
	$bank_info = get_field( 'bank_informationen', $person_id );
	$bank_name = is_array( $bank_info ) && isset( $bank_info['bank_name'] ) ? $bank_info['bank_name'] : '';
	$bank_iban = is_array( $bank_info ) && isset( $bank_info['bank_iban'] ) ? $bank_info['bank_iban'] : '';
	// Load rows from custom database table.
	$all_rows = soe_db_payroll_get_rows( $payroll_id );
	$training_rows = array_values( array_filter( $all_rows, function ( $r ) {
		return ! isset( $r['event_title'] ) || $r['event_title'] === '';
	} ) );
	$event_rows = array_values( array_filter( $all_rows, function ( $r ) {
		return isset( $r['event_title'] ) && $r['event_title'] !== '';
	} ) );
	$member_name = trim( ( is_string( $vorname ) ? $vorname : '' ) . ' ' . ( is_string( $nachname ) ? $nachname : '' ) );
	$period_label = ( $start && $end ) ? date_i18n( 'd.m.Y', strtotime( $start ) ) . ' – ' . date_i18n( 'd.m.Y', strtotime( $end ) ) : ( $start . ' – ' . $end );
	$table_rows_html = '';
	foreach ( array_merge( $training_rows, $event_rows ) as $r ) {
		$sport = isset( $r['sport'] ) ? $r['sport'] : ( isset( $r['event_title'] ) ? $r['event_title'] : '' );
		$qual_label = soe_payroll_qualification_to_label( isset( $r['qualification'] ) ? $r['qualification'] : '' );
		$table_rows_html .= '<tr><td>' . esc_html( $sport ) . '</td><td>' . esc_html( isset( $r['notes'] ) ? $r['notes'] : '' ) . '</td><td>' . esc_html( $qual_label ) . '</td><td class="center">' . esc_html( isset( $r['duration'] ) ? $r['duration'] : '' ) . '</td><td class="center">' . esc_html( isset( $r['ref_no'] ) ? $r['ref_no'] : '' ) . '</td><td class="right">' . (int) ( isset( $r['quantity'] ) ? $r['quantity'] : 0 ) . '</td><td class="right">' . esc_html( isset( $r['chf_per_hour'] ) ? $r['chf_per_hour'] : '' ) . '</td><td class="right">' . ( isset( $r['chf_amount'] ) ? number_format( (float) $r['chf_amount'], 2 ) : '' ) . '</td></tr>';
	}
	$adjustments = soe_db_payroll_get_adjustments( $payroll_id );
	foreach ( $adjustments as $a ) {
		$comment = isset( $a['comment'] ) ? $a['comment'] : '';
		$amount = (float) ( $a['amount'] ?? 0 );
		$amt_display = ( $amount >= 0 ? '+' : '' ) . number_format( $amount, 2 );
		$table_rows_html .= '<tr><td>Manuelle Änderung</td><td>' . esc_html( $comment ) . '</td><td>–</td><td class="center">–</td><td class="center">–</td><td class="right">–</td><td class="right">–</td><td class="right">' . esc_html( $amt_display ) . '</td></tr>';
	}
	$grand_total = 0;
	foreach ( array_merge( $training_rows, $event_rows ) as $r ) {
		$grand_total += isset( $r['chf_amount'] ) ? (float) $r['chf_amount'] : 0;
	}
	foreach ( $adjustments as $a ) {
		$grand_total += (float) ( $a['amount'] ?? 0 );
	}
	$templates_dir = dirname( dirname( __FILE__ ) ) . '/assets/payroll/templates';
	$template_path = $templates_dir . '/settlement-template.html';
	if ( ! file_exists( $template_path ) || ! is_readable( $template_path ) ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Payroll template missing', array( 'template_path' => $template_path ) );
		}
		return '';
	}
	$html = file_get_contents( $template_path );
	$plugin_dir = dirname( dirname( __FILE__ ) );

	// Fonts as base64 data URIs for reliable embedding in PDF.
	$font_light_path  = $plugin_dir . '/assets/payroll/templates/Ubuntu-Light.ttf';
	$font_medium_path = $plugin_dir . '/assets/payroll/templates/Ubuntu-Medium.ttf';
	$font_light_src   = '';
	$font_medium_src  = '';
	if ( file_exists( $font_light_path ) && is_readable( $font_light_path ) ) {
		$font_light_src = 'data:font/truetype;base64,' . base64_encode( file_get_contents( $font_light_path ) );
	}
	if ( file_exists( $font_medium_path ) && is_readable( $font_medium_path ) ) {
		$font_medium_src = 'data:font/truetype;base64,' . base64_encode( file_get_contents( $font_medium_path ) );
	}

	// Logo from assets/img/logo (SVG) as base64 for reliable embedding in PDF.
	$logo_path  = $plugin_dir . '/assets/img/logo/Logo-center.svg';
	$logo_src   = '';
	if ( file_exists( $logo_path ) && is_readable( $logo_path ) ) {
		$logo_src = 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( $logo_path ) );
	}
	$org_logo_img = $logo_src ? '<img src="' . esc_attr( $logo_src ) . '" alt="" class="org-logo" />' : '';
	$replace = array(
		'{{font_ubuntu_light}}'  => $font_light_src,
		'{{font_ubuntu_medium}}' => $font_medium_src,
		'{{org_logo_img}}'       => $org_logo_img,
		'{{period_label}}'       => $period_label,
		'{{date_from}}'      => $start ? date_i18n( 'd.m.Y', strtotime( $start ) ) : '',
		'{{date_to}}'        => $end ? date_i18n( 'd.m.Y', strtotime( $end ) ) : '',
		'{{created_date}}'   => $row['created_at'] ? date_i18n( 'd.m.Y', strtotime( $row['created_at'] ) ) : '',
		'{{org_name}}'       => 'Special Olympics',
		'{{org_address_line1}}' => '',
		'{{org_address_line2}}' => '',
		'{{member_name}}'   => $member_name,
		'{{member_street}}' => trim( ( is_string( $strasse ) ? $strasse : '' ) . ' ' . ( is_string( get_field( 'hausnummer', $person_id ) ) ? get_field( 'hausnummer', $person_id ) : '' ) ),
		'{{member_zip}}'    => is_string( $plz ) ? $plz : '',
		'{{member_city}}'   => is_string( $ort ) ? $ort : '',
		'{{member_country}}' => '',
		'{{bank_name}}'     => is_string( $bank_name ) ? $bank_name : '',
		'{{iban}}'          => is_string( $bank_iban ) ? $bank_iban : '',
		'{{table_rows}}'    => $table_rows_html,
		'{{grand_total_chf}}' => number_format( $grand_total, 2 ),
	);
	$html = str_replace( array_keys( $replace ), array_values( $replace ), $html );
	$upload_dir = wp_upload_dir();
	$subdir = '/payroll-pdfs';
	$dir = $upload_dir['basedir'] . $subdir;
	if ( ! wp_mkdir_p( $dir ) ) {
		return '';
	}
	// Ensure .htaccess protects this folder (no direct URL access; download only via AJAX).
	$htaccess_path = $dir . '/.htaccess';
	if ( ! file_exists( $htaccess_path ) ) {
		$htaccess_content = "# Payroll PDFs: no direct access; download via admin-ajax.php only.\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>";
		$written = file_put_contents( $htaccess_path, $htaccess_content );
		if ( $written === false && function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Payroll .htaccess write failed', array( 'path' => $htaccess_path ) );
		}
	}
	$vorname_safe  = soe_payroll_sanitize_filename_part( (string) $vorname );
	$nachname_safe = soe_payroll_sanitize_filename_part( (string) $nachname );
	$base_name     = 'Lohnabrechnung_' . $nachname_safe . '_' . $vorname_safe . '_' . (int) $payroll_id;

	if ( soe_payroll_can_generate_pdf() ) {
		try {
			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', true );
			$options->set( 'isFontSubsettingEnabled', true );
			// Chroot auf Plugin-Verzeichnis setzen, damit relative Pfade funktionieren.
			$options->set( 'chroot', $plugin_dir );
			$dompdf = new \Dompdf\Dompdf( $options );

			// Register custom fonts with Dompdf
			$font_metrics = $dompdf->getFontMetrics();
			if ( file_exists( $font_light_path ) ) {
				$font_metrics->registerFont( array( 'family' => 'Ubuntu Light', 'style' => 'normal', 'weight' => 'normal' ), $font_light_path );
			}
			if ( file_exists( $font_medium_path ) ) {
				$font_metrics->registerFont( array( 'family' => 'Ubuntu Medium', 'style' => 'normal', 'weight' => 'normal' ), $font_medium_path );
			}

			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			$pdf_output = $dompdf->output();
			if ( $persist ) {
				$filename = $base_name . '.pdf';
				$path = $dir . '/' . $filename;
				$written = file_put_contents( $path, $pdf_output );
				if ( $written === false ) {
					if ( function_exists( 'soe_debug_log' ) ) {
						soe_debug_log( 'Payroll PDF write failed', array( 'path' => $path ) );
					}
					return '';
				}
				return $path;
			}
			// Draft: stream only – write to temp file (caller must unlink).
			$path = wp_tempnam( 'payroll-' . $payroll_id );
			if ( $path && file_put_contents( $path, $pdf_output ) !== false ) {
				return $path;
			}
			if ( $path && file_exists( $path ) ) {
				@unlink( $path );
			}
			return '';
		} catch ( Exception $e ) {
			if ( function_exists( 'soe_debug_log' ) ) {
				soe_debug_log( 'Payroll PDF generation failed', array( 'error' => $e->getMessage() ) );
			}
		}
	}

	$filename = $base_name . '.html';
	$path = $dir . '/' . $filename;
	$written = file_put_contents( $path, $html );
	if ( $written === false ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Payroll HTML fallback write failed', array( 'path' => $path ) );
		}
		return '';
	}
	return $path;
}

/**
 * AJAX: download PDF (or HTML).
 */
function soe_ajax_payroll_download_pdf() {
	check_ajax_referer( 'soe_payroll_admin', 'nonce' );
	$post_id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'manage_options' ) || ! soe_db_payroll_get( $post_id ) ) {
		wp_die( 'Invalid' );
	}
	$row = soe_db_payroll_get( $post_id );
	$status = soe_payroll_get_status( $post_id );
	$path = '';
	if ( $status === SOE_PAYROLL_STATUS_ABGESCHLOSSEN ) {
		// Abgeschlossene Lohnabrechnungen: bestehende PDF beibehalten (Snapshot).
		$path = $row['pdf_path'] ?? '';
		if ( ! $path ) {
			// Fallback: Falls noch keine PDF existiert (ältere Datensätze), einmalig generieren und speichern.
			$path = soe_payroll_generate_pdf( $post_id );
			if ( $path ) {
				soe_db_payroll_update( $post_id, array( 'pdf_path' => $path, 'pdf_generated_at' => current_time( 'mysql' ) ) );
			}
		}
	} else {
		// Draft: generate to temp file, stream, then delete (do not save to payroll-pdfs/).
		$path = soe_payroll_generate_pdf( $post_id, false );
	}
	if ( $path ) {
		$local = ( $status === SOE_PAYROLL_STATUS_ABGESCHLOSSEN && function_exists( 'soe_payroll_pdf_path_to_local' ) ) ? soe_payroll_pdf_path_to_local( $path ) : $path;
		if ( $local && file_exists( $local ) && is_readable( $local ) ) {
			$filename = basename( $path );
			if ( $status !== SOE_PAYROLL_STATUS_ABGESCHLOSSEN ) {
				$vorname = get_field( 'vorname', (int) ( $row['person_id'] ?? 0 ) );
				$nachname = get_field( 'nachname', (int) ( $row['person_id'] ?? 0 ) );
				$filename = 'Lohnabrechnung_' . soe_payroll_sanitize_filename_part( (string) $nachname ) . '_' . soe_payroll_sanitize_filename_part( (string) $vorname ) . '_' . $post_id . '.pdf';
			}
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
			header( 'Content-Length: ' . filesize( $local ) );
			readfile( $local );
			if ( $status !== SOE_PAYROLL_STATUS_ABGESCHLOSSEN ) {
				@unlink( $local );
			}
			exit;
		}
		if ( $status !== SOE_PAYROLL_STATUS_ABGESCHLOSSEN && $path && file_exists( $path ) ) {
			@unlink( $path );
		}
		wp_die( esc_html__( 'PDF konnte nicht geladen werden.', 'special-olympics-extension' ) );
	}
	wp_die( esc_html__( 'PDF konnte nicht erstellt werden.', 'special-olympics-extension' ) );
}

/**
 * AJAX: refresh payroll data (recollect from training/event).
 */
function soe_ajax_payroll_refresh_data() {
	check_ajax_referer( 'soe_payroll_admin', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'manage_options' ) || ! soe_db_payroll_get( $post_id ) ) {
		wp_send_json_error();
	}
	if ( soe_payroll_get_status( $post_id ) === SOE_PAYROLL_STATUS_ABGESCHLOSSEN ) {
		wp_send_json_error( array( 'message' => __( 'Abgeschlossene Lohnabrechnungen können nicht aktualisiert werden.', 'special-olympics-extension' ) ) );
	}
	soe_payroll_collect_and_save_data( $post_id );
	wp_send_json_success();
}

/**
 * AJAX: delete payroll (only when status is draft). Removes record and all rows from DB.
 */
function soe_ajax_payroll_delete() {
	check_ajax_referer( 'soe_payroll_admin', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error();
	}
	$row = soe_db_payroll_get( $post_id );
	if ( ! $row ) {
		wp_send_json_error();
	}
	$current_status = soe_payroll_get_status( $post_id );
	if ( $current_status !== SOE_PAYROLL_STATUS_DRAFT ) {
		wp_send_json_error( array( 'message' => __( 'Nur Entwürfe können gelöscht werden.', 'special-olympics-extension' ) ) );
	}
	soe_db_payroll_delete( $post_id );
	wp_send_json_success( array( 'redirect' => admin_url( 'admin.php?page=soe-payrolls' ) ) );
}

/**
 * AJAX: send payroll mail.
 */
function soe_ajax_payroll_send_mail() {
	check_ajax_referer( 'soe_payroll_admin', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'manage_options' ) || ! soe_db_payroll_get( $post_id ) ) {
		wp_send_json_error();
	}
	$row = soe_db_payroll_get( $post_id );
	$person_id = (int) ( $row['person_id'] ?? 0 );
	$to = get_field( 'e-mail', $person_id );
	if ( ! is_string( $to ) || trim( $to ) === '' ) {
		wp_send_json_error( array( 'message' => __( 'Keine E-Mail-Adresse beim Mitglied hinterlegt.', 'special-olympics-extension' ) ) );
	}
	$status = soe_payroll_get_status( $post_id );
	$pdf_path = $row['pdf_path'] ?? '';
	$temp_pdf = '';
	if ( ! $pdf_path ) {
		if ( $status === SOE_PAYROLL_STATUS_ABGESCHLOSSEN ) {
			$pdf_path = soe_payroll_generate_pdf( $post_id, true );
			if ( $pdf_path ) {
				soe_db_payroll_update( $post_id, array( 'pdf_path' => $pdf_path, 'pdf_generated_at' => current_time( 'mysql' ) ) );
			}
		} else {
			$temp_pdf = soe_payroll_generate_pdf( $post_id, false );
			$pdf_path = $temp_pdf;
		}
	}
	$attachments = array();
	$local = $temp_pdf ? $temp_pdf : ( function_exists( 'soe_payroll_pdf_path_to_local' ) ? soe_payroll_pdf_path_to_local( $pdf_path ) : '' );
	if ( $local && file_exists( $local ) && is_readable( $local ) ) {
		$attachments[] = $local;
	}
	$subject = isset( $_POST['subject'] ) && is_string( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : soe_get_mail_payroll_subject();
	$body_raw = isset( $_POST['body'] ) && is_string( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : soe_get_mail_payroll_body();
	$body = soe_payroll_replace_mail_placeholders( $body_raw, $post_id );
	$headers = array(
		'Content-Type: text/plain; charset=UTF-8',
		'From: Special Olympics Liechtenstein <info@specialolympics.li>',
	);
	$sent = wp_mail( $to, $subject, $body, $headers, $attachments );
	if ( $temp_pdf && file_exists( $temp_pdf ) ) {
		@unlink( $temp_pdf );
	}
	if ( $sent ) {
		$now = current_time( 'mysql' );
		$log_raw = isset( $row['mail_sent_log'] ) ? $row['mail_sent_log'] : '';
		$log = is_string( $log_raw ) && $log_raw !== '' ? json_decode( $log_raw, true ) : array();
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = $now;
		soe_db_payroll_update( $post_id, array(
			'mail_sent_at'   => $now,
			'mail_text_sent' => $body,
			'mail_sent_log'  => wp_json_encode( $log ),
		) );
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Payroll mail sent', array( 'payroll_id' => $post_id, 'to' => $to ) );
		}
		wp_send_json_success();
	}
	wp_send_json_error( array( 'message' => __( 'Versand fehlgeschlagen.', 'special-olympics-extension' ) ) );
}

/**
 * AJAX: add manual adjustment to payroll.
 */
function soe_ajax_payroll_add_adjustment() {
	check_ajax_referer( 'soe_payroll_admin', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id || ! current_user_can( 'manage_options' ) || ! soe_db_payroll_get( $post_id ) ) {
		wp_send_json_error();
	}
	if ( soe_payroll_get_status( $post_id ) === SOE_PAYROLL_STATUS_ABGESCHLOSSEN ) {
		wp_send_json_error( array( 'message' => __( 'Abgeschlossene Lohnabrechnungen können nicht geändert werden.', 'special-olympics-extension' ) ) );
	}
	$comment = isset( $_POST['comment'] ) && is_string( $_POST['comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['comment'] ) ) : '';
	$amount_raw = isset( $_POST['amount'] ) ? wp_unslash( $_POST['amount'] ) : '';
	$amount = is_numeric( str_replace( ',', '.', $amount_raw ) ) ? (float) str_replace( ',', '.', $amount_raw ) : 0;
	$id = soe_db_payroll_add_adjustment( $post_id, $comment, $amount );
	if ( $id ) {
		wp_send_json_success( array( 'id' => $id, 'comment' => $comment, 'amount' => $amount ) );
	}
	wp_send_json_error( array( 'message' => __( 'Fehler beim Speichern.', 'special-olympics-extension' ) ) );
}

/**
 * AJAX: delete manual adjustment.
 */
function soe_ajax_payroll_delete_adjustment() {
	check_ajax_referer( 'soe_payroll_admin', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	$adjustment_id = isset( $_POST['adjustment_id'] ) ? (int) $_POST['adjustment_id'] : 0;
	if ( ! $post_id || ! $adjustment_id || ! current_user_can( 'manage_options' ) || ! soe_db_payroll_get( $post_id ) ) {
		wp_send_json_error();
	}
	if ( soe_payroll_get_status( $post_id ) === SOE_PAYROLL_STATUS_ABGESCHLOSSEN ) {
		wp_send_json_error( array( 'message' => __( 'Abgeschlossene Lohnabrechnungen können nicht geändert werden.', 'special-olympics-extension' ) ) );
	}
	$deleted = soe_db_payroll_delete_adjustment( $adjustment_id, $post_id );
	if ( $deleted ) {
		wp_send_json_success();
	}
	wp_send_json_error( array( 'message' => __( 'Fehler beim Löschen.', 'special-olympics-extension' ) ) );
}
