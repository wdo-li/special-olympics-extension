<?php
/**
 * Custom Admin UI for Events (no CPT, direct DB).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SOE_EVENT_SNAPSHOT_META', 'soe_event_snapshot' );

add_action( 'admin_menu', 'soe_custom_events_menu', 16 );
add_action( 'admin_init', 'soe_event_process_save_and_delete', 5 );
add_action( 'admin_init', 'soe_event_types_redirect_early', 1 );
add_action( 'admin_enqueue_scripts', 'soe_custom_events_scripts' );
add_action( 'add_meta_boxes', 'soe_add_mitglied_events_meta_box' );

/**
 * Only admins and Hauptleiter may access Events (menu and pages). All others see no Events.
 *
 * @return bool
 */
function soe_can_access_events() {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	$user = wp_get_current_user();
	return $user && ! empty( $user->ID ) && in_array( 'hauptleiter_in', (array) $user->roles, true );
}

function soe_custom_events_menu() {
	if ( ! soe_can_access_events() ) {
		return;
	}
	add_menu_page(
		__( 'Events', 'special-olympics-extension' ),
		__( 'Events', 'special-olympics-extension' ),
		'edit_events',
		'soe-events',
		'soe_render_events_list',
		'dashicons-awards',
		8
	);
	add_submenu_page( 'soe-events', __( 'Events', 'special-olympics-extension' ), __( 'Alle Events', 'special-olympics-extension' ), 'edit_events', 'soe-events', 'soe_render_events_list' );
	add_submenu_page( 'soe-events', __( 'Event hinzufügen', 'special-olympics-extension' ), __( 'Hinzufügen', 'special-olympics-extension' ), 'edit_events', 'soe-event-new', 'soe_render_event_form' );
	add_submenu_page( 'soe-events', __( 'Event-Typen', 'special-olympics-extension' ), __( 'Event-Typen', 'special-olympics-extension' ), 'manage_categories', 'soe-event-types', 'soe_redirect_to_event_types' );
	add_submenu_page( null, __( 'Event bearbeiten', 'special-olympics-extension' ), '', 'edit_events', 'soe-event-edit', 'soe_render_event_form' );
}

/**
 * Redirect to event types taxonomy page early (before output).
 */
function soe_event_types_redirect_early() {
	if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'soe-event-types' ) {
		return;
	}
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	wp_safe_redirect( admin_url( 'edit-tags.php?taxonomy=event_type&post_type=event' ) );
	exit;
}

function soe_redirect_to_event_types() {
	// Redirect happens in soe_event_types_redirect_early() before output.
	// This is a fallback that should not be reached.
	echo '<p>' . esc_html__( 'Weiterleitung...', 'special-olympics-extension' ) . '</p>';
}

/**
 * Process event save and delete before any output so wp_redirect() works (avoids "headers already sent").
 */
function soe_event_process_save_and_delete() {
	if ( ! soe_can_access_events() ) {
		return;
	}
	$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
	if ( $page !== 'soe-event-edit' && $page !== 'soe-event-new' ) {
		return;
	}

	$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

	// Delete (GET): redirect before any output.
	if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && $id && current_user_can( 'manage_options' ) && wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', 'delete_event_' . $id ) ) {
		soe_event_remove_from_all_snapshots( $id );
		soe_db_event_delete( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=soe-events&deleted=1' ) );
		exit;
	}

	// Save (POST): process and redirect before any output.
	if ( ! isset( $_POST['soe_event_save'] ) || ! current_user_can( 'edit_events' ) ) {
		return;
	}
	check_admin_referer( 'soe_event_save' );

	$event    = $id ? soe_db_event_get( $id ) : null;
	$is_edit  = (bool) $event;
	$role_keys = soe_get_event_role_keys();

	$title          = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$event_date     = isset( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : '';
	$duration       = isset( $_POST['duration'] ) ? sanitize_text_field( wp_unslash( $_POST['duration'] ) ) : '';
	$notes          = isset( $_POST['notes'] ) ? sanitize_text_field( wp_unslash( $_POST['notes'] ) ) : '';
	$sport_slug     = isset( $_POST['sport_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['sport_slug'] ) ) : '';
	$event_type_slug = isset( $_POST['event_type_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type_slug'] ) ) : '';
	$bh_override    = isset( $_POST['bh_override'] ) ? sanitize_text_field( wp_unslash( $_POST['bh_override'] ) ) : '';

	$persons_data = soe_parse_persons_from_post( $role_keys, 'persons_' );
	$all_ids      = array();
	foreach ( $persons_data as $ids ) {
		$duplicates = array_intersect( $all_ids, $ids );
		if ( ! empty( $duplicates ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=soe-event-' . ( $is_edit ? 'edit&id=' . $id : 'new' ) . '&error=' . rawurlencode( __( 'Jede Person darf nur einmal pro Event ausgewählt werden.', 'special-olympics-extension' ) ) ) );
			exit;
		}
		$all_ids = array_merge( $all_ids, $ids );
	}

	$data = array(
		'title'           => $title,
		'event_date'      => $event_date ?: null,
		'duration'        => $duration,
		'notes'           => $notes,
		'sport_slug'      => $sport_slug,
		'event_type_slug' => $event_type_slug,
		'bh_override'     => $bh_override,
	);

	if ( $is_edit ) {
		soe_db_event_update( $id, $data );
	} else {
		$id = soe_db_event_insert( $data );
	}

	soe_db_event_save_persons( $id, $persons_data );
	soe_event_sync_snapshot_for_event( $id );

	// Send notification email when a new event is created.
	if ( ! $is_edit ) {
		$to = function_exists( 'soe_get_mail_event_created_to' ) ? soe_get_mail_event_created_to() : '';
		if ( is_email( $to ) && ( ! function_exists( 'soe_is_mail_category_enabled' ) || soe_is_mail_category_enabled( SOE_MAIL_CAT_EVENT_CREATED ) ) ) {
			$user     = wp_get_current_user();
			$creator  = $user->display_name ?: $user->user_login;
			$ev_title = ! empty( $title ) ? $title : __( '(Ohne Titel)', 'special-olympics-extension' );
			$edit_url = admin_url( 'admin.php?page=soe-event-edit&id=' . $id );
			$subject  = sprintf(
				/* translators: 1: site name, 2: event title */
				__( '[%1$s] Neues Event angelegt: %2$s', 'special-olympics-extension' ),
				get_bloginfo( 'name' ),
				$ev_title
			);
			$body = sprintf(
				/* translators: 1: creator name, 2: event title, 3: edit URL */
				__( "Ein neues Event wurde angelegt.\n\nAngelegt von: %1\$s\nEvent: %2\$s\n\nBearbeiten: %3\$s", 'special-olympics-extension' ),
				$creator,
				$ev_title,
				$edit_url
			);
			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
			wp_mail( $to, $subject, $body, $headers );
		}
	}

	if ( function_exists( 'soe_debug_log' ) ) {
		soe_debug_log( 'Event saved', array( 'event_id' => $id, 'participants' => count( $all_ids ) ) );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=soe-event-edit&id=' . $id . '&saved=1' ) );
	exit;
}

function soe_render_events_list() {
	if ( ! soe_can_access_events() ) {
		wp_die( esc_html__( 'Du hast keinen Zugriff auf Events.', 'special-olympics-extension' ), 403 );
	}

	$sport_filter   = isset( $_GET['sport'] ) ? sanitize_text_field( wp_unslash( $_GET['sport'] ) ) : '';
	$type_filter    = isset( $_GET['event_type'] ) ? sanitize_text_field( wp_unslash( $_GET['event_type'] ) ) : '';
	$date_from      = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
	$date_to        = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
	$search_filter  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

	$list_args = array( 'limit' => 500 );
	if ( $sport_filter ) { $list_args['sport_slug'] = $sport_filter; }
	if ( $type_filter ) { $list_args['event_type_slug'] = $type_filter; }
	if ( $date_from ) { $list_args['date_from'] = $date_from; }
	if ( $date_to ) { $list_args['date_to'] = $date_to; }
	if ( $search_filter ) { $list_args['search'] = $search_filter; }
	// Non-admins (Hauptleiter) see only events where they participate.
	if ( ! current_user_can( 'manage_options' ) && function_exists( 'soe_get_current_user_mitglied_id' ) ) {
		$list_args['person_id'] = soe_get_current_user_mitglied_id();
	}

	$events = soe_db_event_list( $list_args );
	$sport_terms  = get_terms( array( 'taxonomy' => 'sport', 'hide_empty' => false ) );
	$event_types  = get_terms( array( 'taxonomy' => 'event_type', 'hide_empty' => false ) );
	$base_url     = admin_url( 'admin.php?page=soe-events' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Events', 'special-olympics-extension' ); ?></h1>
		<?php if ( current_user_can( 'edit_events' ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Event hinzufügen', 'special-olympics-extension' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end">

		<form method="get" class="soe-events-filter">
			<input type="hidden" name="page" value="soe-events" />
			<p class="search-box">
				<label class="screen-reader-text" for="event-search-input"><?php esc_html_e( 'Suchen', 'special-olympics-extension' ); ?></label>
				<input type="search" id="event-search-input" name="s" value="<?php echo esc_attr( $search_filter ); ?>" placeholder="<?php esc_attr_e( 'Titel oder Bemerkungen durchsuchen…', 'special-olympics-extension' ); ?>" />
				<input type="submit" id="search-submit" class="button" value="<?php esc_attr_e( 'Suchen', 'special-olympics-extension' ); ?>" />
			</p>
			<p class="soe-filter-row">
				<select name="sport">
					<option value=""><?php esc_html_e( 'Alle Sportarten', 'special-olympics-extension' ); ?></option>
					<?php foreach ( (array) $sport_terms as $t ) : ?>
						<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $sport_filter, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="event_type">
					<option value=""><?php esc_html_e( 'Alle Event-Typen', 'special-olympics-extension' ); ?></option>
					<?php foreach ( (array) $event_types as $t ) : ?>
						<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $type_filter, $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
					<?php endforeach; ?>
				</select>
				<label><?php esc_html_e( 'Von', 'special-olympics-extension' ); ?> <input type="date" id="event_filter_date_from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" /></label>
				<label><?php esc_html_e( 'Bis', 'special-olympics-extension' ); ?> <input type="date" id="event_filter_date_to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" /></label>
				<input type="submit" class="button" value="<?php esc_attr_e( 'Filtern', 'special-olympics-extension' ); ?>" />
				<?php if ( $sport_filter || $type_filter || $date_from || $date_to || $search_filter ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Filter zurücksetzen', 'special-olympics-extension' ); ?></a>
				<?php endif; ?>
			</p>
		</form>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Titel', 'special-olympics-extension' ); ?></th>
					<th><?php esc_html_e( 'Datum', 'special-olympics-extension' ); ?></th>
					<th><?php esc_html_e( 'Sport', 'special-olympics-extension' ); ?></th>
					<th><?php esc_html_e( 'Event-Typ', 'special-olympics-extension' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $events as $e ) : ?>
					<?php
					$type_term = ! empty( $e['event_type_slug'] ) ? get_term_by( 'slug', $e['event_type_slug'], 'event_type' ) : null;
					$type_name = $type_term ? $type_term->name : ( $e['event_type_slug'] ? $e['event_type_slug'] : '–' );
					?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-edit&id=' . (int) $e['id'] ) ); ?>"><?php echo esc_html( $e['title'] ); ?></a></td>
						<td><?php echo esc_html( $e['event_date'] ? date_i18n( 'd.m.Y', strtotime( $e['event_date'] ) ) : '–' ); ?></td>
						<td><?php echo esc_html( $e['sport_slug'] ? $e['sport_slug'] : '–' ); ?></td>
						<td><?php echo esc_html( $type_name ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-edit&id=' . (int) $e['id'] ) ); ?>"><?php esc_html_e( 'Bearbeiten', 'special-olympics-extension' ); ?></a>
							<?php if ( current_user_can( 'manage_options' ) ) : ?>
								| <a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-event-edit&id=' . (int) $e['id'] . '&action=delete&_wpnonce=' . wp_create_nonce( 'delete_event_' . $e['id'] ) ) ); ?>" class="submitdelete" onclick="return confirm('<?php echo esc_js( __( 'Event löschen?', 'special-olympics-extension' ) ); ?>');"><?php esc_html_e( 'Löschen', 'special-olympics-extension' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $events ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Keine Events.', 'special-olympics-extension' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function soe_render_event_form() {
	if ( ! soe_can_access_events() ) {
		wp_die( esc_html__( 'Du hast keinen Zugriff auf Events.', 'special-olympics-extension' ), 403 );
	}

	$id      = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	$event   = $id ? soe_db_event_get( $id ) : array();
	$is_edit = (bool) $id && ! empty( $event );

	// Hauptleiter may only open events where they participate.
	if ( $is_edit && ! current_user_can( 'manage_options' ) && function_exists( 'soe_get_current_user_mitglied_id' ) ) {
		$my_mitglied_id = soe_get_current_user_mitglied_id();
		$persons_flat   = soe_db_event_get_persons( $id );
		$all_person_ids = array();
		foreach ( (array) $persons_flat as $role_ids ) {
			$all_person_ids = array_merge( $all_person_ids, (array) $role_ids );
		}
		if ( ! $my_mitglied_id || ! in_array( $my_mitglied_id, array_map( 'intval', $all_person_ids ), true ) ) {
			wp_die( esc_html__( 'Du hast keinen Zugriff auf dieses Event.', 'special-olympics-extension' ), 403 );
		}
	}

	$readonly  = $is_edit && ! current_user_can( 'edit_events' );
	$persons   = $is_edit ? soe_db_event_get_persons( $id ) : array();
	$role_keys = soe_get_event_role_keys();
	$sport_terms = get_terms( array( 'taxonomy' => 'sport', 'hide_empty' => false ) );
	$event_type_terms = get_terms( array( 'taxonomy' => 'event_type', 'hide_empty' => false ) );
	$duration_opts = soe_get_duration_options();
	?>
	<div class="wrap">
		<h1><?php echo $is_edit ? esc_html__( 'Event bearbeiten', 'special-olympics-extension' ) : esc_html__( 'Event hinzufügen', 'special-olympics-extension' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'Gespeichert.', 'special-olympics-extension' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['error'] ) && is_string( $_GET['error'] ) && $_GET['error'] !== '' ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p></div>
		<?php endif; ?>

		<form method="post" id="soe-event-form">
			<?php wp_nonce_field( 'soe_event_save' ); ?>
			<input type="hidden" name="soe_event_save" value="1" />

			<div class="postbox">
				<div class="postbox-header"><h2><?php esc_html_e( 'Daten', 'special-olympics-extension' ); ?></h2></div>
				<div class="inside">
					<table class="form-table">
						<tr>
							<th><label for="title"><?php esc_html_e( 'Titel', 'special-olympics-extension' ); ?></label></th>
							<td><input type="text" id="title" name="title" class="large-text" value="<?php echo esc_attr( $event['title'] ?? '' ); ?>" <?php disabled( $readonly ); ?> /></td>
						</tr>
						<tr>
							<th><label for="event_date"><?php esc_html_e( 'Datum', 'special-olympics-extension' ); ?></label></th>
							<td><input type="date" id="event_date" name="event_date" value="<?php echo esc_attr( $event['event_date'] ?? '' ); ?>" <?php disabled( $readonly ); ?> /></td>
						</tr>
						<tr>
							<th><label for="duration"><?php esc_html_e( 'Dauer (Stunden)', 'special-olympics-extension' ); ?></label></th>
							<td>
								<select id="duration" name="duration" <?php disabled( $readonly ); ?>>
									<option value="">— <?php esc_html_e( 'Auswählen', 'special-olympics-extension' ); ?> —</option>
									<?php foreach ( $duration_opts as $d ) : ?>
										<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $event['duration'] ?? '', $d ); ?>><?php echo esc_html( $d ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="sport_slug"><?php esc_html_e( 'Sport', 'special-olympics-extension' ); ?></label></th>
							<td>
								<select id="sport_slug" name="sport_slug" <?php disabled( $readonly ); ?>>
									<option value="">— <?php esc_html_e( 'Auswählen', 'special-olympics-extension' ); ?> —</option>
									<?php foreach ( (array) $sport_terms as $t ) : ?>
										<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $event['sport_slug'] ?? '', $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="event_type_slug"><?php esc_html_e( 'Event-Typ', 'special-olympics-extension' ); ?></label></th>
							<td>
								<select id="event_type_slug" name="event_type_slug" <?php disabled( $readonly ); ?>>
									<option value="">— <?php esc_html_e( 'Auswählen', 'special-olympics-extension' ); ?> —</option>
									<?php foreach ( (array) $event_type_terms as $t ) : ?>
										<option value="<?php echo esc_attr( $t->slug ); ?>" <?php selected( $event['event_type_slug'] ?? '', $t->slug ); ?>><?php echo esc_html( $t->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="notes"><?php esc_html_e( 'Bemerkungen', 'special-olympics-extension' ); ?></label></th>
							<td><input type="text" id="notes" name="notes" class="large-text" value="<?php echo esc_attr( $event['notes'] ?? '' ); ?>" <?php disabled( $readonly ); ?> /></td>
						</tr>
						<tr>
							<th><label for="bh_override"><?php esc_html_e( 'BH-Nummer (Überschreibung)', 'special-olympics-extension' ); ?></label></th>
							<td><input type="text" id="bh_override" name="bh_override" value="<?php echo esc_attr( $event['bh_override'] ?? '' ); ?>" <?php disabled( $readonly ); ?> /></td>
						</tr>
					</table>
				</div>
			</div>

			<div class="postbox">
				<div class="postbox-header"><h2><?php esc_html_e( 'Personen', 'special-olympics-extension' ); ?></h2></div>
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

			<p class="submit"><input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Speichern', 'special-olympics-extension' ); ?>" /></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=soe-events' ) ); ?>">&larr; <?php esc_html_e( 'Zurück zur Liste', 'special-olympics-extension' ); ?></a></p>
		</form>
	</div>
	<?php
}

function soe_event_sync_snapshot_for_event( $event_id ) {
	$event = soe_db_event_get( $event_id );
	if ( ! $event ) {
		return;
	}
	$persons = soe_db_event_get_persons( $event_id );
	$role_map = soe_get_role_filter_map();

	$sport_term = ! empty( $event['sport_slug'] ) ? get_term_by( 'slug', $event['sport_slug'], 'sport' ) : null;
	$type_term = ! empty( $event['event_type_slug'] ) ? get_term_by( 'slug', $event['event_type_slug'], 'event_type' ) : null;

	$participant_ids = array();
	foreach ( $persons as $role => $ids ) {
		$role_slug = isset( $role_map[ $role ] ) ? $role_map[ $role ] : $role;
		foreach ( (array) $ids as $pid ) {
			$participant_ids[] = (int) $pid;
			$entry = array(
				'event_id' => $event_id,
				'date' => $event['event_date'] ?? '',
				'title' => $event['title'] ?? '',
				'sport' => $sport_term ? $sport_term->name : ( $event['sport_slug'] ?? '' ),
				'event_type' => $type_term ? $type_term->name : ( $event['event_type_slug'] ?? '' ),
				'role' => $role_slug,
				'duration' => $event['duration'] ?? '',
				'link' => current_user_can( 'edit_others_posts' ) ? admin_url( 'admin.php?page=soe-event-edit&id=' . $event_id ) : '',
			);
			$snapshot = get_post_meta( $pid, SOE_EVENT_SNAPSHOT_META, true );
			if ( ! is_array( $snapshot ) ) { $snapshot = array(); }
			$snapshot = array_values( array_filter( $snapshot, function ( $e ) use ( $event_id ) {
				return ! ( isset( $e['event_id'] ) && (int) $e['event_id'] === (int) $event_id );
			} ) );
			$snapshot[] = $entry;
			update_post_meta( $pid, SOE_EVENT_SNAPSHOT_META, $snapshot );
		}
	}

	$all = get_posts( array( 'post_type' => 'mitglied', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => SOE_EVENT_SNAPSHOT_META ) );
	foreach ( $all as $mid ) {
		if ( in_array( (int) $mid, $participant_ids, true ) ) { continue; }
		$snapshot = get_post_meta( $mid, SOE_EVENT_SNAPSHOT_META, true );
		if ( ! is_array( $snapshot ) ) { continue; }
		$new_snapshot = array_values( array_filter( $snapshot, function ( $e ) use ( $event_id ) {
			return ! ( isset( $e['event_id'] ) && (int) $e['event_id'] === (int) $event_id );
		} ) );
		if ( count( $new_snapshot ) !== count( $snapshot ) ) {
			update_post_meta( $mid, SOE_EVENT_SNAPSHOT_META, $new_snapshot );
		}
	}
}

function soe_event_remove_from_all_snapshots( $event_id ) {
	global $wpdb;
	$pattern = '%"event_id";i:' . (int) $event_id . ';%';
	$ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
		SOE_EVENT_SNAPSHOT_META,
		$pattern
	) );
	$posts = array_map( 'intval', (array) $ids );
	foreach ( $posts as $mid ) {
		$snapshot = get_post_meta( $mid, SOE_EVENT_SNAPSHOT_META, true );
		if ( ! is_array( $snapshot ) ) { continue; }
		$new_snapshot = array_values( array_filter( $snapshot, function ( $e ) use ( $event_id ) {
			return ! ( isset( $e['event_id'] ) && (int) $e['event_id'] === (int) $event_id );
		} ) );
		if ( count( $new_snapshot ) !== count( $snapshot ) ) {
			update_post_meta( $mid, SOE_EVENT_SNAPSHOT_META, $new_snapshot );
		}
	}
}

function soe_add_mitglied_events_meta_box() {
	add_meta_box( 'soe_mitglied_events', __( 'Events', 'special-olympics-extension' ), 'soe_render_mitglied_events_meta_box', 'mitglied', 'normal', 'default' );
}

function soe_render_mitglied_events_meta_box( $post ) {
	if ( $post->post_type !== 'mitglied' ) { return; }
	$snapshot = get_post_meta( $post->ID, SOE_EVENT_SNAPSHOT_META, true );
	if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
		echo '<p>' . esc_html__( 'Keine Event-Teilnahmen.', 'special-olympics-extension' ) . '</p>';
		return;
	}
	usort( $snapshot, function ( $a, $b ) {
		$da = $a['date'] ?? ''; $db = $b['date'] ?? '';
		return strcmp( $db, $da );
	} );
	echo '<p class="description">' . esc_html__( 'Read-only: Teilnahmen werden beim Speichern von Events aktualisiert.', 'special-olympics-extension' ) . '</p>';
	echo '<table class="widefat striped"><thead><tr><th>Datum</th><th>Event</th><th>Sport</th><th>Rolle</th><th>Dauer</th><th></th></tr></thead><tbody>';
	foreach ( $snapshot as $e ) {
		$date = isset( $e['date'] ) ? $e['date'] : '';
		$date_display = $date ? date_i18n( 'd.m.Y', strtotime( $date ) ) : '';
		$title = isset( $e['title'] ) ? $e['title'] : '';
		$sport = isset( $e['sport'] ) ? $e['sport'] : '';
		$role = isset( $e['role'] ) ? $e['role'] : '';
		$duration = isset( $e['duration'] ) ? $e['duration'] : '';
		$link = isset( $e['link'] ) ? $e['link'] : '';
		$link_cell = $link ? '<a href="' . esc_url( $link ) . '">Bearbeiten</a>' : '';
		echo '<tr><td>' . esc_html( $date_display ) . '</td><td>' . esc_html( $title ) . '</td><td>' . esc_html( $sport ) . '</td><td>' . esc_html( $role ) . '</td><td>' . esc_html( $duration ) . '</td><td>' . $link_cell . '</td></tr>';
	}
	echo '</tbody></table>';
}

function soe_custom_events_scripts( $hook ) {
	if ( strpos( $hook, 'soe-event' ) === false ) { return; }
	$plugin_url = plugin_dir_url( dirname( __FILE__ ) );
	$flatpickr_version = '4.6.13';
	wp_enqueue_style( 'flatpickr', $plugin_url . 'assets/vendor/flatpickr/flatpickr.min.css', array(), $flatpickr_version );
	wp_enqueue_style( 'flatpickr-theme', $plugin_url . 'assets/css/flatpickr-theme.css', array( 'flatpickr' ), SOE_PLUGIN_VERSION );
	wp_enqueue_style( 'soe-person-picker', $plugin_url . 'assets/css/person-picker.css', array(), SOE_PLUGIN_VERSION );
	wp_enqueue_script( 'flatpickr', $plugin_url . 'assets/vendor/flatpickr/flatpickr.min.js', array(), $flatpickr_version, true );
	wp_enqueue_script( 'flatpickr-de', $plugin_url . 'assets/vendor/flatpickr/l10n-de.js', array( 'flatpickr' ), $flatpickr_version, true );
	wp_enqueue_script( 'soe-person-picker', $plugin_url . 'assets/js/person-picker.js', array( 'jquery' ), SOE_PLUGIN_VERSION, true );
	wp_enqueue_script( 'soe-admin-event', $plugin_url . 'assets/js/admin-event.js', array( 'jquery', 'soe-person-picker', 'flatpickr-de' ), SOE_PLUGIN_VERSION, true );
	wp_localize_script( 'soe-admin-event', 'soeEventAdmin', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'personSearchNonce' => wp_create_nonce( 'soe_person_search' ) ) );
}
