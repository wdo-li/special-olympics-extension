/**
 * Floating help widget.
 */
(function ($) {
	'use strict';

	function render() {
		if (typeof soeHelpWidget === 'undefined') {
			return;
		}
		var i = soeHelpWidget.i18n || {};
		var $wrap = $('<div id="soe-help-fab" />');
		var $btn = $('<button type="button" id="soe-help-fab-toggle" aria-label="' + (i.title || 'Help') + '" />');
		$btn.append('<span class="soe-help-question" aria-hidden="true">?</span>');
		var $panel = $('<div id="soe-help-fab-panel" role="dialog" aria-hidden="true" />');
		var mailTo = (soeHelpWidget.mailTo || '').toString();

		// Hero/header like the design screenshot (no photos).
		var $hero = $('<div class="soe-help-hero" />');
		$hero.append('<h3>' + (i.title || '') + '</h3>');

		// Main info text (the sentence requested by user).
		$hero.append($('<p class="soe-help-info" />').text(i.info || ''));

		// Optional recipient shown as a separate line (not embedded in the sentence).
		if (mailTo) {
			$hero.append(
				$('<p class="soe-help-recipient" />').append(
					$('<a />', { href: 'mailto:' + mailTo, text: mailTo })
				)
			);
		}

		var $body = $('<div id="soe-help-fab-body" />');
		$body.append($('<label />', { for: 'soe-help-subject', text: (i.subject || '') }));
		$body.append('<input type="text" id="soe-help-subject" autocomplete="off" />');
		$body.append($('<label />', { for: 'soe-help-message', text: (i.message || '') }));
		$body.append('<textarea id="soe-help-message" rows="4"></textarea>');

		$panel.append($hero);
		$panel.append($body);

		var $actions = $('<div id="soe-help-fab-actions" />');
		$actions.append('<button type="button" id="soe-help-send">' + (i.send || '') + '</button>');
		$actions.append('<button type="button" id="soe-help-close" class="soe-help-secondary">' + (i.close || '') + '</button>');
		$body.append($actions);
		$body.append('<p id="soe-help-fab-msg" style="display:none;"></p>');
		$wrap.append($btn).append($panel);
		$('body').append($wrap);

		function openPanel() {
			$panel.addClass('is-open').attr('aria-hidden', 'false');
		}
		function closePanel() {
			$panel.removeClass('is-open').attr('aria-hidden', 'true');
			$('#soe-help-fab-msg').hide().text('');
		}

		$btn.on('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			if ($panel.hasClass('is-open')) {
				closePanel();
			} else {
				openPanel();
			}
		});
		$('#soe-help-close').on('click', function () {
			closePanel();
		});
		$(document).on('click', function (e) {
			if (!$(e.target).closest('#soe-help-fab').length) {
				closePanel();
			}
		});
		$panel.on('click', function (e) {
			e.stopPropagation();
		});

		$('#soe-help-send').on('click', function () {
			var $msg = $('#soe-help-fab-msg');
			var subject = $.trim($('#soe-help-subject').val() || '');
			var message = $.trim($('#soe-help-message').val() || '');
			var $sendBtn = $(this);
			if (!subject || !message) {
				$msg.addClass('is-error').show().text(i.required || i.error || '');
				return;
			}
			$sendBtn.prop('disabled', true);
			$msg.hide();
			$.post(soeHelpWidget.ajaxUrl, {
				action: 'soe_submit_help',
				nonce: soeHelpWidget.nonce,
				subject: subject,
				message: message,
				page_url: window.location.href
			})
				.done(function (r) {
					if (r && r.success && r.data && r.data.message) {
						$msg.removeClass('is-error').show().text(r.data.message);
						$('#soe-help-subject').val('');
						$('#soe-help-message').val('');
						setTimeout(closePanel, 2000);
					} else {
						var errMsg = (r && r.data && r.data.message) ? r.data.message : (i.error || '');
						$msg.addClass('is-error').show().text(errMsg);
					}
				})
				.fail(function (xhr) {
					var err = i.error;
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						err = xhr.responseJSON.data.message;
					}
					$msg.addClass('is-error').show().text(err);
				})
				.always(function () {
					$sendBtn.prop('disabled', false);
				});
		});
	}

	$(function () {
		render();
	});
})(jQuery);
