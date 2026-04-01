<?php
/**
 * Telefonbuch (Mitgliederübersicht): admin page with Notfall and Alle Daten modes.
 *
 * Navigation point "Telefonbuch" in backend. Data: CPT mitglied (active only).
 * Admins see all; Hauptleiter/Leiter see only persons with at least one shared sport.
 * Two modes: Notfall (cards, tap-to-call) | Alle Daten (DataTables, colvis, expand).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'soe_telefonbuch_add_menu', 15 );
add_action( 'admin_enqueue_scripts', 'soe_telefonbuch_enqueue_scripts' );

/**
 * Adds Telefonbuch to the admin menu (left navigation).
 * Accessible for Administrators, Hauptleiter and Leiter.
 */
function soe_telefonbuch_add_menu() {
	add_menu_page(
		__( 'Telefonbuch', 'special-olympics-extension' ),
		__( 'Telefonbuch', 'special-olympics-extension' ),
		'view_telefonbuch',
		'soe-telefonbuch',
		'soe_render_telefonbuch_page',
		'dashicons-phone',
		null
	);
}

/**
 * Grant view_telefonbuch capability to administrators.
 */
add_action( 'admin_init', 'soe_grant_telefonbuch_cap_to_admin', 1 );
function soe_grant_telefonbuch_cap_to_admin() {
	$admin = get_role( 'administrator' );
	if ( $admin && ! $admin->has_cap( 'view_telefonbuch' ) ) {
		$admin->add_cap( 'view_telefonbuch' );
	}
}

/**
 * Returns mitglied posts for Telefonbuch (active only, filtered by rights).
 * Admins see all. Hauptleiter and Leiter see only members with at least one shared sport
 * (based on their linked Mitglied's sport taxonomy).
 *
 * @return WP_Post[]
 */
function soe_telefonbuch_get_members() {
	$args = array(
		'post_type'      => 'mitglied',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => SOE_MEMBER_STATUS_META, 'value' => SOE_MEMBER_STATUS_ARCHIVED, 'compare' => '!=' ),
			array( 'key' => SOE_MEMBER_STATUS_META, 'compare' => 'NOT EXISTS' ),
		),
	);
	$tax_queries = array();
	$user = wp_get_current_user();
	$user_id = $user && $user->ID ? (int) $user->ID : 0;
	$roles = $user && $user->ID ? (array) $user->roles : array();
	$is_admin = $user_id && function_exists( 'soe_user_is_admin' ) && soe_user_is_admin( $user_id );
	$is_hauptleiter_or_leiter = in_array( 'hauptleiter_in', $roles, true ) || in_array( 'leiter_in', $roles, true );
	if ( ! $is_admin && $is_hauptleiter_or_leiter ) {
		$mitglied_id = function_exists( 'soe_get_current_user_mitglied_id' ) ? soe_get_current_user_mitglied_id() : 0;
		if ( ! $mitglied_id ) {
			return array();
		}
		$my_terms = wp_get_object_terms( $mitglied_id, 'sport' );
		$my_slugs = is_array( $my_terms ) ? wp_list_pluck( $my_terms, 'term_id' ) : array();
		if ( empty( $my_slugs ) ) {
			return array();
		}
		$tax_queries[] = array(
			'taxonomy' => 'sport',
			'field'    => 'term_id',
			'terms'    => $my_slugs,
			'operator' => 'IN',
		);
	}
	if ( ! empty( $tax_queries ) ) {
		$args['tax_query'] = count( $tax_queries ) === 1
			? array( $tax_queries[0] )
			: array_merge( array( 'relation' => 'AND' ), $tax_queries );
	}
	$query = new WP_Query( $args );
	return $query->posts;
}

/**
 * Enqueues scripts and styles for Telefonbuch page.
 *
 * @param string $hook Current admin hook.
 */
function soe_telefonbuch_enqueue_scripts( $hook ) {
	if ( $hook !== 'toplevel_page_soe-telefonbuch' ) {
		return;
	}
	$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
	wp_enqueue_style(
		'datatables-css',
		$plugin_url . 'assets/css/jquery.dataTables.min.css',
		array(),
		'1.13.7'
	);
	wp_enqueue_script(
		'datatables-js',
		$plugin_url . 'assets/js/jquery.dataTables.min.js',
		array( 'jquery' ),
		'1.13.7',
		true
	);
	wp_enqueue_script(
		'soe-telefonbuch',
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/telefonbuch.js',
		array( 'jquery', 'datatables-js' ),
		SOE_PLUGIN_VERSION,
		true
	);
	wp_enqueue_style( 'dashicons' );
	wp_enqueue_style(
		'soe-telefonbuch-css',
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/telefonbuch.css',
		array( 'datatables-css', 'dashicons' ),
		SOE_PLUGIN_VERSION
	);
	$inline_css = '
		.soe-telefonbuch-card { margin-bottom: 1rem; padding: 1.25rem; border: 1px solid #ddd; border-radius: 6px; background: #fff; width: 100%; box-sizing: border-box; }
		.soe-telefonbuch-card-name { font-size: 1.25em; font-weight: 600; margin-bottom: 0.5rem; }
		.soe-telefonbuch-card-address { color: #666; margin-bottom: 0.75rem; }
		.soe-telefonbuch-card-address .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 2px; }
		.soe-telefonbuch-card-row { margin-top: 0.5rem; }
		.soe-telefonbuch-card-row .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 4px; }
		.soe-telefonbuch-card a[href^="tel:"] { font-weight: 600; }
		.soe-telefonbuch-switch .button { margin-right: 0.5rem; }
		.soe-telefonbuch-switch .dashicons { vertical-align: middle; margin-right: 4px; }
		.soe-telefonbuch-alldata { margin-top: 1rem; }
	';
	wp_add_inline_style( 'datatables-css', $inline_css );
}

/**
 * Renders the Telefonbuch page: mode switch (Notfall | Alle Daten), then cards or table.
 */
function soe_render_telefonbuch_page() {
	if ( ! current_user_can( 'view_telefonbuch' ) ) {
		wp_die( esc_html__( 'Du hast keine Berechtigung.', 'special-olympics-extension' ) );
	}
	$members = soe_telefonbuch_get_members();
	$mode = 'notfall';
	if ( isset( $_GET['mode'] ) ) {
		$mode_param = sanitize_text_field( wp_unslash( $_GET['mode'] ) );
		if ( $mode_param === 'all' ) {
			$mode = 'all';
		}
	}
	$base_url = admin_url( 'admin.php?page=soe-telefonbuch' );
	$sport_terms = get_terms( array( 'taxonomy' => 'sport', 'hide_empty' => false ) );
	if ( ! is_array( $sport_terms ) ) {
		$sport_terms = array();
	}
	?>
	<div class="wrap soe-telefonbuch-wrap">
		<h1><?php esc_html_e( 'Telefonbuch', 'special-olympics-extension' ); ?></h1>
		<p class="soe-telefonbuch-switch">
			<a href="<?php echo esc_url( add_query_arg( 'mode', 'notfall', $base_url ) ); ?>" class="button button-large soe-switch-notfall <?php echo $mode === 'notfall' ? 'button-primary' : ''; ?>"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php esc_html_e( 'Notfall', 'special-olympics-extension' ); ?></a>
			<a href="<?php echo esc_url( add_query_arg( 'mode', 'all', $base_url ) ); ?>" class="button button-large <?php echo $mode === 'all' ? 'button-primary' : ''; ?>"><span class="dashicons dashicons-list-view" aria-hidden="true"></span> <?php esc_html_e( 'Alle Daten', 'special-olympics-extension' ); ?></a>
		</p>

		<?php if ( $mode === 'notfall' ) : ?>
			<div class="soe-telefonbuch-notfall-filters" style="margin-bottom:1rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:center;">
				<div>
					<label for="soe-notfall-search"><?php esc_html_e( 'Suchen', 'special-olympics-extension' ); ?>:</label>
					<input type="text" id="soe-notfall-search" class="regular-text" placeholder="<?php esc_attr_e( 'Name eingeben…', 'special-olympics-extension' ); ?>" />
				</div>
				<div>
					<label for="soe-notfall-sport"><?php esc_html_e( 'Sportart', 'special-olympics-extension' ); ?>:</label>
					<select id="soe-notfall-sport">
						<option value=""><?php esc_html_e( 'Alle', 'special-olympics-extension' ); ?></option>
						<?php foreach ( $sport_terms as $term ) : ?>
							<option value="<?php echo esc_attr( $term->name ); ?>"><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="soe-telefonbuch-notfall">
				<?php
				usort( $members, function ( $a, $b ) {
					$na = get_field( 'nachname', $a->ID );
					$nb = get_field( 'nachname', $b->ID );
					return strcasecmp( (string) $na, (string) $nb );
				} );
				foreach ( $members as $m ) :
					$vorname = get_field( 'vorname', $m->ID );
					$nachname = get_field( 'nachname', $m->ID );
					$strasse = get_field( 'strasse', $m->ID );
					$hausnummer = get_field( 'hausnummer', $m->ID );
					$plz = get_field( 'postleitzahl', $m->ID );
					$ort = get_field( 'ort', $m->ID );
					list( $name_notfall, $tel_notfall ) = function_exists( 'soe_get_notfallkontakt_data' ) ? soe_get_notfallkontakt_data( $m->ID ) : array( '', '' );
					$weitere = get_field( 'weitere_kontakte', $m->ID );
					$notfallmed = get_field( 'notfallmedikamente', $m->ID );
					$medikamentangaben = get_field( 'medikamentangaben', $m->ID );
					$datenblatter = get_field( 'medizinische_datenblatter', $m->ID );
					$sport_terms_m = wp_get_object_terms( $m->ID, 'sport' );
					$sport_names_m = is_array( $sport_terms_m ) ? wp_list_pluck( $sport_terms_m, 'name' ) : array();
					$sport_data = implode( ', ', $sport_names_m );
					?>
					<div class="soe-telefonbuch-card" data-search-text="<?php echo esc_attr( strtolower( trim( (string) $vorname . ' ' . (string) $nachname . ' ' . (string) $strasse . ' ' . (string) $ort ) ) ); ?>" data-sport="<?php echo esc_attr( $sport_data ); ?>">
						<div class="soe-telefonbuch-card-name"><?php echo esc_html( trim( (string) $vorname . ' ' . (string) $nachname ) ); ?></div>
						<div class="soe-telefonbuch-card-address"><span class="dashicons dashicons-location" aria-hidden="true"></span> <?php echo esc_html( trim( (string) $strasse . ' ' . (string) $hausnummer ) ); ?>, <?php echo esc_html( (string) $plz . ' ' . (string) $ort ); ?></div>
						<?php if ( $name_notfall || $tel_notfall ) : ?>
							<div class="soe-telefonbuch-card-row soe-card-notfall-highlight">
								<span class="dashicons dashicons-warning" aria-hidden="true"></span>
								<strong><?php esc_html_e( 'Notfallkontakt', 'special-olympics-extension' ); ?>:</strong>
								<?php echo esc_html( (string) $name_notfall ); ?>
								<?php if ( $tel_notfall ) : ?>
									<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $tel_notfall ) ); ?>" class="soe-card-tel-link"><?php echo esc_html( $tel_notfall ); ?></a>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<?php if ( is_array( $weitere ) && ! empty( $weitere ) ) : ?>
							<div class="soe-telefonbuch-card-row soe-card-section">
								<span class="dashicons dashicons-phone" aria-hidden="true"></span>
								<strong><?php esc_html_e( 'Weitere Kontakte', 'special-olympics-extension' ); ?>:</strong><br/>
								<?php
								$wk_parts = array();
								foreach ( $weitere as $k ) {
									$part = trim( ( isset( $k['funktion'] ) ? $k['funktion'] . ' ' : '' ) . ( isset( $k['vorname'] ) ? $k['vorname'] . ' ' : '' ) . ( isset( $k['nachname'] ) ? $k['nachname'] : '' ) );
									if ( ! empty( $k['telefon'] ) ) {
										$part .= ' <a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $k['telefon'] ) ) . '" class="soe-card-tel-link">' . esc_html( $k['telefon'] ) . '</a>';
									}
									$wk_parts[] = $part;
								}
								echo wp_kses( implode( '<br/>', $wk_parts ), array( 'br' => array(), 'a' => array( 'href' => array(), 'class' => array() ) ) );
								?></div>
						<?php endif; ?>
						<?php if ( is_array( $notfallmed ) && ! empty( $notfallmed ) ) : ?>
							<div class="soe-telefonbuch-card-row soe-card-section"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <strong><?php esc_html_e( 'Notfallmedikamente', 'special-olympics-extension' ); ?>:</strong><br/>
								<?php
								$med_parts = array();
								foreach ( $notfallmed as $med ) {
									$name = trim( isset( $med['name_medikament_notfall'] ) ? (string) $med['name_medikament_notfall'] : '' );
									$dosis = trim( isset( $med['dosis_medikament_notfall'] ) ? (string) $med['dosis_medikament_notfall'] : '' );
									$med_parts[] = $name && $dosis ? $name . ', ' . $dosis : ( $name ?: $dosis );
								}
								echo esc_html( implode( '; ', $med_parts ) );
								?></div>
						<?php endif; ?>
						<?php if ( is_array( $medikamentangaben ) && ! empty( $medikamentangaben ) ) : ?>
							<div class="soe-telefonbuch-card-row soe-card-section"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <strong><?php esc_html_e( 'Medikamentangaben', 'special-olympics-extension' ); ?>:</strong><br/>
								<?php
								$med2_parts = array();
								foreach ( $medikamentangaben as $med ) {
									$name = trim( isset( $med['name_medikament'] ) ? (string) $med['name_medikament'] : '' );
									$dosis = trim( isset( $med['dosis_medikament'] ) ? (string) $med['dosis_medikament'] : '' );
									$med2_parts[] = $name && $dosis ? $name . ', ' . $dosis : ( $name ?: $dosis );
								}
								echo esc_html( implode( '; ', $med2_parts ) );
								?></div>
						<?php endif; ?>
						<?php
						if ( $datenblatter ) :
							// Extract attachment ID - handle various ACF formats including transformed arrays
							$att_id = 0;
							if ( is_numeric( $datenblatter ) ) {
								$att_id = (int) $datenblatter;
							} elseif ( is_array( $datenblatter ) ) {
								// Check for nested format from format_value filter: array('ID' => array('id' => X))
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
							if ( $att_id ) :
								$proxy_url = wp_nonce_url(
									admin_url( 'admin-post.php?action=soe_medical_download&id=' . $att_id ),
									'soe_medical_download_' . $att_id
								);
								?>
								<a href="<?php echo esc_url( $proxy_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Medizinische Datenblätter', 'special-olympics-extension' ); ?> (Download)</a>
							<?php endif;
						endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else :
			usort( $members, function ( $a, $b ) {
				$na = get_field( 'nachname', $a->ID );
				$nb = get_field( 'nachname', $b->ID );
				return strcasecmp( (string) $na, (string) $nb );
			} );
			$event_snapshot_meta = defined( 'SOE_EVENT_SNAPSHOT_META' ) ? SOE_EVENT_SNAPSHOT_META : 'soe_event_snapshot';
			$detail_by_id        = array();
			$soe_tb_admin        = current_user_can( 'manage_options' );
			?>
			<div class="soe-telefonbuch-alldata">
				<?php
				$col_config = array(
					array( 'idx' => 1,  'label' => __( 'Bearbeiten', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 2,  'label' => __( 'Nachname', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 3,  'label' => __( 'Vorname', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 4,  'label' => __( 'Rolle', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 5,  'label' => __( 'Telefon', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 6,  'label' => __( 'E-Mail', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 7,  'label' => __( 'Ort', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 8,  'label' => __( 'Strasse', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 9,  'label' => __( 'PLZ', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 10, 'label' => __( 'Sportart', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 11, 'label' => __( 'Kleidergrösse', 'special-olympics-extension' ), 'default' => false ),
					array( 'idx' => 12, 'label' => __( 'Schuhgrösse', 'special-olympics-extension' ), 'default' => false ),
					array( 'idx' => 13, 'label' => __( 'Notfallkontakt', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 14, 'label' => __( 'Notfallkontakt Telefon', 'special-olympics-extension' ), 'default' => true ),
					array( 'idx' => 15, 'label' => __( 'Events', 'special-olympics-extension' ), 'default' => false ),
				);
				if ( $soe_tb_admin ) {
					$col_config[] = array( 'idx' => 16, 'label' => __( 'Bank', 'special-olympics-extension' ), 'default' => false );
					$col_config[] = array( 'idx' => 17, 'label' => __( 'IBAN', 'special-olympics-extension' ), 'default' => false );
				}
				?>
				<div class="soe-telefonbuch-controls">
					<div class="soe-colvis-actions">
						<span class="description"><?php esc_html_e( 'Spalten einblenden:', 'special-olympics-extension' ); ?></span>
						<div class="soe-colvis-checkboxes">
							<?php foreach ( $col_config as $col ) : ?>
								<label class="soe-colvis-checkbox<?php echo $col['label'] === '' ? ' soe-colvis-checkbox-empty' : ''; ?>">
									<input type="checkbox" class="soe-colvis-cb" data-column="<?php echo (int) $col['idx']; ?>"<?php echo $col['default'] ? ' checked' : ''; ?>>
									<?php echo $col['label'] !== '' ? esc_html( $col['label'] ) : '—'; ?>
								</label>
							<?php endforeach; ?>
						</div>
						<span class="soe-colvis-presets">
							<button type="button" class="button button-small soe-colvis-all"><?php esc_html_e( 'Alle', 'special-olympics-extension' ); ?></button>
							<button type="button" class="button button-small soe-colvis-default"><?php esc_html_e( 'Nur Standard', 'special-olympics-extension' ); ?></button>
						</span>
					</div>
					<div class="soe-telefonbuch-filters">
						<label for="soe-filter-sport"><?php esc_html_e( 'Sportart', 'special-olympics-extension' ); ?>:</label>
						<select id="soe-filter-sport" class="soe-table-filter" data-column="10">
							<option value=""><?php esc_html_e( 'Alle', 'special-olympics-extension' ); ?></option>
							<?php foreach ( $sport_terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->name ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<label for="soe-filter-ort"><?php esc_html_e( 'Ort', 'special-olympics-extension' ); ?>:</label>
						<input type="text" id="soe-filter-ort" class="soe-table-filter" data-column="7" placeholder="<?php esc_attr_e( 'Ort eingeben…', 'special-olympics-extension' ); ?>" />
					</div>
					<div class="soe-telefonbuch-actions">
						<button type="button" class="button soe-telefonbuch-copy-emails" title="<?php esc_attr_e( 'Alle sichtbaren E-Mail-Adressen kopieren', 'special-olympics-extension' ); ?>"><?php esc_html_e( 'Mailadressen kopieren', 'special-olympics-extension' ); ?></button>
						<?php if ( function_exists( 'soe_export_can_xls' ) && soe_export_can_xls() ) : ?>
							<form id="soe-telefonbuch-export-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
								<input type="hidden" name="action" value="soe_export_telefonbuch" />
								<?php wp_nonce_field( 'soe_export_telefonbuch' ); ?>
								<input type="hidden" name="ids" id="soe-telefonbuch-export-ids" value="" />
								<button type="submit" class="button soe-telefonbuch-export-btn"><?php esc_html_e( 'Als Excel exportieren', 'special-olympics-extension' ); ?></button>
							</form>
						<?php endif; ?>
					</div>
				</div>
				<table id="soe-telefonbuch-table" class="display stripe" style="width:100%">
					<thead>
						<tr>
							<th class="soe-search-col" aria-hidden="true"></th>
							<th class="soe-edit-col"></th>
							<th><?php esc_html_e( 'Nachname', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Vorname', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Rolle', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Telefon', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'E-Mail', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Ort', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Strasse', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'PLZ', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Sportart', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Kleidergrösse', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Schuhgrösse', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Notfallkontakt', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Notfallkontakt Telefon', 'special-olympics-extension' ); ?></th>
							<th><?php esc_html_e( 'Events', 'special-olympics-extension' ); ?></th>
							<?php if ( $soe_tb_admin ) : ?>
								<th><?php esc_html_e( 'Bank', 'special-olympics-extension' ); ?></th>
								<th><?php esc_html_e( 'IBAN', 'special-olympics-extension' ); ?></th>
							<?php endif; ?>
							<th class="soe-detail-col"><?php esc_html_e( 'Detail', 'special-olympics-extension' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
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
						?>
						<?php foreach ( $members as $m ) :
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
							$tel = get_field( 'telefonnummer', $m->ID );
							$email = get_field( 'e-mail', $m->ID );
							$ort = get_field( 'ort', $m->ID );
							$strasse = get_field( 'strasse', $m->ID );
							$hausnummer = get_field( 'hausnummer', $m->ID );
							$plz = get_field( 'postleitzahl', $m->ID );
							list( $name_notfall, $tel_notfall ) = function_exists( 'soe_get_notfallkontakt_data' ) ? soe_get_notfallkontakt_data( $m->ID ) : array( '', '' );
							$snapshot = get_post_meta( $m->ID, $event_snapshot_meta, true );
							$events_label = '';
							if ( is_array( $snapshot ) && ! empty( $snapshot ) ) {
								$titles = array();
								foreach ( array_slice( $snapshot, 0, 5 ) as $e ) {
									$titles[] = isset( $e['title'] ) ? $e['title'] : '';
								}
								$events_label = implode( ', ', array_filter( $titles ) );
								if ( count( $snapshot ) > 5 ) {
									$events_label .= ' …';
								}
							}
							$edit_link = current_user_can( 'edit_post', $m->ID ) ? get_edit_post_link( $m->ID, 'raw' ) : '';
							$sport_terms_m = wp_get_object_terms( $m->ID, 'sport' );
							$sport_labels = is_array( $sport_terms_m ) ? implode( ', ', wp_list_pluck( $sport_terms_m, 'name' ) ) : '';

							$kleidergrosse = get_field( 'kleidergrosse', $m->ID );
							$schuhgrosse   = get_field( 'schuhgrosse', $m->ID );
							$weitere       = get_field( 'weitere_kontakte', $m->ID );
							$notfallmed    = get_field( 'notfallmedikamente', $m->ID );
							$medikamentangaben = get_field( 'medikamentangaben', $m->ID );
							$datenblatter       = get_field( 'medizinische_datenblatter', $m->ID );
							$krankenkasse       = get_field( 'krankenkasse_name_&_ort', $m->ID );
							$krankenkasse_idnr  = get_field( 'krankenkasse_idnr', $m->ID );
							$unfallv_name       = get_field( 'unfallversicherung_name_&_ort', $m->ID );
							$unfallv_idnr       = get_field( 'unfallversicherung_idnr', $m->ID );
							$hausarzt           = get_field( 'hausarzt_name', $m->ID );
							$hausarzt_tel       = get_field( 'hausarzt_name_telnr', $m->ID );
							$zahnarzt           = get_field( 'zahnarzt_name', $m->ID );
							$zahnarzt_tel       = get_field( 'zahnarzt_telnr', $m->ID );
							$allergien_med      = get_field( 'allergien_auf_medikamente', $m->ID );
							$allergien_lebens   = get_field( 'allergien_auf_lebensmittel', $m->ID );
							$allergien_andere   = get_field( 'andere_allergien', $m->ID );
							$ernaehrung_bes     = get_field( 'ernahrung_besonderheiten', $m->ID );
							$ernaehrung_weitere = get_field( 'ernahrung_weitere_informationen', $m->ID );
							$bemerkungen        = get_field( 'bemerkungen', $m->ID );
							$erforderliche_hilfsmittel = get_field( 'erforderliche_hilfsmittel', $m->ID );
							$unterstutzung_bei  = get_field( 'unterstutzung_bei', $m->ID );
							$andere_hilfsmittel = get_field( 'andere_hilfsmittel', $m->ID );
							$pflegebetreuung    = get_field( 'pflegebetreuung', $m->ID );
							$sprachekommunikation = get_field( 'sprachekommunikation', $m->ID );
							$verhaltenauffalligkeiten = get_field( 'verhaltenauffalligkeiten', $m->ID );
							$vorliebenangste    = get_field( 'vorliebenangste', $m->ID );
							$medizin_parts = array();
							if ( is_array( $notfallmed ) && ! empty( $notfallmed ) ) {
								$names = array_map( function ( $med ) { return isset( $med['name_medikament_notfall'] ) ? $med['name_medikament_notfall'] : ''; }, $notfallmed );
								$medizin_parts[] = implode( ', ', array_filter( $names ) );
							}
							if ( is_string( $allergien_med ) && trim( $allergien_med ) !== '' ) {
								$medizin_parts[] = 'Allergien: ' . $allergien_med;
							}
							if ( is_string( $krankenkasse ) && trim( $krankenkasse ) !== '' ) {
								$medizin_parts[] = $krankenkasse;
							}
							if ( is_string( $hausarzt ) && trim( $hausarzt ) !== '' ) {
								$medizin_parts[] = 'Hausarzt: ' . $hausarzt;
							}
							$has_hilfsmittel = ( is_array( $erforderliche_hilfsmittel ) && ! empty( $erforderliche_hilfsmittel ) ) || ( is_array( $unterstutzung_bei ) && ! empty( $unterstutzung_bei ) ) || ( is_string( $andere_hilfsmittel ) && trim( $andere_hilfsmittel ) !== '' );
							if ( $has_hilfsmittel ) {
								$medizin_parts[] = __( 'Hilfsmittel', 'special-olympics-extension' );
							}
							$has_beachtenswertes = ( is_string( $pflegebetreuung ) && trim( $pflegebetreuung ) !== '' ) || ( is_string( $sprachekommunikation ) && trim( $sprachekommunikation ) !== '' ) || ( is_string( $verhaltenauffalligkeiten ) && trim( $verhaltenauffalligkeiten ) !== '' ) || ( is_string( $vorliebenangste ) && trim( $vorliebenangste ) !== '' );
							if ( $has_beachtenswertes ) {
								$medizin_parts[] = __( 'Beachtenswertes', 'special-olympics-extension' );
							}
							$medizin_label = implode( '; ', array_filter( $medizin_parts ) );
							if ( strlen( $medizin_label ) > 80 ) {
								$medizin_label = substr( $medizin_label, 0, 77 ) . '…';
							}
							// Full-text search string for DataTables (all columns including Medizin, Kontakte, etc.)
							$bank_info_tb = get_field( 'bank_informationen', $m->ID );
							$bank_name_tb = is_array( $bank_info_tb ) && isset( $bank_info_tb['bank_name'] ) ? (string) $bank_info_tb['bank_name'] : '';
							$bank_iban_tb = is_array( $bank_info_tb ) && isset( $bank_info_tb['bank_iban'] ) ? (string) $bank_info_tb['bank_iban'] : '';
							$search_parts = array(
								$vorname, $nachname, $tel, $email, $ort, $strasse, $hausnummer, $plz,
								$sport_labels, $kleidergrosse, $schuhgrosse, $name_notfall, $tel_notfall,
								$medizin_label, $events_label,
							);
							if ( $soe_tb_admin ) {
								$search_parts[] = $bank_name_tb;
								$search_parts[] = $bank_iban_tb;
							}
							if ( is_array( $notfallmed ) ) {
								foreach ( $notfallmed as $med ) {
									$search_parts[] = isset( $med['name_medikament_notfall'] ) ? $med['name_medikament_notfall'] : '';
									$search_parts[] = isset( $med['dosis_medikament_notfall'] ) ? $med['dosis_medikament_notfall'] : '';
								}
							}
							if ( is_array( $medikamentangaben ) ) {
								foreach ( $medikamentangaben as $med ) {
									$search_parts[] = isset( $med['name_medikament'] ) ? $med['name_medikament'] : '';
									$search_parts[] = isset( $med['dosis_medikament'] ) ? $med['dosis_medikament'] : '';
								}
							}
							$search_parts[] = $allergien_med;
							$search_parts[] = $allergien_lebens;
							$search_parts[] = $allergien_andere;
							$search_parts[] = $krankenkasse;
							$search_parts[] = $krankenkasse_idnr;
							$search_parts[] = $unfallv_name;
							$search_parts[] = $unfallv_idnr;
							$search_parts[] = $hausarzt;
							$search_parts[] = $hausarzt_tel;
							$search_parts[] = $zahnarzt;
							$search_parts[] = $zahnarzt_tel;
							$search_parts[] = is_array( $ernaehrung_bes ) ? implode( ' ', $ernaehrung_bes ) : $ernaehrung_bes;
							$search_parts[] = $ernaehrung_weitere;
							$search_parts[] = $bemerkungen;
							$search_parts[] = is_array( $erforderliche_hilfsmittel ) ? implode( ' ', $erforderliche_hilfsmittel ) : $erforderliche_hilfsmittel;
							$search_parts[] = is_array( $unterstutzung_bei ) ? implode( ' ', $unterstutzung_bei ) : $unterstutzung_bei;
							$search_parts[] = $andere_hilfsmittel;
							$search_parts[] = $pflegebetreuung;
							$search_parts[] = $sprachekommunikation;
							$search_parts[] = $verhaltenauffalligkeiten;
							$search_parts[] = $vorliebenangste;
							if ( is_array( $weitere ) ) {
								foreach ( $weitere as $k ) {
									$search_parts[] = isset( $k['funktion'] ) ? $k['funktion'] : '';
									$search_parts[] = isset( $k['vorname'] ) ? $k['vorname'] : '';
									$search_parts[] = isset( $k['nachname'] ) ? $k['nachname'] : '';
									$search_parts[] = isset( $k['adresse'] ) ? $k['adresse'] : '';
									$search_parts[] = isset( $k['e-mail'] ) ? $k['e-mail'] : '';
									$search_parts[] = isset( $k['telefon'] ) ? $k['telefon'] : '';
								}
							}
							$search_parts_flat = array_filter( array_map( function ( $v ) {
								if ( is_array( $v ) ) {
									return implode( ' ', $v );
								}
								return is_string( $v ) ? trim( $v ) : '';
							}, $search_parts ) );
							$search_text = strtolower( implode( ' ', array_map( 'wp_strip_all_tags', $search_parts_flat ) ) );
							ob_start();
							echo '<div class="soe-telefonbuch-detail">';
							$has_kontakte_content = ( is_string( $bemerkungen ) && trim( $bemerkungen ) !== '' );
							if ( is_array( $weitere ) && ! empty( $weitere ) ) {
								foreach ( $weitere as $k ) {
									$parts = array(
										isset( $k['funktion'] ) ? trim( (string) $k['funktion'] ) : '',
										isset( $k['vorname'] ) ? trim( (string) $k['vorname'] ) : '',
										isset( $k['nachname'] ) ? trim( (string) $k['nachname'] ) : '',
										isset( $k['adresse'] ) ? trim( (string) $k['adresse'] ) : '',
										isset( $k['e-mail'] ) ? trim( (string) $k['e-mail'] ) : '',
										isset( $k['telefon'] ) ? trim( (string) $k['telefon'] ) : '',
									);
									if ( implode( '', $parts ) !== '' ) {
										$has_kontakte_content = true;
										break;
									}
								}
							}
							echo '<details class="soe-telefonbuch-detail-medizin soe-telefonbuch-detail-kontakte" open><summary>' . esc_html__( 'Weitere Kontakte', 'special-olympics-extension' ) . '</summary><div class="soe-telefonbuch-detail-medizin-inner">';
							if ( $has_kontakte_content ) {
								if ( is_array( $weitere ) && ! empty( $weitere ) ) {
									$rows_out = 0;
									echo '<table class="soe-telefonbuch-kontakte-table"><tbody>';
									foreach ( $weitere as $k ) {
										$funktion  = isset( $k['funktion'] ) ? trim( (string) $k['funktion'] ) : '';
										$vorname_k = isset( $k['vorname'] ) ? trim( (string) $k['vorname'] ) : '';
										$nachname_k = isset( $k['nachname'] ) ? trim( (string) $k['nachname'] ) : '';
										$adresse   = isset( $k['adresse'] ) ? trim( (string) $k['adresse'] ) : '';
										$email_k   = isset( $k['e-mail'] ) ? trim( (string) $k['e-mail'] ) : '';
										$telefon   = isset( $k['telefon'] ) ? trim( (string) $k['telefon'] ) : '';
										$has_any = $funktion !== '' || $vorname_k !== '' || $nachname_k !== '' || $adresse !== '' || $email_k !== '' || $telefon !== '';
										if ( ! $has_any ) {
											continue;
										}
										$rows_out++;
										echo '<tr>';
										echo '<td>' . esc_html( $funktion ) . '</td>';
										echo '<td>' . esc_html( $vorname_k ) . '</td>';
										echo '<td>' . esc_html( $nachname_k ) . '</td>';
										echo '<td>' . esc_html( $adresse ) . '</td>';
										echo '<td>' . ( $email_k !== '' ? '<a href="mailto:' . esc_attr( $email_k ) . '">' . esc_html( $email_k ) . '</a>' : '' ) . '</td>';
										echo '<td>' . ( $telefon !== '' ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $telefon ) ) . '">' . esc_html( $telefon ) . '</a>' : '' ) . '</td>';
										echo '</tr>';
									}
									echo '</tbody></table>';
								}
								if ( is_string( $bemerkungen ) && trim( $bemerkungen ) !== '' ) {
									$bemerkungen_text = trim( $bemerkungen );
									$bemerkungen_text = wp_strip_all_tags( $bemerkungen_text );
									echo '<p class="soe-telefonbuch-bemerkungen">' . nl2br( esc_html( $bemerkungen_text ) ) . '</p>';
								}
							} else {
								echo '<p class="soe-telefonbuch-detail-empty">' . esc_html__( 'Keine weiteren Kontakte oder Bemerkungen erfasst.', 'special-olympics-extension' ) . '</p>';
							}
							echo '</div></details>';
							echo '<details class="soe-telefonbuch-detail-medizin" open><summary>' . esc_html__( 'Medizin', 'special-olympics-extension' ) . '</summary><div class="soe-telefonbuch-detail-medizin-inner soe-med-layout">';
							if ( $datenblatter ) {
								// Extract attachment ID - handle various ACF formats including transformed arrays
								$att_id = 0;
								if ( is_numeric( $datenblatter ) ) {
									$att_id = (int) $datenblatter;
								} elseif ( is_array( $datenblatter ) ) {
									// Check for nested format from format_value filter: array('ID' => array('id' => X))
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
									$proxy_url = wp_nonce_url(
										admin_url( 'admin-post.php?action=soe_medical_download&id=' . $att_id ),
										'soe_medical_download_' . $att_id
									);
									echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Medizinische Datenblätter', 'special-olympics-extension' ) . '</span><span class="soe-med-value"><a href="' . esc_url( $proxy_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Download', 'special-olympics-extension' ) . '</a></span></div>';
								}
							}
							$has_versicherung = ( is_string( $krankenkasse ) && trim( $krankenkasse ) !== '' ) || ( is_string( $krankenkasse_idnr ) && trim( $krankenkasse_idnr ) !== '' ) || ( is_string( $unfallv_name ) && trim( $unfallv_name ) !== '' ) || ( is_string( $unfallv_idnr ) && trim( $unfallv_idnr ) !== '' );
							$has_aerzte = ( is_string( $hausarzt ) && trim( $hausarzt ) !== '' ) || ( is_string( $hausarzt_tel ) && trim( $hausarzt_tel ) !== '' ) || ( is_string( $zahnarzt ) && trim( $zahnarzt ) !== '' ) || ( is_string( $zahnarzt_tel ) && trim( $zahnarzt_tel ) !== '' );
							$has_allergien = ( is_string( $allergien_med ) && trim( $allergien_med ) !== '' ) || ( is_string( $allergien_lebens ) && trim( $allergien_lebens ) !== '' ) || ( is_string( $allergien_andere ) && trim( $allergien_andere ) !== '' );
							$ernaehrung_bes_arr = is_array( $ernaehrung_bes ) ? $ernaehrung_bes : ( is_string( $ernaehrung_bes ) ? array( $ernaehrung_bes ) : array() );
							$has_ernaehrung = ! empty( $ernaehrung_bes_arr ) || ( is_string( $ernaehrung_weitere ) && trim( $ernaehrung_weitere ) !== '' );
							$has_left  = ( is_array( $notfallmed ) && ! empty( $notfallmed ) ) || ( is_array( $medikamentangaben ) && ! empty( $medikamentangaben ) ) || $has_allergien;
							$has_right = $has_versicherung || $has_aerzte || $has_ernaehrung;
							if ( $has_left || $has_right ) {
								echo '<div class="soe-med-cols">';
								echo '<div class="soe-med-col soe-med-col-left">';
								if ( is_array( $notfallmed ) && ! empty( $notfallmed ) ) {
									echo '<div class="soe-med-group"><div class="soe-med-row soe-med-row-title">' . esc_html__( 'Notfallmedikamente', 'special-olympics-extension' ) . '</div><ul class="soe-med-list">';
									foreach ( $notfallmed as $med ) {
										$name = trim( isset( $med['name_medikament_notfall'] ) ? (string) $med['name_medikament_notfall'] : '' );
										$dosis = trim( isset( $med['dosis_medikament_notfall'] ) ? (string) $med['dosis_medikament_notfall'] : '' );
										$med_text = $name && $dosis ? $name . ', ' . $dosis : ( $name ?: $dosis );
										if ( $med_text !== '' ) {
											echo '<li>' . esc_html( $med_text ) . '</li>';
										}
									}
									echo '</ul></div>';
								}
								if ( is_array( $medikamentangaben ) && ! empty( $medikamentangaben ) ) {
									echo '<div class="soe-med-group"><div class="soe-med-row soe-med-row-title">' . esc_html__( 'Medikamentangaben', 'special-olympics-extension' ) . '</div><ul class="soe-med-list">';
									foreach ( $medikamentangaben as $med ) {
										$name = trim( isset( $med['name_medikament'] ) ? (string) $med['name_medikament'] : '' );
										$dosis = trim( isset( $med['dosis_medikament'] ) ? (string) $med['dosis_medikament'] : '' );
										$med_text = $name && $dosis ? $name . ', ' . $dosis : ( $name ?: $dosis );
										if ( $med_text !== '' ) {
											echo '<li>' . esc_html( $med_text ) . '</li>';
										}
									}
									echo '</ul></div>';
								}
								if ( $has_allergien ) {
									echo '<div class="soe-med-group soe-med-group-allergien"><div class="soe-med-row soe-med-row-title">' . esc_html__( 'Bekannte Allergien', 'special-olympics-extension' ) . '</div><div class="soe-med-subgrid">';
									if ( is_string( $allergien_med ) && trim( $allergien_med ) !== '' ) {
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Medikamente', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( $allergien_med ) . '</span></div>';
									}
									if ( is_string( $allergien_lebens ) && trim( $allergien_lebens ) !== '' ) {
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Lebensmittel', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( $allergien_lebens ) . '</span></div>';
									}
									if ( is_string( $allergien_andere ) && trim( $allergien_andere ) !== '' ) {
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Andere', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( $allergien_andere ) . '</span></div>';
									}
									echo '</div></div>';
								}
								echo '</div>';
								echo '<div class="soe-med-col soe-med-col-right">';
								if ( $has_versicherung ) {
									echo '<div class="soe-med-group soe-med-group-versicherung"><div class="soe-med-row soe-med-row-title">' . esc_html__( 'Versicherungen', 'special-olympics-extension' ) . '</div><div class="soe-med-subgrid">';
									if ( is_string( $krankenkasse ) && trim( $krankenkasse ) !== '' ) {
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Krankenkasse', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( $krankenkasse ) . '</span></div>';
									}
									if ( is_string( $krankenkasse_idnr ) && trim( $krankenkasse_idnr ) !== '' ) {
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Krankenkasse IDNR', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( $krankenkasse_idnr ) . '</span></div>';
									}
									if ( is_string( $unfallv_name ) && trim( $unfallv_name ) !== '' ) {
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Unfallversicherung', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( $unfallv_name ) . '</span></div>';
									}
									if ( is_string( $unfallv_idnr ) && trim( $unfallv_idnr ) !== '' ) {
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Unfallversicherung IDNR', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( $unfallv_idnr ) . '</span></div>';
									}
									echo '</div></div>';
								}
								if ( $has_aerzte ) {
									echo '<div class="soe-med-group soe-med-group-aerzte"><div class="soe-med-row soe-med-row-title">' . esc_html__( 'Ärzte', 'special-olympics-extension' ) . '</div><div class="soe-med-subgrid">';
									if ( ( is_string( $hausarzt ) && trim( $hausarzt ) !== '' ) || ( is_string( $hausarzt_tel ) && trim( $hausarzt_tel ) !== '' ) ) {
										$hausarzt_val = ( is_string( $hausarzt ) && trim( $hausarzt ) !== '' ) ? esc_html( $hausarzt ) : '';
										if ( is_string( $hausarzt_tel ) && trim( $hausarzt_tel ) !== '' ) {
											$hausarzt_val .= ( $hausarzt_val ? ' ' : '' ) . '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $hausarzt_tel ) ) . '">' . esc_html( $hausarzt_tel ) . '</a>';
										}
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Hausarzt', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . $hausarzt_val . '</span></div>';
									}
									if ( ( is_string( $zahnarzt ) && trim( $zahnarzt ) !== '' ) || ( is_string( $zahnarzt_tel ) && trim( $zahnarzt_tel ) !== '' ) ) {
										$zahnarzt_val = ( is_string( $zahnarzt ) && trim( $zahnarzt ) !== '' ) ? esc_html( $zahnarzt ) : '';
										if ( is_string( $zahnarzt_tel ) && trim( $zahnarzt_tel ) !== '' ) {
											$zahnarzt_val .= ( $zahnarzt_val ? ' ' : '' ) . '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $zahnarzt_tel ) ) . '">' . esc_html( $zahnarzt_tel ) . '</a>';
										}
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Zahnarzt', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . $zahnarzt_val . '</span></div>';
									}
									echo '</div></div>';
								}
								if ( $has_ernaehrung ) {
									echo '<div class="soe-med-group soe-med-group-ernaehrung"><div class="soe-med-row soe-med-row-title">' . esc_html__( 'Ernährung', 'special-olympics-extension' ) . '</div><div class="soe-med-subgrid">';
									if ( ! empty( $ernaehrung_bes_arr ) ) {
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Besonderheiten', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( implode( ', ', $ernaehrung_bes_arr ) ) . '</span></div>';
									}
									if ( is_string( $ernaehrung_weitere ) && trim( $ernaehrung_weitere ) !== '' ) {
										echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Weitere Infos', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . nl2br( esc_html( trim( $ernaehrung_weitere ) ) ) . '</span></div>';
									}
									echo '</div></div>';
								}
								echo '</div>';
								echo '</div>';
							}
							$has_any_medizin = ( $datenblatter && ( is_array( $datenblatter ) || is_numeric( $datenblatter ) ) ) || ( is_array( $notfallmed ) && ! empty( $notfallmed ) ) || ( is_string( $krankenkasse ) && trim( $krankenkasse ) !== '' ) || ( is_string( $krankenkasse_idnr ) && trim( $krankenkasse_idnr ) !== '' ) || ( is_string( $unfallv_name ) && trim( $unfallv_name ) !== '' ) || ( is_string( $unfallv_idnr ) && trim( $unfallv_idnr ) !== '' ) || ( is_string( $hausarzt ) && trim( $hausarzt ) !== '' ) || ( is_string( $zahnarzt ) && trim( $zahnarzt ) !== '' ) || ( is_string( $zahnarzt_tel ) && trim( $zahnarzt_tel ) !== '' ) || ( is_array( $medikamentangaben ) && ! empty( $medikamentangaben ) ) || $has_allergien || $has_ernaehrung;
							if ( ! $has_any_medizin ) {
								echo '<p class="soe-telefonbuch-detail-empty">' . esc_html__( 'Keine medizinischen Daten erfasst.', 'special-olympics-extension' ) . '</p>';
							}
							echo '</div></details>';
							echo '<details class="soe-telefonbuch-detail-medizin" open><summary>' . esc_html__( 'Weitere Hilfsmittel', 'special-olympics-extension' ) . '</summary><div class="soe-telefonbuch-detail-medizin-inner soe-med-layout">';
							if ( $has_hilfsmittel ) {
								echo '<div class="soe-med-subgrid">';
								if ( is_array( $erforderliche_hilfsmittel ) && ! empty( $erforderliche_hilfsmittel ) ) {
									echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Erforderliche Hilfsmittel', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( implode( ', ', $erforderliche_hilfsmittel ) ) . '</span></div>';
								}
								if ( is_array( $unterstutzung_bei ) && ! empty( $unterstutzung_bei ) ) {
									echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Unterstützung bei', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . esc_html( implode( ', ', $unterstutzung_bei ) ) . '</span></div>';
								}
								if ( is_string( $andere_hilfsmittel ) && trim( $andere_hilfsmittel ) !== '' ) {
									echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Andere Hilfsmittel', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . nl2br( esc_html( trim( $andere_hilfsmittel ) ) ) . '</span></div>';
								}
								echo '</div>';
							} else {
								echo '<p class="soe-telefonbuch-detail-empty">' . esc_html__( 'Keine weiteren Hilfsmittel erfasst.', 'special-olympics-extension' ) . '</p>';
							}
							echo '</div></details>';
							echo '<details class="soe-telefonbuch-detail-medizin" open><summary>' . esc_html__( 'Beachtenswertes', 'special-olympics-extension' ) . '</summary><div class="soe-telefonbuch-detail-medizin-inner soe-med-layout">';
							if ( $has_beachtenswertes ) {
								echo '<div class="soe-med-subgrid">';
								if ( is_string( $pflegebetreuung ) && trim( $pflegebetreuung ) !== '' ) {
									echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Pflege/Betreuung', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . nl2br( esc_html( trim( $pflegebetreuung ) ) ) . '</span></div>';
								}
								if ( is_string( $sprachekommunikation ) && trim( $sprachekommunikation ) !== '' ) {
									echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Sprache/Kommunikation', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . nl2br( esc_html( trim( $sprachekommunikation ) ) ) . '</span></div>';
								}
								if ( is_string( $verhaltenauffalligkeiten ) && trim( $verhaltenauffalligkeiten ) !== '' ) {
									echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Verhalten/Auffälligkeiten', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . nl2br( esc_html( trim( $verhaltenauffalligkeiten ) ) ) . '</span></div>';
								}
								if ( is_string( $vorliebenangste ) && trim( $vorliebenangste ) !== '' ) {
									echo '<div class="soe-med-row"><span class="soe-med-label">' . esc_html__( 'Vorlieben/Ängste', 'special-olympics-extension' ) . '</span><span class="soe-med-value">' . nl2br( esc_html( trim( $vorliebenangste ) ) ) . '</span></div>';
								}
								echo '</div>';
							} else {
								echo '<p class="soe-telefonbuch-detail-empty">' . esc_html__( 'Keine beachtenswerten Angaben erfasst.', 'special-olympics-extension' ) . '</p>';
							}
							echo '</div></details>';
							if ( is_array( $snapshot ) && ! empty( $snapshot ) ) {
								echo '<p><strong>' . esc_html__( 'Event-Teilnahmen', 'special-olympics-extension' ) . '</strong>: ';
								foreach ( $snapshot as $e ) {
									echo esc_html( ( isset( $e['date'] ) ? date_i18n( 'd.m.Y', strtotime( $e['date'] ) ) . ' ' : '' ) . ( isset( $e['title'] ) ? $e['title'] : '' ) . ( isset( $e['role'] ) ? ' (' . $e['role'] . ')' : '' ) ) . '; ';
								}
								echo '</p>';
							}
							echo '</div>';
							$detail_html = ob_get_clean();
							$detail_by_id[ $m->ID ] = $detail_html;
							?>
							<tr data-id="<?php echo (int) $m->ID; ?>" data-email="<?php echo esc_attr( is_string( $email ) ? $email : '' ); ?>">
								<td class="soe-search-col" data-search="<?php echo esc_attr( $search_text ); ?>"><?php echo esc_html( $search_text ); ?></td>
								<td class="soe-edit-col"><?php if ( $edit_link ) : ?><a href="<?php echo esc_url( $edit_link ); ?>" class="soe-telefonbuch-edit" title="<?php esc_attr_e( 'Bearbeiten', 'special-olympics-extension' ); ?>" aria-label="<?php esc_attr_e( 'Bearbeiten', 'special-olympics-extension' ); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a><?php endif; ?></td>
								<td><?php echo esc_html( (string) $nachname ); ?></td>
								<td><?php echo esc_html( (string) $vorname ); ?></td>
								<td><?php echo esc_html( $role_str ); ?></td>
								<td><?php echo $tel && is_string( $tel ) ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '">' . esc_html( $tel ) . '</a>' : ''; ?></td>
								<td><?php echo $email && is_string( $email ) ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : ''; ?></td>
								<td><?php echo esc_html( (string) $ort ); ?></td>
								<td><?php echo esc_html( trim( (string) $strasse . ' ' . (string) $hausnummer ) ); ?></td>
								<td><?php echo esc_html( (string) $plz ); ?></td>
								<td><?php echo esc_html( $sport_labels ); ?></td>
								<td><?php echo esc_html( is_string( $kleidergrosse ) ? $kleidergrosse : '' ); ?></td>
								<td><?php echo esc_html( is_string( $schuhgrosse ) ? $schuhgrosse : '' ); ?></td>
								<td><?php echo esc_html( (string) $name_notfall ); ?></td>
								<td><?php echo $tel_notfall && is_string( $tel_notfall ) ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel_notfall ) ) . '">' . esc_html( $tel_notfall ) . '</a>' : ''; ?></td>
								<td><?php echo esc_html( $events_label ); ?></td>
								<?php if ( $soe_tb_admin ) : ?>
									<td><?php echo esc_html( $bank_name_tb ); ?></td>
									<td><?php echo esc_html( $bank_iban_tb ); ?></td>
								<?php endif; ?>
								<td><button type="button" class="button-link soe-telefonbuch-expand" aria-label="<?php esc_attr_e( 'Detail einblenden', 'special-olympics-extension' ); ?>" data-label-expand="<?php esc_attr_e( 'Detail einblenden', 'special-olympics-extension' ); ?>" data-label-collapse="<?php esc_attr_e( 'Detail ausblenden', 'special-olympics-extension' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<script type="text/javascript">
					window.soeTelefonbuchDetails = <?php echo wp_json_encode( $detail_by_id ); ?>;
					window.soeTelefonbuchDefaultVisible = <?php echo wp_json_encode( array_values( array_map( function ( $c ) { return $c['idx']; }, array_filter( $col_config, function ( $c ) { return $c['default']; } ) ) ) ); ?>;
					window.soeTelefonbuchDetailCol = <?php echo $soe_tb_admin ? 18 : 16; ?>;
					window.soeTelefonbuchMaxColIndex = <?php echo $soe_tb_admin ? 18 : 16; ?>;
				</script>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
