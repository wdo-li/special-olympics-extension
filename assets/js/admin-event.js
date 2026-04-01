/**
 * Event edit screen: person picker initialization.
 */
(function ($) {
	'use strict';

	$(function () {
		// Flatpickr: date inputs (German locale, display d.m.Y, value Y-m-d)
		if (typeof window.flatpickr !== 'undefined') {
			var dateOpts = { locale: 'de', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', allowInput: true };
			['#event_date', '#event_filter_date_from', '#event_filter_date_to'].forEach(function (sel) {
				var el = document.querySelector(sel);
				if (el && !el.disabled) {
					window.flatpickr(el, dateOpts);
				}
			});
		}

		// Initialize person picker.
		if (typeof soePersonPicker !== 'undefined' && typeof soeEventAdmin !== 'undefined') {
			soePersonPicker.ajaxUrl = soeEventAdmin.ajaxUrl;
			soePersonPicker.nonce = soeEventAdmin.personSearchNonce;
			soePersonPicker.initAll();
		}
	});
})(jQuery);
