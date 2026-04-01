/**
 * Live preview for mail templates on the SOE settings screen.
 *
 * Currently used for the payroll mail fields:
 * - `#soe_mail_subject`
 * - `#soe_mail_body`
 *
 * Placeholders:
 * - {{vorname}}
 * - {{nachname}}
 * - {{period_label}}
 * - {{betrag_chf}}
 */
(function () {
	'use strict';

	function escapeForTextContent(str) {
		// Using textContent later, so no escaping needed here.
		return str;
	}

	function replacePlaceholders(template, values) {
		if (typeof template !== 'string') {
			return '';
		}
		var out = template;
		Object.keys(values).forEach(function (key) {
			// keys are placeholders including the braces: {{vorname}}, etc.
			var val = values[key];
			out = out.split(key).join(val);
		});
		return out;
	}

	function initPayrollPreview() {
		var subjectEl = document.getElementById('soe_mail_subject');
		var bodyEl = document.getElementById('soe_mail_body');
		var previewSubjectEl = document.getElementById('soe-payroll-mail-preview-subject');
		var previewBodyEl = document.getElementById('soe-payroll-mail-preview-body');
		if (!subjectEl || !bodyEl || !previewSubjectEl || !previewBodyEl) {
			return;
		}

		var values = {
			'{{vorname}}': 'Max',
			'{{nachname}}': 'Muster',
			'{{period_label}}': '01.01.2026 – 31.01.2026',
			'{{betrag_chf}}': '1234.50'
		};

		function render() {
			var subject = subjectEl.value || '';
			var body = bodyEl.value || '';
			var replacedSubject = replacePlaceholders(subject, values);
			var replacedBody = replacePlaceholders(body, values);

			previewSubjectEl.textContent = escapeForTextContent(replacedSubject);
			previewBodyEl.textContent = escapeForTextContent(replacedBody);
		}

		subjectEl.addEventListener('input', render);
		bodyEl.addEventListener('input', render);
		render();
	}

	document.addEventListener('DOMContentLoaded', function () {
		initPayrollPreview();
	});
})();

