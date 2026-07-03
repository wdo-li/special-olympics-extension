<?php
/**
 * Telefonbuch per-member PDF export (admin_post handler + HTML renderer).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_post_soe_export_telefonbuch_pdf', 'soe_export_pdf_telefonbuch_member_handler' );

/**
 * Normalize and escape a scalar field value for PDF output.
 *
 * @param mixed $value Field value.
 * @return string Escaped HTML or empty string.
 */
function soe_telefonbuch_pdf_value_html( $value ) {
	$value = is_string( $value ) ? trim( $value ) : ( is_scalar( $value ) ? trim( (string) $value ) : '' );
	if ( $value === '' ) {
		return '';
	}
	$value = wp_strip_all_tags( $value );
	if ( $value === '' ) {
		return '';
	}
	return nl2br( esc_html( $value ) );
}

/**
 * Whether a field should span the full section width in the two-column grid.
 *
 * @param string $value Plain text value.
 * @return bool
 */
function soe_telefonbuch_pdf_should_span_full( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' ) {
		return false;
	}
	return strpos( $value, "\n" ) !== false || strlen( $value ) > 70;
}

/**
 * Build one label/value cell for the PDF grid.
 *
 * @param string $label      Field label.
 * @param string $value_html Pre-rendered value HTML.
 * @return string
 */
function soe_telefonbuch_pdf_field_cell( $label, $value_html ) {
	if ( $value_html === '' ) {
		return '';
	}
	return '<div class="pdf-field"><span class="pdf-label">' . esc_html( $label ) . '</span><span class="pdf-value">' . $value_html . '</span></div>';
}

/**
 * Render label/value fields in a two-column table grid (Dompdf-safe).
 *
 * Each item: label (string), value (string), optional full (bool), optional value_html (string).
 *
 * @param array<int, array<string, mixed>> $fields Field definitions.
 * @return string
 */
function soe_telefonbuch_pdf_fields_grid( $fields ) {
	$rows_html = '';
	$pending   = array();

	$flush_pair = function () use ( &$rows_html, &$pending ) {
		if ( empty( $pending ) ) {
			return;
		}
		$rows_html .= '<tr>';
		$rows_html .= '<td>' . $pending[0] . '</td>';
		$rows_html .= '<td>' . ( isset( $pending[1] ) ? $pending[1] : '' ) . '</td>';
		$rows_html .= '</tr>';
		$pending    = array();
	};

	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}
		$label = isset( $field['label'] ) ? (string) $field['label'] : '';
		$value = isset( $field['value'] ) ? (string) $field['value'] : '';
		$value_html = isset( $field['value_html'] ) ? (string) $field['value_html'] : soe_telefonbuch_pdf_value_html( $value );
		if ( $value_html === '' ) {
			continue;
		}

		$cell = soe_telefonbuch_pdf_field_cell( $label, $value_html );
		$full = ! empty( $field['full'] ) || ( $value !== '' && soe_telefonbuch_pdf_should_span_full( $value ) );

		if ( $full ) {
			$flush_pair();
			$rows_html .= '<tr><td colspan="2" class="pdf-field-full">' . $cell . '</td></tr>';
			continue;
		}

		$pending[] = $cell;
		if ( count( $pending ) === 2 ) {
			$flush_pair();
		}
	}

	$flush_pair();

	if ( $rows_html === '' ) {
		return '';
	}

	return '<table class="pdf-fields-grid" cellpadding="0" cellspacing="0"><tbody>' . $rows_html . '</tbody></table>';
}

/**
 * Build a single label/value row for PDF sections (legacy helper; prefer fields grid).
 *
 * @param string $label Field label.
 * @param string $value Field value.
 * @return string HTML or empty string when value is empty.
 */
function soe_telefonbuch_pdf_row( $label, $value ) {
	$value_html = soe_telefonbuch_pdf_value_html( $value );
	if ( $value_html === '' ) {
		return '';
	}
	return soe_telefonbuch_pdf_fields_grid(
		array(
			array(
				'label' => $label,
				'value' => (string) $value,
				'full'  => true,
			),
		)
	);
}

/**
 * Wrap rows in a titled section (skipped when rows are empty).
 *
 * @param string $title     Section title.
 * @param string $rows_html Inner HTML.
 * @return string
 */
function soe_telefonbuch_pdf_section( $title, $rows_html ) {
	$rows_html = trim( $rows_html );
	if ( $rows_html === '' ) {
		return '';
	}
	return '<div class="pdf-section"><h2 class="pdf-section-title">' . esc_html( $title ) . '</h2>' . $rows_html . '</div>';
}

/**
 * Format ja/nein values for PDF display.
 *
 * @param string $value Raw value.
 * @return string
 */
function soe_telefonbuch_pdf_ja_nein( $value ) {
	if ( function_exists( 'soe_format_ja_nein_value' ) ) {
		return soe_format_ja_nein_value( $value );
	}
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

/**
 * Build HTML list from string lines.
 *
 * @param string[] $lines Line items.
 * @return string
 */
function soe_telefonbuch_pdf_line_list( $lines ) {
	$lines = array_values( array_filter( array_map( 'trim', (array) $lines ) ) );
	if ( empty( $lines ) ) {
		return '';
	}
	$html = '<ul class="pdf-line-list">';
	foreach ( $lines as $line ) {
		$html .= '<li>' . esc_html( $line ) . '</li>';
	}
	$html .= '</ul>';
	return $html;
}

/**
 * Render page-one sections HTML from collected member data.
 *
 * @param array<string, mixed> $data Member data.
 * @return string
 */
function soe_telefonbuch_render_pdf_page_one( $data ) {
	$sections = array();

	$person = isset( $data['person'] ) && is_array( $data['person'] ) ? $data['person'] : array();
	$addr_parts = array();
	if ( ! empty( $person['address'] ) ) {
		$addr_parts[] = $person['address'];
	}
	$plz_ort = trim( ( $person['plz'] ?? '' ) . ' ' . ( $person['ort'] ?? '' ) );
	if ( $plz_ort !== '' ) {
		$addr_parts[] = $plz_ort;
	}
	if ( ! empty( $person['land'] ) ) {
		$addr_parts[] = $person['land'];
	}
	$person_rows = soe_telefonbuch_pdf_fields_grid(
		array(
			array(
				'label' => __( 'Adresse', 'special-olympics-extension' ),
				'value' => implode( "\n", $addr_parts ),
				'full'  => true,
			),
			array(
				'label' => __( 'Telefon', 'special-olympics-extension' ),
				'value' => $person['tel'] ?? '',
			),
			array(
				'label' => __( 'E-Mail', 'special-olympics-extension' ),
				'value' => $person['email'] ?? '',
			),
			array(
				'label' => __( 'Rolle', 'special-olympics-extension' ),
				'value' => $person['rolle'] ?? '',
			),
			array(
				'label' => __( 'Sportart', 'special-olympics-extension' ),
				'value' => $person['sport'] ?? '',
			),
		)
	);
	$sections[] = soe_telefonbuch_pdf_section( __( 'Person', 'special-olympics-extension' ), $person_rows );

	$stamm = isset( $data['stammdaten'] ) && is_array( $data['stammdaten'] ) ? $data['stammdaten'] : array();
	$sections[] = soe_telefonbuch_pdf_section(
		__( 'Stammdaten', 'special-olympics-extension' ),
		soe_telefonbuch_pdf_fields_grid(
			array(
				array( 'label' => __( 'Geburtsdatum', 'special-olympics-extension' ), 'value' => $stamm['geburtsdatum'] ?? '' ),
				array( 'label' => __( 'Geschlecht', 'special-olympics-extension' ), 'value' => $stamm['geschlecht'] ?? '' ),
				array( 'label' => __( 'Staatsbürgerschaft', 'special-olympics-extension' ), 'value' => $stamm['staatsburgerschaft'] ?? '' ),
				array( 'label' => __( 'Land', 'special-olympics-extension' ), 'value' => $stamm['land'] ?? '' ),
				array( 'label' => __( 'PEID-Nr.', 'special-olympics-extension' ), 'value' => $stamm['peid_nr'] ?? '' ),
			)
		)
	);

	$kleid = isset( $data['kleidung'] ) && is_array( $data['kleidung'] ) ? $data['kleidung'] : array();
	$sections[] = soe_telefonbuch_pdf_section(
		__( 'Kleidung', 'special-olympics-extension' ),
		soe_telefonbuch_pdf_fields_grid(
			array(
				array( 'label' => __( 'Kleidergrösse', 'special-olympics-extension' ), 'value' => $kleid['kleidergrosse'] ?? '' ),
				array( 'label' => __( 'Schuhgrösse', 'special-olympics-extension' ), 'value' => $kleid['schuhgrosse'] ?? '' ),
			)
		)
	);

	$notfall = isset( $data['notfallkontakt'] ) && is_array( $data['notfallkontakt'] ) ? $data['notfallkontakt'] : array();
	$sections[] = soe_telefonbuch_pdf_section(
		__( 'Notfallkontakt', 'special-olympics-extension' ),
		soe_telefonbuch_pdf_fields_grid(
			array(
				array( 'label' => __( 'Name', 'special-olympics-extension' ), 'value' => $notfall['name'] ?? '' ),
				array( 'label' => __( 'Telefon', 'special-olympics-extension' ), 'value' => $notfall['tel'] ?? '' ),
			)
		)
	);

	$kontakte = isset( $data['kontakte'] ) && is_array( $data['kontakte'] ) ? $data['kontakte'] : array();
	$kontakte_html = '';
	$weitere       = isset( $kontakte['weitere'] ) && is_array( $kontakte['weitere'] ) ? $kontakte['weitere'] : array();
	if ( ! empty( $weitere ) ) {
		$kontakte_html .= '<table class="pdf-kontakte"><thead><tr>';
		$kontakte_html .= '<th>' . esc_html__( 'Funktion', 'special-olympics-extension' ) . '</th>';
		$kontakte_html .= '<th>' . esc_html__( 'Vorname', 'special-olympics-extension' ) . '</th>';
		$kontakte_html .= '<th>' . esc_html__( 'Name', 'special-olympics-extension' ) . '</th>';
		$kontakte_html .= '<th>' . esc_html__( 'Adresse', 'special-olympics-extension' ) . '</th>';
		$kontakte_html .= '<th>' . esc_html__( 'E-Mail', 'special-olympics-extension' ) . '</th>';
		$kontakte_html .= '<th>' . esc_html__( 'Telefon', 'special-olympics-extension' ) . '</th>';
		$kontakte_html .= '</tr></thead><tbody>';
		foreach ( $weitere as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$kontakte_html .= '<tr>';
			$kontakte_html .= '<td>' . esc_html( $row['funktion'] ?? '' ) . '</td>';
			$kontakte_html .= '<td>' . esc_html( $row['vorname'] ?? '' ) . '</td>';
			$kontakte_html .= '<td>' . esc_html( $row['nachname'] ?? '' ) . '</td>';
			$kontakte_html .= '<td>' . esc_html( $row['adresse'] ?? '' ) . '</td>';
			$kontakte_html .= '<td>' . esc_html( $row['email'] ?? '' ) . '</td>';
			$kontakte_html .= '<td>' . esc_html( $row['telefon'] ?? '' ) . '</td>';
			$kontakte_html .= '</tr>';
		}
		$kontakte_html .= '</tbody></table>';
	}
	$bemerkungen = isset( $kontakte['bemerkungen'] ) ? trim( (string) $kontakte['bemerkungen'] ) : '';
	if ( $bemerkungen !== '' ) {
		$kontakte_html .= soe_telefonbuch_pdf_fields_grid(
			array(
				array(
					'label' => __( 'Bemerkungen', 'special-olympics-extension' ),
					'value' => $bemerkungen,
					'full'  => true,
				),
			)
		);
	}
	$sections[] = soe_telefonbuch_pdf_section( __( 'Weitere Kontakte', 'special-olympics-extension' ), $kontakte_html );

	$bank = isset( $data['bank'] ) && is_array( $data['bank'] ) ? $data['bank'] : array();
	if ( ! empty( $bank['include'] ) ) {
		$sections[] = soe_telefonbuch_pdf_section(
			__( 'Bankverbindung', 'special-olympics-extension' ),
			soe_telefonbuch_pdf_fields_grid(
				array(
					array( 'label' => __( 'Bank', 'special-olympics-extension' ), 'value' => $bank['bank_name'] ?? '' ),
					array( 'label' => __( 'IBAN', 'special-olympics-extension' ), 'value' => $bank['iban'] ?? '' ),
				)
			)
		);
	}

	return implode( '', array_filter( $sections ) );
}

/**
 * Render medical/page-two+ sections HTML from collected member data.
 *
 * @param array<string, mixed> $data Member data.
 * @return string
 */
function soe_telefonbuch_render_pdf_medical( $data ) {
	$sections = array();

	$diag = isset( $data['diagnose'] ) && is_array( $data['diagnose'] ) ? $data['diagnose'] : array();
	$sections[] = soe_telefonbuch_pdf_section(
		__( 'Diagnose', 'special-olympics-extension' ),
		soe_telefonbuch_pdf_fields_grid(
			array(
				array( 'label' => __( 'Hauptdiagnose', 'special-olympics-extension' ), 'value' => $diag['hauptdiagnose'] ?? '' ),
				array( 'label' => __( 'Nebendiagnosen', 'special-olympics-extension' ), 'value' => $diag['nebendiagnosen'] ?? '' ),
				array( 'label' => __( 'Psychische Leiden', 'special-olympics-extension' ), 'value' => $diag['psychische_leiden'] ?? '' ),
			)
		)
	);

	$tri = isset( $data['trisomie_21'] ) && is_array( $data['trisomie_21'] ) ? $data['trisomie_21'] : array();
	$tri_fields = array();
	if ( ! empty( $tri['betroffen'] ) ) {
		$tri_fields[] = array(
			'label' => __( 'Trisomie 21 betroffen', 'special-olympics-extension' ),
			'value' => soe_telefonbuch_pdf_ja_nein( $tri['betroffen'] ),
		);
	}
	if ( ! empty( $tri['betroffen_ja'] ) ) {
		if ( ! empty( $tri['roentgen'] ) ) {
			$tri_fields[] = array(
				'label' => __( 'Röntgenbilder HWS', 'special-olympics-extension' ),
				'value' => soe_telefonbuch_pdf_ja_nein( $tri['roentgen'] ),
			);
		}
		if ( ! empty( $tri['pathological'] ) ) {
			$tri_fields[] = array(
				'label' => __( 'Pathologisch (instabil)', 'special-olympics-extension' ),
				'value' => soe_telefonbuch_pdf_ja_nein( $tri['pathological'] ),
			);
		}
		if ( ! empty( $tri['result'] ) ) {
			$tri_fields[] = array(
				'label' => __( 'Röntgen-Resultat', 'special-olympics-extension' ),
				'value' => $tri['result'],
			);
		}
	}
	$sections[] = soe_telefonbuch_pdf_section( __( 'Trisomie 21', 'special-olympics-extension' ), soe_telefonbuch_pdf_fields_grid( $tri_fields ) );

	$med = isset( $data['medizin'] ) && is_array( $data['medizin'] ) ? $data['medizin'] : array();
	$med_fields = array(
		array( 'label' => __( 'Krankenkasse', 'special-olympics-extension' ), 'value' => $med['krankenkasse'] ?? '' ),
		array( 'label' => __( 'Krankenkasse ID-Nr.', 'special-olympics-extension' ), 'value' => $med['krankenkasse_idnr'] ?? '' ),
		array( 'label' => __( 'Unfallversicherung', 'special-olympics-extension' ), 'value' => $med['unfallv_name'] ?? '' ),
		array( 'label' => __( 'Unfallversicherung ID-Nr.', 'special-olympics-extension' ), 'value' => $med['unfallv_idnr'] ?? '' ),
		array( 'label' => __( 'Hausarzt', 'special-olympics-extension' ), 'value' => $med['hausarzt'] ?? '' ),
		array( 'label' => __( 'Hausarzt Telefon', 'special-olympics-extension' ), 'value' => $med['hausarzt_tel'] ?? '' ),
		array( 'label' => __( 'Zahnarzt', 'special-olympics-extension' ), 'value' => $med['zahnarzt'] ?? '' ),
		array( 'label' => __( 'Zahnarzt Telefon', 'special-olympics-extension' ), 'value' => $med['zahnarzt_tel'] ?? '' ),
	);
	$notfallmed = isset( $med['notfallmed'] ) && is_array( $med['notfallmed'] ) ? $med['notfallmed'] : array();
	if ( ! empty( $notfallmed ) ) {
		$med_fields[] = array(
			'label'      => __( 'Notfallmedikamente', 'special-olympics-extension' ),
			'value'      => implode( "\n", $notfallmed ),
			'value_html' => soe_telefonbuch_pdf_line_list( $notfallmed ),
			'full'       => true,
		);
	}
	$medikamente = isset( $med['medikamente'] ) && is_array( $med['medikamente'] ) ? $med['medikamente'] : array();
	if ( ! empty( $medikamente ) ) {
		$med_fields[] = array(
			'label'      => __( 'Medikamentangaben', 'special-olympics-extension' ),
			'value'      => implode( "\n", $medikamente ),
			'value_html' => soe_telefonbuch_pdf_line_list( $medikamente ),
			'full'       => true,
		);
	}
	$med_fields[] = array( 'label' => __( 'Allergien auf Medikamente', 'special-olympics-extension' ), 'value' => $med['allergien_med'] ?? '' );
	$med_fields[] = array( 'label' => __( 'Allergien auf Lebensmittel', 'special-olympics-extension' ), 'value' => $med['allergien_lebens'] ?? '' );
	$med_fields[] = array( 'label' => __( 'Andere Allergien', 'special-olympics-extension' ), 'value' => $med['allergien_andere'] ?? '' );
	$med_fields[] = array( 'label' => __( 'Ernährung Besonderheiten', 'special-olympics-extension' ), 'value' => $med['ernaehrung_bes'] ?? '' );
	$med_fields[] = array( 'label' => __( 'Ernährung weitere Informationen', 'special-olympics-extension' ), 'value' => $med['ernaehrung_weitere'] ?? '' );
	$sections[] = soe_telefonbuch_pdf_section( __( 'Medizin', 'special-olympics-extension' ), soe_telefonbuch_pdf_fields_grid( $med_fields ) );

	$hilf = isset( $data['hilfsmittel'] ) && is_array( $data['hilfsmittel'] ) ? $data['hilfsmittel'] : array();
	$sections[] = soe_telefonbuch_pdf_section(
		__( 'Hilfsmittel', 'special-olympics-extension' ),
		soe_telefonbuch_pdf_fields_grid(
			array(
				array( 'label' => __( 'Erforderliche Hilfsmittel', 'special-olympics-extension' ), 'value' => $hilf['erforderliche'] ?? '' ),
				array( 'label' => __( 'Unterstützung bei', 'special-olympics-extension' ), 'value' => $hilf['unterstutzung'] ?? '' ),
				array( 'label' => __( 'Andere Hilfsmittel', 'special-olympics-extension' ), 'value' => $hilf['andere'] ?? '' ),
			)
		)
	);

	$beach = isset( $data['beachtenswertes'] ) && is_array( $data['beachtenswertes'] ) ? $data['beachtenswertes'] : array();
	$sections[] = soe_telefonbuch_pdf_section(
		__( 'Beachtenswertes', 'special-olympics-extension' ),
		soe_telefonbuch_pdf_fields_grid(
			array(
				array( 'label' => __( 'Pflege/Betreuung', 'special-olympics-extension' ), 'value' => $beach['pflege'] ?? '' ),
				array( 'label' => __( 'Sprache/Kommunikation', 'special-olympics-extension' ), 'value' => $beach['sprache'] ?? '' ),
				array( 'label' => __( 'Verhalten/Auffälligkeiten', 'special-olympics-extension' ), 'value' => $beach['verhalten'] ?? '' ),
				array( 'label' => __( 'Vorlieben/Ängste', 'special-olympics-extension' ), 'value' => $beach['vorlieben'] ?? '' ),
				array( 'label' => __( 'Gewohnheiten', 'special-olympics-extension' ), 'value' => $beach['gewohnheiten'] ?? '' ),
			)
		)
	);

	$events = isset( $data['events'] ) && is_array( $data['events'] ) ? $data['events'] : array();
	if ( ! empty( $events ) ) {
		$events_html = soe_telefonbuch_pdf_fields_grid(
			array(
				array(
					'label'      => __( 'Teilnahmen', 'special-olympics-extension' ),
					'value'      => implode( "\n", $events ),
					'value_html' => soe_telefonbuch_pdf_line_list( $events ),
					'full'       => true,
				),
			)
		);
		$sections[] = soe_telefonbuch_pdf_section( __( 'Event-Teilnahmen', 'special-olympics-extension' ), $events_html );
	}

	return implode( '', array_filter( $sections ) );
}

/**
 * Render full member PDF HTML from collected data.
 *
 * @param array<string, mixed> $data Member data from soe_telefonbuch_collect_member_data().
 * @return string
 */
function soe_telefonbuch_render_member_pdf_html( $data ) {
	$plugin_dir    = dirname( dirname( __FILE__ ) );
	$template_path = $plugin_dir . '/assets/telefonbuch/templates/member-datasheet-template.html';
	if ( ! file_exists( $template_path ) || ! is_readable( $template_path ) ) {
		return '';
	}

	$html   = file_get_contents( $template_path );
	$assets = function_exists( 'soe_pdf_get_branding_assets' ) ? soe_pdf_get_branding_assets() : array();
	$header = isset( $data['header'] ) && is_array( $data['header'] ) ? $data['header'] : array();

	$page_one     = soe_telefonbuch_render_pdf_page_one( $data );
	$page_medical = soe_telefonbuch_render_pdf_medical( $data );
	if ( $page_medical !== '' ) {
		$page_medical = '<div class="pdf-medical-start">' . $page_medical . '</div>';
	}

	$export_date = isset( $header['export_date'] ) ? (string) $header['export_date'] : '';
	$exported_by = isset( $header['exported_by'] ) ? trim( (string) $header['exported_by'] ) : '';
	if ( $exported_by !== '' && $export_date !== '' ) {
		$export_meta = sprintf(
			/* translators: 1: user display name, 2: export date/time */
			__( 'Exportiert von %1$s am %2$s', 'special-olympics-extension' ),
			$exported_by,
			$export_date
		);
	} elseif ( $export_date !== '' ) {
		$export_meta = sprintf(
			/* translators: %s: export date/time */
			__( 'Export: %s', 'special-olympics-extension' ),
			$export_date
		);
	} else {
		$export_meta = '';
	}

	$replace = array(
		'{{font_ubuntu_light}}'   => $assets['font_ubuntu_light'] ?? '',
		'{{font_ubuntu_medium}}'  => $assets['font_ubuntu_medium'] ?? '',
		'{{org_logo_img}}'        => $assets['org_logo_img'] ?? '',
		'{{member_name}}'         => $header['member_name'] ?? '',
		'{{export_meta}}'         => esc_html( $export_meta ),
		'{{page_one_sections}}'   => $page_one,
		'{{page_medical_sections}}' => $page_medical,
	);

	return str_replace( array_keys( $replace ), array_values( $replace ), $html );
}

/**
 * admin_post handler: stream member PDF download.
 *
 * @return void
 */
function soe_export_pdf_telefonbuch_member_handler() {
	if ( ! current_user_can( 'view_telefonbuch' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}

	$member_id = isset( $_REQUEST['member_id'] ) ? absint( $_REQUEST['member_id'] ) : 0;
	if ( ! $member_id ) {
		wp_die( esc_html__( 'Ungültige Mitglied-ID.', 'special-olympics-extension' ), '', array( 'response' => 400 ) );
	}

	check_admin_referer( 'soe_export_telefonbuch_pdf_' . $member_id );

	if ( ! function_exists( 'soe_telefonbuch_member_is_exportable' ) || ! soe_telefonbuch_member_is_exportable( $member_id ) ) {
		wp_die( esc_html__( 'Mitglied nicht verfügbar.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}

	if ( ! function_exists( 'soe_pdf_can_dompdf' ) || ! soe_pdf_can_dompdf() ) {
		wp_die( esc_html__( 'PDF-Export nicht verfügbar (Dompdf fehlt).', 'special-olympics-extension' ), '', array( 'response' => 503 ) );
	}

	$data = soe_telefonbuch_collect_member_data( $member_id );
	if ( ! is_array( $data ) ) {
		wp_die( esc_html__( 'Mitgliedsdaten konnten nicht geladen werden.', 'special-olympics-extension' ), '', array( 'response' => 404 ) );
	}

	$html = soe_telefonbuch_render_member_pdf_html( $data );
	if ( $html === '' ) {
		wp_die( esc_html__( 'PDF-Vorlage fehlt.', 'special-olympics-extension' ), '', array( 'response' => 500 ) );
	}

	$filename = function_exists( 'soe_telefonbuch_member_pdf_filename' )
		? soe_telefonbuch_member_pdf_filename( $data )
		: 'Mitgliedsdaten.pdf';

	soe_pdf_stream_html( $html, $filename );
}
