<?php
/**
 * Contact Actions: admin UI, CRUD, and AJAX handlers.
 *
 * Provides a flexible checklist/campaign system for the "contact" CPT.
 * Each action has configurable fields (checkboxes) and tracks per-contact
 * participation items with inline-editable values, status, and notes.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// A. Hooks
// ---------------------------------------------------------------------------

add_action( 'admin_menu', 'soe_contact_actions_menu', 17 );
add_action( 'admin_menu', 'soe_contact_submenu_order_actions_before_action_types', 100 );
add_action( 'admin_enqueue_scripts', 'soe_contact_actions_enqueue' );
add_action( 'add_meta_boxes', 'soe_ca_register_contact_history_metabox' );

add_action( 'wp_ajax_soe_ca_save_action',      'soe_ajax_ca_save_action' );
add_action( 'wp_ajax_soe_ca_add_field',        'soe_ajax_ca_add_field' );
add_action( 'wp_ajax_soe_ca_update_field',     'soe_ajax_ca_update_field' );
add_action( 'wp_ajax_soe_ca_deactivate_field', 'soe_ajax_ca_deactivate_field' );
add_action( 'wp_ajax_soe_ca_add_items',        'soe_ajax_ca_add_items' );
add_action( 'wp_ajax_soe_ca_deactivate_item',  'soe_ajax_ca_deactivate_item' );
add_action( 'wp_ajax_soe_ca_set_value',        'soe_ajax_ca_set_value' );
add_action( 'wp_ajax_soe_ca_set_item_status',  'soe_ajax_ca_set_item_status' );
add_action( 'wp_ajax_soe_ca_set_item_note',    'soe_ajax_ca_set_item_note' );
add_action( 'wp_ajax_soe_ca_copy_action',         'soe_ajax_ca_copy_action' );
add_action( 'wp_ajax_soe_ca_search_contacts',     'soe_ajax_ca_search_contacts' );
add_action( 'wp_ajax_soe_ca_filter_items',        'soe_ajax_ca_filter_items' );
add_action( 'wp_ajax_soe_ca_import_from_action',  'soe_ajax_ca_import_from_action' );

add_action( 'admin_post_soe_ca_export_csv',  'soe_ca_export_csv_handler' );
add_action( 'admin_post_soe_ca_export_xlsx', 'soe_ca_export_xlsx_handler' );

// ---------------------------------------------------------------------------
// Spreadsheet export safety (formula injection mitigation)
// ---------------------------------------------------------------------------

/**
 * Prefixes cell text that would otherwise be interpreted as a formula in Excel/Sheets (CSV/XLSX).
 *
 * @param mixed $value Cell value.
 * @return string
 */
function soe_escape_spreadsheet_scalar( $value ) {
	$value = is_scalar( $value ) ? (string) $value : '';
	if ( $value === '' ) {
		return $value;
	}
	if ( preg_match( '/^[=+\-@\t\r]/u', $value ) ) {
		return "'" . $value;
	}
	return $value;
}

// ---------------------------------------------------------------------------
// B. Admin menu
// ---------------------------------------------------------------------------

/**
 * Registers submenu pages under the "contact" CPT menu.
 */
function soe_contact_actions_menu() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_submenu_page(
		'edit.php?post_type=contact',
		__( 'Aktionen', 'special-olympics-extension' ),
		__( 'Aktionen', 'special-olympics-extension' ),
		'manage_options',
		'soe-contact-actions',
		'soe_render_contact_actions_list'
	);
	add_submenu_page(
		null,
		__( 'Aktion bearbeiten', 'special-olympics-extension' ),
		'',
		'manage_options',
		'soe-contact-action-edit',
		'soe_render_contact_action_edit'
	);
}

/**
 * Places "Aktionen" before the "Aktionstypen" taxonomy submenu under Contacts.
 *
 * WordPress registers the taxonomy submenu before our custom page; this reorders
 * the global submenu array after all admin_menu callbacks.
 */
function soe_contact_submenu_order_actions_before_action_types() {
	global $submenu;

	if ( ! isset( $submenu['edit.php?post_type=contact'] ) || ! is_array( $submenu['edit.php?post_type=contact'] ) ) {
		return;
	}

	if ( ! defined( 'SOE_CONTACT_ACTION_TYPE_TAXONOMY' ) ) {
		return;
	}

	$actions_slug = 'soe-contact-actions';
	// Core stores CPT taxonomy submenu slugs with "&amp;" (wp-admin/menu.php); normalize for comparison.
	$taxonomy_slug_normalized = 'edit-tags.php?taxonomy=' . SOE_CONTACT_ACTION_TYPE_TAXONOMY . '&post_type=contact';

	$actions_item = null;
	$rewritten    = array();

	foreach ( $submenu['edit.php?post_type=contact'] as $item ) {
		if ( ! isset( $item[2] ) ) {
			$rewritten[] = $item;
			continue;
		}
		if ( $item[2] === $actions_slug ) {
			$actions_item = $item;
			continue;
		}
		$item_slug_normalized = str_replace( '&amp;', '&', $item[2] );
		if ( $item_slug_normalized === $taxonomy_slug_normalized ) {
			if ( $actions_item !== null ) {
				$rewritten[] = $actions_item;
				$actions_item = null;
			}
			$rewritten[] = $item;
			continue;
		}
		$rewritten[] = $item;
	}

	if ( $actions_item !== null ) {
		$rewritten[] = $actions_item;
	}

	$submenu['edit.php?post_type=contact'] = array_values( $rewritten );
}

/**
 * Enqueues scripts and styles for contact action admin pages.
 *
 * Hook delivers 'contact_page_soe-contact-actions' or
 * 'admin_page_soe-contact-action-edit' – both contain 'soe-contact-action'.
 *
 * @param string $hook Current admin page hook.
 */
function soe_contact_actions_enqueue( $hook ) {
	if ( strpos( $hook, 'soe-contact-action' ) === false ) {
		return;
	}
	$plugin_url         = plugin_dir_url( dirname( __FILE__ ) );
	$flatpickr_version  = '4.6.13';
	wp_enqueue_style( 'flatpickr', $plugin_url . 'assets/vendor/flatpickr/flatpickr.min.css', array(), $flatpickr_version );
	wp_enqueue_style( 'flatpickr-theme', $plugin_url . 'assets/css/flatpickr-theme.css', array( 'flatpickr' ), SOE_PLUGIN_VERSION );
	wp_enqueue_script( 'flatpickr', $plugin_url . 'assets/vendor/flatpickr/flatpickr.min.js', array(), $flatpickr_version, true );
	wp_enqueue_script( 'flatpickr-de', $plugin_url . 'assets/vendor/flatpickr/l10n-de.js', array( 'flatpickr' ), $flatpickr_version, true );
	wp_enqueue_script(
		'soe-admin-contact-actions',
		$plugin_url . 'assets/js/admin-contact-actions.js',
		array( 'jquery', 'flatpickr-de' ),
		SOE_PLUGIN_VERSION,
		true
	);
	wp_localize_script(
		'soe-admin-contact-actions',
		'soeContactActionsAdmin',
		array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'soe_contact_actions_nonce' ),
			'editContactBase' => admin_url( 'post.php?action=edit&post=' ),
			'i18n'            => array(
				'saved'             => __( 'Gespeichert.', 'special-olympics-extension' ),
				'error'             => __( 'Fehler beim Speichern.', 'special-olympics-extension' ),
				'title_required'    => __( 'Titel darf nicht leer sein.', 'special-olympics-extension' ),
				'create'            => __( 'Erstellen', 'special-olympics-extension' ),
				'confirmDeactivate' => __( 'Wirklich deaktivieren?', 'special-olympics-extension' ),
				'confirmCopy'       => __( 'Aktion kopieren?', 'special-olympics-extension' ),
				'copyWithContacts'  => __( 'Kontakte mitübernehmen?', 'special-olympics-extension' ),
				'noResults'         => __( 'Keine Kontakte gefunden.', 'special-olympics-extension' ),
				'no_results'        => __( 'Keine Ergebnisse.', 'special-olympics-extension' ),
				'adding'            => __( 'Wird hinzugefügt…', 'special-olympics-extension' ),
				'contacts'          => __( 'Kontakte', 'special-olympics-extension' ),
				'remove'            => __( 'Entfernen', 'special-olympics-extension' ),
				'note_placeholder'  => __( 'Notiz…', 'special-olympics-extension' ),
				'status_open'       => __( 'Offen', 'special-olympics-extension' ),
				'status_done'       => __( 'Erledigt', 'special-olympics-extension' ),
				'status_cancelled'  => __( 'Abgebrochen', 'special-olympics-extension' ),
				'archived_badge'    => __( 'Archiviert', 'special-olympics-extension' ),
				'select_source'     => __( 'Bitte eine Quellaktion wählen.', 'special-olympics-extension' ),
				'import_confirm'    => __( 'Übernehmen', 'special-olympics-extension' ),
				'date_placeholder'  => __( 'TT.MM.JJJJ', 'special-olympics-extension' ),
			),
		)
	);
	wp_add_inline_style(
		'wp-admin',
		'
		.soe-ca-matrix-wrap { overflow-x: auto; margin-top: 16px; }
		.soe-ca-matrix { border-collapse: collapse; min-width: 100%; table-layout: auto; width: 100%; }
		.soe-ca-matrix th, .soe-ca-matrix td { padding: 6px 10px; border: 1px solid #c3c4c7; vertical-align: middle; white-space: nowrap; }
		.soe-ca-matrix thead th { background: #f6f7f7; font-weight: 600; }
		.soe-ca-matrix th.soe-ca-contact-col,
		.soe-ca-matrix td.soe-ca-contact-cell { min-width: 7.5rem; max-width: 13rem; width: 10rem; white-space: normal; vertical-align: middle; }
		.soe-ca-matrix th.soe-ca-matrix-col-status,
		.soe-ca-matrix td.soe-ca-matrix-col-status { width: 6.5rem; max-width: 7.5rem; }
		.soe-ca-matrix th.soe-ca-matrix-field-th { max-width: 5.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.soe-ca-matrix td.soe-ca-val { text-align: center; }
		.soe-ca-matrix td.soe-ca-val:has(.soe-ca-checkbox) { width: 2.75rem; max-width: 3.25rem; }
		.soe-ca-matrix td.soe-ca-val:has(.soe-ca-text-val) { min-width: 4.5rem; max-width: 7rem; text-align: left; white-space: normal; }
		.soe-ca-matrix .soe-ca-text-val { width: 100%; max-width: 100%; box-sizing: border-box; }
		.soe-ca-matrix th.soe-ca-matrix-col-note,
		.soe-ca-matrix td.soe-ca-note-cell { min-width: 8rem; width: 11rem; max-width: 15rem; white-space: normal; }
		.soe-ca-matrix th.soe-ca-matrix-col-remove,
		.soe-ca-matrix td.soe-ca-matrix-col-remove { width: 2.25rem; max-width: 2.75rem; padding-left: 4px; padding-right: 4px; text-align: center; }
		.soe-ca-matrix .soe-ca-contact-link { color: #2271b1; text-decoration: none; font-weight: 500; font-size: 13px; line-height: 1.4; }
		.soe-ca-matrix .soe-ca-contact-link:hover, .soe-ca-matrix .soe-ca-contact-link:focus { color: #135e96; text-decoration: underline; outline: none; }
		.soe-ca-matrix .soe-ca-archived-badge { display: inline-block; margin-left: 6px; padding: 1px 6px; font-size: 11px; font-weight: 600; color: #646970; background: #f0f0f1; border-radius: 2px; vertical-align: middle; }
		.soe-ca-matrix .soe-ca-contact-link:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; border-radius: 2px; }
		.soe-ca-matrix input[type=checkbox] { margin: 0; cursor: pointer; }
		.soe-ca-matrix input[type=checkbox].is-saving { opacity: 0.5; cursor: wait; }
		.soe-ca-status-select { font-size: 12px; padding: 2px 4px; max-width: 100%; box-sizing: border-box; }
		.soe-ca-note-cell-inner { display: flex; align-items: center; gap: 6px; width: 100%; }
		.soe-ca-note-cell-inner .soe-ca-note-input { flex: 1 1 auto; min-width: 0; width: auto; font-size: 12px; }
		.soe-ca-note-cell-inner .soe-ca-note-spinner { float: none; margin: 0; flex: 0 0 auto; }
		.soe-ca-field-row td { vertical-align: middle; }
		.soe-ca-section { margin-top: 24px; }
		.soe-ca-contacts-panel {
			background: #fff;
			border: 1px solid #c3c4c7;
			padding: 16px 18px;
			margin: 0 0 16px 0;
			box-sizing: border-box;
			max-width: 100%;
		}
		.soe-ca-contacts-panel > h2 {
			margin-top: 0;
			margin-bottom: 14px;
			padding-bottom: 10px;
			border-bottom: 1px solid #f0f0f1;
		}
		.soe-ca-contacts-panel .soe-ca-matrix-wrap { margin-top: 12px; }
		.soe-ca-contacts-panel .soe-ca-contact-search-wrap { margin-top: 16px; position: relative; }
		.soe-ca-progress { color: #646970; font-size: 12px; }
		.soe-ca-contact-search-wrap { display: flex; gap: 8px; align-items: flex-start; margin-top: 12px; }
		.soe-ca-contact-search-results { background: #fff; border: 1px solid #c3c4c7; max-height: 200px; overflow-y: auto; position: absolute; z-index: 100; min-width: 220px; }
		.soe-ca-contact-result { padding: 6px 10px; cursor: pointer; }
		.soe-ca-contact-result:hover { background: #f0f6fc; }
		.soe-ca-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9998; display: none; }
		.soe-ca-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); background: #fff; padding: 24px; z-index: 9999; min-width: 320px; border-radius: 4px; display: none; }
		.soe-ca-modal h2 { margin-top: 0; }
		.soe-ca-edit-layout { display: grid; grid-template-columns: 1fr min(380px, 32%); gap: 20px; align-items: start; margin-top: 16px; }
		.soe-ca-edit-main { min-width: 0; }
		.soe-ca-edit-sidebar { min-width: 0; max-width: 100%; }
		.soe-ca-edit-sidebar #soe-ca-header-form { max-width: 100%; box-sizing: border-box; margin-top: 0; }
		#soe-ca-header-form .soe-ca-header-form-title { margin: 0 0 14px; padding: 0 0 10px; border-bottom: 1px solid #f0f0f1; font-size: 14px; line-height: 1.3; }
		.soe-ca-edit-sidebar .form-table input.regular-text,
		.soe-ca-edit-sidebar .form-table input.soe-ca-flatpickr-date,
		.soe-ca-edit-sidebar .form-table input[type="datetime-local"],
		.soe-ca-edit-sidebar .form-table textarea.large-text,
		.soe-ca-edit-sidebar .form-table select { width: 100%; max-width: 100%; box-sizing: border-box; }
		.soe-ca-edit-sidebar #soe-ca-header-form .soe-ca-header-form-table th { width: auto; padding-right: 10px; vertical-align: top; line-height: 1.35; white-space: normal; word-break: normal; }
		.soe-ca-edit-sidebar #soe-ca-header-form .soe-ca-header-form-table td { min-width: 0; vertical-align: top; }
		button.soe-ca-field-remove-btn.soe-ca-deactivate-field,
		button.soe-ca-matrix-remove-btn.soe-ca-remove-item { margin: 0; padding: 0 2px; min-width: 26px; height: 26px; border: 0; background: transparent; box-shadow: none; color: #d63638; cursor: pointer; font-size: 20px; font-weight: 700; line-height: 1; display: inline-flex; align-items: center; justify-content: center; border-radius: 2px; }
		button.soe-ca-field-remove-btn.soe-ca-deactivate-field:hover, button.soe-ca-field-remove-btn.soe-ca-deactivate-field:focus,
		button.soe-ca-matrix-remove-btn.soe-ca-remove-item:hover, button.soe-ca-matrix-remove-btn.soe-ca-remove-item:focus { color: #a00; background: rgba(214, 54, 56, 0.08); outline: none; box-shadow: none; }
		button.soe-ca-field-remove-btn.soe-ca-deactivate-field:focus-visible,
		button.soe-ca-matrix-remove-btn.soe-ca-remove-item:focus-visible { outline: 2px solid #2271b1; outline-offset: 1px; }
		#soe-ca-fields-table th.soe-ca-fields-col-remove { width: 2rem; text-align: center; padding-left: 4px; padding-right: 4px; }
		#soe-ca-fields-table td.soe-ca-fields-col-remove { text-align: center; vertical-align: middle; }
		.soe-ca-fields-table-wrap { overflow-x: auto; max-width: 100%; margin-top: 8px; -webkit-overflow-scrolling: touch; }
		#soe-ca-fields-table { table-layout: auto; width: 100%; }
		#soe-ca-fields-table .soe-ca-field-label { width: 100%; max-width: 100%; min-width: 0; box-sizing: border-box; }
		.soe-ca-edit-sidebar input.regular-text { max-width: 100%; box-sizing: border-box; }
		.soe-ca-edit-sidebar .form-table .flatpickr-input,
		#soe-ca-new-form .form-table .flatpickr-input { width: 100%; max-width: 100%; box-sizing: border-box; }
		.soe-ca-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
		@media (max-width: 1200px) {
			.soe-ca-edit-layout { grid-template-columns: 1fr; }
			.soe-ca-edit-order-mobile { order: -1; }
		}
		.soe-ca-list-filter-wrap { margin: 12px 0; display: flex; flex-direction: column; gap: 12px; width: 100%; clear: both; }
		.soe-ca-list-filter-tabs .subsubsub { float: none; margin: 0; padding-left: 0; }
		.soe-ca-list-filter-form form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0; }
		'
	);
}

// ---------------------------------------------------------------------------
// C. DB CRUD functions
// ---------------------------------------------------------------------------

// --- Actions ---

/**
 * Returns a single contact action by ID.
 *
 * @param int $id Action ID.
 * @return array|null Row as associative array, or null if not found.
 */
function soe_db_ca_action_get( $id ) {
	global $wpdb;
	return $wpdb->get_row(
		$wpdb->prepare( 'SELECT * FROM ' . soe_table_contact_actions() . ' WHERE id = %d', (int) $id ),
		ARRAY_A
	);
}

/**
 * Inserts a new contact action.
 *
 * @param array $data Keys: title, description, action_type, action_year, event_label,
 *                    status, due_date, responsible_user_id, created_by.
 * @return int Inserted ID, or 0 on failure.
 */
function soe_db_ca_action_insert( $data ) {
	global $wpdb;
	$now = current_time( 'mysql' );
	$defaults = array(
		'title'               => '',
		'description'         => '',
		'action_type'         => '',
		'action_year'         => null,
		'event_label'         => '',
		'status'              => 'active',
		'due_date'            => null,
		'responsible_user_id' => null,
		'created_by'          => get_current_user_id(),
		'is_active'           => 1,
		'created_at'          => $now,
		'updated_at'          => $now,
	);
	$row     = array_intersect_key( wp_parse_args( $data, $defaults ), $defaults );
	$formats = array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' );
	$ok      = $wpdb->insert( soe_table_contact_actions(), $row, $formats );
	if ( ! $ok ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'CA action insert failed', array( 'db_error' => $wpdb->last_error ) );
		}
		return 0;
	}
	return (int) $wpdb->insert_id;
}

/**
 * Updates an existing contact action.
 *
 * @param int   $id   Action ID.
 * @param array $data Keys: title, description, action_type, action_year, event_label,
 *                    status, due_date, responsible_user_id.
 * @return bool
 */
function soe_db_ca_action_update( $id, $data ) {
	global $wpdb;
	$data['updated_at'] = current_time( 'mysql' );
	$formats = array(
		'title'               => '%s',
		'description'         => '%s',
		'action_type'         => '%s',
		'action_year'         => '%d',
		'event_label'         => '%s',
		'status'              => '%s',
		'due_date'            => '%s',
		'responsible_user_id' => '%d',
		'updated_at'          => '%s',
	);
	$updates = array_intersect_key( $data, $formats );
	if ( empty( $updates ) ) {
		return false;
	}
	$ok = $wpdb->update(
		soe_table_contact_actions(),
		$updates,
		array( 'id' => (int) $id ),
		array_values( array_intersect_key( $formats, $updates ) ),
		array( '%d' )
	);
	return $ok !== false;
}

/**
 * Returns active contact actions with optional filters, ordered by title.
 *
 * @param array $args Optional. Keys: action_type (string), action_year (int|null), status (string, action row status).
 * @return array
 */
function soe_db_ca_action_list( $args = array() ) {
	global $wpdb;
	$table        = soe_table_contact_actions();
	$fields_table = soe_table_contact_action_fields();
	$items_table  = soe_table_contact_action_items();

	$where  = array( 'a.is_active = 1' );
	$values = array();

	if ( ! empty( $args['action_type'] ) ) {
		$where[]  = 'a.action_type = %s';
		$values[] = $args['action_type'];
	}
	if ( ! empty( $args['action_year'] ) ) {
		$where[]  = 'a.action_year = %d';
		$values[] = (int) $args['action_year'];
	}
	if ( ! empty( $args['status'] ) ) {
		$where[]  = 'a.status = %s';
		$values[] = $args['status'];
	}

	$where_sql = implode( ' AND ', $where );
	$sql = "SELECT a.*,
		( SELECT COUNT(*) FROM $fields_table f WHERE f.action_id = a.id AND f.is_active = 1 ) AS field_count,
		( SELECT COUNT(*) FROM $items_table i WHERE i.action_id = a.id AND i.is_active = 1 ) AS item_count,
		( SELECT COUNT(*) FROM $items_table i WHERE i.action_id = a.id AND i.is_active = 1 AND i.status = 'open' ) AS open_count,
		( SELECT COUNT(*) FROM $items_table i WHERE i.action_id = a.id AND i.is_active = 1 AND i.status = 'done' ) AS done_count,
		( SELECT COUNT(*) FROM $items_table i WHERE i.action_id = a.id AND i.is_active = 1 AND i.status = 'cancelled' ) AS cancelled_count
	FROM $table a
	WHERE $where_sql
	ORDER BY a.title ASC";

	if ( ! empty( $values ) ) {
		$sql = $wpdb->prepare( $sql, $values );
	}
	return $wpdb->get_results( $sql, ARRAY_A );
}

// --- Fields ---

/**
 * Returns all active fields for a contact action, ordered by sort_order.
 *
 * @param int $action_id Action ID.
 * @return array
 */
function soe_db_ca_fields_get( $action_id ) {
	global $wpdb;
	return $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . soe_table_contact_action_fields() . ' WHERE action_id = %d AND is_active = 1 ORDER BY sort_order ASC, id ASC',
			(int) $action_id
		),
		ARRAY_A
	);
}

/**
 * Adds a field to a contact action.
 *
 * Generates a unique field_key from field_label (sanitize_key + numeric suffix if needed).
 *
 * @param int   $action_id Action ID.
 * @param array $data      Keys: field_label, field_type (optional, default 'checkbox').
 * @return int Inserted field ID, or 0 on failure.
 */
function soe_db_ca_field_add( $action_id, $data ) {
	global $wpdb;
	$action_id       = (int) $action_id;
	$field_label     = isset( $data['field_label'] ) ? sanitize_text_field( $data['field_label'] ) : '';
	$allowed_types   = array( 'checkbox' );
	$raw_type        = isset( $data['field_type'] ) ? sanitize_key( $data['field_type'] ) : 'checkbox';
	$field_type      = in_array( $raw_type, $allowed_types, true ) ? $raw_type : 'checkbox';
	$sort_order      = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;
	$table           = soe_table_contact_action_fields();

	if ( $field_label === '' ) {
		return 0;
	}

	// Generate unique field_key within this action.
	$base_key = sanitize_key( $field_label );
	if ( $base_key === '' ) {
		$base_key = 'feld';
	}
	$field_key = $base_key;
	$suffix    = 1;
	while ( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE action_id = %d AND field_key = %s", $action_id, $field_key ) ) > 0 ) {
		$field_key = $base_key . '_' . $suffix;
		$suffix++;
	}

	// Append at end of existing fields if sort_order not provided.
	if ( $sort_order === 0 ) {
		$max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(sort_order),0) FROM $table WHERE action_id = %d", $action_id ) );
		$sort_order = $max + 1;
	}

	$now = current_time( 'mysql' );
	$ok  = $wpdb->insert(
		$table,
		array(
			'action_id'   => $action_id,
			'field_key'   => $field_key,
			'field_label' => $field_label,
			'field_type'  => $field_type,
			'sort_order'  => $sort_order,
			'is_active'   => 1,
			'created_at'  => $now,
			'updated_at'  => $now,
		),
		array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
	);
	if ( ! $ok ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'CA field insert failed', array( 'action_id' => $action_id, 'db_error' => $wpdb->last_error ) );
		}
		return 0;
	}
	return (int) $wpdb->insert_id;
}

/**
 * Updates a field's label and/or sort_order (field_key is immutable).
 *
 * @param int   $field_id  Field ID.
 * @param int   $action_id Action ID (ownership check).
 * @param array $data      Keys: field_label, sort_order.
 * @return bool
 */
function soe_db_ca_field_update( $field_id, $action_id, $data ) {
	global $wpdb;
	$formats = array( 'field_label' => '%s', 'sort_order' => '%d' );
	$updates = array_intersect_key( $data, $formats );
	if ( empty( $updates ) ) {
		return false;
	}
	$updates['updated_at'] = current_time( 'mysql' );
	$formats['updated_at'] = '%s';
	return $wpdb->update(
		soe_table_contact_action_fields(),
		$updates,
		array( 'id' => (int) $field_id, 'action_id' => (int) $action_id ),
		array_values( array_intersect_key( $formats, $updates ) ),
		array( '%d', '%d' )
	) !== false;
}

/**
 * Soft-deletes a field (is_active = 0).
 *
 * @param int $field_id  Field ID.
 * @param int $action_id Action ID (ownership check).
 * @return bool
 */
function soe_db_ca_field_deactivate( $field_id, $action_id ) {
	global $wpdb;
	return $wpdb->update(
		soe_table_contact_action_fields(),
		array( 'is_active' => 0, 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => (int) $field_id, 'action_id' => (int) $action_id ),
		array( '%d', '%s' ),
		array( '%d', '%d' )
	) !== false;
}

// --- Items ---

/**
 * Returns all active items for a contact action with contact post titles.
 *
 * @param int $action_id Action ID.
 * @return array Each row includes item columns + contact_title + contact_is_archived (0|1).
 */
function soe_db_ca_items_get( $action_id ) {
	global $wpdb;
	$items_table = soe_table_contact_action_items();
	$posts_table = $wpdb->posts;
	$meta_key    = defined( 'SOE_CONTACT_STATUS_META' ) ? SOE_CONTACT_STATUS_META : 'soe_contact_status';
	$archived    = defined( 'SOE_CONTACT_STATUS_ARCHIVED' ) ? SOE_CONTACT_STATUS_ARCHIVED : 'archived';
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT i.*, COALESCE(p.post_title, '') AS contact_title,
			CASE WHEN pm.meta_value = %s THEN 1 ELSE 0 END AS contact_is_archived
			FROM $items_table i
			LEFT JOIN $posts_table p ON p.ID = i.contact_id AND p.post_type = 'contact'
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = i.contact_id AND pm.meta_key = %s
			WHERE i.action_id = %d AND i.is_active = 1
			ORDER BY p.post_title ASC, i.id ASC",
			$archived,
			$meta_key,
			(int) $action_id
		),
		ARRAY_A
	);
}

/**
 * Adds a contact to an action item.
 *
 * - Already active → no-op (returns existing ID).
 * - Inactive (soft-deleted) → reactivates without changing id (item_values preserved).
 * - Not found → inserts new row.
 *
 * @param int $action_id  Action ID.
 * @param int $contact_id Contact post ID.
 * @return int Item ID, or 0 on failure.
 */
function soe_db_ca_item_add( $action_id, $contact_id ) {
	global $wpdb;
	$action_id  = (int) $action_id;
	$contact_id = (int) $contact_id;
	if ( function_exists( 'soe_is_contact_active' ) && ! soe_is_contact_active( $contact_id ) ) {
		return 0;
	}
	$table      = soe_table_contact_action_items();
	$now        = current_time( 'mysql' );

	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, is_active FROM $table WHERE action_id = %d AND contact_id = %d",
			$action_id,
			$contact_id
		),
		ARRAY_A
	);

	if ( $existing ) {
		if ( (int) $existing['is_active'] === 1 ) {
			return (int) $existing['id'];
		}
		// Reactivate without touching id so item_values remain valid.
		$ok = $wpdb->update(
			$table,
			array( 'is_active' => 1, 'status' => 'open', 'updated_at' => $now ),
			array( 'id' => (int) $existing['id'] ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
		return $ok !== false ? (int) $existing['id'] : 0;
	}

	$ok = $wpdb->insert(
		$table,
		array(
			'action_id'  => $action_id,
			'contact_id' => $contact_id,
			'status'     => 'open',
			'note'       => '',
			'is_active'  => 1,
			'created_at' => $now,
			'updated_at' => $now,
		),
		array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
	);
	if ( ! $ok ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'CA item insert failed', array( 'action_id' => $action_id, 'contact_id' => $contact_id, 'db_error' => $wpdb->last_error ) );
		}
		return 0;
	}
	return (int) $wpdb->insert_id;
}

/**
 * Soft-deletes an item (is_active = 0).
 *
 * @param int $item_id   Item ID.
 * @param int $action_id Action ID (ownership check).
 * @return bool
 */
function soe_db_ca_item_deactivate( $item_id, $action_id ) {
	global $wpdb;
	return $wpdb->update(
		soe_table_contact_action_items(),
		array( 'is_active' => 0, 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => (int) $item_id, 'action_id' => (int) $action_id ),
		array( '%d', '%s' ),
		array( '%d', '%d' )
	) !== false;
}

// --- Values ---

/**
 * Sets the value for a specific field on a specific item.
 *
 * Uses replace (INSERT … ON DUPLICATE KEY UPDATE) via $wpdb->replace.
 * Safe because item_values is a leaf table (nothing references its id).
 *
 * @param int    $item_id  Item ID.
 * @param int    $field_id Field ID.
 * @param string $value    Value string ('0'/'1' for checkbox).
 * @return bool
 */
function soe_db_ca_value_set( $item_id, $field_id, $value ) {
	global $wpdb;
	$now = current_time( 'mysql' );
	$ok  = $wpdb->replace(
		soe_table_contact_action_item_values(),
		array(
			'item_id'    => (int) $item_id,
			'field_id'   => (int) $field_id,
			'value'      => (string) $value,
			'created_at' => $now,
			'updated_at' => $now,
		),
		array( '%d', '%d', '%s', '%s', '%s' )
	);
	if ( $ok === false ) {
		if ( function_exists( 'soe_debug_log' ) ) {
			soe_debug_log( 'CA value set failed', array( 'item_id' => $item_id, 'field_id' => $field_id, 'db_error' => $wpdb->last_error ) );
		}
	}
	return $ok !== false;
}

/**
 * Returns all values for all active items of an action (bulk, one query).
 *
 * @param int $action_id Action ID.
 * @return array Keyed as [ item_id => [ field_id => value ] ].
 */
function soe_db_ca_values_get_for_action( $action_id ) {
	global $wpdb;
	$values_table = soe_table_contact_action_item_values();
	$items_table  = soe_table_contact_action_items();
	$rows         = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT v.item_id, v.field_id, v.value
			FROM $values_table v
			INNER JOIN $items_table i ON i.id = v.item_id AND i.action_id = %d AND i.is_active = 1",
			(int) $action_id
		),
		ARRAY_A
	);
	$out = array();
	if ( is_array( $rows ) ) {
		foreach ( $rows as $r ) {
			$iid = (int) $r['item_id'];
			$fid = (int) $r['field_id'];
			if ( ! isset( $out[ $iid ] ) ) {
				$out[ $iid ] = array();
			}
			$out[ $iid ][ $fid ] = $r['value'];
		}
	}
	return $out;
}

// --- Copy Action ---

/**
 * Copies a contact action to a new one (transactional).
 *
 * Copies all active fields (keeps field_key). Optionally copies active items
 * (reset status='open', note='', no values).
 *
 * @param int  $source_id        Source action ID.
 * @param bool $include_contacts Whether to copy contact items.
 * @return int New action ID, or 0 on failure.
 */
function soe_db_ca_copy_action( $source_id, $include_contacts = false ) {
	global $wpdb;
	$source_id = (int) $source_id;
	$source    = soe_db_ca_action_get( $source_id );
	if ( ! $source ) {
		return 0;
	}

	$wpdb->query( 'START TRANSACTION' );

	$new_id = soe_db_ca_action_insert( array(
		'title'               => $source['title'] . ' ' . __( '(Kopie)', 'special-olympics-extension' ),
		'description'         => $source['description'],
		'action_type'         => $source['action_type'],
		'action_year'         => $source['action_year'] ? (int) $source['action_year'] : null,
		'event_label'         => '',
		'status'              => 'active',
		'created_by'          => get_current_user_id(),
	) );
	if ( ! $new_id ) {
		$wpdb->query( 'ROLLBACK' );
		return 0;
	}

	// Copy active fields; collect id mapping for potential value resets.
	$source_fields = soe_db_ca_fields_get( $source_id );
	$field_id_map  = array();
	$now           = current_time( 'mysql' );
	$fields_table  = soe_table_contact_action_fields();

	foreach ( $source_fields as $f ) {
		$ok = $wpdb->insert(
			$fields_table,
			array(
				'action_id'   => $new_id,
				'field_key'   => $f['field_key'],
				'field_label' => $f['field_label'],
				'field_type'  => $f['field_type'],
				'sort_order'  => $f['sort_order'],
				'is_active'   => 1,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);
		if ( ! $ok ) {
			$wpdb->query( 'ROLLBACK' );
			if ( function_exists( 'soe_debug_log' ) ) {
				soe_debug_log( 'CA copy_action field insert failed', array( 'source_id' => $source_id, 'db_error' => $wpdb->last_error ) );
			}
			return 0;
		}
		$field_id_map[ (int) $f['id'] ] = (int) $wpdb->insert_id;
	}

	// Optionally copy active contact items (reset status, no note, no values).
	if ( $include_contacts ) {
		$source_items = soe_db_ca_items_get( $source_id );
		$items_table  = soe_table_contact_action_items();
		foreach ( $source_items as $item ) {
			$ok = $wpdb->insert(
				$items_table,
				array(
					'action_id'  => $new_id,
					'contact_id' => (int) $item['contact_id'],
					'status'     => 'open',
					'note'       => '',
					'is_active'  => 1,
					'created_at' => $now,
					'updated_at' => $now,
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
			);
			if ( ! $ok ) {
				$wpdb->query( 'ROLLBACK' );
				if ( function_exists( 'soe_debug_log' ) ) {
					soe_debug_log( 'CA copy_action item insert failed', array( 'source_id' => $source_id, 'db_error' => $wpdb->last_error ) );
				}
				return 0;
			}
		}
	}

	$wpdb->query( 'COMMIT' );
	return $new_id;
}

// ---------------------------------------------------------------------------
// D. AJAX handlers
// ---------------------------------------------------------------------------

/**
 * Returns action type terms for dropdowns (taxonomy `soe_contact_action_type`).
 *
 * @return WP_Term[]
 */
function soe_ca_get_action_type_terms() {
	if ( ! taxonomy_exists( SOE_CONTACT_ACTION_TYPE_TAXONOMY ) ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => SOE_CONTACT_ACTION_TYPE_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return array();
	}
	return $terms;
}

/**
 * Validates an action type slug against registered taxonomy terms.
 *
 * @param string $raw Raw slug (e.g. from POST or GET).
 * @return string Empty string if invalid or unknown taxonomy; otherwise canonical term slug.
 */
function soe_ca_validate_action_type_slug( $raw ) {
	$raw = is_string( $raw ) ? trim( sanitize_text_field( wp_unslash( $raw ) ) ) : '';
	if ( $raw === '' || ! taxonomy_exists( SOE_CONTACT_ACTION_TYPE_TAXONOMY ) ) {
		return '';
	}
	$term = get_term_by( 'slug', $raw, SOE_CONTACT_ACTION_TYPE_TAXONOMY );
	if ( ! $term || is_wp_error( $term ) ) {
		return '';
	}
	return $term->slug;
}

/**
 * Returns users eligible as "responsible" for contact actions (administrators only).
 *
 * @return stdClass[] Each object has ID and display_name (see get_users fields).
 */
function soe_ca_get_responsible_user_candidates() {
	return get_users(
		array(
			'role'    => 'administrator',
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => array( 'ID', 'display_name' ),
		)
	);
}

/**
 * Ensures responsible_user_id is empty or one of the administrator candidates.
 *
 * @param int $user_id Candidate user ID.
 * @return int|null Null if unset/invalid; positive ID if valid.
 */
function soe_ca_validate_responsible_user_id( $user_id ) {
	$user_id = (int) $user_id;
	if ( $user_id <= 0 ) {
		return null;
	}
	$allowed = wp_list_pluck( soe_ca_get_responsible_user_candidates(), 'ID' );
	if ( ! in_array( $user_id, $allowed, true ) ) {
		return null;
	}
	return $user_id;
}

/**
 * Returns the allowed action status slugs (status of the action itself, not items).
 *
 * @return string[]
 */
function soe_ca_allowed_action_statuses() {
	return array( 'active', 'completed' );
}

/**
 * Returns a human-readable label for an action type slug (taxonomy term name).
 *
 * @param string $type Slug stored in `action_type`.
 * @return string
 */
function soe_ca_action_type_label( $type ) {
	$type = is_string( $type ) ? trim( $type ) : '';
	if ( $type === '' || ! taxonomy_exists( SOE_CONTACT_ACTION_TYPE_TAXONOMY ) ) {
		return '';
	}
	$term = get_term_by( 'slug', $type, SOE_CONTACT_ACTION_TYPE_TAXONOMY );
	if ( ! $term || is_wp_error( $term ) ) {
		return esc_html( $type );
	}
	return esc_html( $term->name );
}

/**
 * Returns a human-readable label for an action status slug.
 *
 * @param string $status Slug.
 * @return string
 */
function soe_ca_action_status_label( $status ) {
	$labels = array(
		'active'    => __( 'In Bearbeitung', 'special-olympics-extension' ),
		'completed' => __( 'Abgeschlossen', 'special-olympics-extension' ),
	);
	return isset( $labels[ $status ] ) ? $labels[ $status ] : esc_html( $status );
}

/**
 * Shared nonce and capability check for all contact action AJAX handlers.
 *
 * Calls wp_die() on failure (terminates output).
 */
function soe_ca_check_ajax_access() {
	check_ajax_referer( 'soe_contact_actions_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'code' => 'soe_ca_unauthorized', 'message' => __( 'Keine Berechtigung.', 'special-olympics-extension' ) ), 403 );
	}
}

/**
 * AJAX: Create or update a contact action.
 *
 * POST: action_id (0 = create), title, description, nonce, optional field_sort_json
 *       (JSON object: field_id string keys → sort_order int) to persist checkbox column order on edit.
 */
function soe_ajax_ca_save_action() {
	soe_ca_check_ajax_access();

	$action_id   = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
	$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

	$raw_type    = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
	$action_type = soe_ca_validate_action_type_slug( $raw_type );

	$raw_year    = isset( $_POST['action_year'] ) ? (int) $_POST['action_year'] : 0;
	$action_year = ( $raw_year >= 2000 && $raw_year <= 2100 ) ? $raw_year : null;

	$allowed_statuses = soe_ca_allowed_action_statuses();
	$raw_status         = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	$status             = in_array( $raw_status, $allowed_statuses, true ) ? $raw_status : 'active';

	$due_date = '';
	if ( ! empty( $_POST['due_date'] ) ) {
		$raw_due = sanitize_text_field( wp_unslash( $_POST['due_date'] ) );
		if ( strlen( $raw_due ) === 10 ) {
			$d = DateTimeImmutable::createFromFormat( 'Y-m-d', $raw_due );
			// getLastErrors() returns false when there were no warnings/errors (success), or an array otherwise.
			$errors = DateTimeImmutable::getLastErrors();
			$no_parse_issues = false === $errors
				|| ( is_array( $errors )
					&& (int) $errors['warning_count'] === 0
					&& (int) $errors['error_count'] === 0 );
			if ( $d instanceof DateTimeImmutable && $no_parse_issues && $d->format( 'Y-m-d' ) === $raw_due ) {
				$due_date = $raw_due;
			}
		}
	}

	$responsible_user_id = isset( $_POST['responsible_user_id'] ) ? (int) $_POST['responsible_user_id'] : 0;
	$responsible_user_id = soe_ca_validate_responsible_user_id( $responsible_user_id );

	if ( $title === '' ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Titel darf nicht leer sein.', 'special-olympics-extension' ) ), 400 );
	}

	$field_data = array(
		'title'               => $title,
		'description'         => $description,
		'action_type'         => $action_type,
		'action_year'         => $action_year,
		'event_label'         => '',
		'status'              => $status,
		'due_date'            => $due_date ?: null,
		'responsible_user_id' => $responsible_user_id,
	);

	if ( $action_id ) {
		$action = soe_db_ca_action_get( $action_id );
		if ( ! $action ) {
			wp_send_json_error( array( 'code' => 'soe_ca_not_found', 'message' => __( 'Aktion nicht gefunden.', 'special-olympics-extension' ) ), 404 );
		}
		$ok = soe_db_ca_action_update( $action_id, $field_data );
		if ( ! $ok ) {
			wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Speichern.', 'special-olympics-extension' ) ), 500 );
		}
		// Checkbox column sort orders: submitted with "Speichern" so users need not blur each row first.
		if ( ! empty( $_POST['field_sort_json'] ) && is_string( $_POST['field_sort_json'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['field_sort_json'] ), true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $fid_key => $ord_val ) {
					$fid = (int) $fid_key;
					if ( $fid <= 0 ) {
						continue;
					}
					$ord = (int) $ord_val;
					soe_db_ca_field_update( $fid, $action_id, array( 'sort_order' => $ord ) );
				}
			}
		}
		wp_send_json_success( array( 'action_id' => $action_id ) );
	}

	$new_id = soe_db_ca_action_insert( $field_data );
	if ( ! $new_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Erstellen.', 'special-olympics-extension' ) ), 500 );
	}
	wp_send_json_success( array( 'action_id' => $new_id, 'redirect' => admin_url( 'admin.php?page=soe-contact-action-edit&id=' . $new_id ) ) );
}

/**
 * AJAX: Add a field to a contact action.
 *
 * POST: action_id, field_label, field_type (optional), nonce.
 */
function soe_ajax_ca_add_field() {
	soe_ca_check_ajax_access();

	$action_id     = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$field_label   = isset( $_POST['field_label'] ) ? sanitize_text_field( wp_unslash( $_POST['field_label'] ) ) : '';
	$allowed_types = array( 'checkbox' );
	$raw_type      = isset( $_POST['field_type'] ) ? sanitize_key( wp_unslash( $_POST['field_type'] ) ) : 'checkbox';
	$field_type    = in_array( $raw_type, $allowed_types, true ) ? $raw_type : 'checkbox';

	if ( ! $action_id || $field_label === '' ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}
	if ( ! soe_db_ca_action_get( $action_id ) ) {
		wp_send_json_error( array( 'code' => 'soe_ca_not_found', 'message' => __( 'Aktion nicht gefunden.', 'special-olympics-extension' ) ), 404 );
	}

	$field_id = soe_db_ca_field_add( $action_id, array( 'field_label' => $field_label, 'field_type' => $field_type ) );
	if ( ! $field_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Hinzufügen.', 'special-olympics-extension' ) ), 500 );
	}

	$fields = soe_db_ca_fields_get( $action_id );
	wp_send_json_success( array( 'field_id' => $field_id, 'fields' => $fields ) );
}

/**
 * AJAX: Update a field's label or sort_order.
 *
 * POST: action_id, field_id, field_label (optional), sort_order (optional), nonce.
 */
function soe_ajax_ca_update_field() {
	soe_ca_check_ajax_access();

	$action_id  = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$field_id   = isset( $_POST['field_id'] ) ? (int) $_POST['field_id'] : 0;

	if ( ! $action_id || ! $field_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}

	$data = array();
	if ( isset( $_POST['field_label'] ) ) {
		$label = sanitize_text_field( wp_unslash( $_POST['field_label'] ) );
		if ( $label !== '' ) {
			$data['field_label'] = $label;
		}
	}
	if ( isset( $_POST['sort_order'] ) ) {
		$data['sort_order'] = (int) $_POST['sort_order'];
	}

	if ( empty( $data ) ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Keine Änderungen übergeben.', 'special-olympics-extension' ) ), 400 );
	}

	$ok = soe_db_ca_field_update( $field_id, $action_id, $data );
	if ( ! $ok ) {
		wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Aktualisieren.', 'special-olympics-extension' ) ), 500 );
	}
	wp_send_json_success( array( 'fields' => soe_db_ca_fields_get( $action_id ) ) );
}

/**
 * AJAX: Soft-delete a field (is_active = 0).
 *
 * POST: action_id, field_id, nonce.
 */
function soe_ajax_ca_deactivate_field() {
	soe_ca_check_ajax_access();

	$action_id = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$field_id  = isset( $_POST['field_id'] ) ? (int) $_POST['field_id'] : 0;

	if ( ! $action_id || ! $field_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}

	$ok = soe_db_ca_field_deactivate( $field_id, $action_id );
	if ( ! $ok ) {
		wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Deaktivieren.', 'special-olympics-extension' ) ), 500 );
	}
	wp_send_json_success( array( 'fields' => soe_db_ca_fields_get( $action_id ) ) );
}

/**
 * AJAX: Add one or more contacts to a contact action (bulk).
 *
 * POST: action_id, contact_ids[] (array of int), nonce.
 */
function soe_ajax_ca_add_items() {
	soe_ca_check_ajax_access();

	$action_id   = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$contact_ids = isset( $_POST['contact_ids'] ) && is_array( $_POST['contact_ids'] )
		? array_map( 'intval', $_POST['contact_ids'] )
		: array();

	if ( ! $action_id || empty( $contact_ids ) ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}
	if ( ! soe_db_ca_action_get( $action_id ) ) {
		wp_send_json_error( array( 'code' => 'soe_ca_not_found', 'message' => __( 'Aktion nicht gefunden.', 'special-olympics-extension' ) ), 404 );
	}

	$active_rows         = soe_db_ca_items_get( $action_id );
	$known_contact_ids   = array();
	foreach ( $active_rows as $row ) {
		$known_contact_ids[ (int) $row['contact_id'] ] = true;
	}

	$added   = 0;
	$skipped = 0;
	foreach ( $contact_ids as $contact_id ) {
		if ( $contact_id <= 0 ) {
			continue;
		}
		if ( isset( $known_contact_ids[ $contact_id ] ) ) {
			++$skipped;
			continue;
		}
		$post = get_post( $contact_id );
		if ( ! $post || $post->post_type !== 'contact' ) {
			continue;
		}
		$item_id = soe_db_ca_item_add( $action_id, $contact_id );
		if ( $item_id ) {
			++$added;
			$known_contact_ids[ $contact_id ] = true;
		}
	}

	wp_send_json_success( array(
		'added'   => $added,
		'skipped' => $skipped,
		'items'   => soe_db_ca_items_get( $action_id ),
	) );
}

/**
 * AJAX: Soft-delete an item (is_active = 0).
 *
 * POST: action_id, item_id, nonce.
 */
function soe_ajax_ca_deactivate_item() {
	soe_ca_check_ajax_access();

	$action_id = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$item_id   = isset( $_POST['item_id'] ) ? (int) $_POST['item_id'] : 0;

	if ( ! $action_id || ! $item_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}

	$ok = soe_db_ca_item_deactivate( $item_id, $action_id );
	if ( ! $ok ) {
		wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Deaktivieren.', 'special-olympics-extension' ) ), 500 );
	}
	wp_send_json_success();
}

/**
 * AJAX: Set checkbox/field value for a specific item (inline editing).
 *
 * Cross-validates that item belongs to action and field belongs to action.
 * POST: action_id, item_id, field_id, value, nonce.
 */
function soe_ajax_ca_set_value() {
	soe_ca_check_ajax_access();

	$action_id = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$item_id   = isset( $_POST['item_id'] ) ? (int) $_POST['item_id'] : 0;
	$field_id  = isset( $_POST['field_id'] ) ? (int) $_POST['field_id'] : 0;
	$value     = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '0';

	if ( ! $action_id || ! $item_id || ! $field_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}

	global $wpdb;

	// Verify item belongs to action and is active.
	$item_ok = (int) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT COUNT(*) FROM ' . soe_table_contact_action_items() . ' WHERE id = %d AND action_id = %d AND is_active = 1',
			$item_id,
			$action_id
		)
	);
	if ( ! $item_ok ) {
		wp_send_json_error( array( 'code' => 'soe_ca_not_found', 'message' => __( 'Item nicht gefunden.', 'special-olympics-extension' ) ), 404 );
	}

	// Verify field belongs to action and is active.
	$field_row = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT field_type FROM ' . soe_table_contact_action_fields() . ' WHERE id = %d AND action_id = %d AND is_active = 1',
			$field_id,
			$action_id
		),
		ARRAY_A
	);
	if ( ! $field_row ) {
		wp_send_json_error( array( 'code' => 'soe_ca_not_found', 'message' => __( 'Feld nicht gefunden.', 'special-olympics-extension' ) ), 404 );
	}

	// Cast value according to field_type.
	if ( $field_row['field_type'] === 'checkbox' ) {
		$value = ( $value === '1' || $value === 'true' || $value === 'on' ) ? '1' : '0';
	}

	$ok = soe_db_ca_value_set( $item_id, $field_id, $value );
	if ( ! $ok ) {
		wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Speichern.', 'special-olympics-extension' ) ), 500 );
	}
	wp_send_json_success( array( 'value' => $value ) );
}

/**
 * AJAX: Update item status.
 *
 * POST: action_id, item_id, status, nonce.
 */
function soe_ajax_ca_set_item_status() {
	soe_ca_check_ajax_access();

	$action_id = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$item_id   = isset( $_POST['item_id'] ) ? (int) $_POST['item_id'] : 0;
	$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

	$allowed = array( 'open', 'done', 'cancelled' );
	if ( ! $action_id || ! $item_id || ! in_array( $status, $allowed, true ) ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}

	global $wpdb;
	$ok = $wpdb->update(
		soe_table_contact_action_items(),
		array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => $item_id, 'action_id' => $action_id, 'is_active' => 1 ),
		array( '%s', '%s' ),
		array( '%d', '%d', '%d' )
	);
	if ( $ok === false ) {
		wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Speichern.', 'special-olympics-extension' ) ), 500 );
	}
	wp_send_json_success( array( 'status' => $status ) );
}

/**
 * AJAX: Update item note.
 *
 * POST: action_id, item_id, note, nonce.
 */
function soe_ajax_ca_set_item_note() {
	soe_ca_check_ajax_access();

	$action_id = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$item_id   = isset( $_POST['item_id'] ) ? (int) $_POST['item_id'] : 0;
	$note      = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

	if ( ! $action_id || ! $item_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}

	global $wpdb;
	$ok = $wpdb->update(
		soe_table_contact_action_items(),
		array( 'note' => $note, 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => $item_id, 'action_id' => $action_id, 'is_active' => 1 ),
		array( '%s', '%s' ),
		array( '%d', '%d', '%d' )
	);
	if ( $ok === false ) {
		wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Speichern.', 'special-olympics-extension' ) ), 500 );
	}
	wp_send_json_success( array( 'note' => $note ) );
}

/**
 * AJAX: Copy a contact action.
 *
 * POST: action_id, include_contacts (1/0), nonce.
 */
function soe_ajax_ca_copy_action() {
	soe_ca_check_ajax_access();

	$action_id        = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$include_contacts = ! empty( $_POST['include_contacts'] ) && $_POST['include_contacts'] !== '0';

	if ( ! $action_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}

	$new_id = soe_db_ca_copy_action( $action_id, $include_contacts );
	if ( ! $new_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_db_error', 'message' => __( 'Fehler beim Kopieren.', 'special-olympics-extension' ) ), 500 );
	}
	wp_send_json_success( array(
		'action_id' => $new_id,
		'redirect'  => admin_url( 'admin.php?page=soe-contact-action-edit&id=' . $new_id ),
	) );
}

/**
 * AJAX: Search contact CPT by title (live search for adding contacts).
 *
 * POST: q (search term), action_id (to exclude already-active contacts), nonce.
 */
function soe_ajax_ca_search_contacts() {
	soe_ca_check_ajax_access();

	$q         = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
	$action_id = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;

	if ( strlen( $q ) < 2 ) {
		wp_send_json_success( array( 'results' => array() ) );
	}

	// Fetch already-active contact IDs to exclude from results.
	$exclude_ids = array();
	if ( $action_id ) {
		$active_items = soe_db_ca_items_get( $action_id );
		foreach ( $active_items as $item ) {
			$exclude_ids[] = (int) $item['contact_id'];
		}
	}

	$args = array(
		'post_type'      => 'contact',
		'post_status'    => 'publish',
		's'              => $q,
		'posts_per_page' => 20,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	);
	if ( function_exists( 'soe_contact_meta_query_active_only' ) ) {
		$args['meta_query'] = soe_contact_meta_query_active_only();
	}
	if ( ! empty( $exclude_ids ) ) {
		$args['post__not_in'] = $exclude_ids;
	}

	$query   = new WP_Query( $args );
	$results = array();
	foreach ( $query->posts as $post_id ) {
		$results[] = array(
			'id'    => (int) $post_id,
			'title' => get_the_title( $post_id ),
		);
	}
	wp_send_json_success( array( 'results' => $results ) );
}

/**
 * AJAX: Filter contact items for an action.
 *
 * POST: action_id, status (optional), field_id (optional), field_value (optional, '0'/'1'/''),
 *       search (optional contact name).
 * Returns: items[], values{item_id: {field_id: value}}.
 */
function soe_ajax_ca_filter_items() {
	soe_ca_check_ajax_access();

	$action_id   = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$status      = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	$field_id    = isset( $_POST['field_id'] ) ? (int) $_POST['field_id'] : 0;
	$field_value = isset( $_POST['field_value'] ) ? sanitize_text_field( wp_unslash( $_POST['field_value'] ) ) : '';
	$search      = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

	if ( ! $action_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Aktion.', 'special-olympics-extension' ) ), 400 );
	}

	$rows = soe_db_ca_filter_items( $action_id, array(
		'status'      => $status,
		'field_id'    => $field_id,
		'field_value' => $field_value,
		'search'      => $search,
	) );

	// Build flat values map: item_id -> field_id -> value.
	$items  = array();
	$values = array();
	foreach ( $rows as $row ) {
		$iid = (int) $row['item_id'];
		if ( ! isset( $values[ $iid ] ) ) {
			$items[]        = array(
				'item_id'              => $iid,
				'contact_id'           => (int) $row['contact_id'],
				'contact_name'         => $row['contact_name'],
				'contact_is_archived'  => ! empty( $row['contact_is_archived'] ),
				'status'               => $row['status'],
				'note'                 => $row['note'],
			);
			$values[ $iid ] = array();
		}
		if ( ! empty( $row['fid'] ) ) {
			$values[ $iid ][ (int) $row['fid'] ] = $row['fval'];
		}
	}

	// Enrich with all field values for returned items (not just the filtered field).
	if ( ! empty( $items ) ) {
		$item_ids = array_column( $items, 'item_id' );
		$all_vals = soe_db_ca_values_for_items( $item_ids );
		foreach ( $all_vals as $v ) {
			$values[ (int) $v['item_id'] ][ (int) $v['field_id'] ] = $v['value'];
		}
	}

	wp_send_json_success( array( 'items' => $items, 'values' => $values ) );
}

/**
 * AJAX: Import contacts from another action into the current one.
 *
 * POST: action_id (target), source_action_id, filter_status (optional, 'open'/'done'/'cancelled').
 */
function soe_ajax_ca_import_from_action() {
	soe_ca_check_ajax_access();

	$action_id        = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	$source_action_id = isset( $_POST['source_action_id'] ) ? (int) $_POST['source_action_id'] : 0;
	$filter_status    = isset( $_POST['filter_status'] ) ? sanitize_key( wp_unslash( $_POST['filter_status'] ) ) : '';

	if ( ! $action_id || ! $source_action_id || $action_id === $source_action_id ) {
		wp_send_json_error( array( 'code' => 'soe_ca_invalid', 'message' => __( 'Ungültige Angaben.', 'special-olympics-extension' ) ), 400 );
	}
	if ( ! soe_db_ca_action_get( $action_id ) || ! soe_db_ca_action_get( $source_action_id ) ) {
		wp_send_json_error( array( 'code' => 'soe_ca_not_found', 'message' => __( 'Aktion nicht gefunden.', 'special-olympics-extension' ) ), 404 );
	}

	global $wpdb;
	$items_table = soe_table_contact_action_items();

	$sql    = "SELECT contact_id FROM $items_table WHERE action_id = %d AND is_active = 1";
	$params = array( $source_action_id );
	$allowed_statuses = array( 'open', 'done', 'cancelled' );
	if ( $filter_status && in_array( $filter_status, $allowed_statuses, true ) ) {
		$sql    .= ' AND status = %s';
		$params[] = $filter_status;
	}
	$contact_ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );

	// Load all contact IDs already active in the target action so we can count correctly.
	$existing_ids = array_map( 'intval', $wpdb->get_col(
		$wpdb->prepare(
			"SELECT contact_id FROM $items_table WHERE action_id = %d AND is_active = 1",
			$action_id
		)
	) );
	$existing_set = array_flip( $existing_ids );

	$added   = 0;
	$skipped = 0;
	foreach ( $contact_ids as $cid ) {
		$cid = (int) $cid;
		if ( isset( $existing_set[ $cid ] ) ) {
			$skipped++;
			continue;
		}
		if ( function_exists( 'soe_is_contact_active' ) && ! soe_is_contact_active( $cid ) ) {
			$skipped++;
			continue;
		}
		$result = soe_db_ca_item_add( $action_id, $cid );
		if ( $result > 0 ) {
			$added++;
			// Keep the set up to date so duplicate contact_ids within the source don't double-count.
			$existing_set[ $cid ] = true;
		} else {
			$skipped++;
		}
	}

	wp_send_json_success( array(
		'added'   => $added,
		'skipped' => $skipped,
		'message' => sprintf(
			/* translators: %1$d = added, %2$d = skipped */
			__( '%1$d Kontakte übernommen, %2$d bereits vorhanden.', 'special-olympics-extension' ),
			$added,
			$skipped
		),
	) );
}

// ---------------------------------------------------------------------------
// Export admin_post handlers and helpers
// ---------------------------------------------------------------------------

/**
 * Returns filtered item rows for export, each row containing all field values.
 *
 * @param int   $action_id Action ID.
 * @param array $filters   Keys: status, field_id, field_value, search.
 * @return array  Each element: item_id, contact_id, contact_name, status, note,
 *                plus one key per active field_key.
 */
function soe_db_ca_export_rows( $action_id, $filters = array() ) {
	$action_id = (int) $action_id;
	if ( ! $action_id ) {
		return array();
	}

	// Get all active fields for column headers.
	$fields = soe_db_ca_fields_get( $action_id );

	// Get filtered items.
	$items_raw = soe_db_ca_filter_items( $action_id, $filters );

	// Collect unique item IDs.
	$seen     = array();
	$item_ids = array();
	foreach ( $items_raw as $row ) {
		$iid = (int) $row['item_id'];
		if ( ! isset( $seen[ $iid ] ) ) {
			$seen[ $iid ] = $row;
			$item_ids[]   = $iid;
		}
	}
	if ( empty( $item_ids ) ) {
		return array();
	}

	// Load all field values for these items.
	$all_vals   = soe_db_ca_values_for_items( $item_ids );
	$values_map = array();
	foreach ( $all_vals as $v ) {
		$values_map[ (int) $v['item_id'] ][ (int) $v['field_id'] ] = $v['value'];
	}

	// Build flat export rows.
	$rows = array();
	foreach ( $item_ids as $iid ) {
		$raw  = $seen[ $iid ];
		$row  = array(
			'contact_name' => $raw['contact_name'],
			'status'       => $raw['status'],
			'note'         => $raw['note'],
		);
		foreach ( $fields as $f ) {
			$fid         = (int) $f['id'];
			$row[ $f['field_label'] ] = isset( $values_map[ $iid ][ $fid ] ) ? $values_map[ $iid ][ $fid ] : '';
		}
		$rows[] = $row;
	}
	return $rows;
}

/**
 * Shared filtered item query (used by filter AJAX and export).
 *
 * @param int   $action_id Action ID.
 * @param array $filters   Keys: status (string), field_id (int), field_value ('0'/'1'/''), search (string).
 * @return array Raw rows: item_id, contact_id, contact_name, contact_is_archived, status, note, fid, fval.
 */
function soe_db_ca_filter_items( $action_id, $filters = array() ) {
	global $wpdb;
	$items_table  = soe_table_contact_action_items();
	$values_table = soe_table_contact_action_item_values();

	$status      = ! empty( $filters['status'] ) ? $filters['status'] : '';
	$field_id    = ! empty( $filters['field_id'] ) ? (int) $filters['field_id'] : 0;
	$field_value = isset( $filters['field_value'] ) && $filters['field_value'] !== '' ? $filters['field_value'] : null;
	$search      = ! empty( $filters['search'] ) ? $filters['search'] : '';

	$allowed_statuses = array( 'open', 'done', 'cancelled' );

	// Only JOIN item_values when filtering by a specific field+value combination.
	$join_active = $field_id && $field_value !== null;

	$join   = '';
	$where  = array( 'i.action_id = %d', 'i.is_active = 1', "p.post_status = 'publish'" );
	$params = array();
	if ( defined( 'SOE_CONTACT_STATUS_ARCHIVED' ) && defined( 'SOE_CONTACT_STATUS_META' ) ) {
		$params[] = SOE_CONTACT_STATUS_ARCHIVED;
		$params[] = SOE_CONTACT_STATUS_META;
	} else {
		$params[] = 'archived';
		$params[] = 'soe_contact_status';
	}
	$params[] = (int) $action_id;

	if ( $join_active ) {
		$join    = $wpdb->prepare(
			" LEFT JOIN $values_table iv ON iv.item_id = i.id AND iv.field_id = %d",
			$field_id
		);
		// Treat missing value rows as '0' (= not activated). Works correctly for both '0' and '1'.
		$where[]  = 'COALESCE(iv.value, %s) = %s';
		$params[] = '0';
		$params[] = $field_value;
	}
	if ( $status && in_array( $status, $allowed_statuses, true ) ) {
		$where[]  = 'i.status = %s';
		$params[] = $status;
	}
	if ( $search !== '' ) {
		$where[]  = 'p.post_title LIKE %s';
		$params[] = '%' . $wpdb->esc_like( $search ) . '%';
	}

	$where_sql = implode( ' AND ', $where );

	// Only select iv columns when the JOIN is active to avoid "Unknown column" errors.
	$iv_select = $join_active ? 'iv.field_id AS fid, iv.value AS fval' : 'NULL AS fid, NULL AS fval';

	$sql = "SELECT i.id AS item_id, i.contact_id, i.status, i.note,
		p.post_title AS contact_name,
		CASE WHEN pm.meta_value = %s THEN 1 ELSE 0 END AS contact_is_archived,
		$iv_select
	FROM $items_table i
	INNER JOIN {$wpdb->posts} p ON p.ID = i.contact_id
	LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = i.contact_id AND pm.meta_key = %s
	$join
	WHERE $where_sql
	ORDER BY p.post_title ASC";

	return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
}

/**
 * Returns all item_values rows for a set of item IDs.
 *
 * @param int[] $item_ids Array of item IDs.
 * @return array
 */
function soe_db_ca_values_for_items( $item_ids ) {
	global $wpdb;
	if ( empty( $item_ids ) ) {
		return array();
	}
	$table       = soe_table_contact_action_item_values();
	$placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT item_id, field_id, value FROM $table WHERE item_id IN ($placeholders)",
			$item_ids
		),
		ARRAY_A
	);
}

/**
 * admin_post handler: export contact action items as CSV.
 */
function soe_ca_export_csv_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'soe_ca_export' ) ) {
		wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}

	$action_id = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	if ( ! $action_id ) {
		wp_die( esc_html__( 'Ungültige Aktion.', 'special-olympics-extension' ) );
	}
	$action = soe_db_ca_action_get( $action_id );
	if ( ! $action ) {
		wp_die( esc_html__( 'Aktion nicht gefunden.', 'special-olympics-extension' ) );
	}

	$filters = array(
		'status'      => isset( $_POST['filter_status'] ) ? sanitize_key( wp_unslash( $_POST['filter_status'] ) ) : '',
		'field_id'    => isset( $_POST['filter_field_id'] ) ? (int) $_POST['filter_field_id'] : 0,
		'field_value' => isset( $_POST['filter_field_value'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_field_value'] ) ) : '',
		'search'      => isset( $_POST['filter_search'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_search'] ) ) : '',
	);

	$rows     = soe_db_ca_export_rows( $action_id, $filters );
	$filename = 'aktion-' . sanitize_file_name( $action['title'] ) . '-' . date( 'Y-m-d' ) . '.csv';

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );
	// UTF-8 BOM so Excel opens the file correctly.
	echo "\xEF\xBB\xBF";

	$out = fopen( 'php://output', 'w' );
	if ( ! empty( $rows ) ) {
		fputcsv( $out, array_map( 'soe_escape_spreadsheet_scalar', array_keys( $rows[0] ) ), ';' );
		foreach ( $rows as $row ) {
			// Map checkbox values to human-readable text.
			$mapped = array_map(
				function ( $v ) {
					if ( $v === '1' ) {
						return soe_escape_spreadsheet_scalar( 'ja' );
					}
					if ( $v === '0' ) {
						return soe_escape_spreadsheet_scalar( 'nein' );
					}
					return soe_escape_spreadsheet_scalar( $v );
				},
				$row
			);
			fputcsv( $out, $mapped, ';' );
		}
	}
	fclose( $out );
	exit;
}

/**
 * admin_post handler: export contact action items as Excel (.xlsx).
 */
function soe_ca_export_xlsx_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}
	$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'soe_ca_export' ) ) {
		wp_die( esc_html__( 'Sicherheitsprüfung fehlgeschlagen.', 'special-olympics-extension' ), '', array( 'response' => 403 ) );
	}

	$autoload = dirname( dirname( __FILE__ ) ) . '/vendor/autoload.php';
	if ( ! file_exists( $autoload ) ) {
		wp_die( esc_html__( 'Excel-Export nicht verfügbar (PhpSpreadsheet fehlt).', 'special-olympics-extension' ) );
	}
	require_once $autoload;

	$action_id = isset( $_POST['action_id'] ) ? (int) $_POST['action_id'] : 0;
	if ( ! $action_id ) {
		wp_die( esc_html__( 'Ungültige Aktion.', 'special-olympics-extension' ) );
	}
	$action = soe_db_ca_action_get( $action_id );
	if ( ! $action ) {
		wp_die( esc_html__( 'Aktion nicht gefunden.', 'special-olympics-extension' ) );
	}

	$filters = array(
		'status'      => isset( $_POST['filter_status'] ) ? sanitize_key( wp_unslash( $_POST['filter_status'] ) ) : '',
		'field_id'    => isset( $_POST['filter_field_id'] ) ? (int) $_POST['filter_field_id'] : 0,
		'field_value' => isset( $_POST['filter_field_value'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_field_value'] ) ) : '',
		'search'      => isset( $_POST['filter_search'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_search'] ) ) : '',
	);

	$rows     = soe_db_ca_export_rows( $action_id, $filters );
	$filename = 'aktion-' . sanitize_file_name( $action['title'] ) . '-' . date( 'Y-m-d' ) . '.xlsx';

	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet       = $spreadsheet->getActiveSheet();
	$sheet->setTitle( mb_substr( sanitize_text_field( $action['title'] ), 0, 31 ) );

	if ( ! empty( $rows ) ) {
		$headers = array_keys( $rows[0] );
		$col     = 1;
		foreach ( $headers as $h ) {
			$sheet->setCellValueByColumnAndRow( $col++, 1, soe_escape_spreadsheet_scalar( $h ) );
		}
		// Bold header row.
		$last_col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex( count( $headers ) );
		$sheet->getStyle( 'A1:' . $last_col . '1' )->getFont()->setBold( true );

		$r = 2;
		foreach ( $rows as $row ) {
			$col = 1;
			foreach ( $row as $v ) {
				if ( $v === '1' ) {
					$v = 'ja';
				} elseif ( $v === '0' ) {
					$v = 'nein';
				}
				$sheet->setCellValueByColumnAndRow( $col++, $r, soe_escape_spreadsheet_scalar( $v ) );
			}
			$r++;
		}
		// Auto-size columns.
		foreach ( range( 1, count( $headers ) ) as $ci ) {
			$sheet->getColumnDimensionByColumn( $ci )->setAutoSize( true );
		}
	}

	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate' );

	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
	$writer->save( 'php://output' );
	exit;
}

// ---------------------------------------------------------------------------
// E. Admin UI rendering
// ---------------------------------------------------------------------------

/**
 * Formats per-contact item status counts for the actions list "Fortschritt" column.
 *
 * @param array $action Row from soe_db_ca_action_list() with item_count, open_count, done_count, cancelled_count.
 * @return string
 */
function soe_ca_format_contact_action_progress_label( array $action ) {
	$item = (int) ( $action['item_count'] ?? 0 );
	if ( $item <= 0 ) {
		return '—';
	}
	$open      = (int) ( $action['open_count'] ?? 0 );
	$done      = (int) ( $action['done_count'] ?? 0 );
	$cancelled = (int) ( $action['cancelled_count'] ?? 0 );
	$parts     = array();
	if ( $done > 0 ) {
		$parts[] = sprintf(
			/* translators: %d: number of contacts marked done */
			_n( '%d erledigt', '%d erledigt', $done, 'special-olympics-extension' ),
			$done
		);
	}
	if ( $open > 0 ) {
		$parts[] = sprintf(
			/* translators: %d: number of contacts still open */
			_n( '%d offen', '%d offen', $open, 'special-olympics-extension' ),
			$open
		);
	}
	if ( $cancelled > 0 ) {
		$parts[] = sprintf(
			/* translators: %d: number of contacts marked cancelled */
			_n( '%d abgebrochen', '%d abgebrochen', $cancelled, 'special-olympics-extension' ),
			$cancelled
		);
	}
	if ( empty( $parts ) ) {
		return '—';
	}
	return implode( ' · ', $parts );
}

/**
 * Renders the contact actions list page.
 */
function soe_render_contact_actions_list() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'special-olympics-extension' ) );
	}

	$raw_view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
	$view      = in_array( $raw_view, array( 'running', 'completed', 'all' ), true ) ? $raw_view : 'running';

	$filter_type = isset( $_GET['action_type'] ) ? sanitize_text_field( wp_unslash( $_GET['action_type'] ) ) : '';
	$filter_type = soe_ca_validate_action_type_slug( $filter_type );
	$filter_year = isset( $_GET['action_year'] ) ? (int) $_GET['action_year'] : 0;

	$list_args = array(
		'action_type' => $filter_type,
		'action_year' => $filter_year ? $filter_year : null,
	);
	if ( 'running' === $view ) {
		$list_args['status'] = 'active';
	} elseif ( 'completed' === $view ) {
		$list_args['status'] = 'completed';
	}

	$actions = soe_db_ca_action_list( $list_args );

	$action_type_terms  = soe_ca_get_action_type_terms();
	$responsible_users = soe_ca_get_responsible_user_candidates();

	$current_year = (int) date( 'Y' );
	$year_options = range( $current_year - 3, $current_year + 2 );

	$list_base = admin_url( 'admin.php?page=soe-contact-actions' );
	$link_args = function ( $v ) use ( $filter_type, $filter_year ) {
		$args = array( 'view' => $v );
		if ( $filter_type ) {
			$args['action_type'] = $filter_type;
		}
		if ( $filter_year ) {
			$args['action_year'] = $filter_year;
		}
		return add_query_arg( $args, admin_url( 'admin.php?page=soe-contact-actions' ) );
	};
	$has_filters = ( $filter_type || $filter_year || 'running' !== $view );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Kontakt-Aktionen', 'special-olympics-extension' ); ?></h1>
		<button type="button" class="page-title-action" id="soe-ca-new-btn"><?php esc_html_e( 'Neue Aktion', 'special-olympics-extension' ); ?></button>
		<hr class="wp-header-end">

		<?php if ( isset( $_GET['created'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Aktion erstellt.', 'special-olympics-extension' ); ?></p></div>
		<?php endif; ?>

		<div class="soe-ca-list-filter-wrap">
			<div class="soe-ca-list-filter-tabs">
				<ul class="subsubsub">
					<li>
						<a href="<?php echo esc_url( $link_args( 'running' ) ); ?>" class="<?php echo 'running' === $view ? 'current' : ''; ?>">
							<?php esc_html_e( 'Laufende Aktionen', 'special-olympics-extension' ); ?>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( $link_args( 'completed' ) ); ?>" class="<?php echo 'completed' === $view ? 'current' : ''; ?>">
							<?php esc_html_e( 'Abgeschlossene Aktionen', 'special-olympics-extension' ); ?>
						</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( $link_args( 'all' ) ); ?>" class="<?php echo 'all' === $view ? 'current' : ''; ?>">
							<?php esc_html_e( 'Alle', 'special-olympics-extension' ); ?>
						</a>
					</li>
				</ul>
			</div>

			<!-- Filter bar (second row: type, year, buttons) -->
			<div class="soe-ca-list-filter-form">
		<form method="get">
			<input type="hidden" name="page" value="soe-contact-actions">
			<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
			<select name="action_type">
				<option value=""><?php esc_html_e( 'Alle Typen', 'special-olympics-extension' ); ?></option>
				<?php foreach ( $action_type_terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $filter_type, $term->slug ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<select name="action_year">
				<option value=""><?php esc_html_e( 'Alle Jahre', 'special-olympics-extension' ); ?></option>
				<?php foreach ( $year_options as $y ) : ?>
					<option value="<?php echo $y; ?>" <?php selected( $filter_year, $y ); ?>><?php echo (int) $y; ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="button"><?php esc_html_e( 'Filtern', 'special-olympics-extension' ); ?></button>
			<?php if ( $has_filters ) : ?>
				<a href="<?php echo esc_url( $list_base ); ?>" class="button">
					<?php esc_html_e( 'Zurücksetzen', 'special-olympics-extension' ); ?>
				</a>
			<?php endif; ?>
		</form>
			</div>
		</div>

		<!-- New action inline form -->
		<div id="soe-ca-new-form" style="display:none;background:#fff;border:1px solid #c3c4c7;padding:16px;margin:16px 0;max-width:680px;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Neue Aktion', 'special-olympics-extension' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="soe-ca-new-title"><?php esc_html_e( 'Titel', 'special-olympics-extension' ); ?></label></th>
					<td><input type="text" id="soe-ca-new-title" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="soe-ca-new-type"><?php esc_html_e( 'Typ', 'special-olympics-extension' ); ?></label></th>
					<td>
						<select id="soe-ca-new-type">
							<option value=""><?php esc_html_e( '— Bitte wählen —', 'special-olympics-extension' ); ?></option>
							<?php foreach ( $action_type_terms as $term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select>
						<?php if ( current_user_can( 'manage_options' ) && taxonomy_exists( SOE_CONTACT_ACTION_TYPE_TAXONOMY ) ) : ?>
							<p class="description" style="margin-top:8px;">
								<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . SOE_CONTACT_ACTION_TYPE_TAXONOMY . '&post_type=contact' ) ); ?>"><?php esc_html_e( 'Aktionstypen verwalten', 'special-olympics-extension' ); ?></a>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="soe-ca-new-year"><?php esc_html_e( 'Jahr', 'special-olympics-extension' ); ?></label></th>
					<td><input type="number" id="soe-ca-new-year" class="small-text" min="2000" max="2100" value="<?php echo (int) $current_year; ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="soe-ca-new-due"><?php esc_html_e( 'Fälligkeitsdatum', 'special-olympics-extension' ); ?></label></th>
					<td><input type="text" id="soe-ca-new-due" class="regular-text soe-ca-flatpickr-date" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><label for="soe-ca-new-responsible"><?php esc_html_e( 'Verantwortlich', 'special-olympics-extension' ); ?></label></th>
					<td>
						<select id="soe-ca-new-responsible">
							<option value=""><?php esc_html_e( '— Niemand zugewiesen —', 'special-olympics-extension' ); ?></option>
							<?php foreach ( $responsible_users as $u ) : ?>
								<option value="<?php echo (int) $u->ID; ?>"><?php echo esc_html( $u->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="soe-ca-new-desc"><?php esc_html_e( 'Beschreibung', 'special-olympics-extension' ); ?></label></th>
					<td><textarea id="soe-ca-new-desc" rows="2" class="large-text"></textarea></td>
				</tr>
			</table>
			<p>
				<button type="button" class="button button-primary" id="soe-ca-new-submit"><?php esc_html_e( 'Erstellen', 'special-olympics-extension' ); ?></button>
				<button type="button" class="button" id="soe-ca-new-cancel"><?php esc_html_e( 'Abbrechen', 'special-olympics-extension' ); ?></button>
				<span class="soe-ca-msg" style="margin-left:8px;"></span>
			</p>
		</div>

		<?php if ( empty( $actions ) ) : ?>
			<p><?php esc_html_e( 'Keine Aktionen gefunden.', 'special-olympics-extension' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Titel', 'special-olympics-extension' ); ?></th>
						<th style="width:110px;"><?php esc_html_e( 'Typ', 'special-olympics-extension' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Jahr', 'special-olympics-extension' ); ?></th>
						<th style="width:110px;"><?php esc_html_e( 'Status', 'special-olympics-extension' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Fälligkeit', 'special-olympics-extension' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Felder', 'special-olympics-extension' ); ?></th>
						<th style="width:70px;"><?php esc_html_e( 'Kontakte', 'special-olympics-extension' ); ?></th>
						<th style="min-width:11rem;"><?php esc_html_e( 'Fortschritt', 'special-olympics-extension' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $actions as $action ) :
						$edit_url   = admin_url( 'admin.php?page=soe-contact-action-edit&id=' . (int) $action['id'] );
						$item_count = (int) $action['item_count'];
						$due_fmt    = ! empty( $action['due_date'] ) ? date_i18n( 'd.m.Y', strtotime( $action['due_date'] ) ) : '—';
						?>
						<tr>
							<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $action['title'] ); ?></a></td>
							<td><?php echo $action['action_type'] ? soe_ca_action_type_label( $action['action_type'] ) : esc_html( '—' ); ?></td>
							<td><?php echo $action['action_year'] ? (int) $action['action_year'] : '—'; ?></td>
							<td><?php echo esc_html( soe_ca_action_status_label( $action['status'] ) ); ?></td>
							<td><?php echo esc_html( $due_fmt ); ?></td>
							<td><?php echo (int) $action['field_count']; ?></td>
							<td><?php echo $item_count; ?></td>
							<td class="soe-ca-progress"><?php echo esc_html( soe_ca_format_contact_action_progress_label( $action ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renders the contact action edit/detail page (fields + items matrix).
 */
function soe_render_contact_action_edit() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Keine Berechtigung.', 'special-olympics-extension' ) );
	}

	$action_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
	if ( ! $action_id ) {
		wp_safe_redirect( admin_url( 'admin.php?page=soe-contact-actions' ) );
		exit;
	}

	$action = soe_db_ca_action_get( $action_id );
	if ( ! $action ) {
		wp_die( esc_html__( 'Aktion nicht gefunden.', 'special-olympics-extension' ) );
	}

	$fields          = soe_db_ca_fields_get( $action_id );
	$items           = soe_db_ca_items_get( $action_id );
	$values          = soe_db_ca_values_get_for_action( $action_id );
	$action_type_terms = soe_ca_get_action_type_terms();
	$action_statuses   = soe_ca_allowed_action_statuses();
	$responsible_users = soe_ca_get_responsible_user_candidates();
	$all_actions       = soe_db_ca_action_list();

	$current_action_status = isset( $action['status'] ) ? (string) $action['status'] : 'active';
	if ( ! in_array( $current_action_status, $action_statuses, true ) ) {
		$current_action_status = 'active';
	}

	$list_url        = admin_url( 'admin.php?page=soe-contact-actions' );
	$export_nonce    = wp_create_nonce( 'soe_ca_export' );

	$item_status_labels = array(
		'open'      => __( 'Offen', 'special-olympics-extension' ),
		'done'      => __( 'Erledigt', 'special-olympics-extension' ),
		'cancelled' => __( 'Abgebrochen', 'special-olympics-extension' ),
	);
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline">
			<a href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Aktionen', 'special-olympics-extension' ); ?></a>
			&rsaquo;
			<span id="soe-ca-title-display"><?php echo esc_html( $action['title'] ); ?></span>
		</h1>
		<hr class="wp-header-end">

		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Gespeichert.', 'special-olympics-extension' ); ?></p></div>
		<?php endif; ?>

		<div class="soe-ca-edit-layout">
			<div class="soe-ca-edit-main soe-ca-edit-order-mobile">
				<div class="soe-ca-toolbar">
					<button type="button" class="button" id="soe-ca-copy-btn" data-action-id="<?php echo (int) $action_id; ?>">
						<?php esc_html_e( 'Aktion kopieren', 'special-olympics-extension' ); ?>
					</button>
					<button type="button" class="button" id="soe-ca-import-action-btn">
						<?php esc_html_e( 'Kontakte aus Aktion übernehmen', 'special-olympics-extension' ); ?>
					</button>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
						<input type="hidden" name="action" value="soe_ca_export_csv">
						<input type="hidden" name="action_id" value="<?php echo (int) $action_id; ?>">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $export_nonce ); ?>">
						<input type="hidden" name="filter_status" class="soe-ca-export-filter-status">
						<input type="hidden" name="filter_field_id" class="soe-ca-export-filter-field-id">
						<input type="hidden" name="filter_field_value" class="soe-ca-export-filter-field-value">
						<input type="hidden" name="filter_search" class="soe-ca-export-filter-search">
						<button type="submit" class="button"><?php esc_html_e( 'CSV exportieren', 'special-olympics-extension' ); ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
						<input type="hidden" name="action" value="soe_ca_export_xlsx">
						<input type="hidden" name="action_id" value="<?php echo (int) $action_id; ?>">
						<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $export_nonce ); ?>">
						<input type="hidden" name="filter_status" class="soe-ca-export-filter-status">
						<input type="hidden" name="filter_field_id" class="soe-ca-export-filter-field-id">
						<input type="hidden" name="filter_field_value" class="soe-ca-export-filter-field-value">
						<input type="hidden" name="filter_search" class="soe-ca-export-filter-search">
						<button type="submit" class="button"><?php esc_html_e( 'Excel exportieren', 'special-olympics-extension' ); ?></button>
					</form>
				</div>

				<div class="soe-ca-section">
					<div class="soe-ca-contacts-panel">
					<h2><?php esc_html_e( 'Kontakte', 'special-olympics-extension' ); ?></h2>

					<!-- Filter bar -->
					<div id="soe-ca-filter-bar" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px;">
				<label>
					<?php esc_html_e( 'Status:', 'special-olympics-extension' ); ?>
					<select id="soe-ca-filter-status">
						<option value=""><?php esc_html_e( 'Alle', 'special-olympics-extension' ); ?></option>
						<?php foreach ( $item_status_labels as $s_key => $s_label ) : ?>
							<option value="<?php echo esc_attr( $s_key ); ?>"><?php echo esc_html( $s_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php if ( ! empty( $fields ) ) : ?>
				<label>
					<?php esc_html_e( 'Feld:', 'special-olympics-extension' ); ?>
					<select id="soe-ca-filter-field-id">
						<option value=""><?php esc_html_e( 'Alle Felder', 'special-olympics-extension' ); ?></option>
						<?php foreach ( $fields as $f ) : ?>
							<option value="<?php echo (int) $f['id']; ?>"><?php echo esc_html( $f['field_label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label id="soe-ca-filter-value-wrap" style="display:none;">
					<?php esc_html_e( 'Wert:', 'special-olympics-extension' ); ?>
					<select id="soe-ca-filter-field-value">
						<option value=""><?php esc_html_e( 'Alle', 'special-olympics-extension' ); ?></option>
						<option value="1"><?php esc_html_e( 'Aktiviert', 'special-olympics-extension' ); ?></option>
						<option value="0"><?php esc_html_e( 'Nicht aktiviert', 'special-olympics-extension' ); ?></option>
					</select>
				</label>
				<?php endif; ?>
				<label>
					<input type="search" id="soe-ca-filter-search" placeholder="<?php esc_attr_e( 'Kontakt suchen…', 'special-olympics-extension' ); ?>" style="width:200px;">
				</label>
				<button type="button" class="button" id="soe-ca-filter-reset"><?php esc_html_e( 'Zurücksetzen', 'special-olympics-extension' ); ?></button>
				<span id="soe-ca-filter-count" style="color:#646970;font-size:13px;"></span>
			</div>

			<?php if ( empty( $items ) && empty( $fields ) ) : ?>
				<p><?php esc_html_e( 'Bitte zuerst Felder hinzufügen.', 'special-olympics-extension' ); ?></p>
			<?php elseif ( empty( $items ) ) : ?>
				<p><?php esc_html_e( 'Noch keine Kontakte in dieser Aktion.', 'special-olympics-extension' ); ?></p>
			<?php else : ?>
				<div class="soe-ca-matrix-wrap">
					<table class="soe-ca-matrix" id="soe-ca-items-table"
						data-action-id="<?php echo (int) $action_id; ?>">
						<thead>
							<tr>
								<th class="soe-ca-contact-col"><?php esc_html_e( 'Kontakt', 'special-olympics-extension' ); ?></th>
								<th class="soe-ca-matrix-col-status"><?php esc_html_e( 'Status', 'special-olympics-extension' ); ?></th>
								<?php foreach ( $fields as $field ) : ?>
									<th class="soe-ca-matrix-field-th" title="<?php echo esc_attr( $field['field_key'] ); ?>"
										data-field-id="<?php echo (int) $field['id']; ?>">
										<?php echo esc_html( $field['field_label'] ); ?>
									</th>
								<?php endforeach; ?>
								<th class="soe-ca-matrix-col-note"><?php esc_html_e( 'Notiz', 'special-olympics-extension' ); ?></th>
								<th class="soe-ca-matrix-col-remove" aria-hidden="true"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $item ) :
								$iid        = (int) $item['id'];
								$item_vals  = isset( $values[ $iid ] ) ? $values[ $iid ] : array();
								?>
								<tr data-item-id="<?php echo $iid; ?>">
									<td class="soe-ca-contact-cell">
										<a class="soe-ca-contact-link" href="<?php echo esc_url( admin_url( 'post.php?action=edit&post=' . (int) $item['contact_id'] ) ); ?>">
											<?php echo esc_html( $item['contact_title'] ); ?>
										</a>
										<?php if ( ! empty( $item['contact_is_archived'] ) ) : ?>
											<span class="soe-ca-archived-badge"><?php esc_html_e( 'Archiviert', 'special-olympics-extension' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="soe-ca-matrix-col-status">
										<select class="soe-ca-status-select"
											data-item-id="<?php echo $iid; ?>"
											data-action-id="<?php echo (int) $action_id; ?>">
											<?php foreach ( $item_status_labels as $s_key => $s_label ) : ?>
												<option value="<?php echo esc_attr( $s_key ); ?>" <?php selected( $item['status'], $s_key ); ?>>
													<?php echo esc_html( $s_label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<?php foreach ( $fields as $field ) :
										$fid     = (int) $field['id'];
										$checked = isset( $item_vals[ $fid ] ) && $item_vals[ $fid ] === '1';
										?>
										<td class="soe-ca-val">
											<?php if ( $field['field_type'] === 'checkbox' ) : ?>
												<input type="checkbox"
													class="soe-ca-checkbox"
													data-action-id="<?php echo (int) $action_id; ?>"
													data-item-id="<?php echo $iid; ?>"
													data-field-id="<?php echo $fid; ?>"
													<?php checked( $checked ); ?>>
											<?php else : ?>
												<input type="text"
													class="soe-ca-text-val small-text"
													value="<?php echo esc_attr( isset( $item_vals[ $fid ] ) ? $item_vals[ $fid ] : '' ); ?>"
													data-action-id="<?php echo (int) $action_id; ?>"
													data-item-id="<?php echo $iid; ?>"
													data-field-id="<?php echo $fid; ?>">
											<?php endif; ?>
										</td>
									<?php endforeach; ?>
									<td class="soe-ca-note-cell">
										<span class="soe-ca-note-cell-inner">
											<input type="text" class="soe-ca-note-input"
												value="<?php echo esc_attr( $item['note'] ); ?>"
												placeholder="<?php esc_attr_e( 'Notiz…', 'special-olympics-extension' ); ?>"
												data-item-id="<?php echo $iid; ?>"
												data-action-id="<?php echo (int) $action_id; ?>">
											<span class="spinner soe-ca-note-spinner" aria-hidden="true"></span>
										</span>
									</td>
									<td class="soe-ca-matrix-col-remove">
										<button type="button" class="soe-ca-remove-item soe-ca-matrix-remove-btn"
											data-item-id="<?php echo $iid; ?>"
											data-action-id="<?php echo (int) $action_id; ?>"
											aria-label="<?php esc_attr_e( 'Entfernen', 'special-olympics-extension' ); ?>">
											<span class="soe-ca-matrix-remove-x" aria-hidden="true">&times;</span>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<!-- Contact search & add -->
			<div class="soe-ca-contact-search-wrap">
				<div style="position:relative;">
					<input type="text" id="soe-ca-contact-search" class="regular-text"
						placeholder="<?php esc_attr_e( 'Kontakt hinzufügen…', 'special-olympics-extension' ); ?>"
						data-action-id="<?php echo (int) $action_id; ?>"
						autocomplete="off">
					<div id="soe-ca-contact-results" class="soe-ca-contact-search-results" style="display:none;"></div>
				</div>
				<button type="button" class="button button-primary" id="soe-ca-add-contacts-btn"
					data-action-id="<?php echo (int) $action_id; ?>" disabled>
					<?php esc_html_e( 'Hinzufügen', 'special-olympics-extension' ); ?>
				</button>
				<span class="soe-ca-contact-msg"></span>
			</div>
					</div><!-- .soe-ca-contacts-panel -->
				</div><!-- .soe-ca-section contacts -->

			</div><!-- .soe-ca-edit-main -->

			<div class="soe-ca-edit-sidebar">
				<div id="soe-ca-header-form" style="background:#fff;border:1px solid #c3c4c7;padding:16px;margin:0 0 16px;box-sizing:border-box;max-width:100%;">
					<h2 class="soe-ca-header-form-title"><?php esc_html_e( 'Grunddaten', 'special-olympics-extension' ); ?></h2>
					<table class="form-table soe-ca-header-form-table" role="presentation">
						<tr>
							<th scope="row"><label for="soe-ca-edit-title"><?php esc_html_e( 'Titel', 'special-olympics-extension' ); ?></label></th>
							<td><input type="text" id="soe-ca-edit-title" class="regular-text" value="<?php echo esc_attr( $action['title'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="soe-ca-edit-type"><?php esc_html_e( 'Typ', 'special-olympics-extension' ); ?></label></th>
							<td>
								<select id="soe-ca-edit-type">
									<option value=""><?php esc_html_e( '— Bitte wählen —', 'special-olympics-extension' ); ?></option>
									<?php foreach ( $action_type_terms as $term ) : ?>
										<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $action['action_type'], $term->slug ); ?>>
											<?php echo esc_html( $term->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( taxonomy_exists( SOE_CONTACT_ACTION_TYPE_TAXONOMY ) ) : ?>
									<p class="description" style="margin-top:8px;">
										<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . SOE_CONTACT_ACTION_TYPE_TAXONOMY . '&post_type=contact' ) ); ?>"><?php esc_html_e( 'Aktionstypen verwalten', 'special-olympics-extension' ); ?></a>
									</p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="soe-ca-edit-year"><?php esc_html_e( 'Jahr', 'special-olympics-extension' ); ?></label></th>
							<td><input type="number" id="soe-ca-edit-year" class="small-text" min="2000" max="2100" value="<?php echo esc_attr( $action['action_year'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="soe-ca-edit-status"><?php esc_html_e( 'Aktionsstatus', 'special-olympics-extension' ); ?></label></th>
							<td>
								<select id="soe-ca-edit-status">
									<?php foreach ( $action_statuses as $s ) : ?>
										<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $current_action_status, $s ); ?>>
											<?php echo esc_html( soe_ca_action_status_label( $s ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="soe-ca-edit-due"><?php esc_html_e( 'Fälligkeitsdatum', 'special-olympics-extension' ); ?></label></th>
							<td><input type="text" id="soe-ca-edit-due" class="regular-text soe-ca-flatpickr-date" value="<?php echo esc_attr( ! empty( $action['due_date'] ) ? (string) $action['due_date'] : '' ); ?>" autocomplete="off"></td>
						</tr>
						<tr>
							<th scope="row"><label for="soe-ca-edit-responsible"><?php esc_html_e( 'Verantwortlich', 'special-olympics-extension' ); ?></label></th>
							<td>
								<select id="soe-ca-edit-responsible">
									<option value=""><?php esc_html_e( '— Niemand zugewiesen —', 'special-olympics-extension' ); ?></option>
									<?php foreach ( $responsible_users as $u ) : ?>
										<option value="<?php echo (int) $u->ID; ?>" <?php selected( (int) $action['responsible_user_id'], (int) $u->ID ); ?>>
											<?php echo esc_html( $u->display_name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="soe-ca-edit-desc"><?php esc_html_e( 'Beschreibung', 'special-olympics-extension' ); ?></label></th>
							<td><textarea id="soe-ca-edit-desc" rows="2" class="large-text"><?php echo esc_textarea( $action['description'] ); ?></textarea></td>
						</tr>
					</table>
					<p>
						<button type="button" class="button button-primary" id="soe-ca-save-header"
							data-action-id="<?php echo (int) $action_id; ?>">
							<?php esc_html_e( 'Speichern', 'special-olympics-extension' ); ?>
						</button>
						<span class="soe-ca-msg" style="margin-left:8px;"></span>
					</p>
				</div>

				<div class="soe-ca-section">
					<h2><?php esc_html_e( 'Checkboxen', 'special-olympics-extension' ); ?></h2>
					<div class="soe-ca-fields-table-wrap">
					<table class="wp-list-table widefat striped" id="soe-ca-fields-table">
						<thead>
							<tr>
								<th style="width:40px;"><?php esc_html_e( 'Reihenf.', 'special-olympics-extension' ); ?></th>
								<th><?php esc_html_e( 'Bezeichnung', 'special-olympics-extension' ); ?></th>
								<th style="width:100px;"><?php esc_html_e( 'Typ', 'special-olympics-extension' ); ?></th>
								<th style="width:100px;"><?php esc_html_e( 'Schlüssel', 'special-olympics-extension' ); ?></th>
								<th class="soe-ca-fields-col-remove" aria-hidden="true"></th>
							</tr>
						</thead>
						<tbody id="soe-ca-fields-body">
							<?php foreach ( $fields as $field ) : ?>
								<tr data-field-id="<?php echo (int) $field['id']; ?>">
									<td>
										<input type="number" class="soe-ca-sort-order small-text" value="<?php echo (int) $field['sort_order']; ?>"
											data-field-id="<?php echo (int) $field['id']; ?>"
											data-action-id="<?php echo (int) $action_id; ?>" style="width:48px;">
									</td>
									<td>
										<input type="text" class="soe-ca-field-label regular-text" value="<?php echo esc_attr( $field['field_label'] ); ?>"
											data-field-id="<?php echo (int) $field['id']; ?>"
											data-action-id="<?php echo (int) $action_id; ?>">
									</td>
									<td><?php echo esc_html( $field['field_type'] ); ?></td>
									<td><code><?php echo esc_html( $field['field_key'] ); ?></code></td>
									<td class="soe-ca-fields-col-remove">
										<button type="button" class="soe-ca-deactivate-field soe-ca-field-remove-btn"
											data-field-id="<?php echo (int) $field['id']; ?>"
											data-action-id="<?php echo (int) $action_id; ?>"
											aria-label="<?php esc_attr_e( 'Entfernen', 'special-olympics-extension' ); ?>">
											<span class="soe-ca-field-remove-x" aria-hidden="true">&times;</span>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if ( empty( $fields ) ) : ?>
								<tr id="soe-ca-no-fields-row"><td colspan="5"><?php esc_html_e( 'Noch keine Felder.', 'special-olympics-extension' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
					</div>

					<div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
						<input type="text" id="soe-ca-new-field-label" class="regular-text"
							placeholder="<?php esc_attr_e( 'Feldbezeichnung…', 'special-olympics-extension' ); ?>">
						<button type="button" class="button" id="soe-ca-add-field-btn"
							data-action-id="<?php echo (int) $action_id; ?>">
							<?php esc_html_e( 'Feld hinzufügen', 'special-olympics-extension' ); ?>
						</button>
						<span class="soe-ca-field-msg"></span>
					</div>
				</div>
			</div><!-- .soe-ca-edit-sidebar -->

		</div><!-- .soe-ca-edit-layout -->

	</div><!-- .wrap -->

	<!-- Copy action modal -->
	<div class="soe-ca-modal-backdrop" id="soe-ca-copy-backdrop"></div>
	<div class="soe-ca-modal" id="soe-ca-copy-modal">
		<h2><?php esc_html_e( 'Aktion kopieren', 'special-olympics-extension' ); ?></h2>
		<p><?php esc_html_e( 'Eine neue Aktion wird mit denselben Feldern erstellt. Werte und Notizen werden nicht übernommen.', 'special-olympics-extension' ); ?></p>
		<p>
			<label>
				<input type="checkbox" id="soe-ca-copy-contacts">
				<?php esc_html_e( 'Kontakte mitübernehmen (Status wird auf „Offen" zurückgesetzt)', 'special-olympics-extension' ); ?>
			</label>
		</p>
		<p>
			<button type="button" class="button button-primary" id="soe-ca-copy-confirm"
				data-action-id="<?php echo (int) $action_id; ?>">
				<?php esc_html_e( 'Kopieren', 'special-olympics-extension' ); ?>
			</button>
			<button type="button" class="button" id="soe-ca-copy-cancel">
				<?php esc_html_e( 'Abbrechen', 'special-olympics-extension' ); ?>
			</button>
			<span class="soe-ca-copy-msg" style="margin-left:8px;"></span>
		</p>
	</div>

	<!-- Import from action modal -->
	<div class="soe-ca-modal-backdrop" id="soe-ca-import-action-backdrop"></div>
	<div class="soe-ca-modal" id="soe-ca-import-action-modal">
		<h2><?php esc_html_e( 'Kontakte aus Aktion übernehmen', 'special-olympics-extension' ); ?></h2>
		<p><?php esc_html_e( 'Alle Kontakte der gewählten Quellaktion werden in diese Aktion übernommen. Bereits vorhandene Kontakte werden übersprungen.', 'special-olympics-extension' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="soe-ca-import-source"><?php esc_html_e( 'Quellaktion', 'special-olympics-extension' ); ?></label></th>
				<td>
					<select id="soe-ca-import-source" style="min-width:240px;">
						<option value=""><?php esc_html_e( '— Bitte wählen —', 'special-olympics-extension' ); ?></option>
						<?php foreach ( $all_actions as $a ) :
							if ( (int) $a['id'] === $action_id ) continue; ?>
							<option value="<?php echo (int) $a['id']; ?>">
								<?php
								$label = esc_html( $a['title'] );
								if ( $a['action_year'] ) $label .= ' (' . (int) $a['action_year'] . ')';
								echo $label;
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Nur Status', 'special-olympics-extension' ); ?></th>
				<td>
					<label style="margin-right:12px;">
						<input type="radio" name="soe_ca_import_status" value="" checked>
						<?php esc_html_e( 'Alle', 'special-olympics-extension' ); ?>
					</label>
					<label style="margin-right:12px;">
						<input type="radio" name="soe_ca_import_status" value="open">
						<?php esc_html_e( 'Offen', 'special-olympics-extension' ); ?>
					</label>
					<label style="margin-right:12px;">
						<input type="radio" name="soe_ca_import_status" value="done">
						<?php esc_html_e( 'Erledigt', 'special-olympics-extension' ); ?>
					</label>
					<label>
						<input type="radio" name="soe_ca_import_status" value="cancelled">
						<?php esc_html_e( 'Abgebrochen', 'special-olympics-extension' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<p>
			<button type="button" class="button button-primary" id="soe-ca-import-action-confirm"
				data-action-id="<?php echo (int) $action_id; ?>">
				<?php esc_html_e( 'Übernehmen', 'special-olympics-extension' ); ?>
			</button>
			<button type="button" class="button" id="soe-ca-import-action-cancel">
				<?php esc_html_e( 'Abbrechen', 'special-olympics-extension' ); ?>
			</button>
			<span class="soe-ca-import-action-msg" style="margin-left:8px;"></span>
		</p>
	</div>
	<?php
}

// ---------------------------------------------------------------------------
// F. Contact History Metabox
// ---------------------------------------------------------------------------

/**
 * Registers the "Aktions-Historie" metabox on the contact CPT.
 */
function soe_ca_register_contact_history_metabox() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_meta_box(
		'soe_ca_contact_history',
		__( 'Aktions-Historie', 'special-olympics-extension' ),
		'soe_ca_render_contact_history_metabox',
		'contact',
		'normal',
		'default'
	);
}

/**
 * Returns all active actions for a given contact (by WP post ID), newest first.
 *
 * @param int $contact_id WP post ID of the contact.
 * @return array
 */
function soe_db_ca_actions_for_contact( $contact_id ) {
	global $wpdb;
	$items_table   = soe_table_contact_action_items();
	$actions_table = soe_table_contact_actions();

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.id, a.title, a.action_type, a.action_year, a.status AS action_status,
				a.due_date, i.id AS item_id, i.status AS item_status, i.note, i.updated_at
			FROM $items_table i
			INNER JOIN $actions_table a ON a.id = i.action_id
			WHERE i.contact_id = %d
			  AND i.is_active = 1
			  AND a.is_active = 1
			ORDER BY a.action_year DESC, a.created_at DESC",
			(int) $contact_id
		),
		ARRAY_A
	);
}

/**
 * Renders the Aktions-Historie metabox content for a contact post.
 *
 * @param WP_Post $post The current contact post.
 */
function soe_ca_render_contact_history_metabox( $post ) {
	$rows = soe_db_ca_actions_for_contact( $post->ID );

	if ( empty( $rows ) ) {
		echo '<p>' . esc_html__( 'Dieser Kontakt ist noch in keiner Aktion erfasst.', 'special-olympics-extension' ) . '</p>';
		return;
	}

	// Load activated field values and field labels for all item IDs in one batch.
	$item_ids = array_column( $rows, 'item_id' );
	$all_vals = soe_db_ca_values_for_items( $item_ids );

	// Build map: item_id → [field_id, ...] where value = '1'.
	$active_field_ids = array();
	foreach ( $all_vals as $v ) {
		if ( $v['value'] === '1' ) {
			$active_field_ids[ (int) $v['item_id'] ][] = (int) $v['field_id'];
		}
	}

	// Load field labels per action (keyed by field id).
	$action_ids      = array_unique( array_column( $rows, 'id' ) );
	$field_labels    = array();
	$fields_table    = soe_table_contact_action_fields();
	global $wpdb;
	if ( ! empty( $action_ids ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $action_ids ), '%d' ) );
		$field_rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, field_label FROM $fields_table WHERE action_id IN ($placeholders) AND is_active = 1 ORDER BY sort_order ASC",
				$action_ids
			),
			ARRAY_A
		);
		foreach ( $field_rows as $f ) {
			$field_labels[ (int) $f['id'] ] = $f['field_label'];
		}
	}

	$item_status_labels = array(
		'open'      => __( 'Offen', 'special-olympics-extension' ),
		'done'      => __( 'Erledigt', 'special-olympics-extension' ),
		'cancelled' => __( 'Abgebrochen', 'special-olympics-extension' ),
	);
	?>
	<p class="description" style="margin-top:0;margin-bottom:10px;">
		<?php esc_html_e( '„Aktivierte Felder“ sind die angekreuzten Checkbox-Optionen für diesen Kontakt in der jeweiligen Aktion.', 'special-olympics-extension' ); ?>
		<?php esc_html_e( '„Status der Aktion“ bezieht sich auf die gesamte Aktion; „Status der Teilnahme“ nur auf diese Kontaktzeile (Offen / Erledigt / …).', 'special-olympics-extension' ); ?>
	</p>
	<table class="widefat striped" style="margin-top:4px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Aktion', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Typ', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Jahr', 'special-olympics-extension' ); ?></th>
				<th title="<?php esc_attr_e( 'Labels der Felder, die für diesen Kontakt in dieser Aktion angekreuzt (Wert „ja“) sind.', 'special-olympics-extension' ); ?>"><?php esc_html_e( 'Aktivierte Felder', 'special-olympics-extension' ); ?></th>
				<th title="<?php esc_attr_e( 'Bearbeitungsstand der gesamten Aktion (für alle Kontakte gleich).', 'special-olympics-extension' ); ?>"><?php esc_html_e( 'Status der Aktion', 'special-olympics-extension' ); ?></th>
				<th title="<?php esc_attr_e( 'Bearbeitungsstand nur dieser Kontaktzeile in der Aktion.', 'special-olympics-extension' ); ?>"><?php esc_html_e( 'Status der Teilnahme', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Fälligkeit', 'special-olympics-extension' ); ?></th>
				<th><?php esc_html_e( 'Notiz', 'special-olympics-extension' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) :
				$edit_url    = admin_url( 'admin.php?page=soe-contact-action-edit&id=' . (int) $row['id'] );
				$due_fmt     = ! empty( $row['due_date'] ) ? date_i18n( 'd.m.Y', strtotime( $row['due_date'] ) ) : '—';
				$item_status = isset( $item_status_labels[ $row['item_status'] ] ) ? $item_status_labels[ $row['item_status'] ] : esc_html( $row['item_status'] );

				// Build list of activated checkbox field labels for this item (value === '1' in DB).
				$iid            = (int) $row['item_id'];
				$active_fids    = isset( $active_field_ids[ $iid ] ) ? $active_field_ids[ $iid ] : array();
				$active_labels  = array();
				foreach ( $active_fids as $fid ) {
					if ( isset( $field_labels[ $fid ] ) ) {
						$active_labels[] = $field_labels[ $fid ];
					}
				}
				?>
				<tr>
					<td><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
					<td><?php echo $row['action_type'] ? soe_ca_action_type_label( $row['action_type'] ) : esc_html( '—' ); ?></td>
					<td><?php echo $row['action_year'] ? (int) $row['action_year'] : '—'; ?></td>
					<td>
						<?php
						if ( ! empty( $active_labels ) ) {
							echo '<ul class="soe-ca-contact-history-fields" style="margin:0.15em 0 0 1.1em;padding:0;list-style:disc;">';
							foreach ( $active_labels as $lbl ) {
								echo '<li style="margin:0.15em 0;">' . esc_html( $lbl ) . '</li>';
							}
							echo '</ul>';
						} else {
							echo esc_html( '—' );
						}
						?>
					</td>
					<td><?php echo esc_html( soe_ca_action_status_label( $row['action_status'] ) ); ?></td>
					<td><?php echo esc_html( $item_status ); ?></td>
					<td><?php echo esc_html( $due_fmt ); ?></td>
					<td><?php echo esc_html( $row['note'] ?: '—' ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
