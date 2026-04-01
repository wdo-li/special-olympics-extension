<?php
/**
 * Custom database tables for Training (sessions, attendance) and Payroll (rows).
 *
 * Reduces memory usage by avoiding large serialized post meta; enables efficient queries.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Main trainings table (replaces CPT). */
define( 'SOE_TABLE_TRAININGS', 'soe_trainings' );
/** Training persons (role assignments). */
define( 'SOE_TABLE_TRAINING_PERSONS', 'soe_training_persons' );
/** Training sessions table. */
define( 'SOE_TABLE_TRAINING_SESSIONS', 'soe_training_sessions' );
/** Training attendance table. */
define( 'SOE_TABLE_TRAINING_ATTENDANCE', 'soe_training_attendance' );
/** Main events table (replaces CPT). */
define( 'SOE_TABLE_EVENTS', 'soe_events' );
/** Event persons (role assignments). */
define( 'SOE_TABLE_EVENT_PERSONS', 'soe_event_persons' );
/** Main payrolls table (replaces CPT). */
define( 'SOE_TABLE_PAYROLLS', 'soe_payrolls' );
/** Payroll rows table. */
define( 'SOE_TABLE_PAYROLL_ROWS', 'soe_payroll_rows' );
/** Payroll manual adjustments table (comment + amount, can be +/-). */
define( 'SOE_TABLE_PAYROLL_ADJUSTMENTS', 'soe_payroll_manual_adjustments' );
/** Attendance sync operation log for idempotency (offline sync). */
define( 'SOE_TABLE_ATTENDANCE_OPS', 'soe_attendance_ops' );

/** Database version for schema updates. */
define( 'SOE_DB_VERSION', 12 );

add_action( 'plugins_loaded', 'soe_maybe_create_tables', 5 );

/**
 * Creates or updates custom tables if version changed.
 */
function soe_maybe_create_tables() {
	$installed = (int) get_option( 'soe_db_version', 0 );
	if ( $installed >= SOE_DB_VERSION ) {
		return;
	}
	soe_create_tables();
	if ( $installed > 0 && $installed < 7 ) {
		soe_db_upgrade_to_7();
	}
	if ( $installed > 0 && $installed < 8 ) {
		soe_db_upgrade_to_8();
	}
	if ( $installed > 0 && $installed < 9 ) {
		soe_db_upgrade_to_9();
	}
	if ( $installed > 0 && $installed < 11 ) {
		soe_db_upgrade_to_11();
	}
	if ( $installed > 0 && $installed < 12 ) {
		soe_db_upgrade_to_12();
	}
	update_option( 'soe_db_version', SOE_DB_VERSION );
}

/**
 * Creates attendance operations table for idempotent offline sync.
 */
function soe_db_upgrade_to_12() {
	global $wpdb;
	$charset = $wpdb->get_charset_collate();
	$table = $wpdb->prefix . SOE_TABLE_ATTENDANCE_OPS;
	$sql = "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		op_id varchar(64) NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		training_id bigint(20) unsigned NOT NULL,
		session_date date NOT NULL,
		person_id bigint(20) unsigned NOT NULL,
		received_at datetime NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY op_id (op_id),
		KEY user_id (user_id),
		KEY training_id (training_id),
		KEY session_date (session_date),
		KEY person_id (person_id)
	) $charset;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Adds mail_sent_log column for payroll send history.
 */
function soe_db_upgrade_to_11() {
	global $wpdb;
	$table = soe_table_payrolls();
	$cols = $wpdb->get_col( "DESCRIBE $table", 0 );
	if ( ! in_array( 'mail_sent_log', $cols, true ) ) {
		$wpdb->query( "ALTER TABLE $table ADD COLUMN mail_sent_log longtext DEFAULT NULL AFTER mail_text_sent" );
	}
}

/**
 * Migrates payroll status 'geprüft' to 'draft' (status reduced to 2: draft, abgeschlossen).
 */
function soe_db_upgrade_to_9() {
	global $wpdb;
	$table = soe_table_payrolls();
	$wpdb->query( "UPDATE $table SET status = 'draft' WHERE status = 'geprüft'" );
}

/**
 * Upgrades payroll_rows table: add training_id and event_id columns (v6 → v7).
 */
function soe_db_upgrade_to_7() {
	global $wpdb;
	$table = soe_table_payroll_rows();
	$cols = $wpdb->get_col( "DESCRIBE $table", 0 );
	if ( ! in_array( 'training_id', $cols, true ) ) {
		$wpdb->query( "ALTER TABLE $table ADD COLUMN training_id bigint(20) unsigned DEFAULT NULL AFTER sport_or_event_label" );
	}
	if ( ! in_array( 'event_id', $cols, true ) ) {
		$wpdb->query( "ALTER TABLE $table ADD COLUMN event_id bigint(20) unsigned DEFAULT NULL AFTER training_id" );
	}
}

/**
 * Creates the payroll manual adjustments table (v7 → v8).
 */
function soe_db_upgrade_to_8() {
	global $wpdb;
	$charset = $wpdb->get_charset_collate();
	$table = $wpdb->prefix . SOE_TABLE_PAYROLL_ADJUSTMENTS;
	$sql = "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		payroll_id bigint(20) unsigned NOT NULL,
		comment text NOT NULL,
		amount decimal(10,2) NOT NULL DEFAULT 0.00,
		sort_order int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY payroll_id (payroll_id)
	) $charset;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/**
 * Creates custom tables using dbDelta.
 */
function soe_create_tables() {
	global $wpdb;
	$charset = $wpdb->get_charset_collate();
	$trainings = $wpdb->prefix . SOE_TABLE_TRAININGS;
	$training_persons = $wpdb->prefix . SOE_TABLE_TRAINING_PERSONS;
	$sessions = $wpdb->prefix . SOE_TABLE_TRAINING_SESSIONS;
	$attendance = $wpdb->prefix . SOE_TABLE_TRAINING_ATTENDANCE;
	$events = $wpdb->prefix . SOE_TABLE_EVENTS;
	$event_persons = $wpdb->prefix . SOE_TABLE_EVENT_PERSONS;
	$payrolls = $wpdb->prefix . SOE_TABLE_PAYROLLS;
	$payroll_rows = $wpdb->prefix . SOE_TABLE_PAYROLL_ROWS;
	$payroll_adjustments = $wpdb->prefix . SOE_TABLE_PAYROLL_ADJUSTMENTS;
	$attendance_ops = $wpdb->prefix . SOE_TABLE_ATTENDANCE_OPS;

	$sql_trainings = "CREATE TABLE $trainings (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		title varchar(255) NOT NULL DEFAULT '',
		start_date date DEFAULT NULL,
		end_date date DEFAULT NULL,
		weekdays varchar(20) DEFAULT NULL,
		time time DEFAULT NULL,
		duration varchar(20) NOT NULL DEFAULT '',
		excluded_dates text,
		notes text,
		bh_override varchar(20) NOT NULL DEFAULT '',
		completed tinyint(1) NOT NULL DEFAULT 0,
		sport_slug varchar(50) NOT NULL DEFAULT '',
		created_at datetime DEFAULT NULL,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY completed (completed),
		KEY sport_slug (sport_slug)
	) $charset;";

	$sql_training_persons = "CREATE TABLE $training_persons (
		training_id bigint(20) unsigned NOT NULL,
		person_id bigint(20) unsigned NOT NULL,
		role varchar(30) NOT NULL DEFAULT '',
		PRIMARY KEY (training_id, person_id, role),
		KEY person_id (person_id),
		KEY training_id (training_id)
	) $charset;";

	$sql_sessions = "CREATE TABLE $sessions (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		training_id bigint(20) unsigned NOT NULL,
		session_date date NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY training_date (training_id, session_date),
		KEY training_id (training_id)
	) $charset;";

	$sql_attendance = "CREATE TABLE $attendance (
		training_id bigint(20) unsigned NOT NULL,
		session_date date NOT NULL,
		person_id bigint(20) unsigned NOT NULL,
		attended tinyint(1) NOT NULL DEFAULT 0,
		PRIMARY KEY (training_id, session_date, person_id),
		KEY training_id (training_id),
		KEY person_id (person_id)
	) $charset;";

	$sql_events = "CREATE TABLE $events (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		title varchar(255) NOT NULL DEFAULT '',
		event_date date DEFAULT NULL,
		duration varchar(20) NOT NULL DEFAULT '',
		notes text,
		sport_slug varchar(50) NOT NULL DEFAULT '',
		event_type_slug varchar(50) NOT NULL DEFAULT '',
		bh_override varchar(20) NOT NULL DEFAULT '',
		created_at datetime DEFAULT NULL,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY event_date (event_date),
		KEY sport_slug (sport_slug),
		KEY event_type_slug (event_type_slug)
	) $charset;";

	$sql_event_persons = "CREATE TABLE $event_persons (
		event_id bigint(20) unsigned NOT NULL,
		person_id bigint(20) unsigned NOT NULL,
		role varchar(30) NOT NULL DEFAULT '',
		PRIMARY KEY (event_id, person_id, role),
		KEY person_id (person_id),
		KEY event_id (event_id)
	) $charset;";

	$sql_payrolls = "CREATE TABLE $payrolls (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		person_id bigint(20) unsigned NOT NULL DEFAULT 0,
		period_start date DEFAULT NULL,
		period_end date DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'draft',
		pdf_path varchar(255) NOT NULL DEFAULT '',
		pdf_generated_at datetime DEFAULT NULL,
		mail_sent_at datetime DEFAULT NULL,
		mail_text_sent longtext,
		mail_sent_log longtext,
		created_at datetime DEFAULT NULL,
		updated_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		KEY person_id (person_id),
		KEY status (status)
	) $charset;";

	$sql_payroll_rows = "CREATE TABLE $payroll_rows (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		payroll_id bigint(20) unsigned NOT NULL,
		row_type varchar(20) NOT NULL DEFAULT 'training',
		sport_or_event_label varchar(255) NOT NULL DEFAULT '',
		training_id bigint(20) unsigned DEFAULT NULL,
		event_id bigint(20) unsigned DEFAULT NULL,
		notes text,
		qualification varchar(50) NOT NULL DEFAULT '',
		duration varchar(50) NOT NULL DEFAULT '',
		ref_no varchar(20) NOT NULL DEFAULT '',
		quantity int(11) NOT NULL DEFAULT 0,
		chf_per_hour varchar(20) NOT NULL DEFAULT '',
		chf_amount decimal(10,2) NOT NULL DEFAULT 0.00,
		session_dates_json longtext,
		sort_order int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY payroll_id (payroll_id)
	) $charset;";

	$sql_payroll_adjustments = "CREATE TABLE $payroll_adjustments (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		payroll_id bigint(20) unsigned NOT NULL,
		comment text NOT NULL,
		amount decimal(10,2) NOT NULL DEFAULT 0.00,
		sort_order int(11) NOT NULL DEFAULT 0,
		PRIMARY KEY (id),
		KEY payroll_id (payroll_id)
	) $charset;";

	$sql_attendance_ops = "CREATE TABLE $attendance_ops (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		op_id varchar(64) NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		training_id bigint(20) unsigned NOT NULL,
		session_date date NOT NULL,
		person_id bigint(20) unsigned NOT NULL,
		received_at datetime NOT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY op_id (op_id),
		KEY user_id (user_id),
		KEY training_id (training_id),
		KEY session_date (session_date),
		KEY person_id (person_id)
	) $charset;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_trainings );
	dbDelta( $sql_training_persons );
	dbDelta( $sql_sessions );
	dbDelta( $sql_attendance );
	dbDelta( $sql_events );
	dbDelta( $sql_event_persons );
	dbDelta( $sql_payrolls );
	dbDelta( $sql_payroll_rows );
	dbDelta( $sql_payroll_adjustments );
	dbDelta( $sql_attendance_ops );
}

/**
 * Migrates old weekday column to new weekdays column.
 */
/**
 * Returns table names (with prefix).
 *
 * @return string
 */
function soe_table_trainings() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_TRAININGS;
}
function soe_table_training_persons() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_TRAINING_PERSONS;
}
function soe_table_training_sessions() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_TRAINING_SESSIONS;
}
function soe_table_training_attendance() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_TRAINING_ATTENDANCE;
}
function soe_table_events() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_EVENTS;
}
function soe_table_event_persons() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_EVENT_PERSONS;
}
function soe_table_payrolls() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_PAYROLLS;
}
function soe_table_payroll_rows() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_PAYROLL_ROWS;
}
function soe_table_payroll_adjustments() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_PAYROLL_ADJUSTMENTS;
}
function soe_table_attendance_ops() {
	global $wpdb;
	return $wpdb->prefix . SOE_TABLE_ATTENDANCE_OPS;
}

// --- Trainings CRUD ---

function soe_db_training_get( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . soe_table_trainings() . " WHERE id = %d", (int) $id ), ARRAY_A );
}

function soe_db_training_insert( $data ) {
	global $wpdb;
	$now = current_time( 'mysql' );
	$defaults = array(
		'title' => '', 'start_date' => null, 'end_date' => null, 'weekdays' => null, 'time' => null,
		'duration' => '', 'excluded_dates' => '', 'notes' => '', 'bh_override' => '',
		'completed' => 0, 'sport_slug' => '', 'created_at' => $now, 'updated_at' => $now,
	);
	$row = array_intersect_key( wp_parse_args( $data, $defaults ), $defaults );
	return $wpdb->insert( soe_table_trainings(), $row, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ) ) ? (int) $wpdb->insert_id : 0;
}

function soe_db_training_update( $id, $data ) {
	global $wpdb;
	$data['updated_at'] = current_time( 'mysql' );
	$formats = array( 'title' => '%s', 'start_date' => '%s', 'end_date' => '%s', 'weekdays' => '%s', 'time' => '%s', 'duration' => '%s', 'excluded_dates' => '%s', 'notes' => '%s', 'bh_override' => '%s', 'completed' => '%d', 'sport_slug' => '%s', 'updated_at' => '%s' );
	$updates = array_intersect_key( $data, $formats );
	return $wpdb->update( soe_table_trainings(), $updates, array( 'id' => (int) $id ), array_values( array_intersect_key( $formats, $updates ) ), array( '%d' ) ) !== false;
}

function soe_db_training_delete( $id ) {
	global $wpdb;
	$id = (int) $id;
	$wpdb->query( 'START TRANSACTION' );
	$ok = true;
	if ( false === $wpdb->delete( soe_table_training_persons(), array( 'training_id' => $id ), array( '%d' ) ) ) {
		$ok = false;
	}
	if ( $ok && false === $wpdb->delete( soe_table_training_sessions(), array( 'training_id' => $id ), array( '%d' ) ) ) {
		$ok = false;
	}
	if ( $ok && false === $wpdb->delete( soe_table_training_attendance(), array( 'training_id' => $id ), array( '%d' ) ) ) {
		$ok = false;
	}
	if ( $ok && false === $wpdb->delete( soe_table_trainings(), array( 'id' => $id ), array( '%d' ) ) ) {
		$ok = false;
	}
	if ( $ok ) {
		$wpdb->query( 'COMMIT' );
		return true;
	}
	$wpdb->query( 'ROLLBACK' );
	if ( function_exists( 'soe_debug_log' ) ) {
		soe_debug_log( 'Training delete failed', array( 'training_id' => $id, 'db_error' => $wpdb->last_error ) );
	}
	return false;
}

function soe_db_training_get_persons( $training_id ) {
	global $wpdb;
	$results = $wpdb->get_results( $wpdb->prepare( "SELECT person_id, role FROM " . soe_table_training_persons() . " WHERE training_id = %d", (int) $training_id ), ARRAY_A );
	$out = array();
	foreach ( (array) $results as $r ) {
		$role = $r['role'];
		if ( ! isset( $out[ $role ] ) ) { $out[ $role ] = array(); }
		$out[ $role ][] = (int) $r['person_id'];
	}
	return $out;
}

function soe_db_training_save_persons( $training_id, $persons ) {
	global $wpdb;
	$training_id = (int) $training_id;
	$wpdb->delete( soe_table_training_persons(), array( 'training_id' => $training_id ), array( '%d' ) );
	$role_keys = soe_get_training_role_keys();
	foreach ( $role_keys as $role ) {
		$ids = isset( $persons[ $role ] ) && is_array( $persons[ $role ] ) ? $persons[ $role ] : array();
		foreach ( $ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid ) {
				$wpdb->insert( soe_table_training_persons(), array( 'training_id' => $training_id, 'person_id' => $pid, 'role' => $role ), array( '%d', '%d', '%s' ) );
			}
		}
	}
	return true;
}

function soe_db_training_list( $args = array() ) {
	global $wpdb;
	$defaults = array( 'completed' => null, 'sport_slug' => '', 'limit' => 100, 'offset' => 0, 'hauptleiter_person_id' => 0, 'person_id_roles' => null );
	$args = wp_parse_args( $args, $defaults );
	$where = array( '1=1' );
	if ( $args['completed'] !== null ) {
		$where[] = $wpdb->prepare( 't.completed = %d', $args['completed'] ? 1 : 0 );
	}
	if ( $args['sport_slug'] !== '' ) {
		$where[] = $wpdb->prepare( 't.sport_slug = %s', $args['sport_slug'] );
	}
	if ( $args['hauptleiter_person_id'] ) {
		$where[] = $wpdb->prepare( 'EXISTS (SELECT 1 FROM ' . soe_table_training_persons() . ' tp WHERE tp.training_id = t.id AND tp.person_id = %d AND tp.role = "hauptleiter")', $args['hauptleiter_person_id'] );
	}
	if ( ! empty( $args['person_id_roles'] ) && is_array( $args['person_id_roles'] ) ) {
		$person_id = isset( $args['person_id_roles']['person_id'] ) ? (int) $args['person_id_roles']['person_id'] : 0;
		$roles = isset( $args['person_id_roles']['roles'] ) && is_array( $args['person_id_roles']['roles'] ) ? $args['person_id_roles']['roles'] : array();
		$roles = array_filter( array_map( 'sanitize_key', $roles ) );
		if ( $person_id && ! empty( $roles ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $roles ), '%s' ) );
			$where[] = $wpdb->prepare(
				'EXISTS (SELECT 1 FROM ' . soe_table_training_persons() . ' tp WHERE tp.training_id = t.id AND tp.person_id = %d AND tp.role IN (' . $placeholders . '))',
				array_merge( array( $person_id ), $roles )
			);
		}
	}
	$sql = "SELECT t.* FROM " . soe_table_trainings() . " t WHERE " . implode( ' AND ', $where ) . " ORDER BY t.start_date DESC, t.id DESC LIMIT " . (int) $args['limit'] . " OFFSET " . (int) $args['offset'];
	return $wpdb->get_results( $sql, ARRAY_A );
}

// --- Events CRUD ---

function soe_db_event_get( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . soe_table_events() . " WHERE id = %d", (int) $id ), ARRAY_A );
}

function soe_db_event_insert( $data ) {
	global $wpdb;
	$now = current_time( 'mysql' );
	$defaults = array( 'title' => '', 'event_date' => null, 'duration' => '', 'notes' => '', 'sport_slug' => '', 'event_type_slug' => '', 'bh_override' => '', 'created_at' => $now, 'updated_at' => $now );
	$row = wp_parse_args( $data, $defaults );
	return $wpdb->insert( soe_table_events(), $row, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ) ? (int) $wpdb->insert_id : 0;
}

function soe_db_event_update( $id, $data ) {
	global $wpdb;
	$data['updated_at'] = current_time( 'mysql' );
	$formats = array( 'title' => '%s', 'event_date' => '%s', 'duration' => '%s', 'notes' => '%s', 'sport_slug' => '%s', 'event_type_slug' => '%s', 'bh_override' => '%s', 'updated_at' => '%s' );
	$updates = array_intersect_key( $data, $formats );
	return $wpdb->update( soe_table_events(), $updates, array( 'id' => (int) $id ), array_values( array_intersect_key( $formats, $updates ) ), array( '%d' ) ) !== false;
}

function soe_db_event_delete( $id ) {
	global $wpdb;
	$id = (int) $id;
	$wpdb->query( 'START TRANSACTION' );
	$ok = true;
	if ( false === $wpdb->delete( soe_table_event_persons(), array( 'event_id' => $id ), array( '%d' ) ) ) {
		$ok = false;
	}
	if ( $ok && false === $wpdb->delete( soe_table_events(), array( 'id' => $id ), array( '%d' ) ) ) {
		$ok = false;
	}
	if ( $ok ) {
		$wpdb->query( 'COMMIT' );
		return true;
	}
	$wpdb->query( 'ROLLBACK' );
	if ( function_exists( 'soe_debug_log' ) ) {
		soe_debug_log( 'Event delete failed', array( 'event_id' => $id, 'db_error' => $wpdb->last_error ) );
	}
	return false;
}

function soe_db_event_get_persons( $event_id ) {
	global $wpdb;
	$results = $wpdb->get_results( $wpdb->prepare( "SELECT person_id, role FROM " . soe_table_event_persons() . " WHERE event_id = %d", (int) $event_id ), ARRAY_A );
	$role_map = array_flip( soe_get_role_filter_map() );
	$out = array();
	foreach ( (array) $results as $r ) {
		$role = isset( $role_map[ $r['role'] ] ) ? $role_map[ $r['role'] ] : $r['role'];
		if ( ! isset( $out[ $role ] ) ) { $out[ $role ] = array(); }
		$out[ $role ][] = (int) $r['person_id'];
	}
	return $out;
}

function soe_db_event_save_persons( $event_id, $persons ) {
	global $wpdb;
	$event_id = (int) $event_id;
	$role_map = soe_get_role_filter_map();
	$wpdb->delete( soe_table_event_persons(), array( 'event_id' => $event_id ), array( '%d' ) );
	$role_keys = array_keys( $role_map );
	foreach ( $role_keys as $role ) {
		$ids = isset( $persons[ $role ] ) && is_array( $persons[ $role ] ) ? $persons[ $role ] : array();
		$role_slug = $role_map[ $role ];
		foreach ( $ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid ) {
				$wpdb->insert( soe_table_event_persons(), array( 'event_id' => $event_id, 'person_id' => $pid, 'role' => $role_slug ), array( '%d', '%d', '%s' ) );
			}
		}
	}
	return true;
}

function soe_db_event_list( $args = array() ) {
	global $wpdb;
	$defaults = array( 'limit' => 100, 'offset' => 0, 'sport_slug' => '', 'event_type_slug' => '', 'date_from' => '', 'date_to' => '', 'search' => '', 'person_id' => 0 );
	$args = wp_parse_args( $args, $defaults );
	$where = array( '1=1' );
	$values = array();
	if ( ! empty( $args['sport_slug'] ) ) {
		$where[] = 'sport_slug = %s';
		$values[] = $args['sport_slug'];
	}
	if ( ! empty( $args['event_type_slug'] ) ) {
		$where[] = 'event_type_slug = %s';
		$values[] = $args['event_type_slug'];
	}
	if ( ! empty( $args['date_from'] ) ) {
		$where[] = 'event_date >= %s';
		$values[] = $args['date_from'];
	}
	if ( ! empty( $args['date_to'] ) ) {
		$where[] = 'event_date <= %s';
		$values[] = $args['date_to'];
	}
	if ( ! empty( $args['search'] ) ) {
		$where[] = '( title LIKE %s OR notes LIKE %s )';
		$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$values[] = $like;
		$values[] = $like;
	}
	// Filter by participant (person_id = mitglied post ID). Only when > 0 so admins (no person_id) see all events.
	if ( ! empty( $args['person_id'] ) ) {
		$ep_table = soe_table_event_persons();
		$ev_table = soe_table_events();
		$where[]  = $wpdb->prepare( 'EXISTS (SELECT 1 FROM ' . $ep_table . ' ep WHERE ep.event_id = ' . $ev_table . '.id AND ep.person_id = %d)', (int) $args['person_id'] );
	}
	$sql = "SELECT * FROM " . soe_table_events() . " WHERE " . implode( ' AND ', $where ) . " ORDER BY event_date DESC, id DESC LIMIT " . (int) $args['limit'] . " OFFSET " . (int) $args['offset'];
	if ( ! empty( $values ) ) {
		$sql = $wpdb->prepare( $sql, $values );
	}
	return $wpdb->get_results( $sql, ARRAY_A );
}

// --- Payrolls CRUD ---

function soe_db_payroll_get( $id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . soe_table_payrolls() . " WHERE id = %d", (int) $id ), ARRAY_A );
}

function soe_db_payroll_insert( $data ) {
	global $wpdb;
	$now = current_time( 'mysql' );
	$defaults = array( 'person_id' => 0, 'period_start' => null, 'period_end' => null, 'status' => 'draft', 'pdf_path' => '', 'pdf_generated_at' => null, 'mail_sent_at' => null, 'mail_text_sent' => null, 'mail_sent_log' => null, 'created_at' => $now, 'updated_at' => $now );
	$row = array_intersect_key( wp_parse_args( $data, $defaults ), $defaults );
	return $wpdb->insert( soe_table_payrolls(), $row, array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) ) ? (int) $wpdb->insert_id : 0;
}

function soe_db_payroll_update( $id, $data ) {
	global $wpdb;
	$data['updated_at'] = current_time( 'mysql' );
	$formats = array( 'person_id' => '%d', 'period_start' => '%s', 'period_end' => '%s', 'status' => '%s', 'pdf_path' => '%s', 'pdf_generated_at' => '%s', 'mail_sent_at' => '%s', 'mail_text_sent' => '%s', 'mail_sent_log' => '%s', 'updated_at' => '%s' );
	$updates = array_intersect_key( $data, $formats );
	return $wpdb->update( soe_table_payrolls(), $updates, array( 'id' => (int) $id ), array_values( array_intersect_key( $formats, $updates ) ), array( '%d' ) ) !== false;
}

function soe_db_payroll_delete( $id ) {
	global $wpdb;
	$id = (int) $id;
	$row = soe_db_payroll_get( $id );
	if ( $row && ! empty( $row['pdf_path'] ) && function_exists( 'soe_payroll_pdf_path_to_local' ) ) {
		$local = soe_payroll_pdf_path_to_local( $row['pdf_path'] );
		if ( $local && file_exists( $local ) && is_writable( $local ) ) {
			@unlink( $local );
		}
	}
	$wpdb->query( 'START TRANSACTION' );
	$ok = true;
	if ( false === $wpdb->delete( soe_table_payroll_rows(), array( 'payroll_id' => $id ), array( '%d' ) ) ) {
		$ok = false;
	}
	if ( $ok && false === $wpdb->delete( soe_table_payroll_adjustments(), array( 'payroll_id' => $id ), array( '%d' ) ) ) {
		$ok = false;
	}
	if ( $ok && false === $wpdb->delete( soe_table_payrolls(), array( 'id' => $id ), array( '%d' ) ) ) {
		$ok = false;
	}
	if ( $ok ) {
		$wpdb->query( 'COMMIT' );
		return true;
	}
	$wpdb->query( 'ROLLBACK' );
	if ( function_exists( 'soe_debug_log' ) ) {
		soe_debug_log( 'Payroll delete failed', array( 'payroll_id' => $id, 'db_error' => $wpdb->last_error ) );
	}
	return false;
}

/**
 * Finds overlapping payrolls for a person (period_start <= $end AND period_end >= $start).
 *
 * @param int    $person_id Person (mitglied) ID.
 * @param string $start     Period start Y-m-d.
 * @param string $end       Period end Y-m-d.
 * @param int    $exclude_id Optional payroll ID to exclude (e.g. when editing).
 * @return array Rows with overlapping periods.
 */
function soe_db_payroll_find_overlapping( $person_id, $start, $end, $exclude_id = 0 ) {
	global $wpdb;
	$table = soe_table_payrolls();
	$person_id = (int) $person_id;
	$exclude_id = (int) $exclude_id;
	$sql = "SELECT * FROM $table WHERE person_id = %d AND period_start <= %s AND period_end >= %s";
	$values = array( $person_id, $end, $start );
	if ( $exclude_id > 0 ) {
		$sql .= " AND id != %d";
		$values[] = $exclude_id;
	}
	$sql .= " ORDER BY period_start ASC";
	return $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A );
}

function soe_db_payroll_list( $args = array() ) {
	global $wpdb;
	$defaults = array( 'person_id' => 0, 'status' => '', 'status_in' => array(), 'limit' => 200, 'offset' => 0 );
	$args = wp_parse_args( $args, $defaults );
	$where = '1=1';
	$values = array();
	if ( $args['person_id'] ) {
		$where .= ' AND person_id = %d';
		$values[] = $args['person_id'];
	}
	if ( $args['status'] !== '' ) {
		$where .= ' AND status = %s';
		$values[] = $args['status'];
	}
	if ( ! empty( $args['status_in'] ) && is_array( $args['status_in'] ) ) {
		$placeholders = implode( ', ', array_fill( 0, count( $args['status_in'] ), '%s' ) );
		$where .= ' AND status IN (' . $placeholders . ')';
		$values = array_merge( $values, $args['status_in'] );
	}
	$sql = "SELECT * FROM " . soe_table_payrolls() . " WHERE $where ORDER BY period_end DESC, id DESC LIMIT " . (int) $args['limit'] . " OFFSET " . (int) $args['offset'];
	if ( ! empty( $values ) ) {
		$sql = $wpdb->prepare( $sql, $values );
	}
	return $wpdb->get_results( $sql, ARRAY_A );
}

// --- Training sessions (DB) ---

/**
 * Gets session dates for a training from the database.
 *
 * @param int $training_id Training post ID.
 * @return array Array of Y-m-d strings.
 */
function soe_db_training_get_sessions( $training_id ) {
	global $wpdb;
	$table = soe_table_training_sessions();
	$training_id = (int) $training_id;
	$results = $wpdb->get_col( $wpdb->prepare(
		"SELECT session_date FROM $table WHERE training_id = %d ORDER BY session_date ASC",
		$training_id
	) );
	return is_array( $results ) ? array_map( 'strval', $results ) : array();
}

/**
 * Saves session dates for a training (replaces existing).
 *
 * @param int   $training_id Training post ID.
 * @param array $dates       Array of Y-m-d strings.
 * @return bool
 */
function soe_db_training_save_sessions( $training_id, $dates ) {
	global $wpdb;
	$table = soe_table_training_sessions();
	$training_id = (int) $training_id;
	$wpdb->delete( $table, array( 'training_id' => $training_id ), array( '%d' ) );
	if ( empty( $dates ) ) {
		return true;
	}
	$values = array();
	foreach ( array_unique( array_map( 'strval', $dates ) ) as $d ) {
		if ( strlen( $d ) === 10 ) {
			$values[] = $wpdb->prepare( '(%d, %s)', $training_id, $d );
		}
	}
	if ( empty( $values ) ) {
		return true;
	}
	$sql = "INSERT INTO $table (training_id, session_date) VALUES " . implode( ', ', $values );
	return (bool) $wpdb->query( $sql );
}

/**
 * Adds a single session date (if not already present).
 *
 * @param int    $training_id Training ID.
 * @param string $date        Y-m-d.
 * @return bool True if added or already exists.
 */
function soe_db_training_add_session( $training_id, $date ) {
	global $wpdb;
	$table = soe_table_training_sessions();
	$training_id = (int) $training_id;
	if ( strlen( $date ) !== 10 ) {
		return false;
	}
	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT 1 FROM $table WHERE training_id = %d AND session_date = %s",
		$training_id,
		$date
	) );
	if ( $exists ) {
		return true;
	}
	return (bool) $wpdb->insert( $table, array( 'training_id' => $training_id, 'session_date' => $date ), array( '%d', '%s' ) );
}

/**
 * Removes a single session date.
 *
 * @param int    $training_id Training ID.
 * @param string $date        Y-m-d.
 * @return bool
 */
function soe_db_training_remove_session( $training_id, $date ) {
	global $wpdb;
	$table = soe_table_training_sessions();
	if ( strlen( $date ) !== 10 ) {
		return false;
	}
	return (bool) $wpdb->delete( $table, array( 'training_id' => (int) $training_id, 'session_date' => $date ), array( '%d', '%s' ) );
}

// --- Training attendance (DB) ---

/**
 * Gets attendance for a training from the database.
 *
 * @param int $training_id Training post ID.
 * @return array [ session_date => [ person_id => 1|0 ] ]
 */
function soe_db_training_get_attendance( $training_id ) {
	global $wpdb;
	$table = soe_table_training_attendance();
	$training_id = (int) $training_id;
	$results = $wpdb->get_results( $wpdb->prepare(
		"SELECT session_date, person_id, attended FROM $table WHERE training_id = %d",
		$training_id
	), ARRAY_A );
	$out = array();
	if ( is_array( $results ) ) {
		foreach ( $results as $row ) {
			$d = $row['session_date'];
			if ( ! isset( $out[ $d ] ) ) {
				$out[ $d ] = array();
			}
			$out[ $d ][ (int) $row['person_id'] ] = (int) $row['attended'];
		}
	}
	return $out;
}

/**
 * Sets a single attendance record (insert or update).
 *
 * @param int    $training_id Training post ID.
 * @param string $session_date Y-m-d.
 * @param int    $person_id    Mitglied post ID.
 * @param int    $attended    0 or 1.
 * @return bool
 */
function soe_db_training_set_attendance( $training_id, $session_date, $person_id, $attended ) {
	global $wpdb;
	$table = soe_table_training_attendance();
	$training_id = (int) $training_id;
	$person_id = (int) $person_id;
	$attended = ( (int) $attended ) === 1 ? 1 : 0;
	if ( strlen( $session_date ) !== 10 ) {
		return false;
	}
	$written = $wpdb->replace(
		$table,
		array(
			'training_id'   => $training_id,
			'session_date'  => $session_date,
			'person_id'     => $person_id,
			'attended'      => $attended,
		),
		array( '%d', '%s', '%d', '%d' )
	);
	if ( false === $written ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log(
				'Attendance write failed',
				array(
					'training_id'  => $training_id,
					'session_date' => $session_date,
					'person_id'    => $person_id,
					'attended'     => $attended,
					'db_error'     => isset( $wpdb->last_error ) ? $wpdb->last_error : '',
				)
			);
		}
		return false;
	}
	return $written !== false;
}

/**
 * Checks whether an attendance operation ID was already processed.
 *
 * @param string $op_id Operation ID.
 * @return bool
 */
function soe_db_attendance_op_exists( $op_id ) {
	global $wpdb;
	if ( ! is_string( $op_id ) || $op_id === '' ) {
		return false;
	}
	$table = soe_table_attendance_ops();
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT 1 FROM $table WHERE op_id = %s",
			$op_id
		)
	);
	return (bool) $exists;
}

/**
 * Marks an attendance operation ID as processed.
 *
 * @param string $op_id        Operation ID.
 * @param int    $user_id      User ID resolved from attendance token.
 * @param int    $training_id  Training ID.
 * @param string $session_date Session date (Y-m-d).
 * @param int    $person_id    Person ID.
 * @return bool
 */
function soe_db_attendance_op_mark_processed( $op_id, $user_id, $training_id, $session_date, $person_id ) {
	global $wpdb;
	if ( ! is_string( $op_id ) || $op_id === '' ) {
		return false;
	}
	$table = soe_table_attendance_ops();
	$inserted = $wpdb->insert(
		$table,
		array(
			'op_id'        => $op_id,
			'user_id'      => (int) $user_id,
			'training_id'  => (int) $training_id,
			'session_date' => $session_date,
			'person_id'    => (int) $person_id,
			'received_at'  => current_time( 'mysql' ),
		),
		array( '%s', '%d', '%d', '%s', '%d', '%s' )
	);
	return (bool) $inserted;
}

// --- Payroll rows (DB) ---

/**
 * Gets all rows for a payroll from the database.
 *
 * @param int $payroll_id Payroll post ID.
 * @return array Array of row arrays (with keys sport_or_event_label, notes, qualification, duration, ref_no, quantity, chf_per_hour, chf_amount, session_dates_json, row_type).
 */
function soe_db_payroll_get_rows( $payroll_id ) {
	global $wpdb;
	$table = soe_table_payroll_rows();
	$payroll_id = (int) $payroll_id;
	$results = $wpdb->get_results( $wpdb->prepare(
		"SELECT row_type, sport_or_event_label, training_id, event_id, notes, qualification, duration, ref_no, quantity, chf_per_hour, chf_amount, session_dates_json FROM $table WHERE payroll_id = %d ORDER BY sort_order ASC, id ASC",
		$payroll_id
	), ARRAY_A );
	$out = array();
	if ( is_array( $results ) ) {
		foreach ( $results as $row ) {
			$r = array(
				'sport'          => $row['sport_or_event_label'],
				'event_title'    => $row['row_type'] === 'event' ? $row['sport_or_event_label'] : '',
				'training_id'    => ! empty( $row['training_id'] ) ? (int) $row['training_id'] : null,
				'event_id'       => ! empty( $row['event_id'] ) ? (int) $row['event_id'] : null,
				'notes'          => $row['notes'],
				'qualification'  => $row['qualification'],
				'duration'       => $row['duration'],
				'ref_no'         => $row['ref_no'],
				'quantity'       => (int) $row['quantity'],
				'chf_per_hour'   => $row['chf_per_hour'],
				'chf_amount'     => (float) $row['chf_amount'],
				'session_dates'  => array(),
			);
			if ( ! empty( $row['session_dates_json'] ) ) {
				$decoded = json_decode( $row['session_dates_json'], true );
				if ( is_array( $decoded ) ) {
					$r['session_dates'] = $decoded;
				}
			}
			$out[] = $r;
		}
	}
	return $out;
}

/**
 * Saves payroll rows (replaces all rows for this payroll).
 *
 * @param int   $payroll_id   Payroll post ID.
 * @param array $training_rows Array of row arrays from soe_payroll_collect_training_rows.
 * @param array $event_rows    Array of row arrays from soe_payroll_collect_event_rows.
 * @return bool
 */
function soe_db_payroll_save_rows( $payroll_id, $training_rows, $event_rows ) {
	global $wpdb;
	$table = soe_table_payroll_rows();
	$payroll_id = (int) $payroll_id;
	$wpdb->query( 'START TRANSACTION' );
	if ( false === $wpdb->delete( $table, array( 'payroll_id' => $payroll_id ), array( '%d' ) ) ) {
		$wpdb->query( 'ROLLBACK' );
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'Payroll rows delete failed', array( 'payroll_id' => $payroll_id, 'db_error' => $wpdb->last_error ) );
		}
		return false;
	}
	$sort = 0;
	$rows = array_merge(
		array_map( function ( $r ) use ( &$sort ) {
			return array( 'training', $r, $sort++ );
		}, is_array( $training_rows ) ? $training_rows : array() ),
		array_map( function ( $r ) use ( &$sort ) {
			return array( 'event', $r, $sort++ );
		}, is_array( $event_rows ) ? $event_rows : array() )
	);
	foreach ( $rows as $item ) {
		list( $row_type, $r, $order ) = $item;
		$label = ( $row_type === 'event' && isset( $r['event_title'] ) && $r['event_title'] !== '' )
			? $r['event_title']
			: ( isset( $r['sport'] ) ? $r['sport'] : ( isset( $r['event_title'] ) ? $r['event_title'] : '' ) );
		$session_dates = isset( $r['session_dates'] ) && is_array( $r['session_dates'] ) ? $r['session_dates'] : array();
		$training_id = isset( $r['training_id'] ) && $r['training_id'] ? (int) $r['training_id'] : null;
		$event_id = isset( $r['event_id'] ) && $r['event_id'] ? (int) $r['event_id'] : null;
		$inserted = $wpdb->insert(
			$table,
			array(
				'payroll_id'           => $payroll_id,
				'row_type'             => $row_type,
				'sport_or_event_label' => $label,
				'training_id'          => $training_id,
				'event_id'             => $event_id,
				'notes'                => isset( $r['notes'] ) ? $r['notes'] : '',
				'qualification'        => isset( $r['qualification'] ) ? $r['qualification'] : '',
				'duration'             => isset( $r['duration'] ) ? $r['duration'] : '',
				'ref_no'               => isset( $r['ref_no'] ) ? $r['ref_no'] : '',
				'quantity'             => isset( $r['quantity'] ) ? (int) $r['quantity'] : 0,
				'chf_per_hour'         => isset( $r['chf_per_hour'] ) ? $r['chf_per_hour'] : '',
				'chf_amount'           => isset( $r['chf_amount'] ) ? (float) $r['chf_amount'] : 0,
				'session_dates_json'   => wp_json_encode( $session_dates ),
				'sort_order'           => $order,
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%s', '%d' )
		);
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			if ( function_exists( 'soe_debug_log' ) ) {
				soe_debug_log( 'Payroll row insert failed', array( 'payroll_id' => $payroll_id, 'db_error' => $wpdb->last_error ) );
			}
			return false;
		}
	}
	$wpdb->query( 'COMMIT' );
	return true;
}

// --- Payroll manual adjustments (DB) ---

/**
 * Gets manual adjustments for a payroll.
 *
 * @param int $payroll_id Payroll ID.
 * @return array Array of rows with id, payroll_id, comment, amount, sort_order.
 */
function soe_db_payroll_get_adjustments( $payroll_id ) {
	global $wpdb;
	$table = soe_table_payroll_adjustments();
	$payroll_id = (int) $payroll_id;
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, payroll_id, comment, amount, sort_order FROM $table WHERE payroll_id = %d ORDER BY sort_order ASC, id ASC",
			$payroll_id
		),
		ARRAY_A
	);
}

/**
 * Adds a manual adjustment to a payroll.
 *
 * @param int    $payroll_id Payroll ID.
 * @param string $comment    Comment text.
 * @param float  $amount     Amount (positive or negative).
 * @return int|false Insert ID or false on failure.
 */
function soe_db_payroll_add_adjustment( $payroll_id, $comment, $amount ) {
	global $wpdb;
	$table = soe_table_payroll_adjustments();
	$payroll_id = (int) $payroll_id;
	$comment = is_string( $comment ) ? $comment : '';
	$amount = (float) $amount;
	$max_order = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COALESCE(MAX(sort_order), 0) FROM $table WHERE payroll_id = %d",
		$payroll_id
	) );
	$ok = $wpdb->insert(
		$table,
		array(
			'payroll_id'  => $payroll_id,
			'comment'     => $comment,
			'amount'      => $amount,
			'sort_order'  => $max_order + 1,
		),
		array( '%d', '%s', '%f', '%d' )
	);
	return $ok ? (int) $wpdb->insert_id : false;
}

/**
 * Deletes a manual adjustment.
 *
 * @param int $adjustment_id Adjustment row ID.
 * @param int $payroll_id    Payroll ID (for permission check).
 * @return bool
 */
function soe_db_payroll_delete_adjustment( $adjustment_id, $payroll_id ) {
	global $wpdb;
	$table = soe_table_payroll_adjustments();
	$adjustment_id = (int) $adjustment_id;
	$payroll_id = (int) $payroll_id;
	return (bool) $wpdb->delete( $table, array( 'id' => $adjustment_id, 'payroll_id' => $payroll_id ), array( '%d', '%d' ) );
}
