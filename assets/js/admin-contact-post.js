/**
 * Contact edit: core #title must not submit post_title (hidden field does). Archive / restore.
 */
(function ($) {
	'use strict';

	function detachCoreTitleInput() {
		var $core = $('#titlediv #title');
		if (!$core.length) {
			return;
		}
		$core.removeAttr('name').prop('readonly', true);
	}

	function showContactStatusMessage($box, text, isError) {
		var $msg = $box.find('.soe-contact-status-message');
		$msg.text(text).css('color', isError ? '#b32d2e' : '#00a32a').show();
	}

	function bindArchiveContact() {
		$(document).on('click', '.soe-archive-contact', function () {
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var nonce = $btn.data('nonce');
			var $box = $btn.closest('#soe_contact_status');
			$btn.prop('disabled', true);
			$.post(ajaxurl, {
				action: 'soe_archive_contact',
				post_id: postId,
				nonce: nonce
			})
				.done(function (r) {
					if (r.success) {
						showContactStatusMessage($box, r.data.message, false);
						setTimeout(function () {
							location.reload();
						}, 800);
					} else {
						showContactStatusMessage($box, r.data && r.data.message ? r.data.message : 'Fehler', true);
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					showContactStatusMessage($box, 'Anfrage fehlgeschlagen.', true);
					$btn.prop('disabled', false);
				});
		});
	}

	function bindRestoreContact() {
		$(document).on('click', '.soe-restore-contact', function () {
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var nonce = $btn.data('nonce');
			var $box = $btn.closest('#soe_contact_status');
			$btn.prop('disabled', true);
			$.post(ajaxurl, {
				action: 'soe_restore_contact',
				post_id: postId,
				nonce: nonce
			})
				.done(function (r) {
					if (r.success) {
						showContactStatusMessage($box, r.data.message, false);
						setTimeout(function () {
							location.reload();
						}, 800);
					} else {
						showContactStatusMessage($box, r.data && r.data.message ? r.data.message : 'Fehler', true);
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					showContactStatusMessage($box, 'Anfrage fehlgeschlagen.', true);
					$btn.prop('disabled', false);
				});
		});
	}

	$(function () {
		detachCoreTitleInput();
		bindArchiveContact();
		bindRestoreContact();
	});
})(jQuery);
