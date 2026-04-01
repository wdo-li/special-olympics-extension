/**
 * Payroll edit screen: refresh data, mark geprüft, abschliessen, download PDF, send mail.
 */
(function ($) {
	'use strict';

	$(function () {
		// Initialize Person Picker on "Neue Lohnabrechnung" page.
		if (typeof window.soePersonPicker !== 'undefined' && typeof soePayrollAdmin !== 'undefined') {
			soePersonPicker.ajaxUrl = soePayrollAdmin.ajaxUrl;
			soePersonPicker.nonce = soePayrollAdmin.personSearchNonce || '';
			soePersonPicker.initAll();
		}

		// Flatpickr: period date inputs on "Neue Lohnabrechnung" page (German locale, d.m.Y display, Y-m-d value)
		if (typeof window.flatpickr !== 'undefined') {
			var dateOpts = { locale: 'de', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', allowInput: true };
			['#period_start', '#period_end'].forEach(function (sel) {
				var el = document.querySelector(sel);
				if (el) { window.flatpickr(el, dateOpts); }
			});
		}

		// Validate "Neue Lohnabrechnung" form: require person selection.
		$('#soe-payroll-new-form').on('submit', function (e) {
			var $personInput = $(this).find('input[name="person_id"]');
			if ($personInput.length && !$personInput.val()) {
				e.preventDefault();
				alert('Bitte wähle eine Person aus.');
				$(this).find('.soe-pp-search').focus();
				return false;
			}
		});

		$(document).on('click', '.soe-payroll-refresh-data', function () {
			var postId = $(this).data('post-id');
			var $btn = $(this);
			var btnText = $btn.text();
			$btn.prop('disabled', true).text( (soePayrollAdmin.i18n && soePayrollAdmin.i18n.refreshing) || 'Wird gesammelt…' );
			$.ajax({
				url: soePayrollAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'soe_payroll_refresh_data',
					nonce: soePayrollAdmin.nonce,
					post_id: postId
				},
				dataType: 'json'
			})
				.done(function (r) {
					if (r && r.success) {
						var url = new URL(window.location.href);
						url.searchParams.set('soe_refreshed', '1');
						window.location.href = url.toString();
					} else {
						$btn.prop('disabled', false).text(btnText);
						alert( (r && r.data && r.data.message) ? r.data.message : ( (soePayrollAdmin.i18n && soePayrollAdmin.i18n.error) || 'Fehler.' ) );
					}
				})
				.fail(function (xhr, status, err) {
					$btn.prop('disabled', false).text(btnText);
					alert( (soePayrollAdmin.i18n && soePayrollAdmin.i18n.error) || 'Fehler beim Aktualisieren.' );
				});
		});

		// Auto-refresh data when opening payroll edit page (only when not abgeschlossen – button exists).
		// Skip if we just reloaded after a refresh (soe_refreshed param) to avoid infinite loop.
		var urlParams = new URLSearchParams(window.location.search);
		if (!urlParams.has('soe_refreshed')) {
			var $refreshBtn = $('.soe-payroll-refresh-data');
			if ($refreshBtn.length) {
				$refreshBtn.trigger('click');
			}
		}

		$('.soe-payroll-abschliessen').on('click', function () {
			if (!confirm('Lohnabrechnung abschliessen? Danach nicht mehr änderbar.')) {
				return;
			}
			var postId = $(this).data('post-id');
			$.post(soePayrollAdmin.ajaxUrl, {
				action: 'soe_payroll_abschliessen',
				nonce: soePayrollAdmin.nonce,
				post_id: postId
			}).done(function (r) {
				if (r.success) {
					location.reload();
				}
			});
		});

		$(document).on('click', '.soe-payroll-delete', function (e) {
			e.preventDefault();
			var msg = (soePayrollAdmin.i18n && soePayrollAdmin.i18n.deleteConfirm) || 'Lohnabrechnung unwiderruflich löschen?';
			if (!confirm(msg)) {
				return;
			}
			var postId = $(this).data('post-id');
			var $btn = $(this);
			if ($btn.is('button')) {
				$btn.prop('disabled', true);
			}
			$.post(soePayrollAdmin.ajaxUrl, {
				action: 'soe_payroll_delete',
				nonce: soePayrollAdmin.nonce,
				post_id: postId
			}).done(function (r) {
				if (r.success && r.data && r.data.redirect) {
					window.location.href = r.data.redirect;
				} else if (r.success && soePayrollAdmin.historyUrl) {
					window.location.href = soePayrollAdmin.historyUrl;
				} else if (!r.success) {
					if ($btn.is('button')) $btn.prop('disabled', false);
					alert(r.data && r.data.message ? r.data.message : 'Fehler.');
				}
			}).fail(function () {
				if ($btn.is('button')) $btn.prop('disabled', false);
				alert('Fehler.');
			});
		});

		$('.soe-payroll-download-pdf').on('click', function () {
			var postId = $(this).data('post-id');
			window.location.href = soePayrollAdmin.ajaxUrl + '?action=soe_payroll_download_pdf&nonce=' + soePayrollAdmin.nonce + '&post_id=' + postId;
		});

		$('.soe-payroll-send-mail').on('click', function () {
			var postId = $(this).data('post-id');
			var subject = (soePayrollAdmin.mailSubject || '').toString();
			var body = (soePayrollAdmin.mailBody || '').toString();
			var i18n = soePayrollAdmin.i18n || {};
			var $wrap = $('<div class="soe-payroll-mail-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;"></div>');
			var $box = $('<div style="background:#fff;padding:20px;max-width:560px;width:90%;max-height:90vh;overflow:auto;border-radius:4px;"></div>');
			$box.append($('<h3>').text(i18n.sendMail || 'Mail senden'));
			$box.append($('<p>').append($('<label>').append((i18n.subject || 'Betreff') + ': <br>').append($('<input type="text" class="soe-mail-subject large-text" style="width:100%">').val(subject))));
			$box.append($('<p>').append($('<label>').append((i18n.message || 'Nachricht') + ': <br>').append($('<textarea class="soe-mail-body large-text" rows="8" style="width:100%">').text(body))));
			var $btns = $('<p>');
			$btns.append($('<button type="button" class="button button-primary soe-mail-send-btn">').text(i18n.sendMail || 'Mail senden'));
			$btns.append(' ');
			$btns.append($('<button type="button" class="button soe-mail-cancel-btn">').text(i18n.cancel || 'Abbrechen'));
			$box.append($btns);
			$wrap.append($box);
			$('body').append($wrap);
			$wrap.find('.soe-mail-cancel-btn').on('click', function () { $wrap.remove(); });
			$wrap.find('.soe-mail-send-btn').on('click', function () {
				var $btn = $(this);
				$btn.prop('disabled', true);
				$.post(soePayrollAdmin.ajaxUrl, {
					action: 'soe_payroll_send_mail',
					nonce: soePayrollAdmin.nonce,
					post_id: postId,
					subject: $wrap.find('.soe-mail-subject').val(),
					body: $wrap.find('.soe-mail-body').val()
				}).done(function (r) {
					if (r.success) {
						alert(i18n.sent || 'Mail wurde gesendet.');
						$wrap.remove();
						location.reload();
					} else {
						alert(r.data && r.data.message ? r.data.message : (i18n.error || 'Fehler.'));
						$btn.prop('disabled', false);
					}
				}).fail(function () {
					alert(i18n.error || 'Fehler beim Senden.');
					$btn.prop('disabled', false);
				});
			});
		});

		$(document).on('click', '.soe-view-mail-text', function (e) {
			e.preventDefault();
			var text = $(this).data('text') || '';
			var $modal = $('<div class="soe-payroll-mail-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:100000;display:flex;align-items:center;justify-content:center;"></div>');
			var $box = $('<div style="background:#fff;padding:20px;max-width:560px;width:90%;max-height:80vh;overflow:auto;border-radius:4px;"></div>');
			$box.append($('<h3>').text('Mail-Text'));
			$box.append($('<pre style="white-space:pre-wrap;word-wrap:break-word;max-height:400px;overflow:auto;">').text(text));
			$box.append($('<p>').append($('<button type="button" class="button">').text('Schliessen').on('click', function () { $modal.remove(); })));
			$modal.append($box);
			$modal.on('click', function (e) { if (e.target === $modal[0]) $modal.remove(); });
			$('body').append($modal);
		});

		// Manuelle Änderungen: hinzufügen
		$(document).on('click', '.soe-payroll-add-adjustment', function () {
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var $form = $btn.closest('.soe-payroll-add-adjustment-form');
			var comment = $form.find('.soe-adjustment-comment').val() || '';
			var amountRaw = $form.find('.soe-adjustment-amount').val();
			var amount = amountRaw && !isNaN(parseFloat(String(amountRaw).replace(',', '.'))) ? parseFloat(String(amountRaw).replace(',', '.')) : 0;
			if (comment === '' && amount === 0) {
				return;
			}
			$btn.prop('disabled', true);
			$.post(soePayrollAdmin.ajaxUrl, {
				action: 'soe_payroll_add_adjustment',
				nonce: soePayrollAdmin.nonce,
				post_id: postId,
				comment: comment,
				amount: amount
			}).done(function (r) {
				if (r.success) {
					location.reload();
				} else {
					$btn.prop('disabled', false);
					alert((r.data && r.data.message) ? r.data.message : ((soePayrollAdmin.i18n && soePayrollAdmin.i18n.error) || 'Fehler.'));
				}
			}).fail(function () {
				$btn.prop('disabled', false);
				alert((soePayrollAdmin.i18n && soePayrollAdmin.i18n.error) || 'Fehler.');
			});
		});

		// Manuelle Änderungen: löschen
		$(document).on('click', '.soe-payroll-delete-adjustment', function (e) {
			e.preventDefault();
			var msg = (soePayrollAdmin.i18n && soePayrollAdmin.i18n.deleteAdjConfirm) || 'Position wirklich löschen?';
			if (!confirm(msg)) {
				return;
			}
			var postId = $(this).data('post-id');
			var adjustmentId = $(this).data('adjustment-id');
			var $btn = $(this);
			$btn.prop('disabled', true);
			$.post(soePayrollAdmin.ajaxUrl, {
				action: 'soe_payroll_delete_adjustment',
				nonce: soePayrollAdmin.nonce,
				post_id: postId,
				adjustment_id: adjustmentId
			}).done(function (r) {
				if (r.success) {
					location.reload();
				} else {
					$btn.prop('disabled', false);
					alert((r.data && r.data.message) ? r.data.message : 'Fehler.');
				}
			}).fail(function () {
				$btn.prop('disabled', false);
				alert('Fehler.');
			});
		});
	});
})(jQuery);
