<?php
/**
 * Custom Admin UI for Trainings (no CPT, direct DB).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'soe_custom_trainings_menu', 15 );
add_action( 'admin_menu', 'soe_custom_trainings_remove_new_for_non_admin', 20 );
add_action( 'admin_init', 'soe_training_sports_redirect_early', 1 );
add_action( 'admin_init', function () {
	if ( function_exists( 'soe_training_process_save_before_output' ) ) {
		soe_training_process_save_before_output();
	}
}, 5 );
add_action( 'admin_enqueue_scripts', 'soe_custom_trainings_scripts' );
add_action( 'wp_ajax_soe_training_attendance', 'soe_ajax_training_attendance' );
add_action( 'wp_ajax_soe_training_mark_completed', 'soe_ajax_training_mark_completed' );
add_action( 'wp_ajax_soe_training_request_completed', 'soe_ajax_training_request_completed' );
add_action( 'wp_ajax_soe_training_mark_running', 'soe_ajax_training_mark_running' );
add_action( 'wp_ajax_soe_training_add_session', 'soe_ajax_training_add_session' );
add_action( 'wp_ajax_soe_training_remove_session', 'soe_ajax_training_remove_session' );
add_action( 'wp_ajax_soe_attendance_save_pin', 'soe_ajax_attendance_save_pin' );
add_action( 'wp_ajax_soe_attendance_regenerate_token', 'soe_ajax_attendance_regenerate_token' );

define( 'SOE_TRAINING_COMPLETED', 1 );

function soe_custom_trainings_menu() {
	add_menu_page(
		__( 'Trainings', 'special-olympics-extension' ),
		__( 'Trainings', 'special-olympics-extension' ),
		'edit_trainings',
		'soe-trainings',
		'soe_render_trainings_list',
		'dashicons-calendar-alt',
		7
	);
	add_submenu_page( 'soe-trainings', __( 'Laufende Trainings', 'special-olympics-extension' ), __( 'Laufende Trainings', 'special-olympics-extension' ), 'edit_trainings', 'soe-trainings', 'soe_render_trainings_list' );
	add_submenu_page( 'soe-trainings', __( 'Training hinzufügen', 'special-olympics-extension' ), __( 'Hinzufügen', 'special-olympics-extension' ), 'edit_trainings', 'soe-training-new', 'soe_render_training_form' );
	add_submenu_page( 'soe-trainings', __( 'Sportarten', 'special-olympics-extension' ), __( 'Sportarten', 'special-olympics-extension' ), 'manage_options', 'soe-training-sports', 'soe_redirect_to_sport_taxonomy' );
	add_submenu_page( 'soe-trainings', __( 'Statistik', 'special-olympics-extension' ), __( 'Statistik', 'special-olympics-extension' ), 'edit_trainings', 'soe-training-stats', 'soe_render_training_stats' );
	add_submenu_page( null, __( 'Training bearbeiten', 'special-olympics-extension' ), '', 'edit_trainings', 'soe-training-edit', 'soe_render_training_form' );
}

/**
 * Redirect to sport taxonomy page early (before output).
 */
function soe_training_sports_redirect_early() {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'soe-training-sports' ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_safe_redirect( admin_url( 'edit-tags.php?taxonomy=sport' ) );
	exit;
}

/**
 * Fallback callback for Sportarten submenu (redirect happens in soe_training_sports_redirect_early).
 */
function soe_redirect_to_sport_taxonomy() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Sie haben keine Berechtigung, diese Seite aufzurufen.', 'special-olympics-extension' ) );
	}
	echo '<p>' . esc_html__( 'Weiterleitung...', 'special-olympics-extension' ) . '</p>';
}

/**
 * Removes "Training hinzufügen" submenu for non-admins (Hauptleiter may not create new trainings).
 */
function soe_custom_trainings_remove_new_for_non_admin() {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	remove_submenu_page( 'soe-trainings', 'soe-training-new' );
}

/**
 * Process training actions (save, delete, permission redirects) before any output.
 * This avoids "headers already sent" errors.
 */
function soe_training_process_save_before_output() {
	global $pagenow;
	if ( $pagenow !== 'admin.php' ) {
		return;
	}
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

	// Handle training delete (from trainings list)
	if ( $page === 'soe-trainings' && isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
		if ( current_user_can( 'manage_options' ) && wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'delete_training_' . (int) $_GET['id'] ) ) {
			$tid = (int) $_GET['id'];
			soe_db_training_delete( $tid );
			wp_safe_redirect( admin_url( 'admin.php?page=soe-trainings&deleted=1' ) );
			exit;
		}
	}

	// Handle permission redirects for training form
	if ( $page === 'soe-training-edit' || $page === 'soe-training-new' ) {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$training = $id ? soe_db_training_get( $id ) : null;
		$is_edit = (bool) $training;

		// Only administrators may create new trainings
		if ( ! $is_edit && ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=soe-trainings' ) );
			exit;
		}

		// Check if non-admin has permission to edit this training
		if ( $is_edit && ! current_user_can( 'manage_options' ) ) {
			$persons = soe_db_training_get_persons( $id );
			$allowed_ids = array();
			foreach ( array( 'hauptleiter', 'leiter' ) as $role ) {
				if ( ! empty( $persons[ $role ] ) ) {
					$allowed_ids = array_merge( $allowed_ids, array_map( 'intval', $persons[ $role ] ) );
				}
			}
			$mitglied_id = function_exists( 'soe_get_current_user_mitglied_id' ) ? soe_get_current_user_mitglied_id() : 0;
			if ( ! $mitglied_id || ! in_array( $mitglied_id, $allowed_ids, true ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=soe-trainings' ) );
				exit;
			}
		}
	}

	if ( $page !== 'soe-training-edit' && $page !== 'soe-training-new' ) {
		return;
	}
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$training = $id ? soe_db_training_get( $id ) : null;
	$is_edit = (bool) $training;

	if ( ! $is_edit && ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$readonly = false;
	if ( $is_edit ) {
		if ( $training['completed'] ) {
			$readonly = true;
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			$readonly = true;
		}
	}
	if ( ! isset( $_POST['soe_training_save'] ) || ! check_admin_referer( 'soe_training_save' ) || $readonly ) {
		return;
	}

	$role_keys = soe_get_training_role_keys();
	$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$start = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
	$end = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
	// Multiple weekdays as array -> comma-separated string
	$weekdays_raw = isset( $_POST['weekdays'] ) && is_array( $_POST['weekdays'] ) ? array_map( 'absint', $_POST['weekdays'] ) : array();
	$weekdays_raw = array_filter( $weekdays_raw, function( $v ) { return $v >= 1 && $v <= 7; } );
	sort( $weekdays_raw );
	$weekdays = ! empty( $weekdays_raw ) ? implode( ',', $weekdays_raw ) : '';
	$time = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
	$duration = isset( $_POST['duration'] ) ? sanitize_text_field( wp_unslash( $_POST['duration'] ) ) : '';
	$excluded = isset( $_POST['excluded_dates'] ) ? sanitize_text_field( wp_unslash( $_POST['excluded_dates'] ) ) : '';
	$notes = isset( $_POST['notes'] ) ? sanitize_text_field( wp_unslash( $_POST['notes'] ) ) : '';
	$bh_override = isset( $_POST['bh_override'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_override'] ) ) : '';
	$sport_slug = isset( $_POST['sport_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['sport_slug'] ) ) : '';

	$excluded_err = soe_validate_excluded_dates( $excluded );
	if ( $excluded_err ) {
		wp_safe_redirect( admin_url( 'admin.php?page=soe-training-' . ( $is_edit ? 'edit&id=' . $id : 'new' ) . '&error=' . rawurlencode( $excluded_err ) ) );
		exit;
	}

	$data = array( 'title' => $title, 'start_date' => $start ?: null, 'end_date' => $end ?: null, 'weekdays' => $weekdays ?: null, 'time' => $time ?: null, 'duration' => $duration, 'excluded_dates' => $excluded, 'notes' => $notes, 'bh_override' => $bh_override, 'sport_slug' => $sport_slug );
	if ( $is_edit ) {
		soe_db_training_update( $id, $data );
	} else {
		$id = soe_db_training_insert( $data );
	}

	$persons_data = soe_parse_persons_from_post( $role_keys, 'persons_' );
	soe_db_training_save_persons( $id, $persons_data );

	$computed = soe_training_compute_sessions_from_row( array( 'start_date' => $start, 'end_date' => $end, 'weekdays' => $weekdays, 'excluded_dates' => $excluded ) );
	$existing = soe_db_training_get_sessions( $id );
	$manual_additions = array_diff( $existing, $computed );
	$sessions = array_values( array_unique( array_merge( $computed, $manual_additions ) ) );
	sort( $sessions );
	soe_db_training_save_sessions( $id, $sessions );

	if ( function_exists( 'soe_debug_log' ) ) {
		soe_debug_log( 'Training saved', array( 'training_id' => $id, 'sessions_count' => count( $sessions ) ) );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=soe-training-edit&id=' . $id . '&saved=1' ) );
	exit;
}

/**
 * Renders the "Anwesenheit schnell erfassen" box. Call from trainings list or dashboard.
 *
 * @param bool $margin_top Whether to add top margin (for use below table).
 */
function soe_render_attendance_quick_box( $margin_top = false ) {
	if ( ! function_exists( 'soe_attendance_can_user_have_token' ) || ! function_exists( 'soe_attendance_get_or_create_token' ) || ! soe_attendance_can_user_have_token( get_current_user_id() ) ) {
		return;
	}
	$current_user_id = get_current_user_id();
	$attendance_token = soe_attendance_get_or_create_token( $current_user_id );
	if ( ! $attendance_token ) {
		return;
	}
	$attendance_url = home_url( '/soe-anwesenheit/?token=' . rawurlencode( $attendance_token ) );
	$has_pin = function_exists( 'soe_attendance_has_pin' ) && soe_attendance_has_pin( $current_user_id );
	$token_expiry = function_exists( 'soe_attendance_get_token_expiry' ) ? soe_attendance_get_token_expiry( $current_user_id ) : 0;
	$expiry_date = $token_expiry ? date_i18n( 'd.m.Y', $token_expiry ) : '–';
	$margin_css = $margin_top ? 'margin-top: 2em;' : '';
	?>
	<style>
		.soe-quick-attendance { <?php echo $margin_css; ?> background: #fff; border: 1px solid #dcdcde; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
		.soe-quick-attendance-header { background: #f9f9f9; border-bottom: 1px solid #e5e5e5; padding: 1em 1.25em; display: flex; align-items: center; gap: 0.5em; }
		.soe-quick-attendance-header .dashicons { color: #646970; font-size: 18px; width: 18px; height: 18px; }
		.soe-quick-attendance-header h3 { margin: 0; font-size: 14px; font-weight: 600; color: #1d2327; }
		.soe-quick-attendance-body { padding: 1.25em; }
		.soe-attendance-url-box { background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 0.75em 1em; margin-bottom: 1.25em; display: flex; align-items: center; gap: 0.75em; flex-wrap: wrap; }
		.soe-attendance-url-box .dashicons-admin-links { color: #646970; font-size: 16px; }
		.soe-attendance-url-box a { word-break: break-all; flex: 1; min-width: 200px; font-size: 13px; }
		.soe-attendance-url-box .button { flex-shrink: 0; }
		.soe-attendance-sections { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25em; }
		.soe-attendance-section { background: #fafafa; border: 1px solid #e5e5e5; border-radius: 4px; padding: 1em; }
		.soe-attendance-section h4 { margin: 0 0 0.6em 0; font-size: 13px; display: flex; align-items: center; gap: 0.4em; color: #1d2327; font-weight: 600; }
		.soe-attendance-section h4 .dashicons { color: #646970; font-size: 16px; width: 16px; height: 16px; }
		.soe-pin-status { display: flex; align-items: center; gap: 0.4em; margin-bottom: 0.6em; font-size: 13px; }
		.soe-pin-status.is-set { color: #00a32a; }
		.soe-pin-status.not-set { color: #d63638; }
		.soe-pin-status .dashicons { font-size: 16px; width: 16px; height: 16px; }
		.soe-pin-form-inline { display: flex; align-items: center; gap: 0.5em; flex-wrap: wrap; margin-top: 0.6em; }
		.soe-pin-form-inline input { width: 90px; }
		.soe-save-pin-btn { touch-action: manipulation; min-height: 44px; min-width: 44px; cursor: pointer; }
		.soe-token-info { display: flex; align-items: center; gap: 0.4em; margin-bottom: 0.75em; color: #50575e; font-size: 13px; }
		.soe-token-info .dashicons { color: #646970; font-size: 16px; width: 16px; height: 16px; }
		.soe-attendance-footer { margin-top: 1.25em; padding-top: 0.75em; border-top: 1px solid #e5e5e5; color: #646970; font-size: 12px; display: flex; align-items: flex-start; gap: 0.4em; }
		.soe-attendance-footer .dashicons { color: #646970; font-size: 14px; width: 14px; height: 14px; margin-top: 1px; }
	</style>
	<div class="soe-quick-attendance soe-attendance-box">
		<div class="soe-quick-attendance-header">
			<span class="dashicons dashicons-smartphone"></span>
			<h3><?php esc_html_e( 'Anwesenheit schnell erfassen', 'special-olympics-extension' ); ?></h3>
		</div>
		<div class="soe-quick-attendance-body">
			<div class="soe-attendance-url-box">
				<span class="dashicons dashicons-admin-links"></span>
				<a href="<?php echo esc_url( $attendance_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $attendance_url ); ?></a>
				<a href="<?php echo esc_url( $attendance_url ); ?>" target="_blank" rel="noopener" class="button"><?php esc_html_e( 'Öffnen', 'special-olympics-extension' ); ?></a>
			</div>
			<div class="soe-attendance-sections">
				<div class="soe-attendance-section soe-pin-section">
					<h4><span class="dashicons dashicons-lock"></span><?php esc_html_e( 'PIN-Schutz', 'special-olympics-extension' ); ?></h4>
					<?php if ( $has_pin ) : ?>
						<div class="soe-pin-status is-set"><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'PIN ist aktiv', 'special-olympics-extension' ); ?></div>
						<button type="button" class="button button-small soe-change-pin-btn"><?php esc_html_e( 'PIN ändern', 'special-olympics-extension' ); ?></button>
					<?php else : ?>
						<div class="soe-pin-status not-set"><span class="dashicons dashicons-warning"></span><?php esc_html_e( 'Kein PIN gesetzt', 'special-olympics-extension' ); ?></div>
						<p style="margin:0 0 0.5em;color:#646970;font-size:0.9em;"><?php esc_html_e( 'Setze einen PIN, um den Link nutzen zu können.', 'special-olympics-extension' ); ?></p>
					<?php endif; ?>
					<div class="soe-pin-form soe-pin-form-inline" style="<?php echo $has_pin ? 'display:none;' : ''; ?>">
						<input type="password" class="soe-attendance-pin-input" inputmode="numeric" pattern="[0-9]*" maxlength="6" minlength="4" placeholder="4-6 Ziffern" />
						<button type="button" class="button button-primary soe-save-pin-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( 'soe_attendance_pin_' . $current_user_id ) ); ?>"><?php esc_html_e( 'Speichern', 'special-olympics-extension' ); ?></button>
						<?php if ( $has_pin ) : ?><button type="button" class="button soe-cancel-pin-btn"><?php esc_html_e( 'Abbrechen', 'special-olympics-extension' ); ?></button><?php endif; ?>
						<span class="soe-pin-msg" style="display:none;"></span>
					</div>
				</div>
				<div class="soe-attendance-section soe-token-section">
					<h4><span class="dashicons dashicons-calendar-alt"></span><?php esc_html_e( 'Link-Gültigkeit', 'special-olympics-extension' ); ?></h4>
					<div class="soe-token-info"><span class="dashicons dashicons-clock"></span><?php esc_html_e( 'Gültig bis:', 'special-olympics-extension' ); ?> <strong><?php echo esc_html( $expiry_date ); ?></strong></div>
					<button type="button" class="button button-small soe-regenerate-token-btn" data-nonce="<?php echo esc_attr( wp_create_nonce( 'soe_attendance_regenerate_' . $current_user_id ) ); ?>"><?php esc_html_e( 'Neuen Link erzeugen', 'special-olympics-extension' ); ?></button>
					<span class="soe-token-msg" style="display:none;margin-left:0.5em;"></span>
				</div>
			</div>
			<div class="soe-attendance-footer">
				<span class="dashicons dashicons-info"></span>
				<span><?php esc_html_e( 'Dieser Link ermöglicht die Anwesenheitserfassung ohne Anmeldung. Schütze ihn mit deinem persönlichen PIN.', 'special-olympics-extension' ); ?></span>
			</div>
		</div>
	</div>
	<?php
}

function soe_render_trainings_list() {
	// Delete action is handled in soe_training_process_save_before_output() to avoid "headers already sent".

	$view = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : 'running';
	$mitglied_id = soe_get_current_user_mitglied_id();
	$current_user = wp_get_current_user();
	$roles = (array) $current_user->roles;
	$is_hauptleiter_or_leiter = $mitglied_id && ! current_user_can( 'manage_options' ) && ( in_array( 'hauptleiter_in', $roles, true ) || in_array( 'leiter_in', $roles, true ) );

	$args = array( 'limit' => 500 );
	if ( $view === 'completed' ) {
		$args['completed'] = 1;
	} else {
		$args['completed'] = 0;
	}
	if ( current_user_can( 'manage_options' ) ) {
		// Admins see all trainings; no filter.
	} elseif ( $is_hauptleiter_or_leiter ) {
		$args['person_id_roles'] = array( 'person_id' => $mitglied_id, 'roles' => array( 'hauptleiter', 'leiter' ) );
	} else {
		// Legacy: only Hauptleiter filter for users that are not Leiter (e.g. only hauptleiter_in).
		$args['hauptleiter_person_id'] = $mitglied_id;
	}

	$trainings = soe_db_training_list( $args );
	$today = current_time( 'Y-m-d' );
	$can_complete_count = 0;
	foreach ( $trainings as $t ) {
		$ed = isset( $t['end_date'] ) ? $t['end_date'] : '';
		if ( $ed && $ed < $today ) {
			$can_complete_count++;
		}
	}

	$base = admin_url( 'admin.php?page=soe-trainings' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Trainings', 'special-olympics-extension' ); ?></h1>
		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-training-new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Neues Training anlegen', 'special-olympics-extension' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end">
		<?php if ( $view === 'running' && $can_complete_count > 0 ) : ?>
			<div class="notice notice-info inline"><p><?php
				/* translators: %d: number of trainings */
				echo esc_html( sprintf( _n( 'Du hast %d Training mit Enddatum in der Vergangenheit. Bitte prüfe, ob es als abgeschlossen markiert werden kann.', 'Du hast %d Trainings mit Enddatum in der Vergangenheit. Bitte prüfe, ob diese als abgeschlossen markiert werden können.', $can_complete_count, 'special-olympics-extension' ), $can_complete_count ) );
			?></p></div>
		<?php endif; ?>
		<ul class="subsubsub">
			<li><a href="<?php echo esc_url( $base . '&view=running' ); ?>" class="<?php echo $view === 'running' ? 'current' : ''; ?>"><?php esc_html_e( 'Laufende Trainings', 'special-olympics-extension' ); ?></a> |</li>
			<li><a href="<?php echo esc_url( $base . '&view=completed' ); ?>" class="<?php echo $view === 'completed' ? 'current' : ''; ?>"><?php esc_html_e( 'Abgeschlossene Trainings', 'special-olympics-extension' ); ?></a></li>
		</ul>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Titel', 'special-olympics-extension' ); ?></th>
					<th><?php esc_html_e( 'Sport', 'special-olympics-extension' ); ?></th>
					<th><?php esc_html_e( 'Start', 'special-olympics-extension' ); ?></th>
					<th><?php esc_html_e( 'Ende', 'special-olympics-extension' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $trainings as $t ) :
					$end_date_t = isset( $t['end_date'] ) ? $t['end_date'] : '';
					$can_complete = $end_date_t && $end_date_t < $today;
					?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-training-edit&id=' . (int) $t['id'] ) ); ?>"><?php echo esc_html( $t['title'] ); ?></a><?php if ( $view === 'running' && $can_complete ) : ?> <span class="description">(<?php esc_html_e( 'Kann abgeschlossen werden', 'special-olympics-extension' ); ?>)</span><?php endif; ?></td>
						<td><?php echo esc_html( $t['sport_slug'] ); ?></td>
						<td><?php echo esc_html( $t['start_date'] ? date_i18n( 'd.m.Y', strtotime( $t['start_date'] ) ) : '–' ); ?></td>
						<td><?php echo esc_html( $t['end_date'] ? date_i18n( 'd.m.Y', strtotime( $t['end_date'] ) ) : '–' ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-training-edit&id=' . (int) $t['id'] ) ); ?>"><?php esc_html_e( 'Bearbeiten', 'special-olympics-extension' ); ?></a>
							<?php if ( current_user_can( 'manage_options' ) ) : ?>
								| <a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-trainings&action=delete&id=' . (int) $t['id'] . '&_wpnonce=' . wp_create_nonce( 'delete_training_' . $t['id'] ) ) ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'Training löschen?', 'special-olympics-extension' ) ); ?>');"><?php esc_html_e( 'Löschen', 'special-olympics-extension' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $trainings ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Keine Trainings.', 'special-olympics-extension' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>

		<?php soe_render_attendance_quick_box( true ); ?>
	</div>
	<?php
}

function soe_render_training_form() {
	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$training = $id ? soe_db_training_get( $id ) : null;
	$is_edit = (bool) $training;

	// Permission checks and redirects are handled in soe_training_process_save_before_output() to avoid "headers already sent".

	$readonly = false;
	if ( $is_edit ) {
		if ( $training['completed'] ) {
			$readonly = true;
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			$readonly = true; // Hauptleiter: only attendance editable via AJAX
		}
	}

	$persons = $is_edit ? soe_db_training_get_persons( $id ) : array();
	$role_keys = soe_get_training_role_keys();

	$training = $is_edit ? soe_db_training_get( $id ) : array();
	$persons = $is_edit ? soe_db_training_get_persons( $id ) : array();
	$sessions = $is_edit ? soe_db_training_get_sessions( $id ) : array();
	$computed_sessions = $is_edit ? soe_training_compute_sessions_from_row( array( 'start_date' => $training['start_date'] ?? '', 'end_date' => $training['end_date'] ?? '', 'weekdays' => $training['weekdays'] ?? '', 'excluded_dates' => $training['excluded_dates'] ?? '' ) ) : array();
	$attendance = $is_edit ? soe_db_training_get_attendance( $id ) : array();
	$all_persons = soe_training_get_all_person_labels( $id );
	$sport_terms = get_terms( array( 'taxonomy' => 'sport', 'hide_empty' => false ) );
	$duration_opts = soe_get_duration_options();
	$weekday_labels = array( '1' => __( 'Montag', 'special-olympics-extension' ), '2' => __( 'Dienstag', 'special-olympics-extension' ), '3' => __( 'Mittwoch', 'special-olympics-extension' ), '4' => __( 'Donnerstag', 'special-olympics-extension' ), '5' => __( 'Freitag', 'special-olympics-extension' ), '6' => __( 'Samstag', 'special-olympics-extension' ), '7' => __( 'Sonntag', 'special-olympics-extension' ) );
	$selected_weekdays = ! empty( $training['weekdays'] ) ? array_map( 'trim', explode( ',', $training['weekdays'] ) ) : array();
	// For non-admins: Daten and Personen postboxes start collapsed (read-only for Hauptleiter).
	$collapse_data_personen = ! current_user_can( 'manage_options' );

	// Hauptleiter may add sessions; only admins may remove sessions.
	$can_add_session = $is_edit && empty( $training['completed'] ) && current_user_can( 'edit_trainings' )
		&& ( current_user_can( 'manage_options' ) || ( function_exists( 'soe_is_current_user_hauptleiter_of_training' ) && soe_is_current_user_hauptleiter_of_training( $id ) ) );
	$can_remove_session = current_user_can( 'manage_options' );

	?>
	<div class="wrap">
		<h1><?php echo $is_edit ? esc_html__( 'Training bearbeiten', 'special-olympics-extension' ) : esc_html__( 'Training hinzufügen', 'special-olympics-extension' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Gespeichert.', 'special-olympics-extension' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['error'] ) && is_string( $_GET['error'] ) && $_GET['error'] !== '' ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p></div>
		<?php endif; ?>
		<?php
		$end_date = isset( $training['end_date'] ) ? $training['end_date'] : '';
		$end_in_past = $is_edit && ! ( $training['completed'] ?? 0 ) && $end_date && $end_date < current_time( 'Y-m-d' );
		if ( $end_in_past ) :
			?>
			<div class="notice notice-info"><p><?php esc_html_e( 'Das Enddatum liegt in der Vergangenheit. Wenn alle Anwesenheiten eingetragen sind, kannst du das Training als abgeschlossen markieren.', 'special-olympics-extension' ); ?></p></div>
		<?php endif; ?>

		<form method="post" id="soe-training-form">
			<?php wp_nonce_field( 'soe_training_save' ); ?>
			<input type="hidden" name="soe_training_save" value="1" />

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<?php
						if ( $is_edit && ! empty( $sessions ) ) :
							$today = current_time( 'Y-m-d' );
							$default_session = $sessions[0];
							if ( in_array( $today, $sessions, true ) ) {
								$default_session = $today;
							} else {
								foreach ( $sessions as $d ) {
									if ( $d >= $today ) {
										$default_session = $d;
										break;
									}
								}
							}
							$can_edit_attendance = ! $training['completed'] && current_user_can( 'edit_trainings' );
						?>
						<div class="postbox">
							<div class="postbox-header"><h2><?php esc_html_e( 'Anwesenheit', 'special-olympics-extension' ); ?></h2></div>
							<div class="inside">
								<?php if ( function_exists( 'soe_export_can_xls' ) && soe_export_can_xls() ) : ?>
									<p>
										<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'soe_export_training_attendance', 'id' => $id ), admin_url( 'admin-post.php' ) ), 'soe_export_training_attendance' ) ); ?>" class="button"><?php esc_html_e( 'Als Excel exportieren', 'special-olympics-extension' ); ?></a>
									</p>
								<?php endif; ?>
								<!-- Desktop: wide table (all dates as columns); hidden on tablet/small -->
								<div class="soe-attendance-desktop">
									<div class="soe-attendance-table-wrap">
										<table class="widefat striped soe-attendance-table">
											<thead><tr><th><?php esc_html_e( 'Person', 'special-olympics-extension' ); ?></th>
											<?php foreach ( $sessions as $d ) : ?><th><?php echo esc_html( date_i18n( 'd.m.', strtotime( $d ) ) ); ?></th><?php endforeach; ?>
											</tr></thead>
											<tbody>
											<?php foreach ( $all_persons as $pid => $label ) : ?>
												<tr><td><?php echo esc_html( $label ); ?></td>
												<?php foreach ( $sessions as $d ) :
													$checked = ! empty( $attendance[ $d ][ $pid ] );
												?>
												<td><?php if ( $can_edit_attendance ) : ?>
													<span class="soe-attendance-cell-wrap"><input type="checkbox" class="soe-attendance-cb" data-session="<?php echo esc_attr( $d ); ?>" data-person="<?php echo (int) $pid; ?>" data-post="<?php echo (int) $id; ?>" <?php checked( $checked ); ?> /><span class="soe-attendance-inline-status" aria-hidden="true"></span></span>
												<?php else : ?>
													<?php echo $checked ? '✓' : '–'; ?>
												<?php endif; ?></td>
												<?php endforeach; ?>
												</tr>
											<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>
								<!-- Tablet/small: date dropdown + one table per date; hidden on desktop -->
								<div class="soe-attendance-mobile">
									<p>
										<label for="soe-attendance-date-select"><?php esc_html_e( 'Datum', 'special-olympics-extension' ); ?></label>
										<select id="soe-attendance-date-select" class="soe-attendance-date-select">
											<?php foreach ( $sessions as $d ) : ?>
												<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $d, $default_session ); ?>><?php echo esc_html( date_i18n( 'l, d.m.Y', strtotime( $d ) ) ); ?></option>
											<?php endforeach; ?>
										</select>
									</p>
									<?php foreach ( $sessions as $d ) :
										$is_default = ( $d === $default_session );
									?>
									<div class="soe-attendance-for-date" data-date="<?php echo esc_attr( $d ); ?>" style="<?php echo $is_default ? '' : 'display:none;'; ?>">
										<table class="widefat striped soe-attendance-table soe-attendance-single-date">
											<thead><tr><th><?php esc_html_e( 'Person', 'special-olympics-extension' ); ?></th><th><?php esc_html_e( 'Anwesenheit', 'special-olympics-extension' ); ?></th></tr></thead>
											<tbody>
											<?php foreach ( $all_persons as $pid => $label ) :
												$checked = ! empty( $attendance[ $d ][ $pid ] );
											?>
												<tr>
													<td><?php echo esc_html( $label ); ?></td>
													<td><?php if ( $can_edit_attendance ) : ?>
														<span class="soe-attendance-cell-wrap"><input type="checkbox" class="soe-attendance-cb" data-session="<?php echo esc_attr( $d ); ?>" data-person="<?php echo (int) $pid; ?>" data-post="<?php echo (int) $id; ?>" <?php checked( $checked ); ?> /><span class="soe-attendance-inline-status" aria-hidden="true"></span></span>
													<?php else : ?>
														<?php echo $checked ? '✓' : '–'; ?>
													<?php endif; ?></td>
												</tr>
											<?php endforeach; ?>
											</tbody>
										</table>
									</div>
									<?php endforeach; ?>
								</div>
								<p class="soe-attendance-msg" style="display:none;margin-top:8px;"></p>
							</div>
						</div>
						<?php endif; ?>
						<div class="postbox<?php echo $collapse_data_personen ? ' closed' : ''; ?>">
							<div class="postbox-header">
								<h2 class="hndle"><?php esc_html_e( 'Daten', 'special-olympics-extension' ); ?></h2>
								<button type="button" class="handlediv" aria-expanded="<?php echo $collapse_data_personen ? 'false' : 'true'; ?>"><span class="screen-reader-text"><?php esc_html_e( 'Bereich ein- oder ausklappen: Daten', 'special-olympics-extension' ); ?></span><span class="toggle-indicator" aria-hidden="true"></span></button>
							</div>
							<div class="inside">
								<table class="form-table">
									<tr>
										<th><label for="title"><?php esc_html_e( 'Titel', 'special-olympics-extension' ); ?></label></th>
										<td><input type="text" id="title" name="title" class="large-text" value="<?php echo esc_attr( $training['title'] ?? '' ); ?>" <?php disabled( $readonly ); ?> /></td>
									</tr>
									<tr>
										<th><label for="sport_slug"><?php esc_html_e( 'Sport', 'special-olympics-extension' ); ?></label></th>
										<td>
											<select id="sport_slug" name="sport_slug" <?php disabled( $readonly ); ?>>
												<option value="">— <?php esc_html_e( 'Auswählen', 'special-olympics-extension' ); ?> —</option>
												<?php foreach ( (array) $sport_terms as $t ) : ?>
													<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $training['sport_slug'] ?? '', $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th><label for="start_date"><?php esc_html_e( 'Startdatum', 'special-olympics-extension' ); ?></label></th>
										<td><input type="date" id="start_date" name="start_date" value="<?php echo esc_attr( $training['start_date'] ?? '' ); ?>" <?php disabled( $readonly ); ?> /></td>
									</tr>
									<tr>
										<th><label for="end_date"><?php esc_html_e( 'Enddatum', 'special-olympics-extension' ); ?></label></th>
										<td><input type="date" id="end_date" name="end_date" value="<?php echo esc_attr( $training['end_date'] ?? '' ); ?>" <?php disabled( $readonly ); ?> /></td>
									</tr>
									<tr>
										<th><?php esc_html_e( 'Wochentage', 'special-olympics-extension' ); ?></th>
										<td>
											<fieldset>
												<?php foreach ( $weekday_labels as $v => $l ) : ?>
													<label style="display:inline-block;margin-right:1em;margin-bottom:0.5em;">
														<input type="checkbox" name="weekdays[]" value="<?php echo esc_attr( $v ); ?>" <?php checked( in_array( $v, $selected_weekdays, true ) || in_array( (int) $v, $selected_weekdays, true ) ); ?> <?php disabled( $readonly ); ?> />
														<?php echo esc_html( $l ); ?>
													</label>
												<?php endforeach; ?>
											</fieldset>
										</td>
									</tr>
									<tr>
										<th><label for="time"><?php esc_html_e( 'Uhrzeit', 'special-olympics-extension' ); ?></label></th>
										<td><input type="time" id="time" name="time" value="<?php echo esc_attr( $training['time'] ?? '' ); ?>" <?php disabled( $readonly ); ?> /></td>
									</tr>
									<tr>
										<th><label for="duration"><?php esc_html_e( 'Dauer', 'special-olympics-extension' ); ?></label></th>
										<td>
											<select id="duration" name="duration" <?php disabled( $readonly ); ?>>
												<option value="">— <?php esc_html_e( 'Auswählen', 'special-olympics-extension' ); ?> —</option>
												<?php foreach ( $duration_opts as $d ) : ?>
													<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $training['duration'] ?? '', $d ); ?>><?php echo esc_html( $d ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th><label for="excluded_dates"><?php esc_html_e( 'Ausgelassene Termine', 'special-olympics-extension' ); ?></label></th>
										<td><input type="text" id="excluded_dates" name="excluded_dates" class="large-text" value="<?php echo esc_attr( $training['excluded_dates'] ?? '' ); ?>" placeholder="dd.mm.yyyy, dd.mm.yyyy" <?php disabled( $readonly ); ?> /></td>
									</tr>
									<tr>
										<th><label for="notes"><?php esc_html_e( 'Bemerkungen', 'special-olympics-extension' ); ?></label></th>
										<td>
											<input type="text" id="notes" name="notes" class="large-text" value="<?php echo esc_attr( $training['notes'] ?? '' ); ?>" <?php disabled( $readonly ); ?> />
											<p class="description"><?php esc_html_e( 'Dieses Feld erscheint auf der Lohnabrechnung.', 'special-olympics-extension' ); ?></p>
										</td>
									</tr>
									<tr>
										<th><label for="bh_override"><?php esc_html_e( 'BH-Nummer (Überschreibung)', 'special-olympics-extension' ); ?></label></th>
										<td><input type="text" id="bh_override" name="bh_override" value="<?php echo esc_attr( $training['bh_override'] ?? '' ); ?>" <?php disabled( $readonly ); ?> /></td>
									</tr>
								</table>
							</div>
						</div>

						<div class="postbox<?php echo $collapse_data_personen ? ' closed' : ''; ?>">
							<div class="postbox-header">
								<h2 class="hndle"><?php esc_html_e( 'Personen', 'special-olympics-extension' ); ?></h2>
								<button type="button" class="handlediv" aria-expanded="<?php echo $collapse_data_personen ? 'false' : 'true'; ?>"><span class="screen-reader-text"><?php esc_html_e( 'Bereich ein- oder ausklappen: Personen', 'special-olympics-extension' ); ?></span><span class="toggle-indicator" aria-hidden="true"></span></button>
							</div>
							<div class="inside">
								<?php
								$role_filter_map = soe_get_role_filter_map();
								$role_labels = soe_get_role_labels();
								foreach ( $role_keys as $key ) :
									$ids = isset( $persons[ $key ] ) ? $persons[ $key ] : array();
									$ids_str = implode( ',', $ids );
									$role_filter = isset( $role_filter_map[ $key ] ) ? $role_filter_map[ $key ] : '';
									$role_label = isset( $role_labels[ $key ] ) ? $role_labels[ $key ] : ucfirst( $key );
								?>
								<div class="soe-field-group">
									<label><?php echo esc_html( $role_label ); ?></label>
									<div class="soe-person-picker" data-field-name="persons_<?php echo esc_attr( $key ); ?>" data-role-filter="<?php echo esc_attr( $role_filter ); ?>" data-readonly="<?php echo $readonly ? 'true' : 'false'; ?>">
										<input type="text" class="soe-pp-search" placeholder="<?php esc_attr_e( 'Suchen...', 'special-olympics-extension' ); ?>" <?php disabled( $readonly ); ?> />
										<div class="soe-pp-results"></div>
										<div class="soe-pp-selected"></div>
										<input type="hidden" class="soe-pp-hidden" name="persons_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $ids_str ); ?>" />
									</div>
								</div>
								<?php endforeach; ?>
							</div>
						</div>

					</div>
					<div id="postbox-container-1">
						<div class="postbox">
							<div class="postbox-header"><h2><?php esc_html_e( 'Status', 'special-olympics-extension' ); ?></h2></div>
							<div class="inside">
								<?php if ( $training['completed'] ?? 0 ) : ?>
									<p><strong><?php esc_html_e( 'Abgeschlossen', 'special-olympics-extension' ); ?></strong></p>
									<?php if ( current_user_can( 'manage_options' ) ) : ?>
										<p><button type="button" class="button soe-training-mark-running" data-training-id="<?php echo (int) $id; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'soe_training_mark_running_' . $id ) ); ?>"><?php esc_html_e( 'Als laufend markieren', 'special-olympics-extension' ); ?></button></p>
										<p class="soe-training-status-msg" style="display:none;"></p>
									<?php endif; ?>
								<?php elseif ( $is_edit && current_user_can( 'manage_options' ) ) : ?>
									<p><button type="button" class="button soe-mark-training-completed" data-post-id="<?php echo (int) $id; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'soe_training_complete_' . $id ) ); ?>"><?php esc_html_e( 'Als abgeschlossen markieren', 'special-olympics-extension' ); ?></button></p>
									<p class="soe-training-status-msg" style="display:none;"></p>
								<?php elseif ( $is_edit && function_exists( 'soe_is_current_user_hauptleiter_of_training' ) && soe_is_current_user_hauptleiter_of_training( $id ) ) : ?>
									<p><button type="button" class="button soe-training-request-completed" data-training-id="<?php echo (int) $id; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'soe_training_request_completed_' . $id ) ); ?>"><?php esc_html_e( 'Training als abgeschlossen melden', 'special-olympics-extension' ); ?></button></p>
									<p class="soe-training-status-msg" style="display:none;"></p>
								<?php endif; ?>
								<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-trainings' ) ); ?>">&larr; <?php esc_html_e( 'Zurück zur Liste', 'special-olympics-extension' ); ?></a></p>
								<?php if ( ! $readonly ) : ?>
								<p class="submit"><input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Speichern', 'special-olympics-extension' ); ?>" /></p>
								<?php endif; ?>
							</div>
						</div>
						<?php if ( $is_edit ) : ?>
						<div class="postbox soe-sessions-box">
							<div class="postbox-header"><h2><?php esc_html_e( 'Trainings-Sessions', 'special-olympics-extension' ); ?></h2></div>
							<div class="inside">
								<?php if ( $can_add_session ) : ?>
								<p class="soe-sessions-add">
									<input type="date" class="soe-session-date-input" />
									<button type="button" class="button soe-add-session" data-post-id="<?php echo (int) $id; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'soe_training_sessions_' . $id ) ); ?>"><?php esc_html_e( 'Session hinzufügen', 'special-olympics-extension' ); ?></button>
									<span class="soe-session-msg" style="display:none;"></span>
								</p>
								<?php endif; ?>
								<ul class="soe-sessions-list">
								<?php foreach ( $sessions as $d ) :
									$is_auto = in_array( $d, $computed_sessions, true );
								?>
								<li data-date="<?php echo esc_attr( $d ); ?>">
									<?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $d ) ) ); ?>
									<?php if ( $can_remove_session ) : ?><button type="button" class="button-link soe-remove-session" data-post-id="<?php echo (int) $id; ?>" data-date="<?php echo esc_attr( $d ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'soe_training_sessions_' . $id ) ); ?>" aria-label="<?php esc_attr_e( 'Entfernen', 'special-olympics-extension' ); ?>">×</button><?php endif; ?>
								</li>
								<?php endforeach; ?>
								<?php if ( empty( $sessions ) ) : ?><li class="soe-sessions-empty"><?php esc_html_e( 'Keine Sessions. Periodendaten eingeben und speichern oder manuell hinzufügen.', 'special-olympics-extension' ); ?></li><?php endif; ?>
								</ul>
								<script type="text/javascript">var soeComputedSessions = <?php echo wp_json_encode( $computed_sessions ); ?>;</script>
							</div>
						</div>
						<?php if ( ! empty( $sessions ) ) : ?>
						<div class="postbox">
							<div class="postbox-header"><h2><?php esc_html_e( 'Statistik', 'special-olympics-extension' ); ?></h2></div>
							<div class="inside">
								<?php soe_render_training_stats_table( $id, $sessions, $attendance, $all_persons ); ?>
							</div>
						</div>
						<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>

		</form>
	</div>
	<!-- FIX: Mobile layout for training edit page -->
	<style>
	@media (max-width: 782px) {
		#soe-training-form #post-body.columns-2 {
			display: flex;
			flex-direction: column;
		}
		#soe-training-form #post-body-content {
			float: none !important;
			width: 100% !important;
			order: 1;
		}
		#soe-training-form #postbox-container-1 {
			float: none !important;
			width: 100% !important;
			order: 2;
			margin-left: 0 !important;
		}
	}
	</style>
	<?php
	if ( function_exists( 'wp_add_inline_script' ) && wp_script_is( 'postbox', 'enqueued' ) ) {
		wp_add_inline_script( 'postbox', "jQuery(document).ready(function(){ if ( typeof postboxes !== 'undefined' ) { postboxes.add_postbox_toggles( 'soe-training-edit' ); } if ( window.innerWidth <= 782 ) { jQuery( '#soe-training-form .postbox.closed' ).removeClass( 'closed' ).find( '.handlediv' ).attr( 'aria-expanded', 'true' ); } });", 'after' );
	}
	?>
	<?php
}

/**
 * Validates excluded dates format (dd.mm.yyyy, comma-separated).
 *
 * @param string $raw Comma-separated dates.
 * @return string Empty if valid, error message otherwise.
 */
function soe_validate_excluded_dates( $raw ) {
	if ( trim( $raw ) === '' ) {
		return '';
	}
	$parts = array_map( 'trim', explode( ',', $raw ) );
	foreach ( $parts as $p ) {
		if ( $p === '' ) {
			continue;
		}
		if ( ! preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $p, $m ) ) {
			return sprintf( __( 'Ungültiges Datum "%s". Format: TT.MM.JJJJ (z.B. 20.05.2026)', 'special-olympics-extension' ), esc_html( $p ) );
		}
		$d = sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
		if ( strtotime( $d ) === false ) {
			return sprintf( __( 'Ungültiges Datum "%s"', 'special-olympics-extension' ), esc_html( $p ) );
		}
	}
	return '';
}

/**
 * Computes session dates from training row.
 * Supports multiple weekdays (comma-separated string like "1,3,5").
 *
 * @param array $row Keys: start_date, end_date, weekdays, excluded_dates.
 * @return array List of Y-m-d strings.
 */
function soe_training_compute_sessions_from_row( $row ) {
	$start = $row['start_date'] ?? '';
	$end = $row['end_date'] ?? '';
	$weekdays_raw = $row['weekdays'] ?? '';
	if ( empty( $start ) || empty( $end ) || empty( $weekdays_raw ) ) {
		return array();
	}
	// Parse weekdays (comma-separated string like "1,3,5")
	$weekdays = array_filter( array_map( 'intval', explode( ',', $weekdays_raw ) ), function( $v ) {
		return $v >= 1 && $v <= 7;
	} );
	if ( empty( $weekdays ) ) {
		return array();
	}
	$start_ts = strtotime( $start );
	$end_ts = strtotime( $end );
	if ( $start_ts === false || $end_ts === false || $end_ts < $start_ts ) {
		return array();
	}
	$excluded_raw = $row['excluded_dates'] ?? '';
	$parts = array_map( 'trim', explode( ',', $excluded_raw ) );
	$excluded = array();
	foreach ( $parts as $p ) {
		if ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $p, $m ) ) {
			$d = sprintf( '%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1] );
			if ( strtotime( $d ) !== false ) {
				$excluded[] = $d;
			}
		}
	}
	$out = array();
	$current = $start_ts;
	while ( $current <= $end_ts ) {
		$current_weekday = (int) date( 'N', $current );
		if ( in_array( $current_weekday, $weekdays, true ) ) {
			$d = date( 'Y-m-d', $current );
			if ( ! in_array( $d, $excluded, true ) ) {
				$out[] = $d;
			}
		}
		$current = strtotime( '+1 day', $current );
	}
	sort( $out );
	return array_values( array_unique( $out ) );
}

/**
 * Renders attendance statistics table for a single training.
 */
function soe_render_training_stats_table( $training_id, $sessions, $attendance, $all_persons ) {
	$total = count( $sessions );
	if ( $total === 0 ) {
		echo '<p>' . esc_html__( 'Keine Sessions.', 'special-olympics-extension' ) . '</p>';
		return;
	}
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'special-olympics-extension' ) . '</th><th>' . esc_html__( 'Anw.', 'special-olympics-extension' ) . '</th><th>' . esc_html__( 'Anw. %', 'special-olympics-extension' ) . '</th></tr></thead><tbody>';
	foreach ( $all_persons as $pid => $label ) {
		$count = 0;
		foreach ( $sessions as $d ) {
			if ( ! empty( $attendance[ $d ][ $pid ] ) ) {
				$count++;
			}
		}
		$pct = $total > 0 ? round( 100 * $count / $total ) : 0;
		echo '<tr><td>' . esc_html( $label ) . '</td><td>' . (int) $count . '</td><td>' . (int) $pct . '%</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * Global training statistics page (grouped by training, filter by sport).
 */
function soe_render_training_stats() {
	$sport_filter = isset( $_GET['sport'] ) ? sanitize_text_field( wp_unslash( $_GET['sport'] ) ) : '';
	$mitglied_id = soe_get_current_user_mitglied_id();
	$is_hauptleiter = $mitglied_id && ! current_user_can( 'manage_options' );

	$args = array( 'completed' => 0, 'limit' => 500 );
	if ( $sport_filter ) {
		$args['sport_slug'] = $sport_filter;
	}
	if ( $is_hauptleiter ) {
		$args['hauptleiter_person_id'] = $mitglied_id;
	}
	$trainings = soe_db_training_list( $args );

	$sport_terms = get_terms( array( 'taxonomy' => 'sport', 'hide_empty' => false ) );
	$base = admin_url( 'admin.php?page=soe-training-stats' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Trainings-Statistik', 'special-olympics-extension' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Nur laufende Trainings. Abgeschlossene Trainings zählen nicht.', 'special-olympics-extension' ); ?></p>
		<form method="get" class="soe-stats-filter">
			<input type="hidden" name="page" value="soe-training-stats" />
			<select name="sport">
				<option value=""><?php esc_html_e( 'Alle Sportarten', 'special-olympics-extension' ); ?></option>
				<?php foreach ( (array) $sport_terms as $t ) : ?>
					<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $sport_filter, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filtern', 'special-olympics-extension' ); ?></button>
			<?php if ( function_exists( 'soe_export_can_xls' ) && soe_export_can_xls() ) : ?>
				<?php
				$stats_export_url = add_query_arg( array( 'sport' => $sport_filter ), admin_url( 'admin-post.php' ) );
				$stats_export_url = add_query_arg( 'action', 'soe_export_training_stats', $stats_export_url );
				$stats_export_url = wp_nonce_url( $stats_export_url, 'soe_export_training_stats' );
				?>
				<a href="<?php echo esc_url( $stats_export_url ); ?>" class="button"><?php esc_html_e( 'Als Excel exportieren', 'special-olympics-extension' ); ?></a>
			<?php endif; ?>
		</form>
		<?php foreach ( $trainings as $t ) :
			$sessions = soe_db_training_get_sessions( $t['id'] );
			$attendance = soe_db_training_get_attendance( $t['id'] );
			$all_persons = soe_training_get_all_person_labels( $t['id'] );
		?>
		<h2><?php echo esc_html( $t['title'] ); ?> <?php echo $t['sport_slug'] ? '(' . esc_html( $t['sport_slug'] ) . ')' : ''; ?></h2>
		<?php soe_render_training_stats_table( $t['id'], $sessions, $attendance, $all_persons ); ?>
		<?php endforeach; ?>
		<?php if ( empty( $trainings ) ) : ?>
			<p><?php esc_html_e( 'Keine laufenden Trainings.', 'special-olympics-extension' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

function soe_training_get_all_person_labels( $training_id ) {
	$persons = soe_db_training_get_persons( $training_id );
	$ids = array();
	foreach ( $persons as $role => $list ) {
		foreach ( (array) $list as $pid ) {
			$ids[ (int) $pid ] = true;
		}
	}
	$out = array();
	foreach ( array_keys( $ids ) as $pid ) {
		$post = get_post( $pid );
		$out[ $pid ] = $post && $post->post_title ? $post->post_title : (string) $pid;
	}
	return $out;
}

function soe_custom_trainings_scripts( $hook ) {
	if ( strpos( $hook, 'soe-training' ) === false && $hook !== 'toplevel_page_soe-trainings' ) {
		return;
	}
	wp_enqueue_script( 'postbox' );
	$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
	$flatpickr_version = '4.6.13';
	wp_enqueue_style( 'flatpickr', $plugin_url . 'assets/vendor/flatpickr/flatpickr.min.css', array(), $flatpickr_version );
	wp_enqueue_style( 'flatpickr-theme', $plugin_url . 'assets/css/flatpickr-theme.css', array( 'flatpickr' ), SOE_PLUGIN_VERSION );
	wp_enqueue_style( 'soe-person-picker', $plugin_url . 'assets/css/person-picker.css', array(), SOE_PLUGIN_VERSION );
	wp_add_inline_style(
		'soe-person-picker',
		'.soe-attendance-msg.updated { color: #00a32a; font-weight: 500; }' .
		'.soe-attendance-cell-wrap { display: inline-flex; align-items: center; gap: 6px; vertical-align: middle; }' .
		'.soe-attendance-inline-status { width: 16px; height: 16px; flex-shrink: 0; display: inline-block; }' .
		'.soe-attendance-inline-status.is-loading::before { content: ""; display: block; width: 14px; height: 14px; border: 2px solid #c3c4c7; border-top-color: #2271b1; border-radius: 50%; animation: soe-att-spin 0.7s linear infinite; }' .
		'@keyframes soe-att-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }'
	);
	wp_enqueue_script( 'flatpickr', $plugin_url . 'assets/vendor/flatpickr/flatpickr.min.js', array(), $flatpickr_version, true );
	wp_enqueue_script( 'flatpickr-de', $plugin_url . 'assets/vendor/flatpickr/l10n-de.js', array( 'flatpickr' ), $flatpickr_version, true );
	wp_enqueue_script( 'soe-person-picker', $plugin_url . 'assets/js/person-picker.js', array( 'jquery' ), SOE_PLUGIN_VERSION, true );
	wp_enqueue_script( 'soe-admin-training', $plugin_url . 'assets/js/admin-training.js', array( 'jquery', 'soe-person-picker', 'flatpickr-de' ), SOE_PLUGIN_VERSION, true );
	wp_localize_script( 'soe-admin-training', 'soeTrainingAdmin', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'soe_training_admin' ),
		'personSearchNonce' => wp_create_nonce( 'soe_person_search' ),
		'i18n' => array(
			'saved' => __( 'Gespeichert.', 'special-olympics-extension' ),
			'error' => __( 'Fehler.', 'special-olympics-extension' ),
			'chooseDate' => __( 'Bitte Datum wählen.', 'special-olympics-extension' ),
			'overlapConfirm' => __( 'Dieses Datum überschneidet sich mit einer automatisch erstellten Session. Bei Bestätigung wird die automatische überschrieben. Fortfahren?', 'special-olympics-extension' ),
			'removeConfirm' => __( 'Session wirklich entfernen?', 'special-olympics-extension' ),
			'markRunningConfirm' => __( 'Training wieder als laufend markieren? Es kann danach erneut bearbeitet werden.', 'special-olympics-extension' ),
		),
	) );
}

function soe_ajax_training_attendance() {
	check_ajax_referer( 'soe_training_admin', 'nonce' );
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	$session = isset( $_POST['session'] ) ? sanitize_text_field( wp_unslash( $_POST['session'] ) ) : '';
	$person_id = isset( $_POST['person_id'] ) ? (int) $_POST['person_id'] : 0;
	$checked = isset( $_POST['checked'] ) && $_POST['checked'] === '1';
	if ( ! $post_id || strlen( $session ) !== 10 || ! $person_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'special-olympics-extension' ) ) );
	}
	if ( ! current_user_can( 'edit_trainings' ) ) {
		wp_send_json_error( array( 'message' => __( 'No permission.', 'special-olympics-extension' ) ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		if ( ! function_exists( 'soe_is_current_user_assigned_to_training' ) || ! soe_is_current_user_assigned_to_training( $post_id, array( 'hauptleiter', 'leiter' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung für dieses Training.', 'special-olympics-extension' ) ) );
		}
	}
	$t = soe_db_training_get( $post_id );
	if ( ! $t || $t['completed'] ) {
		wp_send_json_error( array( 'message' => __( 'Training abgeschlossen.', 'special-olympics-extension' ) ) );
	}
	if ( function_exists( 'soe_attendance_validate_write_context' ) ) {
		$context_valid = soe_attendance_validate_write_context( $post_id, $session, $person_id );
		if ( is_wp_error( $context_valid ) ) {
			wp_send_json_error( array( 'message' => $context_valid->get_error_message() ) );
		}
	}
	$saved = soe_db_training_set_attendance( $post_id, $session, $person_id, $checked ? 1 : 0 );
	if ( ! $saved ) {
		wp_send_json_error( array( 'message' => __( 'Fehler beim Speichern.', 'special-olympics-extension' ) ) );
	}
	wp_send_json_success( array( 'message' => __( 'Gespeichert.', 'special-olympics-extension' ) ) );
}

function soe_ajax_training_mark_completed() {
	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( ! $post_id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid request.', 'special-olympics-extension' ) ) );
	}
	check_ajax_referer( 'soe_training_complete_' . $post_id, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'No permission.', 'special-olympics-extension' ) ) );
	}
	soe_db_training_update( $post_id, array( 'completed' => 1 ) );
	wp_send_json_success( array( 'message' => __( 'Training als abgeschlossen markiert.', 'special-olympics-extension' ) ) );
}

/**
 * AJAX: Send email to admins requesting that training be marked as completed.
 * Available for Hauptleiter (non-admins) who are Hauptleiter of the training.
 */
function soe_ajax_training_request_completed() {
	$training_id = isset( $_POST['training_id'] ) ? (int) $_POST['training_id'] : 0;
	if ( ! $training_id ) {
		wp_send_json_error( array( 'message' => __( 'Ungültige Anfrage.', 'special-olympics-extension' ) ) );
	}
	check_ajax_referer( 'soe_training_request_completed_' . $training_id, 'nonce' );
	if ( current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Admins können das Training direkt als abgeschlossen markieren.', 'special-olympics-extension' ) ) );
	}
	if ( ! function_exists( 'soe_is_current_user_hauptleiter_of_training' ) || ! soe_is_current_user_hauptleiter_of_training( $training_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'special-olympics-extension' ) ) );
	}
	$t = soe_db_training_get( $training_id );
	if ( ! $t || ! empty( $t['completed'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Training ist bereits abgeschlossen.', 'special-olympics-extension' ) ) );
	}
	$to = function_exists( 'soe_get_mail_training_completed_to' ) ? soe_get_mail_training_completed_to() : '';
	if ( ! is_email( $to ) ) {
		wp_send_json_error( array( 'message' => __( 'E-Mail-Empfänger ist nicht konfiguriert. Bitte auf der Einstellungs-Seite eintragen.', 'special-olympics-extension' ) ) );
	}
	if ( function_exists( 'soe_is_mail_category_enabled' ) && ! soe_is_mail_category_enabled( SOE_MAIL_CAT_TRAINING_COMPLETED ) ) {
		wp_send_json_error( array( 'message' => __( 'E-Mail-Versand für diese Benachrichtigung ist in den Einstellungen deaktiviert.', 'special-olympics-extension' ) ) );
	}
	$edit_url = admin_url( 'admin.php?page=soe-training-edit&id=' . $training_id );
	$title = isset( $t['title'] ) ? $t['title'] : '';
	$sport = isset( $t['sport_slug'] ) ? $t['sport_slug'] : '';
	$user = wp_get_current_user();
	$vorname = ! empty( $user->first_name ) ? $user->first_name : $user->display_name;
	if ( empty( trim( $vorname ) ) ) {
		$vorname = __( 'Ein Hauptleiter', 'special-olympics-extension' );
	}
	$subject = sprintf(
		/* translators: 1: training title, 2: site name */
		__( '[%2$s] Training als abgeschlossen melden: %1$s', 'special-olympics-extension' ),
		$title,
		get_bloginfo( 'name' )
	);
	$body = sprintf(
		/* translators: 1: first name of requester, 2: training title, 3: sport, 4: edit URL */
		__( "%1\$s meldet, dass das folgende Training abgeschlossen ist:\n\nTitel: %2\$s\nSport: %3\$s\n\nBitte hier als abgeschlossen markieren:\n%4\$s", 'special-olympics-extension' ),
		$vorname,
		$title,
		$sport,
		$edit_url
	);
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	$sent = wp_mail( $to, $subject, $body, $headers );
	if ( ! $sent ) {
		wp_send_json_error( array( 'message' => __( 'E-Mail konnte nicht versendet werden.', 'special-olympics-extension' ) ) );
	}
	wp_send_json_success( array( 'message' => __( 'Benachrichtigung wurde an die Administratoren gesendet.', 'special-olympics-extension' ) ) );
}

/**
 * AJAX: Mark training as running again (admin only). Reverses "completed" status.
 */
function soe_ajax_training_mark_running() {
	$training_id = isset( $_POST['training_id'] ) ? (int) $_POST['training_id'] : 0;
	if ( ! $training_id ) {
		wp_send_json_error( array( 'message' => __( 'Ungültige Anfrage.', 'special-olympics-extension' ) ) );
	}
	check_ajax_referer( 'soe_training_mark_running_' . $training_id, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'special-olympics-extension' ) ) );
	}
	$t = soe_db_training_get( $training_id );
	if ( ! $t ) {
		wp_send_json_error( array( 'message' => __( 'Training nicht gefunden.', 'special-olympics-extension' ) ) );
	}
	if ( empty( $t['completed'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Training ist bereits laufend.', 'special-olympics-extension' ) ) );
	}
	soe_db_training_update( $training_id, array( 'completed' => 0 ) );
	wp_send_json_success( array( 'message' => __( 'Training wurde als laufend markiert.', 'special-olympics-extension' ) ) );
}

function soe_ajax_training_add_session() {
	$training_id = isset( $_POST['training_id'] ) ? (int) $_POST['training_id'] : 0;
	$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
	$overwrite = isset( $_POST['overwrite'] ) && ( $_POST['overwrite'] === '1' || $_POST['overwrite'] === true );
	if ( ! $training_id || strlen( $date ) !== 10 ) {
		wp_send_json_error( array( 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ) );
	}
	check_ajax_referer( 'soe_training_sessions_' . $training_id, 'nonce' );
	if ( ! current_user_can( 'edit_trainings' ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'special-olympics-extension' ) ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		if ( ! function_exists( 'soe_is_current_user_hauptleiter_of_training' ) || ! soe_is_current_user_hauptleiter_of_training( $training_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Keine Berechtigung für dieses Training.', 'special-olympics-extension' ) ) );
		}
	}
	$t = soe_db_training_get( $training_id );
	if ( ! $t || $t['completed'] ) {
		wp_send_json_error( array( 'message' => __( 'Training abgeschlossen.', 'special-olympics-extension' ) ) );
	}
	$existing = soe_db_training_get_sessions( $training_id );
	if ( in_array( $date, $existing, true ) ) {
		if ( $overwrite ) {
			wp_send_json_success( array( 'message' => __( 'Session bereits vorhanden (Overwrite bestätigt).', 'special-olympics-extension' ), 'reload' => true ) );
		}
		$computed = soe_training_compute_sessions_from_row( array( 'start_date' => $t['start_date'] ?? '', 'end_date' => $t['end_date'] ?? '', 'weekdays' => $t['weekdays'] ?? '', 'excluded_dates' => $t['excluded_dates'] ?? '' ) );
		$overlap_msg = in_array( $date, $computed, true ) ? __( 'Session bereits vorhanden (überschneidet sich mit automatischer Erstellung).', 'special-olympics-extension' ) : __( 'Dieses Datum ist bereits in den Sessions enthalten.', 'special-olympics-extension' );
		wp_send_json_error( array( 'message' => $overlap_msg, 'overlap' => true ) );
	}
	$computed = soe_training_compute_sessions_from_row( array( 'start_date' => $t['start_date'] ?? '', 'end_date' => $t['end_date'] ?? '', 'weekdays' => $t['weekdays'] ?? '', 'excluded_dates' => $t['excluded_dates'] ?? '' ) );
	$is_overlap = in_array( $date, $computed, true );
	$added = soe_db_training_add_session( $training_id, $date );
	if ( ! $added ) {
		wp_send_json_error( array( 'message' => __( 'Konnte Session nicht hinzufügen.', 'special-olympics-extension' ) ) );
	}
	$msg = $is_overlap
		? __( 'Session hinzugefügt. Hinweis: Überschneidung mit automatisch erstellter Session – bei Bestätigung überschrieben.', 'special-olympics-extension' )
		: __( 'Session hinzugefügt.', 'special-olympics-extension' );
	wp_send_json_success( array( 'message' => $msg, 'overlap' => $is_overlap, 'sessions' => soe_db_training_get_sessions( $training_id ), 'reload' => true ) );
}

function soe_ajax_training_remove_session() {
	$training_id = isset( $_POST['training_id'] ) ? (int) $_POST['training_id'] : 0;
	$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
	if ( ! $training_id || strlen( $date ) !== 10 ) {
		wp_send_json_error( array( 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ) );
	}
	check_ajax_referer( 'soe_training_sessions_' . $training_id, 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'special-olympics-extension' ) ) );
	}
	$t = soe_db_training_get( $training_id );
	if ( ! $t || $t['completed'] ) {
		wp_send_json_error( array( 'message' => __( 'Training abgeschlossen.', 'special-olympics-extension' ) ) );
	}
	soe_db_training_remove_session( $training_id, $date );
	wp_send_json_success( array( 'message' => __( 'Session entfernt.', 'special-olympics-extension' ), 'reload' => true ) );
}

/**
 * AJAX: Save the user's attendance PIN.
 */
function soe_ajax_attendance_save_pin() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( array( 'message' => __( 'Nicht angemeldet.', 'special-olympics-extension' ) ) );
	}

	check_ajax_referer( 'soe_attendance_pin_' . $user_id, 'nonce' );

	if ( ! function_exists( 'soe_attendance_can_user_have_token' ) || ! soe_attendance_can_user_have_token( $user_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'special-olympics-extension' ) ) );
	}

	$pin = isset( $_POST['pin'] ) ? sanitize_text_field( wp_unslash( $_POST['pin'] ) ) : '';
	if ( ! preg_match( '/^\d{4,6}$/', $pin ) ) {
		wp_send_json_error( array( 'message' => __( 'PIN muss 4-6 Ziffern enthalten.', 'special-olympics-extension' ) ) );
	}

	if ( ! function_exists( 'soe_attendance_set_pin' ) ) {
		wp_send_json_error( array( 'message' => __( 'Funktion nicht verfügbar.', 'special-olympics-extension' ) ) );
	}

	$saved = soe_attendance_set_pin( $user_id, $pin );
	if ( ! $saved ) {
		wp_send_json_error( array( 'message' => __( 'PIN konnte nicht gespeichert werden.', 'special-olympics-extension' ) ) );
	}

	wp_send_json_success( array( 'message' => __( 'PIN gespeichert.', 'special-olympics-extension' ) ) );
}

/**
 * AJAX: Regenerate the user's attendance token.
 */
function soe_ajax_attendance_regenerate_token() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( array( 'message' => __( 'Nicht angemeldet.', 'special-olympics-extension' ) ) );
	}

	check_ajax_referer( 'soe_attendance_regenerate_' . $user_id, 'nonce' );

	if ( ! function_exists( 'soe_attendance_can_user_have_token' ) || ! soe_attendance_can_user_have_token( $user_id ) ) {
		wp_send_json_error( array( 'message' => __( 'Keine Berechtigung.', 'special-olympics-extension' ) ) );
	}

	if ( ! function_exists( 'soe_attendance_regenerate_token' ) ) {
		wp_send_json_error( array( 'message' => __( 'Funktion nicht verfügbar.', 'special-olympics-extension' ) ) );
	}

	$new_token = soe_attendance_regenerate_token( $user_id );
	if ( ! $new_token ) {
		wp_send_json_error( array( 'message' => __( 'Token konnte nicht erzeugt werden.', 'special-olympics-extension' ) ) );
	}

	$new_url = home_url( '/soe-anwesenheit/?token=' . rawurlencode( $new_token ) );
	$token_expiry = function_exists( 'soe_attendance_get_token_expiry' ) ? soe_attendance_get_token_expiry( $user_id ) : 0;
	$expiry_date = $token_expiry ? date_i18n( 'd.m.Y', $token_expiry ) : '–';

	wp_send_json_success( array(
		'message' => __( 'Neuer Link wurde erzeugt.', 'special-olympics-extension' ),
		'url' => $new_url,
		'expiry_date' => $expiry_date,
	) );
}
