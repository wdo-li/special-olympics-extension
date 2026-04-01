<?php
/**
 * Rollenspezifisches Dashboard für alle Benutzer (inkl. Administratoren).
 * Nach Login werden alle Nutzer auf diese Übersichtsseite umgeleitet.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'soe_dashboard_add_menu', 1 );
add_action( 'admin_init', 'soe_dashboard_redirect_from_index', 1 );
add_action( 'admin_enqueue_scripts', 'soe_dashboard_enqueue_styles', 5 );

/**
 * Redirects all users from the standard Dashboard (index.php) to the SOE dashboard.
 */
function soe_dashboard_redirect_from_index() {
	global $pagenow;
	if ( $pagenow !== 'index.php' ) {
		return;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=soe-dashboard' ) );
	exit;
}

/**
 * Enqueues minimal styles and scripts for the dashboard page.
 */
function soe_dashboard_enqueue_styles( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->id !== 'toplevel_page_soe-dashboard' ) {
		return;
	}
	// Load attendance box script when user can have token (for PIN/regenerate buttons).
	$needs_attendance_script = function_exists( 'soe_attendance_can_user_have_token' ) && soe_attendance_can_user_have_token( get_current_user_id() );
	if ( $needs_attendance_script ) {
		$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
		wp_enqueue_script( 'soe-admin-training', $plugin_url . 'assets/js/admin-training.js', array( 'jquery' ), SOE_PLUGIN_VERSION, true );
		wp_localize_script( 'soe-admin-training', 'soeTrainingAdmin', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'soe_training_admin' ),
			'personSearchNonce' => wp_create_nonce( 'soe_person_search' ),
			'i18n'     => array( 'saved' => __( 'Gespeichert.', 'special-olympics-extension' ), 'error' => __( 'Fehler.', 'special-olympics-extension' ) ),
		) );
	}
	wp_add_inline_style( 'common', '
		.soe-dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 20px 0; }
		@media (max-width: 782px) { .soe-dashboard-grid { grid-template-columns: 1fr; } }
		.soe-dashboard-block { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
		.soe-dashboard-block h2 { margin: 0 0 12px 0; font-size: 1em; }
		.soe-dashboard-list { margin: 0 0 12px 0; padding-left: 20px; }
		.soe-dashboard-list li { margin-bottom: 6px; }
		.soe-dashboard-list .description { color: #646970; font-size: 12px; margin-left: 4px; }
		.soe-dashboard-quicklinks { margin-top: 24px; }
		.soe-dashboard-quicklinks .button { margin-right: 8px; margin-bottom: 8px; }
	' );
}

/**
 * Adds the SOE Dashboard page for all users (replaces standard WordPress dashboard).
 */
function soe_dashboard_add_menu() {
	remove_menu_page( 'index.php' );
	add_menu_page(
		__( 'Übersicht', 'special-olympics-extension' ),
		__( 'Übersicht', 'special-olympics-extension' ),
		'read',
		'soe-dashboard',
		'soe_render_dashboard_page',
		'dashicons-dashboard',
		2
	);
}

/**
 * Checks profile completeness for a Mitglied (linked to current user). Used for dashboard lightbox.
 *
 * @param int $mitglied_id CPT Mitglied post ID.
 * @return array { 'filled' => int, 'total' => int, 'show_lightbox' => bool }
 */
function soe_profile_completeness_check( $mitglied_id ) {
	$total = 10;
	$filled = 0;
	if ( ! $mitglied_id ) {
		return array( 'filled' => 0, 'total' => $total, 'show_lightbox' => false );
	}
	if ( trim( (string) get_field( 'vorname', $mitglied_id ) ) !== '' ) {
		$filled++;
	}
	if ( trim( (string) get_field( 'nachname', $mitglied_id ) ) !== '' ) {
		$filled++;
	}
	if ( is_email( get_field( 'e-mail', $mitglied_id ) ) ) {
		$filled++;
	}
	if ( trim( (string) get_field( 'telefonnummer', $mitglied_id ) ) !== '' ) {
		$filled++;
	}
	if ( trim( (string) get_field( 'geburtsdatum', $mitglied_id ) ) !== '' ) {
		$filled++;
	}
	if ( trim( (string) get_field( 'strasse', $mitglied_id ) ) !== '' ) {
		$filled++;
	}
	if ( trim( (string) get_field( 'postleitzahl', $mitglied_id ) ) !== '' ) {
		$filled++;
	}
	if ( trim( (string) get_field( 'ort', $mitglied_id ) ) !== '' ) {
		$filled++;
	}
	$role = get_field( 'role', $mitglied_id );
	if ( ! empty( $role ) && ( is_array( $role ) ? count( $role ) > 0 : true ) ) {
		$filled++;
	}
	$sports = get_the_terms( $mitglied_id, 'sport' );
	if ( is_array( $sports ) && ! is_wp_error( $sports ) && count( $sports ) > 0 ) {
		$filled++;
	}
	$show_lightbox = $filled < 6;
	return array( 'filled' => $filled, 'total' => $total, 'show_lightbox' => $show_lightbox );
}

/**
 * Renders the dashboard page content based on user role.
 */
function soe_render_dashboard_page() {
	$mitglied_id = function_exists( 'soe_get_current_user_mitglied_id' ) ? soe_get_current_user_mitglied_id() : 0;
	$profile_check = $mitglied_id ? soe_profile_completeness_check( $mitglied_id ) : array( 'show_lightbox' => false );
	$show_profile_lightbox = ! empty( $profile_check['show_lightbox'] );
	$profile_edit_url = $mitglied_id ? get_edit_post_link( $mitglied_id, 'raw' ) : '';

	if ( current_user_can( 'manage_options' ) ) {
		?>
		<div class="wrap soe-dashboard" data-soe-profile-lightbox="<?php echo $show_profile_lightbox ? '1' : '0'; ?>" data-soe-profile-edit-url="<?php echo esc_attr( $profile_edit_url ?: '' ); ?>">
		<?php if ( $show_profile_lightbox ) : ?>
		<div id="soe-profile-lightbox-overlay" class="soe-profile-lightbox-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:100000; align-items:center; justify-content:center; flex;">
			<div class="soe-profile-lightbox-box" style="background:#fff; max-width:440px; padding:24px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3); margin:20px;">
				<h2 style="margin:0 0 12px 0; font-size:1.3em;"><?php esc_html_e( 'Profil vervollständigen', 'special-olympics-extension' ); ?></h2>
				<p style="margin:0 0 20px 0; color:#50575e;"><?php esc_html_e( 'Viele Angaben in deinem Profil fehlen noch. Bitte nimm dir einen Moment und pflege die fehlenden Daten unter „Mein Account“.', 'special-olympics-extension' ); ?></p>
				<p style="margin:0 0 20px 0;">
					<a href="<?php echo esc_url( $profile_edit_url ); ?>" class="button button-primary button-large"><?php esc_html_e( 'Profil bearbeiten', 'special-olympics-extension' ); ?></a>
				</p>
				<p style="margin:0;">
					<button type="button" class="button soe-profile-lightbox-remind"><?php esc_html_e( 'Später erinnern', 'special-olympics-extension' ); ?></button>
				</p>
			</div>
		</div>
		<?php endif; ?>
		<?php if ( $show_profile_lightbox ) : ?>
		<script>
		(function(){
			var overlay = document.getElementById('soe-profile-lightbox-overlay');
			if (!overlay) return;
			var key = 'soe_profile_remind_later';
			function getCookie(n){ var c=document.cookie.split(';'); for(var i=0;i<c.length;i++){ var p=c[i].trim().split('='); if(p[0]===n) return p[1]; } return ''; }
			if (getCookie(key)==='1') return;
			overlay.style.display = 'flex';
			overlay.querySelector('.soe-profile-lightbox-remind').addEventListener('click', function(){
				document.cookie = key + '=1; path=/; max-age=' + (7*24*60*60);
				overlay.style.display = 'none';
			});
		})();
		</script>
		<?php endif; ?>
		<h1><?php esc_html_e( 'Übersicht', 'special-olympics-extension' ); ?></h1>
		<?php
		soe_dashboard_render_admin();
		echo '</div>';
		return;
	}

	$user  = wp_get_current_user();
	$roles = (array) $user->roles;
	$is_hauptleiter_or_leiter = $mitglied_id && ( in_array( 'hauptleiter_in', $roles, true ) || in_array( 'leiter_in', $roles, true ) );
	$is_ansprechperson = in_array( 'ansprechperson', $roles, true );

	?>
	<div class="wrap soe-dashboard" data-soe-profile-lightbox="<?php echo $show_profile_lightbox ? '1' : '0'; ?>" data-soe-profile-edit-url="<?php echo esc_attr( $profile_edit_url ?: '' ); ?>">
		<?php if ( $show_profile_lightbox ) : ?>
		<div id="soe-profile-lightbox-overlay" class="soe-profile-lightbox-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); z-index:100000; align-items:center; justify-content:center; flex;">
			<div class="soe-profile-lightbox-box" style="background:#fff; max-width:440px; padding:24px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3); margin:20px;">
				<h2 style="margin:0 0 12px 0; font-size:1.3em;"><?php esc_html_e( 'Profil vervollständigen', 'special-olympics-extension' ); ?></h2>
				<p style="margin:0 0 20px 0; color:#50575e;"><?php esc_html_e( 'Viele Angaben in deinem Profil fehlen noch. Bitte nimm dir einen Moment und pflege die fehlenden Daten unter „Mein Account“.', 'special-olympics-extension' ); ?></p>
				<p style="margin:0 0 20px 0;">
					<a href="<?php echo esc_url( $profile_edit_url ); ?>" class="button button-primary button-large"><?php esc_html_e( 'Profil bearbeiten', 'special-olympics-extension' ); ?></a>
				</p>
				<p style="margin:0;">
					<button type="button" class="button soe-profile-lightbox-remind"><?php esc_html_e( 'Später erinnern', 'special-olympics-extension' ); ?></button>
				</p>
			</div>
		</div>
		<?php endif; ?>
		<?php if ( $show_profile_lightbox ) : ?>
		<script>
		(function(){
			var overlay = document.getElementById('soe-profile-lightbox-overlay');
			if (!overlay) return;
			var key = 'soe_profile_remind_later';
			function getCookie(n){ var c=document.cookie.split(';'); for(var i=0;i<c.length;i++){ var p=c[i].trim().split('='); if(p[0]===n) return p[1]; } return ''; }
			if (getCookie(key)==='1') return;
			overlay.style.display = 'flex';
			overlay.querySelector('.soe-profile-lightbox-remind').addEventListener('click', function(){
				document.cookie = key + '=1; path=/; max-age=' + (7*24*60*60);
				overlay.style.display = 'none';
			});
		})();
		</script>
		<?php endif; ?>
		<h1><?php esc_html_e( 'Übersicht', 'special-olympics-extension' ); ?></h1>

		<?php if ( $is_hauptleiter_or_leiter ) : ?>
			<?php soe_dashboard_render_hauptleiter_leiter( $mitglied_id ); ?>
		<?php elseif ( $is_ansprechperson ) : ?>
			<?php soe_dashboard_render_ansprechperson(); ?>
		<?php else : ?>
			<?php soe_dashboard_render_athlet_other( $mitglied_id ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Dashboard content for Administrators.
 */
function soe_dashboard_render_admin() {
	$payrolls  = array();
	$trainings = array();
	$events    = array();
	if ( function_exists( 'soe_db_payroll_list' ) && defined( 'SOE_PAYROLL_STATUS_DRAFT' ) ) {
		$payrolls = soe_db_payroll_list( array( 'limit' => 10, 'status' => SOE_PAYROLL_STATUS_DRAFT ) );
	}
	if ( function_exists( 'soe_db_training_list' ) ) {
		$trainings = soe_db_training_list( array( 'limit' => 10, 'completed' => 0 ) );
	}
	$today = current_time( 'Y-m-d' );
	if ( function_exists( 'soe_db_event_list' ) ) {
		$events = soe_db_event_list( array( 'limit' => 10, 'date_from' => $today ) );
	}
	$mitglied_id = function_exists( 'soe_get_current_user_mitglied_id' ) ? soe_get_current_user_mitglied_id() : 0;
	?>
	<div class="soe-dashboard-quicklinks" style="margin-bottom: 24px;">
		<h2><?php esc_html_e( 'Schnellzugriff', 'special-olympics-extension' ); ?></h2>
		<p>
			<?php if ( current_user_can( 'view_telefonbuch' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-telefonbuch' ) ); ?>" class="button"><?php esc_html_e( 'Telefonbuch', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-trainings' ) ); ?>" class="button"><?php esc_html_e( 'Trainings', 'special-olympics-extension' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-events' ) ); ?>" class="button"><?php esc_html_e( 'Events', 'special-olympics-extension' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mitglied' ) ); ?>" class="button"><?php esc_html_e( 'Mitglieder', 'special-olympics-extension' ); ?></a>
			<?php if ( $mitglied_id ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $mitglied_id, 'raw' ) ); ?>" class="button"><?php esc_html_e( 'Mein Account', 'special-olympics-extension' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-account' ) ); ?>" class="button"><?php esc_html_e( 'Mein Account', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payrolls' ) ); ?>" class="button"><?php esc_html_e( 'Lohnabrechnung', 'special-olympics-extension' ); ?></a>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-settings' ) ); ?>" class="button"><?php esc_html_e( 'Einstellungen', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
		</p>
	</div>

	<div class="soe-dashboard-grid">
		<div class="soe-dashboard-block">
			<h2><?php esc_html_e( 'Offene Lohnabrechnungen', 'special-olympics-extension' ); ?></h2>
			<?php if ( ! empty( $payrolls ) ) : ?>
				<ul class="soe-dashboard-list">
					<?php foreach ( $payrolls as $p ) :
						$pid = (int) ( $p['person_id'] ?? 0 );
						$person_post = get_post( $pid );
						$person_name = $person_post ? $person_post->post_title : (string) $pid;
						$start = $p['period_start'] ?? '';
						$end = $p['period_end'] ?? '';
						$period_fmt = ( $start && $end ) ? date_i18n( 'd.m.Y', strtotime( $start ) ) . ' – ' . date_i18n( 'd.m.Y', strtotime( $end ) ) : ( $start . ' – ' . $end );
					?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-edit&id=' . (int) ( $p['id'] ?? 0 ) ) ); ?>"><?php echo esc_html( $person_name ); ?></a>
							<span class="description"><?php echo esc_html( $period_fmt ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payrolls' ) ); ?>" class="button"><?php esc_html_e( 'Alle offenen', 'special-olympics-extension' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Neue Lohnabrechnung', 'special-olympics-extension' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-history' ) ); ?>" class="button"><?php esc_html_e( 'Historie', 'special-olympics-extension' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-bulk-send' ) ); ?>" class="button"><?php esc_html_e( 'Bulk-Versand', 'special-olympics-extension' ); ?></a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Keine offenen Lohnabrechnungen.', 'special-olympics-extension' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Neue Lohnabrechnung', 'special-olympics-extension' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-history' ) ); ?>" class="button"><?php esc_html_e( 'Historie', 'special-olympics-extension' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-payroll-bulk-send' ) ); ?>" class="button"><?php esc_html_e( 'Bulk-Versand', 'special-olympics-extension' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

		<div class="soe-dashboard-block">
			<h2><?php esc_html_e( 'Laufende Trainings', 'special-olympics-extension' ); ?></h2>
			<?php if ( ! empty( $trainings ) ) : ?>
				<ul class="soe-dashboard-list">
					<?php foreach ( $trainings as $t ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-training-edit&id=' . (int) $t['id'] ) ); ?>"><?php echo esc_html( $t['title'] ); ?></a>
							<span class="description"><?php echo esc_html( $t['sport_slug'] ?: '' ); ?> – <?php echo esc_html( $t['start_date'] ? date_i18n( 'd.m.Y', strtotime( $t['start_date'] ) ) : '' ); ?> – <?php echo esc_html( $t['end_date'] ? date_i18n( 'd.m.Y', strtotime( $t['end_date'] ) ) : '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-trainings' ) ); ?>" class="button"><?php esc_html_e( 'Alle Trainings', 'special-olympics-extension' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-training-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Neues Training anlegen', 'special-olympics-extension' ); ?></a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Keine laufenden Trainings.', 'special-olympics-extension' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-trainings' ) ); ?>" class="button"><?php esc_html_e( 'Zu den Trainings', 'special-olympics-extension' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-training-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Neues Training anlegen', 'special-olympics-extension' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

		<div class="soe-dashboard-block">
			<h2><?php esc_html_e( 'Kommende Events', 'special-olympics-extension' ); ?></h2>
			<?php if ( ! empty( $events ) ) : ?>
				<ul class="soe-dashboard-list">
					<?php foreach ( $events as $e ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-edit&id=' . (int) $e['id'] ) ); ?>"><?php echo esc_html( $e['title'] ); ?></a>
							<span class="description"><?php echo esc_html( $e['event_date'] ? date_i18n( 'd.m.Y', strtotime( $e['event_date'] ) ) : '' ); ?> – <?php echo esc_html( $e['sport_slug'] ?: '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-events' ) ); ?>" class="button"><?php esc_html_e( 'Alle Events', 'special-olympics-extension' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Event hinzufügen', 'special-olympics-extension' ); ?></a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Keine kommenden Events.', 'special-olympics-extension' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-events' ) ); ?>" class="button"><?php esc_html_e( 'Zu den Events', 'special-olympics-extension' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Event hinzufügen', 'special-olympics-extension' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( function_exists( 'soe_render_attendance_quick_box' ) && function_exists( 'soe_attendance_can_user_have_token' ) && soe_attendance_can_user_have_token( get_current_user_id() ) ) : ?>
		<?php soe_render_attendance_quick_box( true ); ?>
	<?php endif; ?>
	<?php
}

/**
 * Dashboard content for Hauptleiter and Leiter.
 */
function soe_dashboard_render_hauptleiter_leiter( $mitglied_id ) {
	$args = array( 'limit' => 10, 'completed' => 0 );
	if ( $mitglied_id ) {
		$args['person_id_roles'] = array( 'person_id' => $mitglied_id, 'roles' => array( 'hauptleiter', 'leiter' ) );
	}
	$trainings = function_exists( 'soe_db_training_list' ) ? soe_db_training_list( $args ) : array();

	$event_args = array( 'limit' => 10 );
	if ( $mitglied_id ) {
		$event_args['person_id'] = $mitglied_id;
	}
	$today = current_time( 'Y-m-d' );
	$event_args['date_from'] = $today;
	$events = function_exists( 'soe_db_event_list' ) ? soe_db_event_list( $event_args ) : array();

	?>
	<div class="soe-dashboard-quicklinks" style="margin-bottom: 24px;">
		<h2><?php esc_html_e( 'Schnellzugriff', 'special-olympics-extension' ); ?></h2>
		<p>
			<?php if ( current_user_can( 'view_telefonbuch' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-telefonbuch' ) ); ?>" class="button"><?php esc_html_e( 'Telefonbuch', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-trainings' ) ); ?>" class="button"><?php esc_html_e( 'Trainings', 'special-olympics-extension' ); ?></a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-events' ) ); ?>" class="button"><?php esc_html_e( 'Events', 'special-olympics-extension' ); ?></a>
			<?php if ( $mitglied_id ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $mitglied_id, 'raw' ) ); ?>" class="button"><?php esc_html_e( 'Mein Profil', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
		</p>
	</div>

	<div class="soe-dashboard-grid">
		<div class="soe-dashboard-block">
			<h2><?php esc_html_e( 'Laufende Trainings', 'special-olympics-extension' ); ?></h2>
			<?php if ( ! empty( $trainings ) ) : ?>
				<ul class="soe-dashboard-list">
					<?php foreach ( $trainings as $t ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-training-edit&id=' . (int) $t['id'] ) ); ?>"><?php echo esc_html( $t['title'] ); ?></a>
							<span class="description"><?php echo esc_html( $t['sport_slug'] ?: '' ); ?> – <?php echo esc_html( $t['start_date'] ? date_i18n( 'd.m.Y', strtotime( $t['start_date'] ) ) : '' ); ?> – <?php echo esc_html( $t['end_date'] ? date_i18n( 'd.m.Y', strtotime( $t['end_date'] ) ) : '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-trainings' ) ); ?>" class="button"><?php esc_html_e( 'Alle Trainings', 'special-olympics-extension' ); ?></a>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-training-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Neues Training anlegen', 'special-olympics-extension' ); ?></a>
					<?php endif; ?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Keine laufenden Trainings.', 'special-olympics-extension' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-trainings' ) ); ?>" class="button"><?php esc_html_e( 'Zu den Trainings', 'special-olympics-extension' ); ?></a>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-training-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Neues Training anlegen', 'special-olympics-extension' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="soe-dashboard-block">
			<h2><?php esc_html_e( 'Kommende Events', 'special-olympics-extension' ); ?></h2>
			<?php if ( ! empty( $events ) ) : ?>
				<ul class="soe-dashboard-list">
					<?php foreach ( $events as $e ) : ?>
						<li>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-edit&id=' . (int) $e['id'] ) ); ?>"><?php echo esc_html( $e['title'] ); ?></a>
							<span class="description"><?php echo esc_html( $e['event_date'] ? date_i18n( 'd.m.Y', strtotime( $e['event_date'] ) ) : '' ); ?> – <?php echo esc_html( $e['sport_slug'] ?: '' ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-events' ) ); ?>" class="button"><?php esc_html_e( 'Alle Events', 'special-olympics-extension' ); ?></a>
					<?php if ( current_user_can( 'edit_events' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Event hinzufügen', 'special-olympics-extension' ); ?></a>
					<?php endif; ?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Keine kommenden Events.', 'special-olympics-extension' ); ?></p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-events' ) ); ?>" class="button"><?php esc_html_e( 'Zu den Events', 'special-olympics-extension' ); ?></a>
					<?php if ( current_user_can( 'edit_events' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-new' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Event hinzufügen', 'special-olympics-extension' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( function_exists( 'soe_render_attendance_quick_box' ) && function_exists( 'soe_attendance_can_user_have_token' ) && soe_attendance_can_user_have_token( get_current_user_id() ) ) : ?>
		<?php soe_render_attendance_quick_box( true ); ?>
	<?php endif; ?>
	<?php
}

/**
 * Dashboard content for Ansprechperson.
 */
function soe_dashboard_render_ansprechperson() {
	$user_id = get_current_user_id();
	$posts   = get_posts( array(
		'post_type'      => 'mitglied',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'author'         => $user_id,
		'posts_per_page' => 10,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	$my_mitglied_id = function_exists( 'soe_get_current_user_mitglied_id' ) ? soe_get_current_user_mitglied_id() : 0;
	$posts = array_filter( $posts, function ( $post ) use ( $my_mitglied_id ) {
		return (int) $post->ID !== (int) $my_mitglied_id;
	} );
	$posts = array_values( $posts );
	$add_url = admin_url( 'post-new.php?post_type=mitglied' );
	$can_create = current_user_can( 'publish_mitglieds' );
	?>
	<div class="soe-dashboard-block">
		<h2><?php esc_html_e( 'Meine Athlet*innen', 'special-olympics-extension' ); ?></h2>
		<?php if ( ! empty( $posts ) ) : ?>
			<ul class="soe-dashboard-list">
				<?php foreach ( $posts as $post ) :
					$edit_url = get_edit_post_link( $post->ID, 'raw' );
					if ( ! $edit_url || ! current_user_can( 'edit_post', $post->ID ) ) {
						continue;
					}
					?>
					<li>
						<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $post->post_title ?: __( '(Ohne Titel)', 'special-olympics-extension' ) ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-meine-athleten' ) ); ?>" class="button"><?php esc_html_e( 'Alle Athlet*innen', 'special-olympics-extension' ); ?></a>
				<?php if ( $can_create ) : ?>
					<a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary"><?php esc_html_e( 'Athlet*in hinzufügen', 'special-olympics-extension' ); ?></a>
				<?php endif; ?>
			</p>
		<?php else : ?>
			<p><?php esc_html_e( 'Noch keine Athlet*innen angelegt.', 'special-olympics-extension' ); ?></p>
			<?php if ( $can_create ) : ?>
				<p><a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary"><?php esc_html_e( 'Athlet*in hinzufügen', 'special-olympics-extension' ); ?></a></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<div class="soe-dashboard-quicklinks">
		<h2><?php esc_html_e( 'Schnellzugriff', 'special-olympics-extension' ); ?></h2>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-meine-athleten' ) ); ?>" class="button"><?php esc_html_e( 'Athlet*innen', 'special-olympics-extension' ); ?></a>
			<?php if ( $my_mitglied_id ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $my_mitglied_id, 'raw' ) ); ?>" class="button"><?php esc_html_e( 'Mein Profil', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

/**
 * Dashboard content for Athlet and other roles.
 */
function soe_dashboard_render_athlet_other( $mitglied_id ) {
	$name = '';
	$sports = array();
	if ( $mitglied_id ) {
		$post = get_post( $mitglied_id );
		if ( $post ) {
			$name = $post->post_title;
		}
		$terms = get_the_terms( $mitglied_id, 'sport' );
		if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
			$sports = wp_list_pluck( $terms, 'name' );
		}
	}

	$event_args = array( 'limit' => 5 );
	if ( $mitglied_id ) {
		$event_args['person_id'] = $mitglied_id;
	}
	$today = current_time( 'Y-m-d' );
	$event_args['date_from'] = $today;
	$events = function_exists( 'soe_db_event_list' ) ? soe_db_event_list( $event_args ) : array();
	?>
	<div class="soe-dashboard-block">
		<h2><?php esc_html_e( 'Willkommen', 'special-olympics-extension' ); ?></h2>
		<?php if ( $name ) : ?>
			<p><strong><?php echo esc_html( $name ); ?></strong></p>
		<?php endif; ?>
		<?php if ( ! empty( $sports ) ) : ?>
			<p><?php esc_html_e( 'Sportarten:', 'special-olympics-extension' ); ?> <?php echo esc_html( implode( ', ', $sports ) ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $events ) ) : ?>
		<div class="soe-dashboard-block">
			<h2><?php esc_html_e( 'Kommende Events', 'special-olympics-extension' ); ?></h2>
			<ul class="soe-dashboard-list">
				<?php foreach ( $events as $e ) : ?>
					<li>
						<?php echo esc_html( $e['title'] ); ?>
						<span class="description"><?php echo esc_html( $e['event_date'] ? date_i18n( 'd.m.Y', strtotime( $e['event_date'] ) ) : '' ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<div class="soe-dashboard-quicklinks">
		<h2><?php esc_html_e( 'Schnellzugriff', 'special-olympics-extension' ); ?></h2>
		<p>
			<?php if ( $mitglied_id ) : ?>
				<a href="<?php echo esc_url( get_edit_post_link( $mitglied_id, 'raw' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Mein Profil', 'special-olympics-extension' ); ?></a>
			<?php else : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-account' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Mein Account', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}
