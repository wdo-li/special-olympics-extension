/**
 * Training edit screen: person picker, attendance AJAX save, mark as completed.
 */
(function ($) {
	'use strict';

	$(function () {
		// Flatpickr: date inputs (German locale, display d.m.Y, value Y-m-d)
		if (typeof window.flatpickr !== 'undefined') {
			var dateOpts = { locale: 'de', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', allowInput: true };
			['#start_date', '#end_date', '.soe-session-date-input'].forEach(function (sel) {
				var el = document.querySelector(sel);
				if (el) { window.flatpickr(el, dateOpts); }
			});
		}

		// Initialize person picker.
		if (typeof soePersonPicker !== 'undefined' && typeof soeTrainingAdmin !== 'undefined') {
			soePersonPicker.ajaxUrl = soeTrainingAdmin.ajaxUrl;
			soePersonPicker.nonce = soeTrainingAdmin.personSearchNonce;
			soePersonPicker.initAll();
		}

		// Attendance: show table for selected date only (default = today or next session).
		$(document).on('change', '.soe-attendance-date-select', function () {
			var val = $(this).val();
			$('.soe-attendance-for-date').hide().filter('[data-date="' + val + '"]').show();
		});

		// Attendance checkbox: save on change (spinner per cell; prevent parallel toggles on same box)
		$(document).on('change', '.soe-attendance-cb', function () {
			var $cb = $(this);
			if ($cb.prop('disabled')) {
				return;
			}
			var postId = $cb.data('post');
			var session = $cb.data('session');
			var personId = $cb.data('person');
			var checked = $cb.is(':checked') ? '1' : '0';
			var $msg = $('.soe-attendance-msg');
			var $status = $cb.siblings('.soe-attendance-inline-status');

			$cb.prop('disabled', true);
			$status.addClass('is-loading');

			$.post(soeTrainingAdmin.ajaxUrl, {
				action: 'soe_training_attendance',
				nonce: soeTrainingAdmin.nonce,
				post_id: postId,
				session: session,
				person_id: personId,
				checked: checked
			})
				.done(function (r) {
					if (r.success && r.data && r.data.message) {
						$msg.removeClass('error').addClass('updated').hide().text('');
					} else {
						$msg.removeClass('updated').addClass('error').text(soeTrainingAdmin.i18n.error).show();
					}
				})
				.fail(function () {
					$msg.removeClass('updated').addClass('error').text(soeTrainingAdmin.i18n.error).show();
				})
				.always(function () {
					$cb.prop('disabled', false);
					$status.removeClass('is-loading');
				});
		});

		// Training als abgeschlossen melden (Hauptleiter → Mail an Admins)
		$(document).on('click', '.soe-training-request-completed', function () {
			var $btn = $(this);
			var trainingId = $btn.data('training-id');
			var nonce = $btn.data('nonce');
			var $msg = $('.soe-training-status-msg');

			$btn.prop('disabled', true);
			$msg.hide();
			$.post(soeTrainingAdmin.ajaxUrl, {
				action: 'soe_training_request_completed',
				training_id: trainingId,
				nonce: nonce
			})
				.done(function (r) {
					if (r.success && r.data && r.data.message) {
						$msg.removeClass('error').addClass('updated').text(r.data.message).show();
					} else {
						$msg.removeClass('updated').addClass('error').text(r.data && r.data.message ? r.data.message : soeTrainingAdmin.i18n.error).show();
					}
					$btn.prop('disabled', false);
				})
				.fail(function () {
					$msg.removeClass('updated').addClass('error').text(soeTrainingAdmin.i18n.error).show();
					$btn.prop('disabled', false);
				});
		});

		// Als laufend markieren (Admin: abgeschlossenes Training zurücksetzen)
		$(document).on('click', '.soe-training-mark-running', function () {
			if (!confirm(soeTrainingAdmin.i18n.markRunningConfirm || 'Training wieder als laufend markieren? Es kann danach erneut bearbeitet werden.')) {
				return;
			}
			var $btn = $(this);
			var trainingId = $btn.data('training-id');
			var nonce = $btn.data('nonce');
			var $msg = $('.soe-training-status-msg');

			$btn.prop('disabled', true);
			$msg.hide();
			$.post(soeTrainingAdmin.ajaxUrl, {
				action: 'soe_training_mark_running',
				training_id: trainingId,
				nonce: nonce
			})
				.done(function (r) {
					if (r.success && r.data && r.data.message) {
						$msg.removeClass('error').addClass('updated').text(r.data.message).show();
						setTimeout(function () { location.reload(); }, 800);
					} else {
						$msg.removeClass('updated').addClass('error').text(r.data && r.data.message ? r.data.message : soeTrainingAdmin.i18n.error).show();
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					$msg.removeClass('updated').addClass('error').text(soeTrainingAdmin.i18n.error).show();
					$btn.prop('disabled', false);
				});
		});

		// Mark training as completed
		$(document).on('click', '.soe-mark-training-completed', function () {
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var nonce = $btn.data('nonce');
			var $msg = $('.soe-training-status-msg');

			$btn.prop('disabled', true);
			$.post(soeTrainingAdmin.ajaxUrl, {
				action: 'soe_training_mark_completed',
				post_id: postId,
				nonce: nonce
			})
				.done(function (r) {
					if (r.success) {
						$msg.removeClass('error').addClass('updated').text(r.data && r.data.message ? r.data.message : soeTrainingAdmin.i18n.saved).show();
						setTimeout(function () { location.reload(); }, 800);
					} else {
						$msg.removeClass('updated').addClass('error').text(r.data && r.data.message ? r.data.message : soeTrainingAdmin.i18n.error).show();
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					$msg.removeClass('updated').addClass('error').text(soeTrainingAdmin.i18n.error).show();
					$btn.prop('disabled', false);
				});
		});

		// Add session
		$(document).on('click', '.soe-add-session', function () {
			var $btn = $(this);
			var trainingId = $btn.data('post-id');
			var nonce = $btn.data('nonce');
			var date = $('.soe-session-date-input').val();
			var $msg = $('.soe-session-msg');
			var computed = (typeof soeComputedSessions !== 'undefined' && Array.isArray(soeComputedSessions)) ? soeComputedSessions : [];
			if (!date || date.length !== 10) {
				$msg.removeClass('updated').addClass('error').text(soeTrainingAdmin.i18n.chooseDate || 'Bitte Datum wählen.').show();
				return;
			}
			var isOverlap = computed.indexOf(date) >= 0;
			if (isOverlap && !confirm(soeTrainingAdmin.i18n.overlapConfirm || 'Dieses Datum überschneidet sich mit einer automatisch erstellten Session. Bei Bestätigung wird die automatische überschrieben. Fortfahren?')) {
				return;
			}
			$btn.prop('disabled', true);
			$msg.hide();
			var postData = { action: 'soe_training_add_session', training_id: trainingId, date: date, nonce: nonce };
			if (isOverlap) { postData.overwrite = 1; }
			$.post(soeTrainingAdmin.ajaxUrl, postData)
				.done(function (r) {
					if (r.success && r.data) {
						$msg.removeClass('error').addClass('updated').text(r.data.message || '').show();
						if (r.data.reload) {
							setTimeout(function () { location.reload(); }, 1000);
						}
					} else {
						$msg.removeClass('updated').addClass('error').text(r.data && r.data.message ? r.data.message : soeTrainingAdmin.i18n.error).show();
					}
					$btn.prop('disabled', false);
				})
				.fail(function () {
					$msg.removeClass('updated').addClass('error').text(soeTrainingAdmin.i18n.error).show();
					$btn.prop('disabled', false);
				});
		});

		// Remove session
		$(document).on('click', '.soe-remove-session', function () {
			var $btn = $(this);
			if (!confirm(soeTrainingAdmin.i18n.removeConfirm || 'Session wirklich entfernen?')) {
				return;
			}
			var trainingId = $btn.data('post-id');
			var date = $btn.data('date');
			var nonce = $btn.data('nonce');
			$.post(soeTrainingAdmin.ajaxUrl, { action: 'soe_training_remove_session', training_id: trainingId, date: date, nonce: nonce })
				.done(function (r) {
					if (r.success && r.data && r.data.reload) {
						location.reload();
					}
				});
		});

		// PIN: Show form when "PIN ändern" is clicked
		$(document).on('click', '.soe-change-pin-btn', function () {
			var $box = $(this).closest('.soe-pin-section');
			$box.find('.soe-pin-form').slideDown();
			$box.find('.soe-attendance-pin-input').focus();
			$(this).hide();
		});

		// PIN: Cancel form
		$(document).on('click', '.soe-cancel-pin-btn', function () {
			var $box = $(this).closest('.soe-pin-section');
			$box.find('.soe-pin-form').slideUp();
			$box.find('.soe-change-pin-btn').show();
			$box.find('.soe-attendance-pin-input').val('');
			$box.find('.soe-pin-msg').hide();
		});

		// PIN: Save (click + touchend for mobile)
		function onPinSaveClick(e) {
			if (e && e.type === 'touchend') {
				e.preventDefault();
			}
			var $btn = $(this);
			if ($btn.data('soe-pin-saving')) {
				return;
			}
			var $box = $btn.closest('.soe-pin-section');
			var $input = $box.find('.soe-attendance-pin-input');
			var $msg = $box.find('.soe-pin-msg');
			var nonce = $btn.data('nonce');
			var pin = $input.val();

			if (!/^\d{4,6}$/.test(pin)) {
				$msg.removeClass('updated').css('color', '#dc3232').text('PIN muss 4-6 Ziffern enthalten.').show();
				return;
			}

			$btn.data('soe-pin-saving', true).prop('disabled', true);
			$msg.hide();

			$.post(soeTrainingAdmin.ajaxUrl, {
				action: 'soe_attendance_save_pin',
				nonce: nonce,
				pin: pin
			})
				.done(function (r) {
					if (r.success && r.data && r.data.message) {
						$msg.removeClass('error').css('color', '#46b450').text(r.data.message).show();
						setTimeout(function () { location.reload(); }, 1000);
					} else {
						$msg.css('color', '#dc3232').text(r.data && r.data.message ? r.data.message : 'Fehler.').show();
						$btn.removeData('soe-pin-saving').prop('disabled', false);
					}
				})
				.fail(function () {
					$msg.css('color', '#dc3232').text('Fehler.').show();
					$btn.removeData('soe-pin-saving').prop('disabled', false);
				});
		}
		$(document).on('click', '.soe-save-pin-btn', onPinSaveClick);
		$(document).on('touchend', '.soe-save-pin-btn', onPinSaveClick);

		// Token: Regenerate
		$(document).on('click', '.soe-regenerate-token-btn', function () {
			if (!confirm('Der alte Link wird ungültig. Einen neuen Link erzeugen?')) {
				return;
			}
			var $btn = $(this);
			var nonce = $btn.data('nonce');
			var $msg = $btn.siblings('.soe-token-msg');

			$btn.prop('disabled', true);
			$msg.hide();

			$.post(soeTrainingAdmin.ajaxUrl, {
				action: 'soe_attendance_regenerate_token',
				nonce: nonce
			})
				.done(function (r) {
					if (r.success && r.data) {
						$msg.css('color', '#46b450').text(r.data.message || 'Link erzeugt.').show();
						// Update the URL displayed in the notice
						var $notice = $btn.closest('.soe-attendance-box');
						if (r.data.url) {
							$notice.find('a[target="_blank"]').attr('href', r.data.url).text(r.data.url);
						}
						if (r.data.expiry_date) {
							var $expiryInfo = $notice.find('.soe-token-info');
							$expiryInfo.find('strong').text(r.data.expiry_date);
						}
						$btn.prop('disabled', false);
					} else {
						$msg.css('color', '#dc3232').text(r.data && r.data.message ? r.data.message : 'Fehler.').show();
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					$msg.css('color', '#dc3232').text('Fehler.').show();
					$btn.prop('disabled', false);
				});
		});
	});
})(jQuery);
