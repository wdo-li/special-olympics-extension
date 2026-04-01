/**
 * Admin UI for CPT "mitglied": archive / restore member buttons (AJAX).
 *
 * @package Special_Olympics_Extension
 */

(function ($) {
	'use strict';

	function showMessage($box, text, isError) {
		var $msg = $box.find('.soe-member-status-message');
		$msg.text(text).css('color', isError ? '#b32d2e' : '#00a32a').show();
	}

	function archiveMember() {
		$(document).on('click', '.soe-archive-member', function () {
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var nonce = $btn.data('nonce');
			var $box = $btn.closest('#soe_member_status');
			$btn.prop('disabled', true);
			$.post(ajaxurl, {
				action: 'soe_archive_member',
				post_id: postId,
				nonce: nonce
			})
				.done(function (r) {
					if (r.success) {
						showMessage($box, r.data.message, false);
						setTimeout(function () {
							location.reload();
						}, 800);
					} else {
						showMessage($box, r.data && r.data.message ? r.data.message : 'Fehler', true);
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					showMessage($box, 'Anfrage fehlgeschlagen.', true);
					$btn.prop('disabled', false);
				});
		});
	}

	function restoreMember() {
		$(document).on('click', '.soe-restore-member', function () {
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var nonce = $btn.data('nonce');
			var $box = $btn.closest('#soe_member_status');
			$btn.prop('disabled', true);
			$.post(ajaxurl, {
				action: 'soe_restore_member',
				post_id: postId,
				nonce: nonce
			})
				.done(function (r) {
					if (r.success) {
						showMessage($box, r.data.message, false);
						setTimeout(function () {
							location.reload();
						}, 800);
					} else {
						showMessage($box, r.data && r.data.message ? r.data.message : 'Fehler', true);
						$btn.prop('disabled', false);
					}
				})
				.fail(function () {
					showMessage($box, 'Anfrage fehlgeschlagen.', true);
					$btn.prop('disabled', false);
				});
		});
	}

	/**
	 * Pre-select athlet_in in role field for new members (so required field has a value).
	 */
	function setDefaultRoleAthlet() {
		var $roleField = $('[data-key="field_682b2e76d356b"]');
		if (!$roleField.length) return;
		var $checked = $roleField.find('input[type="checkbox"]:checked');
		if ($checked.length > 0) return;
		var $athletInput = $roleField.find('input[type="checkbox"][value="athlet_in"]');
		if ($athletInput.length) {
			$athletInput.prop('checked', true);
		}
	}

	/**
	 * Rename ACF meta box "Mitglied" to "Athlet*in" for non-admins (PHP filter may not run for DB-stored groups).
	 */
	function renameMitgliedMetaBoxTitle() {
		if (typeof soeMitgliedAdmin === 'undefined' || !soeMitgliedAdmin.isNonAdmin) return;
		var title = soeMitgliedAdmin.acfTitleReplacement || 'Athlet*in';
		$('.postbox .hndle').each(function () {
			var $h = $(this);
			if ($h.text().trim() === 'Mitglied') {
				$h.html($h.html().replace(/Mitglied/g, title));
			}
		});
	}

	/**
	 * Re-evaluate ACF conditional logic when role is pre-selected (e.g. editing existing member).
	 * Fixes tabs (Kleidung, Kontaktpersonen, Medizinische Informationen) not showing on load.
	 * Runs after ACF form is ready and values are loaded.
	 */
	function refreshAcfConditionals() {
		function triggerRoleChange() {
			var $roleField = $('[data-key="field_682b2e76d356b"]');
			if (!$roleField.length) return;
			$roleField.find('input[type="checkbox"]').trigger('change');
		}
		if (typeof acf !== 'undefined' && acf.addAction) {
			acf.addAction('ready', triggerRoleChange);
			acf.addAction('append', triggerRoleChange);
		}
		setTimeout(triggerRoleChange, 1000);
		setTimeout(triggerRoleChange, 2000);
	}

	/**
	 * Handle clicks on medical file links - intercept and redirect to proxy URL.
	 * Uses event delegation so it works for dynamically added content.
	 */
	function setupMedicalFileClickHandler() {
		if (typeof soeMitgliedAdmin === 'undefined' || !soeMitgliedAdmin.adminPostUrl) {
			return;
		}

		// Use event delegation on document for robustness
		$(document).on('click', '[data-name="medizinische_datenblatter"] a', function (e) {
			var $link = $(this);
			var href = $link.attr('href') || '';

			// Only intercept links to protected medical files or PDF uploads
			if (href.indexOf('soe-protected/medical') === -1 && !(href.indexOf('/uploads/') !== -1 && href.indexOf('.pdf') !== -1)) {
				return; // Let other links work normally
			}

			e.preventDefault();

			// Find attachment ID
			var attachmentId = findAttachmentId($link);

			if (!attachmentId) {
				alert('Datei-ID konnte nicht ermittelt werden. Bitte Seite neu laden.');
				return;
			}

			// Check if we have a nonce
			var nonce = soeMitgliedAdmin.medicalNonces && soeMitgliedAdmin.medicalNonces[attachmentId];

			if (nonce) {
				openProxyUrl(attachmentId, nonce);
			} else {
				// Fetch nonce via AJAX
				fetchMedicalNonce(attachmentId, function (fetchedNonce) {
					if (fetchedNonce) {
						soeMitgliedAdmin.medicalNonces = soeMitgliedAdmin.medicalNonces || {};
						soeMitgliedAdmin.medicalNonces[attachmentId] = fetchedNonce;
						openProxyUrl(attachmentId, fetchedNonce);
					} else {
						alert('Download-Berechtigung konnte nicht verifiziert werden.');
					}
				});
			}
		});
	}

	/**
	 * Find attachment ID from link context.
	 */
	function findAttachmentId($link) {
		var $field = $link.closest('[data-name="medizinische_datenblatter"]');

		// Method 1: data-id on file uploader container
		var $fileItem = $link.closest('[data-id]');
		if ($fileItem.length && $fileItem.data('id')) {
			return parseInt($fileItem.data('id'), 10);
		}

		// Method 2: Hidden input in the field
		var $input = $field.find('input[type="hidden"][name*="medizinische_datenblatter"]');
		if ($input.length && $input.val()) {
			return parseInt($input.val(), 10);
		}

		// Method 3: ACF file uploader value input
		var $valueInput = $field.find('.acf-file-uploader input.acf-file-value, input[data-name="id"]');
		if ($valueInput.length && $valueInput.val()) {
			return parseInt($valueInput.val(), 10);
		}

		// Method 4: Check acf field value attribute
		var $acfInput = $field.find('input[type="hidden"]').filter(function () {
			var val = $(this).val();
			return val && /^\d+$/.test(val);
		});
		if ($acfInput.length) {
			return parseInt($acfInput.first().val(), 10);
		}

		return null;
	}

	/**
	 * Open proxy URL in new tab.
	 */
	function openProxyUrl(attachmentId, nonce) {
		var proxyUrl = soeMitgliedAdmin.adminPostUrl +
			'?action=soe_medical_download&id=' + attachmentId +
			'&_wpnonce=' + nonce;
		window.open(proxyUrl, '_blank');
	}

	/**
	 * Fetch a nonce for a medical file attachment via AJAX.
	 */
	function fetchMedicalNonce(attachmentId, callback) {
		var postId = $('#post_ID').val() || new URLSearchParams(window.location.search).get('post');
		if (!postId) {
			callback(null);
			return;
		}
		$.post(ajaxurl, {
			action: 'soe_get_medical_nonce',
			attachment_id: attachmentId,
			post_id: postId
		}).done(function (response) {
			if (response.success && response.data && response.data.nonce) {
				callback(response.data.nonce);
			} else {
				callback(null);
			}
		}).fail(function () {
			callback(null);
		});
	}

	/**
	 * Initialize medical file proxy functionality.
	 */
	function setupMedicalFileProxy() {
		setupMedicalFileClickHandler();
	}

	$(function () {
		archiveMember();
		restoreMember();
		setDefaultRoleAthlet();
		renameMitgliedMetaBoxTitle();
		refreshAcfConditionals();
		setupMedicalFileProxy();
		// Run again after possible late rendering (ACF, block editor, etc.).
		setTimeout(renameMitgliedMetaBoxTitle, 500);
		setTimeout(renameMitgliedMetaBoxTitle, 1500);
	});
})(jQuery);
