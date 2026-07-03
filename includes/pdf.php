<?php
/**
 * Shared PDF generation (Dompdf) for plugin exports.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether Dompdf is available.
 *
 * @return bool
 */
function soe_pdf_can_dompdf() {
	static $can = null;
	if ( $can === null ) {
		$autoload = dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
		$can      = file_exists( $autoload ) && ( require_once $autoload ) && class_exists( '\Dompdf\Dompdf' );
	}
	return $can;
}

/**
 * Logo and font assets for PDF templates (base64 data URIs).
 *
 * @return array{font_ubuntu_light: string, font_ubuntu_medium: string, org_logo_img: string}
 */
function soe_pdf_get_branding_assets() {
	$plugin_dir = dirname( dirname( __FILE__ ) );
	$assets     = array(
		'font_ubuntu_light'  => '',
		'font_ubuntu_medium' => '',
		'org_logo_img'       => '',
	);

	$font_light_path  = $plugin_dir . '/assets/payroll/templates/Ubuntu-Light.ttf';
	$font_medium_path = $plugin_dir . '/assets/payroll/templates/Ubuntu-Medium.ttf';
	if ( file_exists( $font_light_path ) && is_readable( $font_light_path ) ) {
		$assets['font_ubuntu_light'] = 'data:font/truetype;base64,' . base64_encode( file_get_contents( $font_light_path ) );
	}
	if ( file_exists( $font_medium_path ) && is_readable( $font_medium_path ) ) {
		$assets['font_ubuntu_medium'] = 'data:font/truetype;base64,' . base64_encode( file_get_contents( $font_medium_path ) );
	}

	$logo_path = $plugin_dir . '/assets/img/logo/Logo-center.svg';
	if ( file_exists( $logo_path ) && is_readable( $logo_path ) ) {
		$logo_src = 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( $logo_path ) );
		$assets['org_logo_img'] = '<img src="' . esc_attr( $logo_src ) . '" alt="" class="org-logo" />';
	}

	return $assets;
}

/**
 * Stream HTML as PDF download (no server persistence).
 *
 * @param string $html     Full HTML document.
 * @param string $filename Download filename.
 * @return void
 */
function soe_pdf_stream_html( $html, $filename ) {
	if ( ! soe_pdf_can_dompdf() ) {
		wp_die( esc_html__( 'PDF-Export nicht verfügbar (Dompdf fehlt).', 'special-olympics-extension' ), '', array( 'response' => 503 ) );
	}

	$plugin_dir       = dirname( dirname( __FILE__ ) );
	$font_light_path  = $plugin_dir . '/assets/payroll/templates/Ubuntu-Light.ttf';
	$font_medium_path = $plugin_dir . '/assets/payroll/templates/Ubuntu-Medium.ttf';

	try {
		$options = new \Dompdf\Options();
		$options->set( 'isRemoteEnabled', true );
		$options->set( 'isFontSubsettingEnabled', true );
		$options->set( 'chroot', $plugin_dir );

		$dompdf = new \Dompdf\Dompdf( $options );
		$font_metrics = $dompdf->getFontMetrics();
		if ( file_exists( $font_light_path ) ) {
			$font_metrics->registerFont(
				array(
					'family' => 'Ubuntu Light',
					'style'  => 'normal',
					'weight' => 'normal',
				),
				$font_light_path
			);
		}
		if ( file_exists( $font_medium_path ) ) {
			$font_metrics->registerFont(
				array(
					'family' => 'Ubuntu Medium',
					'style'  => 'normal',
					'weight' => 'normal',
				),
				$font_medium_path
			);
		}

		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();
		$dompdf->stream( $filename, array( 'Attachment' => true ) );
		exit;
	} catch ( Exception $e ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'PDF stream failed', array( 'error' => $e->getMessage() ) );
		}
		wp_die( esc_html__( 'PDF konnte nicht erstellt werden.', 'special-olympics-extension' ), '', array( 'response' => 500 ) );
	}
}
