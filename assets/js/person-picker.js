/**
 * Person Picker: AJAX-based multi-select for Mitglied posts.
 *
 * Usage: Initialize with soePersonPicker.init(containerSelector, options).
 * Container must have data-field-name attribute.
 *
 * @package Special_Olympics_Extension
 */
(function($) {
	'use strict';

	window.soePersonPicker = {
		nonce: '',
		ajaxUrl: '',

		/**
		 * Initialize all person picker containers on the page.
		 */
		initAll: function() {
			var self = this;
			$('.soe-person-picker').each(function() {
				self.initContainer($(this));
			});
		},

		/**
		 * Initialize a single person picker container.
		 *
		 * @param {jQuery} $container The container element.
		 */
		initContainer: function($container) {
			var self = this;
			var fieldName = $container.data('field-name');
			var roleFilter = $container.data('role-filter') || '';
			var readonly = $container.data('readonly') === true || $container.data('readonly') === 'true';
			var singleSelect = $container.data('single') === true || $container.data('single') === 'true';
			var excludeAthletes = $container.data('exclude-athletes') === true || $container.data('exclude-athletes') === 'true';

			var $input = $container.find('.soe-pp-search');
			var $results = $container.find('.soe-pp-results');
			var $selected = $container.find('.soe-pp-selected');
			var $hidden = $container.find('.soe-pp-hidden');

			var searchTimeout = null;

			// Load initial selected items.
			var initialIds = $hidden.val();
			if (initialIds && initialIds.length > 0) {
				self.loadPersonsByIds(initialIds, function(persons) {
					persons.forEach(function(p) {
						self.addSelectedItem($container, p, readonly);
					});
				});
			}

			if (readonly) {
				$input.prop('disabled', true).attr('placeholder', 'Nur Ansicht');
				return;
			}

			// Search input handler.
			$input.on('input', function() {
				var q = $(this).val();
				clearTimeout(searchTimeout);
				if (q.length < 2) {
					$results.hide().empty();
					return;
				}
				searchTimeout = setTimeout(function() {
					self.search(q, roleFilter, self.getSelectedIdsInScope($container), excludeAthletes, function(items) {
						self.renderResults($container, items);
					});
				}, 250);
			});

			// Hide results on blur (with delay for click).
			$input.on('blur', function() {
				setTimeout(function() {
					$results.hide();
				}, 200);
			});

			// Focus shows results if there's input.
			$input.on('focus', function() {
				if ($results.children().length > 0) {
					$results.show();
				}
			});

			// Click on result item.
			$results.on('click', '.soe-pp-result-item', function(e) {
				e.preventDefault();
				var id = $(this).data('id');
				var text = $(this).data('text');
				var role = $(this).data('role');
				// In single-select mode, remove previous selection first.
				if (singleSelect) {
					$selected.empty();
				}
				self.addSelectedItem($container, { id: id, text: text, role: role }, false);
				$input.val('');
				$results.hide().empty();
				self.updateHidden($container);
			});

			// Remove selected item.
			$selected.on('click', '.soe-pp-remove', function(e) {
				e.preventDefault();
				$(this).closest('.soe-pp-item').remove();
				self.updateHidden($container);
			});
		},

		/**
		 * Search for persons via AJAX.
		 */
		search: function(q, roleFilter, exclude, excludeAthletes, callback) {
			$.ajax({
				url: this.ajaxUrl,
				method: 'POST',
				data: {
					action: 'soe_search_persons',
					nonce: this.nonce,
					q: q,
					role: roleFilter,
					exclude: exclude.join(','),
					exclude_athletes: excludeAthletes ? 'true' : ''
				},
				success: function(data) {
					callback(data || []);
				},
				error: function() {
					callback([]);
				}
			});
		},

		/**
		 * Load persons by IDs (for initial display).
		 */
		loadPersonsByIds: function(idsString, callback) {
			$.ajax({
				url: this.ajaxUrl,
				method: 'POST',
				data: {
					action: 'soe_get_persons_by_ids',
					nonce: this.nonce,
					ids: idsString
				},
				success: function(data) {
					callback(data || []);
				},
				error: function() {
					callback([]);
				}
			});
		},

		/**
		 * Render search results dropdown.
		 */
		renderResults: function($container, items) {
			var self = this;
			var $results = $container.find('.soe-pp-results');
			$results.empty();
			if (items.length === 0) {
				$results.append('<div class="soe-pp-no-results">Keine Ergebnisse</div>');
			} else {
				items.forEach(function(item) {
					// Role kept in data-role for filtering; not shown in UI.
					$results.append(
						'<div class="soe-pp-result-item" data-id="' + item.id + '" data-text="' + self.escapeHtml(item.text) + '" data-role="' + self.escapeHtml(item.role || '') + '">' +
						self.escapeHtml(item.text) +
						'</div>'
					);
				});
			}
			$results.show();
		},

		/**
		 * Add a selected item to the list.
		 */
		addSelectedItem: function($container, person, readonly) {
			var $selected = $container.find('.soe-pp-selected');
			// Check if already in this picker.
			if ($selected.find('[data-id="' + person.id + '"]').length > 0) {
				return;
			}
			// Check if already selected in another picker in the same scope (one person = one role).
			var idsInScope = this.getSelectedIdsInScope($container);
			if (idsInScope.indexOf(parseInt(person.id, 10)) !== -1) {
				return;
			}
			var removeBtn = readonly ? '' : '<button type="button" class="soe-pp-remove">&times;</button>';
			$selected.append(
				'<span class="soe-pp-item" data-id="' + person.id + '">' +
				this.escapeHtml(person.text) +
				removeBtn +
				'</span>'
			);
		},

		/**
		 * Get array of selected IDs for one container.
		 */
		getSelectedIds: function($container) {
			var ids = [];
			$container.find('.soe-pp-selected .soe-pp-item').each(function() {
				ids.push(parseInt($(this).data('id'), 10));
			});
			return ids;
		},

		/**
		 * Get the scope element: nearest ancestor that contains more than one person picker.
		 * Used so that Hauptleiter/Leiter/Athleten share one scope and exclude each other's selected IDs.
		 */
		getScopeContainer: function($container) {
			var $scope = $container.parent();
			while ($scope.length) {
				if ($scope.find('.soe-person-picker').length >= 2) {
					return $scope;
				}
				$scope = $scope.parent();
			}
			return null;
		},

		/**
		 * Get all selected IDs in the same scope (all pickers in the same group).
		 * Ensures one person can only be assigned once across Hauptleiter/Leiter/Athleten etc.
		 */
		getSelectedIdsInScope: function($container) {
			var $scope = this.getScopeContainer($container);
			if (!$scope || !$scope.length) {
				return this.getSelectedIds($container);
			}
			var ids = [];
			var seen = {};
			$scope.find('.soe-person-picker .soe-pp-selected .soe-pp-item').each(function() {
				var id = parseInt($(this).data('id'), 10);
				if (!seen[id]) {
					seen[id] = true;
					ids.push(id);
				}
			});
			return ids;
		},

		/**
		 * Update hidden input with selected IDs.
		 */
		updateHidden: function($container) {
			var ids = this.getSelectedIds($container);
			$container.find('.soe-pp-hidden').val(ids.join(','));
		},

		/**
		 * Escape HTML entities.
		 */
		escapeHtml: function(str) {
			if (!str) return '';
			return String(str)
				.replace(/&/g, '&amp;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;')
				.replace(/"/g, '&quot;');
		}
	};

})(jQuery);
