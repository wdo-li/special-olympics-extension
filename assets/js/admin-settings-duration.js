/**
 * Duration tiers UI on plugin settings (payroll tab).
 */
(function () {
	'use strict';

	var config = window.soeDurationSettings || {};
	var optionName = config.optionName || 'soe_settings';

	function generateDurationKey() {
		return 'd_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
	}

	function getNextTierIndex(list) {
		var max = -1;
		list.querySelectorAll('.soe-duration-tier-row').forEach(function (row) {
			var index = parseInt(row.getAttribute('data-tier-index'), 10);
			if (!isNaN(index) && index > max) {
				max = index;
			}
		});
		return max + 1;
	}

	function buildRateInputName(slotPrefix, tierKey) {
		return optionName + '[hourly_rates][' + slotPrefix + '_' + tierKey + ']';
	}

	function wirePanelRates(panel, tierKey, tierIndex) {
		panel.querySelectorAll('.soe-hourly-rate-input').forEach(function (input) {
			var slotPrefix = input.getAttribute('data-slot-prefix');
			if (!slotPrefix) {
				return;
			}
			input.name = buildRateInputName(slotPrefix, tierKey);
			if (!input.id) {
				input.id = 'soe_hr_' + tierIndex + '_' + slotPrefix;
			}
			var label = input.closest('tr');
			if (label) {
				var labelEl = label.querySelector('label');
				if (labelEl) {
					labelEl.setAttribute('for', input.id);
				}
			}
		});
	}

	function updateRemoveButtons(list) {
		var rows = list.querySelectorAll('.soe-duration-tier-row');
		var disable = rows.length <= 1;
		rows.forEach(function (row) {
			var btn = row.querySelector('.soe-remove-duration-tier');
			if (btn) {
				btn.disabled = disable;
			}
		});
	}

	function reindexTiers(list, panelsWrap) {
		list.querySelectorAll('.soe-duration-tier-row').forEach(function (row, index) {
			var tierKey = row.getAttribute('data-tier-key') || '';
			row.setAttribute('data-tier-index', String(index));

			var labelInput = row.querySelector('.soe-duration-label-input');
			var keyInput = row.querySelector('.soe-duration-key-input');
			if (labelInput) {
				labelInput.name = optionName + '[duration_tiers][' + index + '][label]';
			}
			if (keyInput) {
				keyInput.name = optionName + '[duration_tiers][' + index + '][key]';
				tierKey = keyInput.value;
				row.setAttribute('data-tier-key', tierKey);
			}

			var panel = panelsWrap.querySelector('.soe-duration-rates-panel[data-tier-key="' + tierKey + '"]');
			if (panel) {
				panel.setAttribute('data-tier-index', String(index));
				wirePanelRates(panel, tierKey, index);
			}
		});
		updateRemoveButtons(list);
	}

	function syncSelectOptions(select, list) {
		var selected = select.value;
		select.innerHTML = '';

		list.querySelectorAll('.soe-duration-tier-row').forEach(function (row) {
			var keyInput = row.querySelector('.soe-duration-key-input');
			var labelInput = row.querySelector('.soe-duration-label-input');
			if (!keyInput) {
				return;
			}
			var option = document.createElement('option');
			option.value = keyInput.value;
			option.textContent = labelInput && labelInput.value ? labelInput.value : keyInput.value;
			select.appendChild(option);
		});

		if (selected && select.querySelector('option[value="' + selected + '"]')) {
			select.value = selected;
		} else if (select.options.length) {
			select.selectedIndex = 0;
		}
	}

	function showRatesPanel(panelsWrap, tierKey) {
		panelsWrap.querySelectorAll('.soe-duration-rates-panel').forEach(function (panel) {
			panel.hidden = panel.getAttribute('data-tier-key') !== tierKey;
		});
	}

	function addDurationTier(list, rowTemplate, panelsWrap, panelTemplate, select) {
		var index = getNextTierIndex(list);
		var tierKey = generateDurationKey();

		var rowHtml = rowTemplate.innerHTML
			.replace(/__INDEX__/g, String(index))
			.replace(/__KEY__/g, tierKey);
		var rowWrap = document.createElement('div');
		rowWrap.innerHTML = rowHtml.trim();
		var rowEl = rowWrap.firstElementChild;
		if (!rowEl) {
			return;
		}
		list.appendChild(rowEl);

		var panelHtml = panelTemplate.innerHTML
			.replace(/__INDEX__/g, String(index))
			.replace(/__KEY__/g, tierKey);
		var panelWrap = document.createElement('div');
		panelWrap.innerHTML = panelHtml.trim();
		var panelEl = panelWrap.firstElementChild;
		if (panelEl) {
			panelsWrap.appendChild(panelEl);
			wirePanelRates(panelEl, tierKey, index);
		}

		reindexTiers(list, panelsWrap);
		syncSelectOptions(select, list);
		select.value = tierKey;
		showRatesPanel(panelsWrap, tierKey);

		var labelInput = rowEl.querySelector('.soe-duration-label-input');
		if (labelInput) {
			labelInput.focus();
		}
	}

	function removeDurationTier(row, list, panelsWrap, select) {
		var tierKey = row.getAttribute('data-tier-key');
		var wasSelected = select.value === tierKey;

		row.remove();
		if (tierKey) {
			var panel = panelsWrap.querySelector('.soe-duration-rates-panel[data-tier-key="' + tierKey + '"]');
			if (panel) {
				panel.remove();
			}
		}

		reindexTiers(list, panelsWrap);
		syncSelectOptions(select, list);
		if (wasSelected && select.options.length) {
			select.selectedIndex = 0;
		}
		showRatesPanel(panelsWrap, select.value);
	}

	function init() {
		var list = document.getElementById('soe-duration-tier-list');
		var panelsWrap = document.getElementById('soe-duration-rates-panels');
		var select = document.getElementById('soe-duration-tier-select');
		var addBtn = document.getElementById('soe-add-duration-tier');
		var rowTemplate = document.getElementById('soe-duration-tier-row-template');
		var panelTemplate = document.getElementById('soe-duration-rates-panel-template');

		if (!list || !panelsWrap || !select || !addBtn || !rowTemplate || !panelTemplate) {
			return;
		}

		panelsWrap.querySelectorAll('.soe-duration-rates-panel').forEach(function (panel) {
			var tierKey = panel.getAttribute('data-tier-key') || '';
			var tierIndex = panel.getAttribute('data-tier-index') || '0';
			if (tierKey) {
				wirePanelRates(panel, tierKey, tierIndex);
			}
		});

		reindexTiers(list, panelsWrap);
		syncSelectOptions(select, list);
		showRatesPanel(panelsWrap, select.value);

		select.addEventListener('change', function () {
			showRatesPanel(panelsWrap, select.value);
		});

		list.addEventListener('input', function (event) {
			if (event.target.classList.contains('soe-duration-label-input')) {
				syncSelectOptions(select, list);
				if (select.value) {
					var row = event.target.closest('.soe-duration-tier-row');
					if (row && row.getAttribute('data-tier-key') === select.value) {
						var option = select.querySelector('option[value="' + select.value + '"]');
						if (option) {
							option.textContent = event.target.value || select.value;
						}
					}
				}
			}
		});

		addBtn.addEventListener('click', function () {
			addDurationTier(list, rowTemplate, panelsWrap, panelTemplate, select);
		});

		list.addEventListener('click', function (event) {
			var removeBtn = event.target.closest('.soe-remove-duration-tier');
			if (!removeBtn || removeBtn.disabled) {
				return;
			}
			var row = removeBtn.closest('.soe-duration-tier-row');
			if (!row) {
				return;
			}
			removeDurationTier(row, list, panelsWrap, select);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
