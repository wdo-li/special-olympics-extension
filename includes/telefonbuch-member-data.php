<?php
/**
 * Telefonbuch member data collection for PDF/export (structured sections).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Role slug => label map for Telefonbuch exports.
 *
 * @return array<string, string>
 */
function soe_telefonbuch_get_role_labels_map() {
	return array(
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
}

/**
 * Normalize scalar ACF value to trimmed string.
 *
 * @param mixed $value Field value.
 * @return string
 */
function soe_telefonbuch_member_str( $value ) {
	if ( is_string( $value ) ) {
		return trim( $value );
	}
	if ( is_scalar( $value ) ) {
		return trim( (string) $value );
	}
	return '';
}

/**
 * Whether the current user may export this member (Telefonbuch scope).
 *
 * @param int $member_id Mitglied post ID.
 * @return bool
 */
function soe_telefonbuch_member_is_exportable( $member_id ) {
	$member_id = absint( $member_id );
	if ( ! $member_id || get_post_type( $member_id ) !== 'mitglied' ) {
		return false;
	}
	if ( ! function_exists( 'soe_telefonbuch_get_members' ) ) {
		return false;
	}
	foreach ( soe_telefonbuch_get_members() as $member ) {
		if ( (int) $member->ID === $member_id ) {
			return true;
		}
	}
	return false;
}

/**
 * Format medication repeater rows as display strings.
 *
 * @param array<int, array<string, mixed>>|mixed $rows        Repeater rows.
 * @param string                               $name_key    Subfield name key.
 * @param string                               $dosis_key   Subfield dose key.
 * @return string[]
 */
function soe_telefonbuch_format_med_repeater_rows( $rows, $name_key, $dosis_key ) {
	$lines = array();
	if ( ! is_array( $rows ) ) {
		return $lines;
	}
	foreach ( $rows as $med ) {
		if ( ! is_array( $med ) ) {
			continue;
		}
		$name  = isset( $med[ $name_key ] ) ? trim( (string) $med[ $name_key ] ) : '';
		$dosis = isset( $med[ $dosis_key ] ) ? trim( (string) $med[ $dosis_key ] ) : '';
		$line  = $name && $dosis ? $name . ', ' . $dosis : ( $name ?: $dosis );
		if ( $line !== '' ) {
			$lines[] = $line;
		}
	}
	return $lines;
}

/**
 * Collect all Telefonbuch member data for PDF/export.
 *
 * @param int $member_id Mitglied post ID.
 * @return array<string, mixed>|null Null when member invalid.
 */
function soe_telefonbuch_collect_member_data( $member_id ) {
	$member_id = absint( $member_id );
	if ( ! $member_id || get_post_type( $member_id ) !== 'mitglied' ) {
		return null;
	}

	$role_labels_map = soe_telefonbuch_get_role_labels_map();
	$role_raw        = get_field( 'role', $member_id );
	$role_arr        = is_array( $role_raw ) ? $role_raw : ( $role_raw ? array( $role_raw ) : array() );
	$role_labels     = array();
	foreach ( $role_arr as $role_slug ) {
		if ( isset( $role_labels_map[ $role_slug ] ) ) {
			$role_labels[] = $role_labels_map[ $role_slug ];
		}
	}

	$sport_terms  = wp_get_object_terms( $member_id, 'sport' );
	$sport_labels = is_array( $sport_terms ) ? implode( ', ', wp_list_pluck( $sport_terms, 'name' ) ) : '';

	$strasse    = soe_telefonbuch_member_str( get_field( 'strasse', $member_id ) );
	$hausnummer = soe_telefonbuch_member_str( get_field( 'hausnummer', $member_id ) );
	$address    = trim( $strasse . ( $hausnummer !== '' ? ' ' . $hausnummer : '' ) );

	list( $name_notfall, $tel_notfall ) = function_exists( 'soe_get_notfallkontakt_data' )
		? soe_get_notfallkontakt_data( $member_id )
		: array( '', '' );

	$weitere_raw = get_field( 'weitere_kontakte', $member_id );
	$weitere     = array();
	if ( is_array( $weitere_raw ) ) {
		foreach ( $weitere_raw as $k ) {
			if ( ! is_array( $k ) ) {
				continue;
			}
			$row = array(
				'funktion' => soe_telefonbuch_member_str( $k['funktion'] ?? '' ),
				'vorname'  => soe_telefonbuch_member_str( $k['vorname'] ?? '' ),
				'nachname' => soe_telefonbuch_member_str( $k['nachname'] ?? '' ),
				'adresse'  => soe_telefonbuch_member_str( $k['adresse'] ?? '' ),
				'email'    => soe_telefonbuch_member_str( $k['e-mail'] ?? '' ),
				'telefon'  => soe_telefonbuch_member_str( $k['telefon'] ?? '' ),
			);
			if ( implode( '', $row ) !== '' ) {
				$weitere[] = $row;
			}
		}
	}

	$bank_info = get_field( 'bank_informationen', $member_id );
	$bank_name = is_array( $bank_info ) && isset( $bank_info['bank_name'] ) ? soe_telefonbuch_member_str( $bank_info['bank_name'] ) : '';
	$bank_iban = is_array( $bank_info ) && isset( $bank_info['bank_iban'] ) ? soe_telefonbuch_member_str( $bank_info['bank_iban'] ) : '';
	$include_bank = current_user_can( 'manage_options' );

	$diagnose = get_field( 'diagnose', $member_id );
	$trisomie = get_field( 'trisomie_21', $member_id );
	$tri_betroffen = is_array( $trisomie ) && isset( $trisomie['trisomie_21_betroffen'] ) ? (string) $trisomie['trisomie_21_betroffen'] : '';
	$tri_betroffen_ja = ( $tri_betroffen === 'ja' );

	$event_snapshot_meta = defined( 'SOE_EVENT_SNAPSHOT_META' ) ? SOE_EVENT_SNAPSHOT_META : 'soe_event_snapshot';
	$snapshot          = get_post_meta( $member_id, $event_snapshot_meta, true );
	$events            = array();
	if ( is_array( $snapshot ) ) {
		foreach ( $snapshot as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$date  = isset( $event['date'] ) ? soe_telefonbuch_member_str( $event['date'] ) : '';
			$title = isset( $event['title'] ) ? soe_telefonbuch_member_str( $event['title'] ) : '';
			$role  = isset( $event['role'] ) ? soe_telefonbuch_member_str( $event['role'] ) : '';
			$label = '';
			if ( $date !== '' ) {
				$label .= date_i18n( 'd.m.Y', strtotime( $date ) ) . ' ';
			}
			$label .= $title;
			if ( $role !== '' ) {
				$label .= ' (' . $role . ')';
			}
			$label = trim( $label );
			if ( $label !== '' ) {
				$events[] = $label;
			}
		}
	}

	$vorname  = soe_telefonbuch_member_str( get_field( 'vorname', $member_id ) );
	$nachname = soe_telefonbuch_member_str( get_field( 'nachname', $member_id ) );

	$export_user = wp_get_current_user();
	$exported_by = '';
	if ( $export_user && $export_user->ID ) {
		$exported_by = trim( (string) $export_user->display_name );
		if ( $exported_by === '' ) {
			$exported_by = trim( (string) $export_user->user_login );
		}
	}

	$bemerkungen_raw = get_field( 'bemerkungen', $member_id );
	$bemerkungen     = is_string( $bemerkungen_raw ) ? wp_strip_all_tags( $bemerkungen_raw ) : '';
	$bemerkungen     = soe_telefonbuch_member_str( $bemerkungen );

	$data = array(
		'member_id'    => $member_id,
		'header'       => array(
			'vorname'      => $vorname,
			'nachname'     => $nachname,
			'member_name'  => trim( $vorname . ' ' . $nachname ),
			'export_date'  => date_i18n( 'd.m.Y H:i' ),
			'exported_by'  => $exported_by,
		),
		'person'       => array(
			'address' => $address,
			'plz'     => soe_telefonbuch_member_str( get_field( 'postleitzahl', $member_id ) ),
			'ort'     => soe_telefonbuch_member_str( get_field( 'ort', $member_id ) ),
			'land'    => soe_telefonbuch_member_str( get_field( 'land', $member_id ) ),
			'tel'     => soe_telefonbuch_member_str( get_field( 'telefonnummer', $member_id ) ),
			'email'   => soe_telefonbuch_member_str( get_field( 'e-mail', $member_id ) ),
			'rolle'   => implode( ', ', $role_labels ),
			'sport'   => $sport_labels,
		),
		'stammdaten'   => array(
			'geburtsdatum'       => soe_telefonbuch_member_str( get_field( 'geburtsdatum', $member_id ) ),
			'geschlecht'         => soe_telefonbuch_member_str( get_field( 'geschlecht', $member_id ) ),
			'staatsburgerschaft' => soe_telefonbuch_member_str( get_field( 'staatsburgerschaft', $member_id ) ),
			'land'               => soe_telefonbuch_member_str( get_field( 'land', $member_id ) ),
			'peid_nr'            => soe_telefonbuch_member_str( get_field( 'peid_nr', $member_id ) ),
		),
		'kleidung'     => array(
			'kleidergrosse' => soe_telefonbuch_member_str( get_field( 'kleidergrosse', $member_id ) ),
			'schuhgrosse'   => soe_telefonbuch_member_str( get_field( 'schuhgrosse', $member_id ) ),
		),
		'notfallkontakt' => array(
			'name' => soe_telefonbuch_member_str( $name_notfall ),
			'tel'  => soe_telefonbuch_member_str( $tel_notfall ),
		),
		'kontakte'     => array(
			'weitere'     => $weitere,
			'bemerkungen' => $bemerkungen,
		),
		'bank'         => array(
			'include'   => $include_bank,
			'bank_name' => $include_bank ? $bank_name : '',
			'iban'      => $include_bank ? $bank_iban : '',
		),
		'diagnose'     => array(
			'hauptdiagnose'     => is_array( $diagnose ) && isset( $diagnose['hauptdiagnose'] ) ? soe_telefonbuch_member_str( $diagnose['hauptdiagnose'] ) : '',
			'nebendiagnosen'    => function_exists( 'soe_format_member_checkbox_field' ) ? soe_format_member_checkbox_field( $member_id, 'nebendiagnosen', 'diagnose' ) : '',
			'psychische_leiden' => is_array( $diagnose ) && isset( $diagnose['psychische_leiden'] ) ? soe_telefonbuch_member_str( $diagnose['psychische_leiden'] ) : '',
		),
		'trisomie_21'  => array(
			'betroffen'    => $tri_betroffen,
			'betroffen_ja' => $tri_betroffen_ja,
			'roentgen'     => $tri_betroffen_ja && is_array( $trisomie ) && isset( $trisomie['roentgenbilder_hws'] ) ? (string) $trisomie['roentgenbilder_hws'] : '',
			'pathological' => $tri_betroffen_ja && is_array( $trisomie ) && isset( $trisomie['xray_pathological'] ) ? (string) $trisomie['xray_pathological'] : '',
			'result'       => $tri_betroffen_ja && is_array( $trisomie ) && isset( $trisomie['xray_result'] ) ? soe_telefonbuch_member_str( $trisomie['xray_result'] ) : '',
		),
		'medizin'      => array(
			'krankenkasse'      => soe_telefonbuch_member_str( get_field( 'krankenkasse_name_&_ort', $member_id ) ),
			'krankenkasse_idnr' => soe_telefonbuch_member_str( get_field( 'krankenkasse_idnr', $member_id ) ),
			'unfallv_name'      => soe_telefonbuch_member_str( get_field( 'unfallversicherung_name_&_ort', $member_id ) ),
			'unfallv_idnr'      => soe_telefonbuch_member_str( get_field( 'unfallversicherung_idnr', $member_id ) ),
			'hausarzt'          => soe_telefonbuch_member_str( get_field( 'hausarzt_name', $member_id ) ),
			'hausarzt_tel'      => soe_telefonbuch_member_str( get_field( 'hausarzt_name_telnr', $member_id ) ),
			'zahnarzt'          => soe_telefonbuch_member_str( get_field( 'zahnarzt_name', $member_id ) ),
			'zahnarzt_tel'      => soe_telefonbuch_member_str( get_field( 'zahnarzt_telnr', $member_id ) ),
			'notfallmed'        => soe_telefonbuch_format_med_repeater_rows( get_field( 'notfallmedikamente', $member_id ), 'name_medikament_notfall', 'dosis_medikament_notfall' ),
			'medikamente'       => soe_telefonbuch_format_med_repeater_rows( get_field( 'medikamentangaben', $member_id ), 'name_medikament', 'dosis_medikament' ),
			'allergien_med'     => soe_telefonbuch_member_str( get_field( 'allergien_auf_medikamente', $member_id ) ),
			'allergien_lebens'  => soe_telefonbuch_member_str( get_field( 'allergien_auf_lebensmittel', $member_id ) ),
			'allergien_andere'  => soe_telefonbuch_member_str( get_field( 'andere_allergien', $member_id ) ),
			'ernaehrung_bes'    => function_exists( 'soe_format_member_checkbox_field' ) ? soe_format_member_checkbox_field( $member_id, 'ernahrung_besonderheiten' ) : '',
			'ernaehrung_weitere'=> soe_telefonbuch_member_str( get_field( 'ernahrung_weitere_informationen', $member_id ) ),
		),
		'hilfsmittel'  => array(
			'erforderliche' => function_exists( 'soe_format_member_checkbox_field' ) ? soe_format_member_checkbox_field( $member_id, 'erforderliche_hilfsmittel' ) : '',
			'unterstutzung' => function_exists( 'soe_format_member_checkbox_field' ) ? soe_format_member_checkbox_field( $member_id, 'unterstutzung_bei' ) : '',
			'andere'        => soe_telefonbuch_member_str( get_field( 'andere_hilfsmittel', $member_id ) ),
		),
		'beachtenswertes' => array(
			'pflege'      => soe_telefonbuch_member_str( get_field( 'pflegebetreuung', $member_id ) ),
			'sprache'     => soe_telefonbuch_member_str( get_field( 'sprachekommunikation', $member_id ) ),
			'verhalten'   => soe_telefonbuch_member_str( get_field( 'verhaltenauffalligkeiten', $member_id ) ),
			'vorlieben'   => soe_telefonbuch_member_str( get_field( 'vorliebenangste', $member_id ) ),
			'gewohnheiten' => soe_telefonbuch_member_str( get_field( 'gewohnheiten', $member_id ) ),
		),
		'events'       => $events,
	);

	return $data;
}

/**
 * Build suggested PDF filename for a member export.
 *
 * @param array<string, mixed> $data Member data from soe_telefonbuch_collect_member_data().
 * @return string
 */
function soe_telefonbuch_member_pdf_filename( $data ) {
	$nachname = isset( $data['header']['nachname'] ) ? (string) $data['header']['nachname'] : '';
	$vorname  = isset( $data['header']['vorname'] ) ? (string) $data['header']['vorname'] : '';
	$sanitize = function_exists( 'soe_payroll_sanitize_filename_part' ) ? 'soe_payroll_sanitize_filename_part' : function ( $s ) {
		return preg_replace( '/[^a-zA-Z0-9_-]/', '_', $s );
	};
	$nach_safe = $sanitize( $nachname );
	$vor_safe  = $sanitize( $vorname );
	return 'Mitgliedsdaten_' . $nach_safe . '_' . $vor_safe . '.pdf';
}
