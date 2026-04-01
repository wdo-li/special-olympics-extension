/**
 * Mediathek pickers on SOE settings (Darstellung tab).
 */
(function ($) {
	'use strict';

	$(function () {
		var frame;

		$(document).on('click', '.soe-media-picker', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var targetSel = $btn.data('target');
			var previewSel = $btn.data('preview');
			var $target = $(targetSel);
			var $preview = $(previewSel);

			if (frame) {
				frame.off('select');
				frame = null;
			}

			frame = wp.media({
				title: $btn.text(),
				button: { text: wp.media.view.l10n.insertIntoPost || 'Insert' },
				multiple: false
			});

			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				$target.val(att.id || '');
				var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
				if (url) {
					$preview.html('<img src="' + url + '" alt="" style="max-height:48px;vertical-align:middle;margin-left:8px;" />');
				} else {
					$preview.empty();
				}
			});

			frame.open();
		});

		$(document).on('click', '.soe-media-clear', function (e) {
			e.preventDefault();
			var $btn = $(this);
			$($btn.data('target')).val('');
			$($btn.data('preview')).empty();
		});
	});
})(jQuery);
