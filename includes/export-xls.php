<?php
/**
 * XLS/Excel exports: Telefonbuch, Trainings-Statistik, Payroll-Detail, Training-Anwesenheitsmatrix.
 *
 * Uses PhpSpreadsheet. admin_post handlers with nonce and capability checks.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Separator for repeater field entries in Telefonbuch export. */
define( 'SOE_EXPORT_REPEATER_SEP', ' | ' );

/**
 * Whether PhpSpreadsheet is available.
 *
 * @return bool
 */
function soe_export_can_xls() {
	static $can = null;
	if ( $can === null ) {
		$autoload = dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
		$can = file_exists( $autoload ) && ( require_once $autoload ) && class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' );
	}
	return $can;
}

/**
 * Format repeater/array value for export: join multiple rows with SOE_EXPORT_REPEATER_SEP.
 *
 * @param mixed  $value   ACF value (array of rows or scalar).
 * @param string $format  'simple' = implode sub-values with ', ' per row; or callback name.
 * @return string
 */
function soe_export_format_repeater( $value, $format = 'simple' ) {
	if ( empty( $value ) ) {
		return '';
	}
	if ( ! is_array( $value ) ) {
		return is_scalar( $value ) ? (string) $value : '';
	}
	$rows = array();
	foreach ( $value as $row ) {
		if ( is_array( $row ) ) {
			$parts = array_filter( array_map( function ( $v ) {
				return is_scalar( $v ) ? trim( (string) $v ) : '';
			}, $row ) );
			$rows[] = implode( ', ', $parts );
		} else {
			$rows[] = (string) $row;
		}
	}
	return implode( SOE_EXPORT_REPEATER_SEP, array_filter( $rows ) );
}

/**
 * Map ACF checkbox values to human-readable labels.
 *
 * @param mixed                $values  Selected value(s).
 * @param array<string,string> $choices ACF choices map (value => label).
 * @return string Comma-separated labels.
 */
function soe_format_checkbox_values_for_display( $values, $choices = array() ) {
	if ( ! is_array( $values ) ) {
		$values = ( $values !== '' && $values !== null ) ? array( $values ) : array();
	}
	$labels = array();
	foreach ( $values as $val ) {
		$val      = (string) $val;
		$labels[] = isset( $choices[ $val ] ) ? (string) $choices[ $val ] : $val;
	}
	return implode( ', ', array_filter( $labels, 'strlen' ) );
}

/**
 * Format ja/nein ACF values for display.
 *
 * @param mixed $value Raw field value.
 * @return string
 */
function soe_format_ja_nein_value( $value ) {
	if ( $value === 'ja' ) {
		return __( 'Ja', 'special-olympics-extension' );
	}
	if ( $value === 'nein' ) {
		return __( 'Nein', 'special-olympics-extension' );
	}
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

/**
 * Resolve ACF checkbox choices for a top-level or group sub-field.
 *
 * @param int    $post_id    Member post ID.
 * @param string $field_name Sub-field or field name.
 * @param string $group_name Optional parent group field name.
 * @return array<string,string>
 */
function soe_get_member_checkbox_choices( $post_id, $field_name, $group_name = '' ) {
	$choices = array();
	if ( $group_name !== '' ) {
		$group_obj = get_field_object( $group_name, $post_id );
		if ( is_array( $group_obj ) && ! empty( $group_obj['sub_fields'] ) ) {
			foreach ( $group_obj['sub_fields'] as $sub ) {
				if ( isset( $sub['name'] ) && $sub['name'] === $field_name && ! empty( $sub['choices'] ) && is_array( $sub['choices'] ) ) {
					return $sub['choices'];
				}
			}
		}
		return $choices;
	}
	$field_obj = get_field_object( $field_name, $post_id );
	if ( is_array( $field_obj ) && ! empty( $field_obj['choices'] ) && is_array( $field_obj['choices'] ) ) {
		return $field_obj['choices'];
	}
	return $choices;
}

/**
 * Format a member checkbox field (top-level or group sub-field) for export/display.
 *
 * @param int    $post_id    Member post ID.
 * @param string $field_name Field name.
 * @param string $group_name Optional parent group field name.
 * @return string
 */
function soe_format_member_checkbox_field( $post_id, $field_name, $group_name = '' ) {
	$value = array();
	if ( $group_name !== '' ) {
		$group_val = get_field( $group_name, $post_id );
		if ( is_array( $group_val ) && isset( $group_val[ $field_name ] ) ) {
			$value = $group_val[ $field_name ];
		}
	} else {
		$value = get_field( $field_name, $post_id );
	}
	$choices = soe_get_member_checkbox_choices( $post_id, $field_name, $group_name );
	return soe_format_checkbox_values_for_display( $value, $choices );
}

add_action( 'admin_post_soe_export_telefonbuch', 'soe_export_xls_telefonbuch_handler' );
function soe_export_xls_telefonbuch_handler() {
	if ( ! current_user_can( 'view_telefonbuch' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'soe_export_telefonbuch' ) ) {
		wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	if ( ! soe_export_can_xls() ) {
		wp_die( esc_html__( 'Excel-Export nicht verfügbar (PhpSpreadsheet fehlt). Bitte Composer ausführen.', 'special-olympics-extension' ), '', array( 'response' => 503 ) );
	}
	$member_ids = null;
	if ( isset( $_REQUEST['ids'] ) ) {
		$raw = is_array( $_REQUEST['ids'] ) ? $_REQUEST['ids'] : explode( ',', (string) $_REQUEST['ids'] );
		$member_ids = array_filter( array_map( 'absint', $raw ) );
		if ( empty( $member_ids ) ) {
			$member_ids = null;
		}
	}
	soe_export_xls_telefonbuch( $member_ids );
	exit;
}

/**
 * Export Telefonbuch: all member data, repeater fields with pipe separator.
 *
 * @param int[]|null $member_ids Optional. If set, only these member post IDs are exported (current filter/view).
 */
function soe_export_xls_telefonbuch( $member_ids = null ) {
	$all = function_exists( 'soe_telefonbuch_get_members' ) ? soe_telefonbuch_get_members() : array();
	if ( $member_ids !== null && ! empty( $member_ids ) ) {
		$by_id = array();
		foreach ( $all as $m ) {
			$by_id[ $m->ID ] = $m;
		}
		$members = array();
		foreach ( $member_ids as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$members[] = $by_id[ $id ];
			}
		}
	} else {
		$members = $all;
	}
	$event_snapshot_meta = defined( 'SOE_EVENT_SNAPSHOT_META' ) ? SOE_EVENT_SNAPSHOT_META : 'soe_event_snapshot';

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle( __( 'Telefonbuch', 'special-olympics-extension' ) );

	$role_labels_map = array(
		'athlet_in'           => __( 'Athlet*in', 'special-olympics-extension' ),
		'ansprechperson'      => __( 'Ansprechperson', 'special-olympics-extension' ),
		'hauptleiter_in'      => __( 'Hauptleiter*in', 'special-olympics-extension' ),
		'leiter_in'           => __( 'Leiter*in', 'special-olympics-extension' ),
		'assistenztrainer_in' => __( 'Assistenztrainer*in', 'special-olympics-extension' ),
		'helfer_in'           => __( 'Helfer*in', 'special-olympics-extension' ),
		'praktikant_in'       => __( 'Praktikant*in', 'special-olympics-extension' ),
		'schueler_in'         => __( 'Schüler*in', 'special-olympics-extension' ),
		'unified'             => __( 'Unified Partner*in', 'special-olympics-extension' ),
		'athlete_leader'      => __( 'Athlete Leader', 'special-olympics-extension' ),
	);

	$cols = array(
		'Nachname', 'Vorname', 'Rolle', 'Geburtsdatum', 'Geschlecht', 'Staatsbürgerschaft', 'Land', 'PEID-Nr.',
		'Telefon', 'E-Mail', 'Strasse', 'Hausnr.', 'PLZ', 'Ort',
		'Sportart', 'Kleidergrösse', 'Schuhgrösse',
		'Notfallkontakt', 'Notfallkontakt Tel.',
		'Weitere Kontakte', 'Notfallmedikamente', 'Medikamentangaben',
		'Hauptdiagnose', 'Nebendiagnosen', 'Psychische Leiden',
		'Trisomie 21 betroffen', 'Röntgenbilder HWS', 'Pathologisch (instabil)', 'Röntgen-Resultat',
		'Krankenkasse', 'KK-ID', 'Unfallversicherung', 'UV-ID',
		'Hausarzt', 'Hausarzt Tel.', 'Zahnarzt', 'Zahnarzt Tel.',
		'Allergien Med.', 'Allergien Lebensm.', 'Andere Allergien',
		'Ernährung Besonderheiten', 'Ernährung Weitere',
		'Bemerkungen', 'Erforderliche Hilfsmittel', 'Unterstützung bei', 'Andere Hilfsmittel',
		'Pflege/Betreuung', 'Sprache/Kommunikation', 'Verhaltensauffälligkeiten', 'Vorlieben/Ängste', 'Gewohnheiten',
		'Medizin. Datenblätter',
	);
	$export_bank = current_user_can( 'manage_options' );
	if ( $export_bank ) {
		$cols[] = 'Bank';
		$cols[] = 'IBAN';
	}
	$cols[] = 'Events';
	$col = 1;
	foreach ( $cols as $label ) {
		$sheet->setCellValueByColumnAndRow( $col, 1, $label );
		$col++;
	}
	$row = 2;
	foreach ( $members as $m ) {
		$vorname = get_field( 'vorname', $m->ID );
		$nachname = get_field( 'nachname', $m->ID );
		$role_raw = get_field( 'role', $m->ID );
		$role_arr = is_array( $role_raw ) ? $role_raw : ( $role_raw ? array( $role_raw ) : array() );
		$role_labels = array();
		foreach ( $role_arr as $r ) {
			if ( isset( $role_labels_map[ $r ] ) ) {
				$role_labels[] = $role_labels_map[ $r ];
			}
		}
		$role_str = implode( ', ', $role_labels );
		$strasse = get_field( 'strasse', $m->ID );
		$hausnummer = get_field( 'hausnummer', $m->ID );
		$plz = get_field( 'postleitzahl', $m->ID );
		$ort = get_field( 'ort', $m->ID );
		$tel = get_field( 'telefonnummer', $m->ID );
		$email = get_field( 'e-mail', $m->ID );
		$kleider = get_field( 'kleidergrosse', $m->ID );
		$schuh = get_field( 'schuhgrosse', $m->ID );

		list( $name_notfall, $tel_notfall ) = function_exists( 'soe_get_notfallkontakt_data' ) ? soe_get_notfallkontakt_data( $m->ID ) : array( '', '' );

		$weitere = get_field( 'weitere_kontakte', $m->ID );
		$weitere_str = '';
		if ( is_array( $weitere ) && ! empty( $weitere ) ) {
			$parts = array();
			foreach ( $weitere as $k ) {
				$p = trim( ( isset( $k['funktion'] ) ? $k['funktion'] . ' ' : '' ) . ( isset( $k['vorname'] ) ? $k['vorname'] . ' ' : '' ) . ( isset( $k['nachname'] ) ? $k['nachname'] : '' ) );
				if ( ! empty( $k['telefon'] ) ) {
					$p .= ' ' . $k['telefon'];
				}
				$parts[] = trim( $p );
			}
			$weitere_str = implode( SOE_EXPORT_REPEATER_SEP, array_filter( $parts ) );
		}

		$notfallmed = get_field( 'notfallmedikamente', $m->ID );
		$notfallmed_str = '';
		if ( is_array( $notfallmed ) && ! empty( $notfallmed ) ) {
			$parts = array();
			foreach ( $notfallmed as $med ) {
				$name = isset( $med['name_medikament_notfall'] ) ? trim( (string) $med['name_medikament_notfall'] ) : '';
				$dosis = isset( $med['dosis_medikament_notfall'] ) ? trim( (string) $med['dosis_medikament_notfall'] ) : '';
				$parts[] = $name && $dosis ? $name . ', ' . $dosis : ( $name ?: $dosis );
			}
			$notfallmed_str = implode( SOE_EXPORT_REPEATER_SEP, array_filter( $parts ) );
		}

		$medik = get_field( 'medikamentangaben', $m->ID );
		$medik_str = '';
		if ( is_array( $medik ) && ! empty( $medik ) ) {
			$parts = array();
			foreach ( $medik as $med ) {
				$name = isset( $med['name_medikament'] ) ? trim( (string) $med['name_medikament'] ) : '';
				$dosis = isset( $med['dosis_medikament'] ) ? trim( (string) $med['dosis_medikament'] ) : '';
				$parts[] = $name && $dosis ? $name . ', ' . $dosis : ( $name ?: $dosis );
			}
			$medik_str = implode( SOE_EXPORT_REPEATER_SEP, array_filter( $parts ) );
		}

		$hilfs_str   = soe_format_member_checkbox_field( $m->ID, 'erforderliche_hilfsmittel' );
		$unterst_str = soe_format_member_checkbox_field( $m->ID, 'unterstutzung_bei' );
		$ernaehrung_bes_str = soe_format_member_checkbox_field( $m->ID, 'ernahrung_besonderheiten' );

		$diagnose_export = get_field( 'diagnose', $m->ID );
		$hauptdiagnose_export = is_array( $diagnose_export ) && isset( $diagnose_export['hauptdiagnose'] ) ? $diagnose_export['hauptdiagnose'] : '';
		$psychische_leiden_export = is_array( $diagnose_export ) && isset( $diagnose_export['psychische_leiden'] ) ? $diagnose_export['psychische_leiden'] : '';
		$nebendiagnosen_export = soe_format_member_checkbox_field( $m->ID, 'nebendiagnosen', 'diagnose' );

		$trisomie_export = get_field( 'trisomie_21', $m->ID );
		$tri_betroffen_export = is_array( $trisomie_export ) && isset( $trisomie_export['trisomie_21_betroffen'] ) ? $trisomie_export['trisomie_21_betroffen'] : '';
		$tri_roentgen_export = is_array( $trisomie_export ) && isset( $trisomie_export['roentgenbilder_hws'] ) ? $trisomie_export['roentgenbilder_hws'] : '';
		$tri_pathological_export = is_array( $trisomie_export ) && isset( $trisomie_export['xray_pathological'] ) ? $trisomie_export['xray_pathological'] : '';
		$tri_result_export = is_array( $trisomie_export ) && isset( $trisomie_export['xray_result'] ) ? $trisomie_export['xray_result'] : '';

		$datenblatter = get_field( 'medizinische_datenblatter', $m->ID );
		$daten_str = '';
		if ( $datenblatter ) {
			// Extract attachment ID - handle various ACF formats
			$att_id = 0;
			if ( is_numeric( $datenblatter ) ) {
				$att_id = (int) $datenblatter;
			} elseif ( is_array( $datenblatter ) ) {
				// Check for nested format: array('ID' => array('id' => X))
				if ( isset( $datenblatter['ID'] ) && is_array( $datenblatter['ID'] ) && isset( $datenblatter['ID']['id'] ) ) {
					$att_id = (int) $datenblatter['ID']['id'];
				} elseif ( isset( $datenblatter['id'] ) && is_array( $datenblatter['id'] ) && isset( $datenblatter['id']['id'] ) ) {
					$att_id = (int) $datenblatter['id']['id'];
				} elseif ( isset( $datenblatter['ID'] ) && is_numeric( $datenblatter['ID'] ) ) {
					$att_id = (int) $datenblatter['ID'];
				} elseif ( isset( $datenblatter['id'] ) && is_numeric( $datenblatter['id'] ) ) {
					$att_id = (int) $datenblatter['id'];
				}
			}
			if ( $att_id ) {
				$p = get_attached_file( $att_id );
				$daten_str = $p ? basename( $p ) : '';
			}
		}

		$snapshot = get_post_meta( $m->ID, $event_snapshot_meta, true );
		$events_str = '';
		if ( is_array( $snapshot ) && ! empty( $snapshot ) ) {
			$titles = array();
			foreach ( $snapshot as $e ) {
				$titles[] = isset( $e['title'] ) ? $e['title'] : '';
			}
			$events_str = implode( SOE_EXPORT_REPEATER_SEP, array_filter( $titles ) );
		}

		$sport_terms = wp_get_object_terms( $m->ID, 'sport' );
		$sport_str = is_array( $sport_terms ) ? implode( ', ', wp_list_pluck( $sport_terms, 'name' ) ) : '';

		$bank_export = get_field( 'bank_informationen', $m->ID );
		$bank_name_x = is_array( $bank_export ) && isset( $bank_export['bank_name'] ) ? $bank_export['bank_name'] : '';
		$bank_iban_x = is_array( $bank_export ) && isset( $bank_export['bank_iban'] ) ? $bank_export['bank_iban'] : '';

		$data = array(
			$nachname, $vorname, $role_str,
			get_field( 'geburtsdatum', $m->ID ),
			get_field( 'geschlecht', $m->ID ),
			get_field( 'staatsburgerschaft', $m->ID ),
			get_field( 'land', $m->ID ),
			get_field( 'peid_nr', $m->ID ),
			$tel, $email, $strasse, $hausnummer, $plz, $ort,
			$sport_str, $kleider, $schuh,
			$name_notfall, $tel_notfall,
			$weitere_str, $notfallmed_str, $medik_str,
			$hauptdiagnose_export, $nebendiagnosen_export, $psychische_leiden_export,
			soe_format_ja_nein_value( $tri_betroffen_export ),
			soe_format_ja_nein_value( $tri_roentgen_export ),
			soe_format_ja_nein_value( $tri_pathological_export ),
			$tri_result_export,
			get_field( 'krankenkasse_name_&_ort', $m->ID ), get_field( 'krankenkasse_idnr', $m->ID ),
			get_field( 'unfallversicherung_name_&_ort', $m->ID ), get_field( 'unfallversicherung_idnr', $m->ID ),
			get_field( 'hausarzt_name', $m->ID ), get_field( 'hausarzt_name_telnr', $m->ID ),
			get_field( 'zahnarzt_name', $m->ID ), get_field( 'zahnarzt_telnr', $m->ID ),
			get_field( 'allergien_auf_medikamente', $m->ID ), get_field( 'allergien_auf_lebensmittel', $m->ID ), get_field( 'andere_allergien', $m->ID ),
			$ernaehrung_bes_str, get_field( 'ernahrung_weitere_informationen', $m->ID ),
			get_field( 'bemerkungen', $m->ID ), $hilfs_str, $unterst_str, get_field( 'andere_hilfsmittel', $m->ID ),
			get_field( 'pflegebetreuung', $m->ID ), get_field( 'sprachekommunikation', $m->ID ),
			get_field( 'verhaltenauffalligkeiten', $m->ID ), get_field( 'vorliebenangste', $m->ID ),
			get_field( 'gewohnheiten', $m->ID ),
			$daten_str,
		);
		if ( $export_bank ) {
			$data[] = is_scalar( $bank_name_x ) ? (string) $bank_name_x : '';
			$data[] = is_scalar( $bank_iban_x ) ? (string) $bank_iban_x : '';
		}
		$data[] = $events_str;
		$col = 1;
		foreach ( $data as $v ) {
			$sheet->setCellValueByColumnAndRow( $col, $row, is_scalar( $v ) ? $v : '' );
			$col++;
		}
		$row++;
	}

	soe_export_send_xlsx( $spreadsheet, 'telefonbuch-export.xlsx' );
}

add_action( 'admin_post_soe_export_training_stats', 'soe_export_xls_training_stats_handler' );
function soe_export_xls_training_stats_handler() {
	if ( ! current_user_can( 'edit_trainings' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'soe_export_training_stats' ) ) {
		wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	if ( ! soe_export_can_xls() ) {
		wp_die( esc_html__( 'Excel-Export nicht verfügbar.', 'special-olympics-extension' ), '', array( 'response' => 503 ) );
	}
	soe_export_xls_training_stats();
	exit;
}

/**
 * Export Trainings-Statistik: per training, Name | Anw. | Anw. %
 */
function soe_export_xls_training_stats() {
	$sport_filter = isset( $_GET['sport'] ) ? sanitize_text_field( wp_unslash( $_GET['sport'] ) ) : '';
	$mitglied_id = function_exists( 'soe_get_current_user_mitglied_id' ) ? soe_get_current_user_mitglied_id() : 0;
	$is_hauptleiter = $mitglied_id && ! current_user_can( 'manage_options' );

	$args = array( 'completed' => 0, 'limit' => 500 );
	if ( $sport_filter ) {
		$args['sport_slug'] = $sport_filter;
	}
	if ( $is_hauptleiter ) {
		$args['hauptleiter_person_id'] = $mitglied_id;
	}
	$trainings = soe_db_training_list( $args );

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle( __( 'Statistik', 'special-olympics-extension' ) );
	$row = 1;
	foreach ( $trainings as $t ) {
		$sessions = soe_db_training_get_sessions( $t['id'] );
		$attendance = soe_db_training_get_attendance( $t['id'] );
		$all_persons = soe_training_get_all_person_labels( $t['id'] );
		$total = count( $sessions );
		$sheet->setCellValue( 'A' . $row, $t['title'] . ( $t['sport_slug'] ? ' (' . $t['sport_slug'] . ')' : '' ) );
		$row += 2;
		$sheet->setCellValue( 'A' . $row, __( 'Name', 'special-olympics-extension' ) );
		$sheet->setCellValue( 'B' . $row, __( 'Anw.', 'special-olympics-extension' ) );
		$sheet->setCellValue( 'C' . $row, __( 'Anw. %', 'special-olympics-extension' ) );
		$row++;
		foreach ( $all_persons as $pid => $label ) {
			$count = 0;
			foreach ( $sessions as $d ) {
				if ( ! empty( $attendance[ $d ][ $pid ] ) ) {
					$count++;
				}
			}
			$pct = $total > 0 ? round( 100 * $count / $total ) : 0;
			$sheet->setCellValue( 'A' . $row, $label );
			$sheet->setCellValue( 'B' . $row, $count );
			$sheet->setCellValue( 'C' . $row, $pct . '%' );
			$row++;
		}
		$row += 2;
	}

	soe_export_send_xlsx( $spreadsheet, 'trainings-statistik-export.xlsx' );
}

add_action( 'admin_post_soe_export_payroll', 'soe_export_xls_payroll_handler' );
function soe_export_xls_payroll_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'soe_export_payroll' ) ) {
		wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$payroll_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! $payroll_id ) {
		wp_die( esc_html__( 'Ungültige Lohnabrechnung.', 'special-olympics-extension' ), '', array( 'response' => 400 ) );
	}
	if ( ! soe_export_can_xls() ) {
		wp_die( esc_html__( 'Excel-Export nicht verfügbar.', 'special-olympics-extension' ), '', array( 'response' => 503 ) );
	}
	soe_export_xls_payroll( $payroll_id );
	exit;
}

/**
 * Export Payroll: single sheet, status, person, period, 3 tables, footer (like PDF).
 */
function soe_export_xls_payroll( $payroll_id ) {
	$row_data = soe_db_payroll_get( $payroll_id );
	if ( ! $row_data ) {
		wp_die( esc_html__( 'Lohnabrechnung nicht gefunden.', 'special-olympics-extension' ), '', array( 'response' => 404 ) );
	}
	$status = function_exists( 'soe_payroll_get_status' ) ? soe_payroll_get_status( $payroll_id ) : ( $row_data['status'] ?? '' );
	$person_id = (int) ( $row_data['person_id'] ?? 0 );
	$person_name = $person_id ? get_the_title( $person_id ) : '';
	$period_start = $row_data['period_start'] ?? '';
	$period_end = $row_data['period_end'] ?? '';

	$all_rows = soe_db_payroll_get_rows( $payroll_id );
	$training_rows = array_filter( $all_rows, function ( $r ) {
		return ! isset( $r['event_title'] ) || $r['event_title'] === '';
	} );
	$event_rows = array_filter( $all_rows, function ( $r ) {
		return isset( $r['event_title'] ) && $r['event_title'] !== '';
	} );
	$adjustments = soe_db_payroll_get_adjustments( $payroll_id );
	$base_total = 0;
	foreach ( array_merge( $training_rows, $event_rows ) as $r ) {
		$base_total += isset( $r['chf_amount'] ) ? (float) $r['chf_amount'] : 0;
	}
	$adjustments_total = 0;
	foreach ( $adjustments as $a ) {
		$adjustments_total += (float) ( $a['amount'] ?? 0 );
	}
	$grand_total = $base_total + $adjustments_total;
	$qual_label_fn = function_exists( 'soe_payroll_qualification_to_label' ) ? 'soe_payroll_qualification_to_label' : function ( $q ) { return $q; };

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle( __( 'Lohnabrechnung', 'special-olympics-extension' ) );

	$row = 1;
	$sheet->setCellValue( 'A' . $row, __( 'Status', 'special-olympics-extension' ) . ': ' . $status );
	$row++;
	$sheet->setCellValue( 'A' . $row, __( 'Person', 'special-olympics-extension' ) . ': ' . $person_name );
	$row++;
	$sheet->setCellValue( 'A' . $row, __( 'Zeitraum', 'special-olympics-extension' ) . ': ' . $period_start . ' – ' . $period_end );
	$row += 2;

	$sheet->setCellValue( 'A' . $row, __( 'Trainingsanwesenheiten', 'special-olympics-extension' ) );
	$row++;
	$headers = array( __( 'Sportart', 'special-olympics-extension' ), __( 'Bemerkungen', 'special-olympics-extension' ), __( 'Qualifikation', 'special-olympics-extension' ), __( 'Dauer', 'special-olympics-extension' ), __( 'BH-Nr.', 'special-olympics-extension' ), __( 'Anzahl', 'special-olympics-extension' ), __( 'CHF/Std', 'special-olympics-extension' ), __( 'CHF/Bereich', 'special-olympics-extension' ) );
	for ( $c = 0; $c < count( $headers ); $c++ ) {
		$sheet->setCellValueByColumnAndRow( $c + 1, $row, $headers[ $c ] );
	}
	$row++;
	foreach ( $training_rows as $r ) {
		$sheet->setCellValue( 'A' . $row, $r['sport'] ?? '' );
		$sheet->setCellValue( 'B' . $row, $r['notes'] ?? '' );
		$sheet->setCellValue( 'C' . $row, is_callable( $qual_label_fn ) ? call_user_func( $qual_label_fn, $r['qualification'] ?? '' ) : ( $r['qualification'] ?? '' ) );
		$sheet->setCellValue( 'D' . $row, soe_get_duration_label( $r['duration'] ?? '' ) );
		$sheet->setCellValue( 'E' . $row, $r['ref_no'] ?? '' );
		$sheet->setCellValue( 'F' . $row, (int) ( $r['quantity'] ?? 0 ) );
		$sheet->setCellValue( 'G' . $row, $r['chf_per_hour'] ?? '' );
		$sheet->setCellValue( 'H' . $row, isset( $r['chf_amount'] ) ? number_format( (float) $r['chf_amount'], 2 ) : '' );
		$row++;
	}
	$row += 2;

	$sheet->setCellValue( 'A' . $row, __( 'Events', 'special-olympics-extension' ) );
	$row++;
	for ( $c = 0; $c < count( $headers ); $c++ ) {
		$sheet->setCellValueByColumnAndRow( $c + 1, $row, $headers[ $c ] );
	}
	$row++;
	foreach ( $event_rows as $r ) {
		$event_label = isset( $r['event_title'] ) ? $r['event_title'] : ( $r['sport'] ?? '' );
		$sheet->setCellValue( 'A' . $row, $event_label );
		$sheet->setCellValue( 'B' . $row, $r['notes'] ?? '' );
		$sheet->setCellValue( 'C' . $row, is_callable( $qual_label_fn ) ? call_user_func( $qual_label_fn, $r['qualification'] ?? '' ) : ( $r['qualification'] ?? '' ) );
		$sheet->setCellValue( 'D' . $row, soe_get_duration_label( $r['duration'] ?? '' ) );
		$sheet->setCellValue( 'E' . $row, $r['ref_no'] ?? '' );
		$sheet->setCellValue( 'F' . $row, (int) ( $r['quantity'] ?? 0 ) );
		$sheet->setCellValue( 'G' . $row, $r['chf_per_hour'] ?? '' );
		$sheet->setCellValue( 'H' . $row, isset( $r['chf_amount'] ) ? number_format( (float) $r['chf_amount'], 2 ) : '' );
		$row++;
	}
	$row += 2;

	$sheet->setCellValue( 'A' . $row, __( 'Manuelle Änderungen', 'special-olympics-extension' ) );
	$row++;
	$sheet->setCellValue( 'A' . $row, __( 'Kommentar', 'special-olympics-extension' ) );
	$sheet->setCellValue( 'B' . $row, __( 'Betrag (CHF)', 'special-olympics-extension' ) );
	$row++;
	foreach ( $adjustments as $a ) {
		$amt = (float) ( $a['amount'] ?? 0 );
		$sheet->setCellValue( 'A' . $row, $a['comment'] ?? '' );
		$sheet->setCellValue( 'B' . $row, ( $amt >= 0 ? '+' : '' ) . number_format( $amt, 2 ) );
		$row++;
	}
	$row += 2;

	if ( ! empty( $adjustments ) ) {
		$sheet->setCellValue( 'A' . $row, __( 'Summe Trainings + Events', 'special-olympics-extension' ) . ': CHF ' . number_format( $base_total, 2 ) );
		$row++;
		$sheet->setCellValue( 'A' . $row, __( 'Manuelle Änderungen', 'special-olympics-extension' ) . ': CHF ' . ( $adjustments_total >= 0 ? '+' : '' ) . number_format( $adjustments_total, 2 ) );
		$row++;
	}
	$sheet->setCellValue( 'A' . $row, __( 'Gesamtsumme', 'special-olympics-extension' ) . ': CHF ' . number_format( $grand_total, 2 ) );

	$filename = 'lohnabrechnung-' . $payroll_id . '.xlsx';
	soe_export_send_xlsx( $spreadsheet, $filename );
}

add_action( 'admin_post_soe_export_training_attendance', 'soe_export_xls_training_attendance_handler' );
function soe_export_xls_training_attendance_handler() {
	if ( ! current_user_can( 'edit_trainings' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'soe_export_training_attendance' ) ) {
		wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$training_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! $training_id ) {
		wp_die( esc_html__( 'Ungültiges Training.', 'special-olympics-extension' ), '', array( 'response' => 400 ) );
	}
	if ( ! soe_export_can_xls() ) {
		wp_die( esc_html__( 'Excel-Export nicht verfügbar.', 'special-olympics-extension' ), '', array( 'response' => 503 ) );
	}
	soe_export_xls_training_attendance( $training_id );
	exit;
}

/**
 * Export Training Anwesenheits-Matrix: rows = persons, columns = sessions, cells = ✓ or –.
 */
function soe_export_xls_training_attendance( $training_id ) {
	$sessions = soe_db_training_get_sessions( $training_id );
	$attendance = soe_db_training_get_attendance( $training_id );
	$all_persons = soe_training_get_all_person_labels( $training_id );

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle( __( 'Anwesenheit', 'special-olympics-extension' ) );

	$sheet->setCellValue( 'A1', __( 'Person', 'special-olympics-extension' ) );
	$col = 2;
	foreach ( $sessions as $d ) {
		$sheet->setCellValueByColumnAndRow( $col, 1, date_i18n( 'd.m.', strtotime( $d ) ) );
		$col++;
	}
	$row = 2;
	foreach ( $all_persons as $pid => $label ) {
		$sheet->setCellValue( 'A' . $row, $label );
		$col = 2;
		foreach ( $sessions as $d ) {
			$checked = ! empty( $attendance[ $d ][ $pid ] );
			$sheet->setCellValueByColumnAndRow( $col, $row, $checked ? '✓' : '–' );
			$col++;
		}
		$row++;
	}

	$filename = 'anwesenheit-training-' . $training_id . '.xlsx';
	soe_export_send_xlsx( $spreadsheet, $filename );
}

/**
 * Output spreadsheet as xlsx download.
 *
 * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet Spreadsheet instance.
 * @param string                                $filename    Download filename.
 */
function soe_export_send_xlsx( $spreadsheet, $filename ) {
	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '"' );
	header( 'Cache-Control: max-age=0' );
	$writer->save( 'php://output' );
}
