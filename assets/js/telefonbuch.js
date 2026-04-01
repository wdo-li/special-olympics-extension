/**
 * Telefonbuch: DataTables on "Alle Daten" with Column Visibility and Expand/Detail row.
 */
(function ($) {
	'use strict';

	$(function () {
		var $table = $('#soe-telefonbuch-table');
		if (!$table.length || !$.fn.DataTable) {
			return;
		}

		var details = window.soeTelefonbuchDetails || {};
		var detailCol = typeof window.soeTelefonbuchDetailCol !== 'undefined' ? window.soeTelefonbuchDetailCol : 16;
		var maxColIdx = typeof window.soeTelefonbuchMaxColIndex !== 'undefined' ? window.soeTelefonbuchMaxColIndex : 16;
		var defaultVisible = window.soeTelefonbuchDefaultVisible || [1, 2, 3, 4, 5, 6, 7, 8, 13, 14, detailCol];
		var storageKey = 'soe_telefonbuch_visible_columns_v6';
		var alwaysVisibleCols = [detailCol];

		function getStoredVisible() {
			try {
				var stored = localStorage.getItem(storageKey);
				if (stored) {
					var arr = JSON.parse(stored);
					if (Array.isArray(arr) && arr.length > 0) {
						return arr;
					}
				}
			} catch (e) {}
			return null;
		}

		function saveVisible(indexes) {
			try {
				localStorage.setItem(storageKey, JSON.stringify(indexes));
			} catch (e) {}
		}

		function getVisibleFromCheckboxes() {
			var indexes = [];
			$('.soe-colvis-cb').each(function () {
				if ($(this).prop('checked')) {
					indexes.push(parseInt($(this).data('column'), 10));
				}
			});
			// Always include alwaysVisibleCols
			alwaysVisibleCols.forEach(function (col) {
				if (indexes.indexOf(col) === -1) {
					indexes.push(col);
				}
			});
			return indexes;
		}

		function applyVisible(indexes) {
			dt.columns().every(function () {
				var colIdx = this.index();
				var isAlwaysVisible = alwaysVisibleCols.indexOf(colIdx) !== -1;
				this.visible(isAlwaysVisible || indexes.indexOf(colIdx) !== -1, false);
			});
			dt.columns.adjust().draw(false);
		}

		function syncCheckboxesToVisible() {
			dt.columns().every(function () {
				var colIdx = this.index();
				var cb = $('.soe-colvis-cb[data-column="' + colIdx + '"]');
				if (cb.length) {
					cb.prop('checked', this.visible());
				}
			});
		}

		var initialVisible = getStoredVisible() || defaultVisible;

		var dt = $table.DataTable({
			order: [[2, 'asc']],
			pageLength: 500,
			lengthMenu: [[25, 100, 250, 500], [25, 100, 250, 500]],
			scrollX: true,
			columnDefs: (function () {
				var defs = [
					{ visible: false, orderable: false, targets: [0] }
				];
				for (var i = 1; i <= maxColIdx; i++) {
					var isAlwaysVisible = alwaysVisibleCols.indexOf(i) !== -1;
					defs.push({ visible: isAlwaysVisible || initialVisible.indexOf(i) !== -1, targets: [i] });
				}
				return defs;
			})(),
			language: {
				search: 'Suchen:',
				lengthMenu: 'Zeige _MENU_ Einträge',
				info: 'Zeige _START_ bis _END_ von _TOTAL_ Einträgen',
				infoEmpty: 'Keine Einträge',
				infoFiltered: '(gefiltert von _MAX_)',
				zeroRecords: 'Keine Treffer'
			}
		});

		syncCheckboxesToVisible();

		$('.soe-colvis-cb').on('change', function () {
			var colIdx = parseInt($(this).data('column'), 10);
			dt.column(colIdx).visible($(this).prop('checked'), false);
			dt.columns.adjust().draw(false);
			saveVisible(getVisibleFromCheckboxes());
		});

		$('.soe-colvis-all').on('click', function () {
			$('.soe-colvis-cb').prop('checked', true);
			dt.columns().every(function () { this.visible(true, false); });
			dt.columns.adjust().draw(false);
			saveVisible(getVisibleFromCheckboxes());
		});

		$('.soe-colvis-default').on('click', function () {
			$('.soe-colvis-cb').each(function () {
				var colIdx = parseInt($(this).data('column'), 10);
				$(this).prop('checked', defaultVisible.indexOf(colIdx) !== -1);
			});
			applyVisible(defaultVisible);
			saveVisible(defaultVisible);
		});

		// Column filters (Sportart dropdown, Ort text)
		$('.soe-table-filter').on('change keyup', function () {
			var $el = $(this);
			var colIdx = parseInt($el.data('column'), 10);
			var val = $el.val();
			if ($el.is('select')) {
				val = val || '';
			} else {
				val = val.trim();
			}
			dt.column(colIdx).search(val).draw();
		});

		$('#soe-telefonbuch-export-form').on('submit', function (e) {
			e.preventDefault();
			var ids = [];
			dt.rows({ search: 'applied' }).every(function () {
				var id = $(this.node()).data('id');
				if (id) { ids.push(id); }
			});
			$('#soe-telefonbuch-export-ids').val(ids.join(','));
			document.getElementById('soe-telefonbuch-export-form').submit();
		});

		$('.soe-telefonbuch-copy-emails').on('click', function () {
			var $btn = $(this);
			var emails = [];
			dt.rows({ search: 'applied' }).every(function () {
				var tr = $(this.node());
				var email = tr.data('email');
				if (email && email.indexOf('@') !== -1) {
					emails.push(email);
				}
			});
			var text = emails.join('; ');
			function showHint(msg) {
				$btn.siblings('.soe-copy-hint').remove();
				var $hint = $('<span class="soe-copy-hint"></span>').text(msg);
				$btn.after($hint);
				setTimeout(function () {
					$hint.addClass('soe-copy-hint-fade');
					setTimeout(function () { $hint.remove(); }, 300);
				}, 1500);
			}
			if (text && navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					showHint('Mailadressen kopiert');
				}).catch(function () {
					showHint('Kopieren fehlgeschlagen');
				});
			} else if (text) {
				var ta = document.createElement('textarea');
				ta.value = text;
				document.body.appendChild(ta);
				ta.select();
				document.execCommand('copy');
				document.body.removeChild(ta);
				showHint('Mailadressen kopiert');
			}
		});

		$table.on('click', 'tbody tr', function (e) {
			if ($(e.target).closest('a').length || $(e.target).closest('.soe-telefonbuch-expand').length || $(e.target).closest('.soe-telefonbuch-detail-wrap').length) {
				return;
			}
			$(this).find('.soe-telefonbuch-expand').trigger('click');
		});

		$table.on('click', '.soe-telefonbuch-expand', function (e) {
			e.preventDefault();
			e.stopPropagation();
			var $btn = $(this);
			var tr = $btn.closest('tr');
			var row = dt.row(tr);
			var id = tr.data('id');
			if (row.child.isShown()) {
				row.child.hide();
				$btn.html('<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>');
				$btn.attr('aria-label', $btn.data('label-expand') || 'Detail einblenden');
			} else {
				var html = details[id] || '<p></p>';
				row.child('<div class="soe-telefonbuch-detail-wrap">' + html + '</div>').show();
				$btn.html('<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>');
				$btn.attr('aria-label', $btn.data('label-collapse') || 'Detail ausblenden');
			}
		});
	});

	function soeNotfallApplyFilters() {
		var q = ($('#soe-notfall-search').val() || '').toLowerCase().trim();
		var sportVal = ($('#soe-notfall-sport').val() || '').trim();
		$('.soe-telefonbuch-card').each(function () {
			var $card = $(this);
			var text = $card.data('search-text') || '';
			var sport = ($card.data('sport') || '').trim();
			var matchSearch = q === '' || text.indexOf(q) !== -1;
			var matchSport = sportVal === '' || (sport !== '' && sport.indexOf(sportVal) !== -1);
			$card.toggle(matchSearch && matchSport);
		});
	}
	$('#soe-notfall-search').on('input', soeNotfallApplyFilters);
	$('#soe-notfall-sport').on('change', soeNotfallApplyFilters);
})(jQuery);
