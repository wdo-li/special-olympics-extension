/* global soeContactActionsAdmin, jQuery */
( function ( $ ) {
	'use strict';

	var cfg = window.soeContactActionsAdmin || {};
	var ajaxUrl = cfg.ajaxUrl || '';
	var nonce   = cfg.nonce   || '';
	var i18n    = cfg.i18n   || {};

	$( function () {
		if ( typeof window.flatpickr !== 'undefined' ) {
			var dateOpts = {
				locale:              'de',
				dateFormat:          'Y-m-d',
				altInput:            true,
				altFormat:           'd.m.Y',
				allowInput:          true,
				altInputPlaceholder: i18n.date_placeholder || 'TT.MM.JJJJ',
			};
			[ '#soe-ca-edit-due', '#soe-ca-new-due' ].forEach( function ( sel ) {
				var el = document.querySelector( sel );
				if ( el && ! el.disabled && ! el.getAttribute( 'data-soe-fp-init' ) ) {
					window.flatpickr( el, dateOpts );
					el.setAttribute( 'data-soe-fp-init', '1' );
				}
			} );
		}
	} );

	// Selected contacts waiting to be added (id → title map).
	var pendingContacts = {};

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Posts to admin-ajax.php with the shared nonce.
	 *
	 * @param {string}   action   wp_ajax action name.
	 * @param {Object}   data     POST data (without action/nonce).
	 * @param {Function} done     Success callback (receives response.data).
	 * @param {Function} [fail]   Error callback (receives response.data or jqXHR).
	 */
	function ajax( action, data, done, fail ) {
		data.action = action;
		data.nonce  = nonce;
		$.post( ajaxUrl, data )
			.done( function ( res ) {
				if ( res && res.success ) {
					if ( done ) { done( res.data ); }
				} else {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : ( i18n.error || 'Fehler.' );
					if ( fail ) { fail( msg ); } else { window.alert( msg ); }
				}
			} )
			.fail( function ( jqXHR ) {
				var msg = i18n.error || 'Fehler.';
				if ( fail ) { fail( msg ); } else { window.alert( msg ); }
			} );
	}

	/**
	 * Shows a temporary status message next to a given element.
	 *
	 * @param {jQuery} $el   The element to show the message near.
	 * @param {string} msg   Message text.
	 * @param {string} type  'success' or 'error'.
	 */
	function showMsg( $el, msg, type ) {
		$el.text( msg ).css( 'color', type === 'error' ? '#d63638' : '#00a32a' );
		setTimeout( function () { $el.text( '' ); }, 3000 );
	}

	/**
	 * Reads checkbox-field sort_order inputs from the edit sidebar (field_id → int).
	 *
	 * @return {Object.<string, number>}
	 */
	function collectFieldSortOrders() {
		var map = {};
		$( '.soe-ca-sort-order' ).each( function () {
			var $el = $( this );
			var fid = $el.attr( 'data-field-id' );
			if ( ! fid ) {
				return;
			}
			var o = parseInt( $el.val(), 10 );
			if ( ! isNaN( o ) ) {
				map[ fid ] = o;
			}
		} );
		return map;
	}

	/**
	 * Persists a single field's sort_order (shared by blur/change handlers).
	 *
	 * @param {jQuery} $input Sort order input.
	 */
	function persistFieldSortOrder( $input ) {
		var field_id  = $input.attr( 'data-field-id' );
		var action_id = $input.attr( 'data-action-id' );
		var order     = parseInt( $input.val(), 10 );
		if ( ! field_id || ! action_id || isNaN( order ) ) {
			return;
		}
		ajax(
			'soe_ca_update_field',
			{ action_id: action_id, field_id: field_id, sort_order: order },
			function () {},
			function ( msg ) {
				window.alert( msg );
			}
		);
	}

	// -----------------------------------------------------------------------
	// List page: new action form
	// -----------------------------------------------------------------------

	$( '#soe-ca-new-btn' ).on( 'click', function () {
		$( '#soe-ca-new-form' ).slideDown( 150 );
		$( '#soe-ca-new-title' ).trigger( 'focus' );
	} );

	$( '#soe-ca-new-cancel' ).on( 'click', function () {
		$( '#soe-ca-new-form' ).slideUp( 150 );
	} );

	$( '#soe-ca-new-submit' ).on( 'click', function () {
		var $btn  = $( this );
		var title = $( '#soe-ca-new-title' ).val().trim();
		var desc  = $( '#soe-ca-new-desc' ).val().trim();
		var type  = $( '#soe-ca-new-type' ).val();
		var year  = $( '#soe-ca-new-year' ).val().trim();
		var due   = $( '#soe-ca-new-due' ).val();
		var $msg  = $( '#soe-ca-new-form .soe-ca-msg' );

		if ( ! title ) {
			showMsg( $msg, i18n.title_required || 'Titel darf nicht leer sein.', 'error' );
			return;
		}

		$btn.prop( 'disabled', true ).text( i18n.adding || '…' );

		ajax( 'soe_ca_save_action', {
				action_id:           0,
				title:               title,
				description:         desc,
				action_type:         type,
				action_year:         year,
				due_date:            due,
				responsible_user_id: $( '#soe-ca-new-responsible' ).val() || '',
			},
			function ( data ) {
				if ( data.redirect ) {
					window.location.href = data.redirect;
				}
			},
			function ( msg ) {
				showMsg( $msg, msg, 'error' );
				$btn.prop( 'disabled', false ).text( i18n.create || 'Erstellen' );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Edit page: save action header (title + description)
	// -----------------------------------------------------------------------

	$( '#soe-ca-save-header' ).on( 'click', function () {
		var $btn      = $( this );
		var $msg      = $btn.closest( 'p' ).find( '.soe-ca-msg' );
		var action_id = $btn.data( 'action-id' );
		var title     = $( '#soe-ca-edit-title' ).val().trim();
		var desc      = $( '#soe-ca-edit-desc' ).val().trim();
		var type      = $( '#soe-ca-edit-type' ).val();
		var year      = $( '#soe-ca-edit-year' ).val().trim();
		var status    = $( '#soe-ca-edit-status' ).val();
		var due       = $( '#soe-ca-edit-due' ).val();
		var resp      = $( '#soe-ca-edit-responsible' ).val();

		if ( ! title ) {
			showMsg( $msg, i18n.title_required || 'Titel darf nicht leer sein.', 'error' );
			return;
		}

		$btn.prop( 'disabled', true );
		var fieldSortMap = collectFieldSortOrders();
		ajax( 'soe_ca_save_action', {
				action_id:           action_id,
				title:               title,
				description:         desc,
				action_type:         type,
				action_year:         year,
				status:              status,
				due_date:            due,
				responsible_user_id: resp,
				field_sort_json:     JSON.stringify( fieldSortMap ),
			},
			function () {
				$( '#soe-ca-title-display' ).text( title );
				showMsg( $msg, i18n.saved || 'Gespeichert.', 'success' );
				$btn.prop( 'disabled', false );
			},
			function ( msg ) {
				showMsg( $msg, msg, 'error' );
				$btn.prop( 'disabled', false );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Edit page: fields – add
	// -----------------------------------------------------------------------

	$( '#soe-ca-add-field-btn' ).on( 'click', function () {
		var $btn      = $( this );
		var action_id = $btn.data( 'action-id' );
		var label     = $( '#soe-ca-new-field-label' ).val().trim();
		var $msg      = $btn.closest( 'div' ).find( '.soe-ca-field-msg' );

		if ( ! label ) {
			showMsg( $msg, 'Bitte Bezeichnung eingeben.', 'error' );
			return;
		}

		$btn.prop( 'disabled', true );
		ajax( 'soe_ca_add_field', { action_id: action_id, field_label: label },
			function () {
				// Reload so the new matrix column appears immediately.
				window.location.reload();
			},
			function ( msg ) {
				showMsg( $msg, msg, 'error' );
				$btn.prop( 'disabled', false );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Edit page: fields – deactivate (delegated for dynamically added rows)
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.soe-ca-deactivate-field', function () {
		if ( ! window.confirm( i18n.confirmDeactivate || 'Wirklich deaktivieren?' ) ) {
			return;
		}
		var $btn      = $( this );
		var field_id  = $btn.data( 'field-id' );
		var action_id = $btn.data( 'action-id' );

		$btn.prop( 'disabled', true );
		ajax( 'soe_ca_deactivate_field', { action_id: action_id, field_id: field_id },
			function ( data ) {
				renderFields( data.fields, action_id );
				// Reload to rebuild matrix columns.
				window.location.reload();
			},
			function ( msg ) {
				window.alert( msg );
				$btn.prop( 'disabled', false );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Edit page: fields – update label (blur) and sort_order (blur)
	// -----------------------------------------------------------------------

	$( document ).on( 'blur', '.soe-ca-field-label', function () {
		var $input    = $( this );
		var field_id  = $input.data( 'field-id' );
		var action_id = $input.data( 'action-id' );
		var label     = $input.val().trim();

		if ( ! label ) { return; }

		ajax( 'soe_ca_update_field', { action_id: action_id, field_id: field_id, field_label: label },
			function () {
				// Reload so the renamed column header is immediately visible in the matrix.
				window.location.reload();
			},
			function ( msg ) { window.alert( msg ); }
		);
	} );

	$( document ).on( 'change', '.soe-ca-sort-order', function () {
		persistFieldSortOrder( $( this ) );
	} );

	// -----------------------------------------------------------------------
	// Edit page: matrix – checkbox (inline, optimistic UI)
	// -----------------------------------------------------------------------

	$( document ).on( 'change', '.soe-ca-checkbox', function () {
		var $cb       = $( this );
		var action_id = $cb.data( 'action-id' );
		var item_id   = $cb.data( 'item-id' );
		var field_id  = $cb.data( 'field-id' );
		var newVal    = $cb.is( ':checked' ) ? '1' : '0';
		var prevVal   = newVal === '1' ? '0' : '1';

		$cb.addClass( 'is-saving' ).prop( 'disabled', true );

		ajax( 'soe_ca_set_value', { action_id: action_id, item_id: item_id, field_id: field_id, value: newVal },
			function () {
				$cb.removeClass( 'is-saving' ).prop( 'disabled', false );
			},
			function ( msg ) {
				// Rollback optimistic UI.
				$cb.prop( 'checked', prevVal === '1' )
					.removeClass( 'is-saving' )
					.prop( 'disabled', false );
				window.alert( msg );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Edit page: matrix – text value (blur)
	// -----------------------------------------------------------------------

	$( document ).on( 'blur', '.soe-ca-text-val', function () {
		var $input    = $( this );
		var action_id = $input.data( 'action-id' );
		var item_id   = $input.data( 'item-id' );
		var field_id  = $input.data( 'field-id' );
		var value     = $input.val();

		ajax( 'soe_ca_set_value', { action_id: action_id, item_id: item_id, field_id: field_id, value: value },
			function () {},
			function ( msg ) { window.alert( msg ); }
		);
	} );

	// -----------------------------------------------------------------------
	// Edit page: matrix – status select
	// -----------------------------------------------------------------------

	$( document ).on( 'change', '.soe-ca-status-select', function () {
		var $sel      = $( this );
		var action_id = $sel.data( 'action-id' );
		var item_id   = $sel.data( 'item-id' );
		var status    = $sel.val();
		var prevVal   = $sel.data( 'soe-prev-status' );
		if ( prevVal === undefined ) {
			prevVal = $sel.val();
		}

		ajax( 'soe_ca_set_item_status', { action_id: action_id, item_id: item_id, status: status },
			function () {
				$sel.data( 'soe-prev-status', status );
			},
			function ( msg ) {
				$sel.val( prevVal );
				window.alert( msg );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Edit page: matrix – note input (blur, per-row debounce; spinner while saving)
	// -----------------------------------------------------------------------

	$( document ).on( 'blur', '.soe-ca-note-input', function () {
		var $input = $( this );
		var prevT  = $input.data( 'soeNoteTimer' );
		if ( prevT ) {
			window.clearTimeout( prevT );
		}
		var delay = 200;
		var timer = window.setTimeout( function () {
			$input.removeData( 'soeNoteTimer' );
			var action_id = $input.attr( 'data-action-id' );
			var item_id   = $input.attr( 'data-item-id' );
			var note      = $input.val();
			var $cell     = $input.closest( '.soe-ca-note-cell' );
			var $spin     = $cell.find( '.soe-ca-note-spinner' );
			$spin.addClass( 'is-active' );
			$input.prop( 'readonly', true );
			ajax(
				'soe_ca_set_item_note',
				{ action_id: action_id, item_id: item_id, note: note },
				function () {
					$spin.removeClass( 'is-active' );
					$input.prop( 'readonly', false );
				},
				function ( msg ) {
					$spin.removeClass( 'is-active' );
					$input.prop( 'readonly', false );
					window.alert( msg );
				}
			);
		}, delay );
		$input.data( 'soeNoteTimer', timer );
	} );

	// -----------------------------------------------------------------------
	// Edit page: matrix – remove item
	// -----------------------------------------------------------------------

	$( document ).on( 'click', '.soe-ca-remove-item', function () {
		if ( ! window.confirm( i18n.confirmDeactivate || 'Wirklich entfernen?' ) ) {
			return;
		}
		var $btn      = $( this );
		var action_id = $btn.attr( 'data-action-id' );
		var item_id   = $btn.attr( 'data-item-id' );
		var $row      = $btn.closest( 'tr' );

		$btn.prop( 'disabled', true );
		ajax( 'soe_ca_deactivate_item', { action_id: action_id, item_id: item_id },
			function () {
				$row.fadeOut( 200, function () { $row.remove(); } );
			},
			function ( msg ) {
				window.alert( msg );
				$btn.prop( 'disabled', false );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Edit page: contact search
	// -----------------------------------------------------------------------

	var searchTimer = null;

	$( '#soe-ca-contact-search' ).on( 'input', function () {
		var $input    = $( this );
		var q         = $input.val().trim();
		var action_id = $input.data( 'action-id' );
		var $results  = $( '#soe-ca-contact-results' );

		clearTimeout( searchTimer );
		if ( q.length < 2 ) {
			$results.hide().empty();
			return;
		}

		searchTimer = setTimeout( function () {
			ajax( 'soe_ca_search_contacts', { q: q, action_id: action_id },
				function ( data ) {
					$results.empty();
					if ( ! data.results || ! data.results.length ) {
						$results.append( $( '<div>' ).addClass( 'soe-ca-contact-result' ).text( i18n.noResults || 'Keine Ergebnisse.' ) );
					} else {
						$.each( data.results, function ( i, r ) {
							var $item = $( '<div>' )
								.addClass( 'soe-ca-contact-result' )
								.text( r.title )
								.data( 'id', r.id )
								.data( 'title', r.title );
							$results.append( $item );
						} );
					}
					$results.show();
				},
				function () {
					$results.hide().empty();
				}
			);
		}, 250 );
	} );

	$( document ).on( 'click', '.soe-ca-contact-result', function () {
		var $item  = $( this );
		var id     = $item.data( 'id' );
		var title  = $item.data( 'title' );

		if ( ! id || ! title ) { return; }

		pendingContacts[ id ] = title;
		$( '#soe-ca-contact-search' ).val( '' );
		$( '#soe-ca-contact-results' ).hide().empty();
		updatePendingDisplay();
	} );

	// Hide results when clicking outside.
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '#soe-ca-contact-search, #soe-ca-contact-results' ).length ) {
			$( '#soe-ca-contact-results' ).hide();
		}
	} );

	function updatePendingDisplay() {
		var ids = Object.keys( pendingContacts );
		var $btn = $( '#soe-ca-add-contacts-btn' );
		$btn.prop( 'disabled', ids.length === 0 );
		if ( ids.length ) {
			var names = ids.map( function ( id ) { return pendingContacts[ id ]; } ).join( ', ' );
			$( '.soe-ca-contact-msg' ).text( names );
		} else {
			$( '.soe-ca-contact-msg' ).text( '' );
		}
	}

	$( '#soe-ca-add-contacts-btn' ).on( 'click', function () {
		var $btn      = $( this );
		var action_id = $btn.data( 'action-id' );
		var ids       = Object.keys( pendingContacts );

		if ( ! ids.length ) { return; }

		$btn.prop( 'disabled', true ).text( i18n.adding || '…' );

		ajax( 'soe_ca_add_items', { action_id: action_id, 'contact_ids[]': ids },
			function () {
				pendingContacts = {};
				$( '.soe-ca-contact-msg' ).text( '' );
				// Reload to rebuild matrix rows.
				window.location.reload();
			},
			function ( msg ) {
				window.alert( msg );
				$btn.prop( 'disabled', false ).text( 'Hinzufügen' );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Edit page: copy action modal
	// -----------------------------------------------------------------------

	$( '#soe-ca-copy-btn' ).on( 'click', function () {
		$( '#soe-ca-copy-backdrop, #soe-ca-copy-modal' ).fadeIn( 150 );
	} );

	$( '#soe-ca-copy-cancel, #soe-ca-copy-backdrop' ).on( 'click', function () {
		$( '#soe-ca-copy-backdrop, #soe-ca-copy-modal' ).fadeOut( 150 );
		$( '#soe-ca-copy-contacts' ).prop( 'checked', false );
		$( '.soe-ca-copy-msg' ).text( '' );
	} );

	$( '#soe-ca-copy-confirm' ).on( 'click', function () {
		var $btn             = $( this );
		var action_id        = $btn.data( 'action-id' );
		var include_contacts = $( '#soe-ca-copy-contacts' ).is( ':checked' ) ? 1 : 0;
		var $msg             = $( '.soe-ca-copy-msg' );

		$btn.prop( 'disabled', true );

		ajax( 'soe_ca_copy_action', { action_id: action_id, include_contacts: include_contacts },
			function ( data ) {
				if ( data.redirect ) {
					window.location.href = data.redirect;
				}
			},
			function ( msg ) {
				showMsg( $msg, msg, 'error' );
				$btn.prop( 'disabled', false );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Matrix filter
	// -----------------------------------------------------------------------

	var $filterStatus     = $( '#soe-ca-filter-status' );
	var $filterFieldId    = $( '#soe-ca-filter-field-id' );
	var $filterFieldValue = $( '#soe-ca-filter-field-value' );
	var $filterValueWrap  = $( '#soe-ca-filter-value-wrap' );
	var $filterSearch     = $( '#soe-ca-filter-search' );
	var $filterCount      = $( '#soe-ca-filter-count' );
	var filterSearchTimer = null;

	/**
	 * Reads the current filter state and syncs it into the export-form hidden inputs.
	 */
	function syncExportFilters() {
		$( '.soe-ca-export-filter-status' ).val( $filterStatus.val() || '' );
		$( '.soe-ca-export-filter-field-id' ).val( $filterFieldId.val() || '' );
		$( '.soe-ca-export-filter-field-value' ).val( $filterFieldValue.val() || '' );
		$( '.soe-ca-export-filter-search' ).val( $filterSearch.val() || '' );
	}

	/**
	 * Reads current filter state and triggers the AJAX filter call.
	 */
	function applyFilter() {
		var $table    = $( '#soe-ca-items-table' );
		if ( ! $table.length ) { return; }
		var action_id = $table.data( 'action-id' );

		var params = {
			action_id:   action_id,
			status:      $filterStatus.val() || '',
			field_id:    $filterFieldId.val() || '',
			field_value: $filterFieldValue.val() || '',
			search:      $filterSearch.val() || '',
		};

		syncExportFilters();

		ajax( 'soe_ca_filter_items', params,
			function ( data ) {
				renderMatrix( data.items, data.values );
				var count = data.items ? data.items.length : 0;
				$filterCount.text( count + ' ' + ( i18n.contacts || 'Kontakte' ) );
			},
			function ( msg ) {
				window.alert( msg );
			}
		);
	}

	/**
	 * Rebuilds the tbody of #soe-ca-items-table from AJAX response data.
	 *
	 * @param {Array}  items  Array of item objects (item_id, contact_id, contact_name, status, note).
	 * @param {Object} values Map: item_id -> { field_id: value }.
	 */
	function renderMatrix( items, values ) {
		var $table = $( '#soe-ca-items-table' );
		if ( ! $table.length ) { return; }
		var action_id = $table.data( 'action-id' );

		// Collect ordered field IDs from the header.
		var fieldIds = [];
		$table.find( 'thead th[data-field-id]' ).each( function () {
			fieldIds.push( parseInt( $( this ).data( 'field-id' ), 10 ) );
		} );

		var statusLabels = {
			'open':      i18n.status_open      || 'Offen',
			'done':      i18n.status_done      || 'Erledigt',
			'cancelled': i18n.status_cancelled || 'Abgebrochen',
		};
		var statusOptions = '';
		$.each( statusLabels, function ( k, v ) {
			statusOptions += '<option value="' + k + '">' + v + '</option>';
		} );

		var $tbody = $table.find( 'tbody' );
		$tbody.empty();

		if ( ! items || ! items.length ) {
			var colspan = 3 + fieldIds.length;
			$tbody.append( '<tr><td colspan="' + colspan + '">' + ( i18n.no_results || 'Keine Ergebnisse.' ) + '</td></tr>' );
			return;
		}

		$.each( items, function ( i, item ) {
			var iid       = item.item_id;
			var itemVals  = values[ iid ] || {};
			var $tr       = $( '<tr>' );
			var contactUrl = cfg.editContactBase ? cfg.editContactBase + item.contact_id : '#';

			// Contact name cell (plain admin link styling).
			var $link = $( '<a>' ).addClass( 'soe-ca-contact-link' ).attr( 'href', contactUrl ).text( item.contact_name );
			var $nameWrap = $( '<span>' );
			$nameWrap.append( $link );
			if ( item.contact_is_archived ) {
				$nameWrap.append(
					$( '<span>' ).addClass( 'soe-ca-archived-badge' ).text( i18n.archived_badge || 'Archiviert' )
				);
			}
			$tr.append(
				$( '<td>' ).addClass( 'soe-ca-contact-cell' ).append( $nameWrap )
			);

			// Status cell.
			var $sel = $( '<select>' )
				.addClass( 'soe-ca-status-select' )
				.attr( 'data-item-id', iid )
				.attr( 'data-action-id', action_id )
				.append( statusOptions );
			$sel.val( item.status );
			$sel.data( 'soe-prev-status', item.status );
			$tr.append( $( '<td>' ).addClass( 'soe-ca-matrix-col-status' ).append( $sel ) );

			// Field value cells.
			$.each( fieldIds, function ( fi, fid ) {
				var val  = itemVals[ fid ] !== undefined ? itemVals[ fid ] : '0';
				var $cb  = $( '<input>' ).attr( { type: 'checkbox' } )
					.addClass( 'soe-ca-checkbox' )
					.prop( 'checked', val === '1' )
					.data( { 'item-id': iid, 'field-id': fid, 'action-id': action_id } );
				$tr.append( $( '<td>' ).addClass( 'soe-ca-val' ).append( $cb ) );
			} );

			// Note cell (spinner matches server markup).
			var $note = $( '<input>' ).attr( {
				type: 'text',
				placeholder: i18n.note_placeholder || 'Notiz…',
				'data-item-id': iid,
				'data-action-id': action_id,
			} )
				.addClass( 'soe-ca-note-input' )
				.val( item.note || '' );
			var $spin = $( '<span>' ).addClass( 'spinner soe-ca-note-spinner' ).attr( 'aria-hidden', 'true' );
			var $inner = $( '<span>' ).addClass( 'soe-ca-note-cell-inner' ).append( $note, $spin );
			$tr.append( $( '<td>' ).addClass( 'soe-ca-note-cell' ).append( $inner ) );

			// Remove control (red ×, same pattern as field definitions table).
			var $removeBtn = $( '<button>' ).attr( {
				type: 'button',
				class: 'soe-ca-remove-item soe-ca-matrix-remove-btn',
				'aria-label': i18n.remove || 'Remove',
				'data-item-id': iid,
				'data-action-id': action_id,
			} ).append(
				$( '<span>' ).addClass( 'soe-ca-matrix-remove-x' ).attr( 'aria-hidden', 'true' ).html( '&times;' )
			);
			$tr.append( $( '<td>' ).addClass( 'soe-ca-matrix-col-remove' ).append( $removeBtn ) );

			$tbody.append( $tr );
		} );
	}

	// Bind filter controls.
	if ( $filterStatus.length ) {
		$filterStatus.on( 'change', applyFilter );
		$filterFieldId.on( 'change', function () {
			var hasField = !! $( this ).val();
			$filterValueWrap.toggle( hasField );
			if ( ! hasField ) {
				$filterFieldValue.val( '' );
			}
			applyFilter();
		} );
		$filterFieldValue.on( 'change', applyFilter );
		$filterSearch.on( 'input', function () {
			clearTimeout( filterSearchTimer );
			filterSearchTimer = setTimeout( applyFilter, 350 );
		} );
		$( '#soe-ca-filter-reset' ).on( 'click', function () {
			$filterStatus.val( '' );
			$filterFieldId.val( '' );
			$filterFieldValue.val( '' );
			$filterValueWrap.hide();
			$filterSearch.val( '' );
			$filterCount.text( '' );
			syncExportFilters();
			// Reload page to restore full matrix without AJAX overhead.
			window.location.reload();
		} );
		// Init export filter state.
		syncExportFilters();
	}

	if ( $( '#soe-ca-items-table' ).length ) {
		$( '#soe-ca-items-table .soe-ca-status-select' ).each( function () {
			var $s = $( this );
			$s.data( 'soe-prev-status', $s.val() );
		} );
	}

	// -----------------------------------------------------------------------
	// Import from action modal
	// -----------------------------------------------------------------------

	$( '#soe-ca-import-action-btn' ).on( 'click', function () {
		$( '#soe-ca-import-action-backdrop, #soe-ca-import-action-modal' ).fadeIn( 150 );
	} );

	$( '#soe-ca-import-action-cancel, #soe-ca-import-action-backdrop' ).on( 'click', function () {
		$( '#soe-ca-import-action-backdrop, #soe-ca-import-action-modal' ).fadeOut( 150 );
		$( '.soe-ca-import-action-msg' ).text( '' );
	} );

	$( '#soe-ca-import-action-confirm' ).on( 'click', function () {
		var $btn           = $( this );
		var action_id      = $btn.data( 'action-id' );
		var source_id      = $( '#soe-ca-import-source' ).val();
		var filter_status  = $( 'input[name="soe_ca_import_status"]:checked' ).val();
		var $msg           = $( '.soe-ca-import-action-msg' );

		if ( ! source_id ) {
			showMsg( $msg, i18n.select_source || 'Bitte eine Quellaktion wählen.', 'error' );
			return;
		}

		$btn.prop( 'disabled', true ).text( i18n.adding || '…' );

		ajax( 'soe_ca_import_from_action', {
				action_id:        action_id,
				source_action_id: source_id,
				filter_status:    filter_status || '',
			},
			function ( data ) {
				showMsg( $msg, data.message, 'success' );
				$btn.prop( 'disabled', false ).text( i18n.import_confirm || 'Übernehmen' );
				// Reload to reflect newly added contacts in the matrix.
				setTimeout( function () { window.location.reload(); }, 1200 );
			},
			function ( msg ) {
				showMsg( $msg, msg, 'error' );
				$btn.prop( 'disabled', false ).text( i18n.import_confirm || 'Übernehmen' );
			}
		);
	} );

	// -----------------------------------------------------------------------
	// Helper: re-render fields table body from server data
	// -----------------------------------------------------------------------

	function renderFields( fields, action_id ) {
		var $tbody = $( '#soe-ca-fields-body' );
		if ( ! $tbody.length ) { return; }
		$tbody.empty();
		if ( ! fields || ! fields.length ) {
			$tbody.append( '<tr id="soe-ca-no-fields-row"><td colspan="5">Noch keine Felder.</td></tr>' );
			return;
		}
		$.each( fields, function ( i, f ) {
			var $tr = $( '<tr>' ).attr( 'data-field-id', f.id );
			$tr.append(
				$( '<td>' ).append(
					$( '<input>' ).attr( {
						type: 'number',
						style: 'width:48px;',
						class: 'soe-ca-sort-order small-text',
						'data-field-id': f.id,
						'data-action-id': action_id,
					} )
						.val( f.sort_order )
				),
				$( '<td>' ).append(
					$( '<input>' ).attr( {
						type: 'text',
						class: 'soe-ca-field-label regular-text',
						'data-field-id': f.id,
						'data-action-id': action_id,
					} )
						.val( f.field_label )
				),
				$( '<td>' ).text( f.field_type ),
				$( '<td>' ).append( $( '<code>' ).text( f.field_key ) ),
				$( '<td>' ).addClass( 'soe-ca-fields-col-remove' ).append(
					$( '<button>' )
						.attr( {
							type: 'button',
							class: 'soe-ca-deactivate-field soe-ca-field-remove-btn',
							'aria-label': i18n.remove || 'Remove',
							'data-field-id': f.id,
							'data-action-id': action_id,
						} )
						.append(
							$( '<span>' )
								.addClass( 'soe-ca-field-remove-x' )
								.attr( 'aria-hidden', 'true' )
								.html( '&times;' )
						)
				)
			);
			$tbody.append( $tr );
		} );
	}

} )( jQuery );
