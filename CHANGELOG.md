# Changelog

All notable changes to this plugin are documented in this file.

## 1.3.84 - 2026-05-11

### Fixed

- Contact action **due date** not saving: strict `Y-m-d` validation incorrectly required `DateTimeImmutable::getLastErrors()` to return an array; PHP returns **`false`** when there are no warnings or errors, so valid dates were dropped and stored as `NULL`.

## 1.3.83 - 2026-05-11

### Security

- Contact action **CSV and Excel exports**: escape cell values that start with spreadsheet formula triggers (`=`, `+`, `-`, `@`, tab/CR) to reduce formula-injection risk when files are opened in Excel.

### Fixed

- Contact actions **list progress** column: show separate counts for **done**, **open**, and **cancelled** items instead of treating cancelled as done.
- **Add contacts to an action**: `added` count no longer increases when the contact was already in the action; response includes `skipped`.
- **Matrix status dropdown** (admin JS): removed empty status option; reliable previous-value tracking and rollback on AJAX failure.
- **Action due date** save: strict `Y-m-d` validation via `DateTimeImmutable` (no silent `strtotime` normalization).

### Changed

- Contact action **matrix** (PHP + filtered AJAX view): show an **Archiviert** badge when the linked contact is archived, while keeping historical assignments visible.

## 1.3.82 - 2026-05-11

### Changed
- Contact edit screen **Aktions-Historie** metabox: activated checkbox fields shown as a **bulleted list**; column headers renamed to **Status der Aktion** vs **Status der Teilnahme**; short description + `title` tooltips clarify the two status levels.
- Telefonbuch contact detail table (**Kontakt-Aktionen**): same column header labels as the Aktions-Historie metabox.

## 1.3.81 - 2026-05-11

### Added
- Telefonbuch **Kontakte**: expanded **Kontakt-Aktionen** table includes an **Aktivierte Felder** column listing checkbox field labels checked for that contact row (same data as the contact’s Aktions-Historie metabox).

## 1.3.80 - 2026-05-11

### Fixed
- Telefonbuch **Kontakte**: DataTables „Zeige … Einträge“ length `<select>` no longer overlaps the value with the dropdown indicator; shared length-`select` styling with **Alle Daten**, colvis checkbox rules scoped to `input[type="checkbox"]` only.

## 1.3.79 - 2026-05-11

### Changed
- Telefonbuch **Alle Daten**: global search (**Filter** / *Alle Spalten durchsuchen…*) is in the same control panel as **Kontakte** — below **Alle** / **Nur Standard**, above Mail/Export actions; Sportart and Ort filters stay on that row. DataTables’ built-in search field is no longer used (`dom: lrtip` + column 0 search).

## 1.3.78 - 2026-05-11

### Added
- Telefonbuch **Kontakte**: column visibility bar (**Spalten einblenden**, **Alle**, **Nur Standard**) matching **Alle Daten**, with separate `localStorage` key and the same checkbox styling on narrow screens.

## 1.3.77 - 2026-05-11

### Fixed
- Contact admin submenu reorder: match the taxonomy submenu slug correctly (WordPress stores `&amp;` in `$submenu`), so **Aktionen** appears above **Aktionstypen**.

## 1.3.76 - 2026-05-11

### Changed
- Contact admin submenu: **Aktionen** is listed above **Aktionstypen** (Kontakte menu).

## 1.3.75 - 2026-05-11

### Changed
- Telefonbuch **Kontakte**: Geldeingänge and **Kontakt-Aktionen** (participation in Contact Actions) are shown in an expandable row (same pattern as member details); the table column shows a short count summary and a **Detail** control.

## 1.3.74 - 2026-05-11

### Changed
- Contact admin list: default ordering is by post title ascending when no column sort is selected.

## 1.3.73 - 2026-05-11

### Changed
- Contact admin list: Adresse shows street on first line and `PLZ Ort` on the second; removed Land column.

## 1.3.72 - 2026-05-11

### Changed
- Contact admin list: renamed columns (Adresse with street/PLZ/city lines, E-Mail with stacked addresses, Telefon with stacked `tel:` links), Beziehung entries one per line, removed Kontakt-Status column; list CSS updated for stacked content.

## 1.3.71 - 2026-05-11

### Added
- Telefonbuch: **Kontakte** mode (`?mode=contacts`) for administrators only — DataTable of active `contact` CPT posts with all main fields, live global filter, and copy buttons for all emails plus Geschäft / Privat / Person separately.

## 1.3.70 - 2026-05-11

### Fixed
- Contact admin list: prevent ultra-narrow columns (vertical character wrapping) by overriding `table-layout: fixed` for the contacts table, setting a sensible `min-width` on the table, and using `min-width` / `overflow-wrap` on custom columns.

## 1.3.69 - 2026-05-11

### Added
- Contact Actions edit sidebar: **Grunddaten** heading above the action metadata form.

### Changed
- Contact Actions: removed **Anlass** (`event_label`) from edit form, new-action form, actions list table, and contact **Aktions-Historie** metabox. AJAX save and copies store empty `event_label`; DB column unchanged.

## 1.3.68 - 2026-05-11

### Added
- Contact admin list: extra columns (Geschäft, Person, Ort, Land, E-Mail, Telefon Geschäft, Beziehung, Kontakt-Status) and filters (Beziehung, Ort, Land, PLZ) combined with existing archive status filter; compact column CSS on the list table.

## 1.3.67 - 2026-05-11

### Added
- Contact CPT: archive/restore via post meta (`soe_contact_status`), admin meta box and list filter (same pattern as members).
- Contact Actions: live search and import-from-action only consider active contacts; archived contacts cannot be added to actions.

## 1.3.66 - 2026-05-11

### Changed
- ACF contact field group (JSON): set wrapper width `33` for field `telefon_geschaeft` (Telefon Geschäft).

## 1.3.65 - 2026-05-11

### Changed
- ACF contact field group (JSON): set wrapper width `33` for field `e-mail_privat` (E-Mail Privat) to match E-Mail Geschäft.

## 1.3.64 - 2026-05-11

### Changed
- ACF contact field group (JSON): checkbox field `beziehung` layout set to `horizontal` to match admin configuration (local JSON is the source of truth for field group structure).

## 1.3.63 - 2026-05-11

### Removed
- Taxonomy `solie_status` (SOLie-Status) for the `contact` post type; registration file removed.

## 1.3.62 - 2026-05-11

### Changed
- Contact CPT: removed the visible auto-title preview; only a hidden `post_title` field remains for valid form posts (title is still derived from ACF fields on save).

## 1.3.61 - 2026-05-11

### Changed
- Contact CPT: show the read-only auto title preview directly under the (hidden) native title area via `edit_form_after_title`, with typography closer to the classic title field, instead of only a separate meta box.

## 1.3.60 - 2026-05-11

### Fixed
- Contact CPT: hide the native WordPress title field (`#titlediv` from `edit-form-advanced.php`) and detach `name="post_title"` from the core `#title` input so the auto-generated title is the only value submitted (meta box removal alone did not apply on current core).

## 1.3.59 - 2026-05-11

### Changed
- Contact CPT: removed the main content editor support; edit screen uses a two-column layout like Mitglied.
- Contact CPT: default title field is hidden; a read-only preview shows the auto-generated title (business name, or first + last name). Title and slug are updated on save via ACF fields.

## 1.3.58 - 2026-05-11

### Changed
- Contact Actions list: status tabs and the type/year filter form are stacked in two rows (clearer layout on narrow viewports).

## 1.3.57 - 2026-05-11

### Changed
- Contact Actions matrix: last column uses a compact red × remove control (same styling pattern as checkbox field rows) with `aria-label`; column widths tuned (contact, status, field headers with ellipsis, narrow checkbox cells, note band, slim action column).

## 1.3.56 - 2026-05-11

### Changed
- Contact Actions matrix: contact name is a standard blue admin link again (removed card/block chrome around the name).
- Matrix notes: saving on blur uses the core `.spinner` next to the field (with `is-active` while the AJAX request runs), `readonly` on the input during save, and per-field debounce timers so multiple rows do not clobber each other.

## 1.3.55 - 2026-05-11

### Changed
- Contact Actions edit: "Kontakte" heading, filter bar, matrix (or empty states), and add-contact row are wrapped in a white bordered panel (`.soe-ca-contacts-panel`) aligned with the sidebar metadata box.

## 1.3.54 - 2026-05-11

### Changed
- Contact Actions edit: placeholder below the matrix is now "Kontakt hinzufügen…" (filter bar search unchanged).

## 1.3.53 - 2026-05-11

### Changed
- Contact Actions matrix: contact name column uses a neutral “card” block (light gray gradient, soft border, slight depth) with a clearer typographic link to the contact edit screen; server-rendered rows now match AJAX-filtered rows.

## 1.3.52 - 2026-05-11

### Changed
- Contact Actions edit sidebar: removed fixed percentage/min/max widths on the metadata `form-table`; label cells use `width: auto` so the column is only as wide as the label text (overrides the default ~200px `th` from wp-admin), controls keep `min-width: 0` on `td` for predictable shrinking.

## 1.3.51 - 2026-05-11

### Fixed
- Contact Actions edit: checkbox field `sort_order` is included when saving the action with **Speichern** (`field_sort_json`); standalone `change` on the order input still calls `soe_ca_update_field`. AJAX-rendered field rows now set `data-field-id` / `data-action-id` as HTML attributes so order updates work after client-side table refresh.

## 1.3.50 - 2026-05-11

### Fixed
- Contact Actions edit sidebar: metadata form label column was unreadable (per-letter wrapping); relaxed `th` width constraints and removed aggressive `word-break` so labels display as words again.

## 1.3.49 - 2026-05-11

### Changed
- Contact Actions (list + edit): due date fields use Flatpickr (German locale, `d.m.Y` display, `Y-m-d` stored value) like other admin screens; vendor scripts/styles shared with training/event screens.

## 1.3.48 - 2026-05-11

### Changed
- Contact Actions edit sidebar: narrower label column in the action metadata form; fields section title renamed to "Checkboxen"; field remove control is a compact red × with `aria-label` instead of a full "Entfernen" button.

## 1.3.47 - 2026-05-11

### Fixed
- Contact Actions edit screen: sidebar form fields (title, occasion, due date, etc.) no longer overflow the column; field definitions table no longer uses `fixed` layout so column headers and cells render correctly in the narrow sidebar (horizontal scroll when needed).

## 1.3.46 - 2026-05-11

### Added
- Taxonomy `soe_contact_action_type` for contact action types (Contacts submenu label: Aktionstypen); `soe_contact_actions.action_type` stores the term slug.
- Contact Actions list: `subsubsub` tabs for running (`active`), completed, and all actions; default tab is running. Type and year filters persist when switching tabs.
- New action form: responsible user field (administrator role only) and a link to manage action types.
- Database schema v16: widen `soe_contact_actions.action_type` to `varchar(200)`; set `status` column default to `active` on upgrade.

### Changed
- Contact Actions: only two action statuses (`active` / `completed`); new actions and copies default to `active`; legacy `draft` / `sent` values display as in progress until the action is saved again.
- Contact Actions edit screen: two-column layout (contacts and matrix on the left; metadata and field definitions on the right); toolbar moved into the main column.
- Responsible user dropdown limited to the `administrator` role; Contact Actions submenu and Contact History metabox registration require `manage_options`.

### Removed
- Hard-coded contact action type slugs and the separate status filter dropdown on the actions list (replaced by taxonomy terms and tabs).

## 1.3.45 - 2026-05-06

### Fixed
- `soe_db_ca_filter_items()`: Fixed SQL bug where `iv.field_id`/`iv.value` were always selected even when the `LEFT JOIN` on `item_values` was not active. Without a field filter, those columns no longer exist as an alias, causing an "Unknown column" error on every unfiltered matrix load, CSV export, and Excel export. Now returns `NULL AS fid, NULL AS fval` when no field-value filter is active.
- `soe_db_ca_filter_items()`: Fixed `COALESCE` default – changed from an inconsistent value to `COALESCE(iv.value, '0')` so that contacts with no saved checkbox value are correctly treated as "not activated" and included when filtering for deactivated checkboxes.
- `soe_ajax_ca_import_from_action()`: Fixed incorrect import counter – existing contacts in the target action are now pre-loaded into a lookup set and counted as `skipped` upfront instead of being counted as `added`.
- `soe_db_ca_copy_action()`: Copying an action now also carries over `action_type`, `action_year`, `event_label`, and `responsible_user_id`. `status` is reset to `active` and `due_date` is set to `null`.

### Changed
- Contact History metabox on the `contact` CPT: added "Activated Fields" column showing a comma-separated list of all checkbox fields with `value = 1` for that contact in each action. Field labels are resolved via a single batch query (no N+1).

## 1.3.44 - 2026-05-06

### Added
- Contact Actions: AJAX matrix filter bar on the edit page (filter by item status, field value, and contact name search). Filter state is preserved in export forms.
- Contact Actions: CSV and Excel export per action, filter-aware (exports only the currently filtered contacts). Uses existing PhpSpreadsheet library.
- Contact Actions: "Import contacts from action" modal – import contacts from any other action with an optional status filter (all / open / done / cancelled). Duplicate contacts are automatically skipped.

### Fixed
- Renaming a field now triggers a page reload so the matrix column header updates immediately.

### Removed
- `contact-actions-spec-v4.md` deleted (outdated REST API spec, replaced by admin-ajax implementation).

## 1.3.43 - 2026-05-06

### Added
- Contact Actions: extended `soe_contact_actions` table with `action_type` (whitelist: jahresbrief/dankeskarte/weihnachtskarte/jahresbericht/sonstiges), `action_year`, `event_label`, `status` (draft/active/sent/completed), `due_date`, and `responsible_user_id` columns. DB schema updated to v15.
- Filter bar on the Actions list page: filter by type, year, and action status.
- New action creation form extended with type, year, event label, and due date fields.
- Action detail page header form now includes all new workflow fields plus a user assignment dropdown.
- Contact History metabox on the `contact` CPT: shows all actions a contact is part of, with type, year, event label, action status, item status, due date, and note.
- Performance indexes on `soe_contact_action_items` (composite `action_status_active`, `contact_active`) and `soe_contact_action_item_values` (`field_value`).

### Fixed
- Matrix columns were not visible immediately after adding a new field; the page now reloads after a successful field creation.
- `field_type` is now whitelisted (`checkbox` only for now) in both the AJAX handler and the DB helper to prevent invalid data entry.

## 1.3.42 - 2026-05-06

### Added
- Contact Actions: a flexible checklist/campaign system for the `contact` CPT. Admins can create named actions with configurable checkbox fields, assign contacts as items, and track per-contact progress with inline-editable values, status (open/done/cancelled), and notes. Includes Copy Action functionality (optionally with contacts, values reset to 0). All interactions are AJAX-based with optimistic UI.
- Database schema v14: four new tables (`soe_contact_actions`, `soe_contact_action_fields`, `soe_contact_action_items`, `soe_contact_action_item_values`) with composite UNIQUE constraints and full index coverage. Existing installations upgrade automatically on next page load.

## 1.3.41 - 2026-04-24

### Added
- Training sessions now support a dedicated status (`normal` / `cancelled`) with extensible DB structure, permission-checked updates, and backend/public UI controls.
- Member list (`CPT mitglied`) now includes additional filters for role (ACF `role`) and sport, plus new columns for phone and email.

### Changed
- Attendance write protection: attendance cannot be saved for cancelled sessions (backend editor, public page, and offline sync endpoint).
- Training statistics now exclude cancelled sessions from totals and attendance percentages.
- Sport taxonomy box on `mitglied` edit is now visible only for admins, `hauptleiter_in`, and `leiter_in`.

## 1.3.40 - 2026-04-20

### Changed
- Member editor (admin only): when the creator (`post_author`) is changed in the meta box, the Notfallkontakt reference (`notfallkontakt_person_id`) is now recalculated for records without linked `user_id` (set to the new creator's linked member or cleared if none exists).

## 1.3.39 - 2026-04-20

### Added
- Member editor (admin only): added a new meta box "Verknuepfung und Ersteller" on CPT `mitglied` with dropdown controls to change the linked `user_id` and the post creator (`post_author`), including nonce and capability checks on save.

## 1.3.38 - 2026-04-20

### Fixed
- Member editor: fixed Notfallkontakt autofill/read-only logic to use the current user's role instead of the edited target member's role. This ensures Ansprechpersonen get automatic contact takeover and locked fields when creating new Athlet*innen.

## 1.3.37 - 2026-04-01

### Changed
- General settings: clearer guidance for `ACF_ENCRYPTION_KEY` (random characters, minimum length, recommended length, 64-char hex example as format-only).

## 1.3.36 - 2026-04-01

### Added
- Plugin list: action link **Nach Updates suchen** triggers a fresh GitHub release check (clears plugin update and release cache, runs `wp_update_plugins()`), then shows a short admin notice with the result.

## 1.3.35 - 2026-04-01

### Added
- General settings tab: beginner-friendly instructions for `ACF_ENCRYPTION_KEY` in `wp-config.php` (encrypted ACF fields), plus current configuration status.

## 1.3.34 - 2026-04-01

### Added
- Added release consistency check: an admin notice appears on Plugin/Update pages when `SOE_PLUGIN_VERSION` and the latest GitHub release tag do not match.

## 1.3.33 - 2026-04-01

### Added
- Added GitHub update integration: the plugin checks public releases from `wdo-li/special-olympics-extension` and reports new versions in the WordPress backend.

### Notes
- Recommended release asset: `special-olympics-extension.zip` with the correct plugin folder name; fallback uses GitHub zipball.

## 1.3.32 - 2026-04-01

### Changed
- Fully revised README and consolidated core plugin features (public GitHub documentation).
- Added AI-assistance note to the README (no corresponding note in code).

## 1.3.31 - 2026-03-31

### Changed
- Member editor: standardized the primary button in the simplified submit box to `Jetzt speichern` (instead of Publish/Update/Submit for Review).

## 1.3.30 - 2026-03-31

### Changed
- Member editor: renamed the simplified submit box title for non-admins from `Publish` to `Save`.

## 1.3.29 - 2026-03-31

### Changed
- Adjusted role precedence in member editor: for combined roles `ansprechperson` + `leiter_in`/`hauptleiter_in`, `Events` and `Sports` are no longer hidden.
- Restricted emergency-contact autofill/read-only logic to pure `ansprechperson`, so combined Leiter/Hauptleiter users are not limited by Ansprechperson-only rules.

## 1.3.28 - 2026-03-31

### Added
- ACF: field groups for `Member` and `Contact` are now auto-loaded from all JSON files in the `acf/` folder (no manual import required).
- Contact editor: added ACF fields for CPT `contact` via `acf/acf-export-contact.json`.
- Taxonomy: added new non-public taxonomy `SOLie-Status` (`solie_status`) for CPT `contact` to categorize contacts.

### Changed
- ACF loader: instead of a single export file (`acf-export-2026-02-14.json` / `acf-export-mitglied.json`), all JSON exports in the `acf/` subfolder are now loaded to simplify future field-group extensions.

## 1.3.27 - 2026-03-30

### Changed
- Member editor: for records with role `ansprechperson`, the `Events` meta box and `Sports` taxonomy box are hidden.

## 1.3.26 - 2026-03-30

### Fixed
- ACF checkbox: fixed `disabled` value for field “Ich bin” (array instead of bool), plus global guard for invalid checkbox `disabled` values.

## 1.3.25 - 2026-03-30

### Fixed
- ACF: added extra normalization for checkbox `default_value` and a global `prepare_field` fallback to avoid `array_map(..., true)` fatals.

## 1.3.24 - 2026-03-30

### Fixed
- ACF: added global safeguard for checkbox fields so invalid boolean values (e.g. `true`) are normalized to arrays before rendering.

## 1.3.23 - 2026-03-30

### Fixed
- Member editor: fixed fatal error when rendering ACF checkbox field “Ich bin” (value is robustly normalized to an array).

## 1.3.22 - 2026-03-30

### Changed
- Member editor: set field “Ich bin” to read-only/disabled for admins as well.

## 1.3.21 - 2026-03-30

### Changed
- Member editor: implemented approach A — field “Ich bin” visible again for stable ACF logic; read-only/disabled for non-admins.

## 1.3.20 - 2026-03-30

### Changed
- Member editor: ACF field “Ich bin” hidden again as before.

## 1.3.19 - 2026-03-30

### Changed
- Member editor: ACF field “Ich bin” re-enabled for testing.

## 1.3.18 - 2026-03-30

### Changed
- Help/contact widget: switched color/branding to SOLIE red (instead of blue).

## 1.3.17 - 2026-03-30

### Changed
- Help/contact widget: rounded corners on opened panel (clipped).

## 1.3.16 - 2026-03-30

### Changed
- Help/contact widget: adjusted info text to requested wording.

## 1.3.15 - 2026-03-30

### Changed
- Help/contact widget: replaced info text and adjusted header design (without photos).

## 1.3.14 - 2026-03-30

### Changed
- Help/contact widget: improved “?” icon centering.

## 1.3.13 - 2026-03-30

### Changed
- Help/contact widget: info text “Schreib uns kurz…” shown with lighter weight.

## 1.3.12 - 2026-03-30

### Changed
- Help/contact widget: made “?” icon significantly larger (about 40px) and centered.

## 1.3.11 - 2026-03-30

### Changed
- Settings: made tab headings subtler and displayed them as part of the jump block (no second block).

## 1.3.10 - 2026-03-30

### Changed
- Settings: moved “Public Attendance” card from tab “General” to “Mobile Attendance Module”.

## 1.3.9 - 2026-03-30

### Changed
- Settings: aligned “Payroll Mail – Subject” label and input vertically.

## 1.3.8 - 2026-03-30

### Changed
- Settings: added a section heading above jump buttons in each tab.
- Settings: improved jump-button font size/padding.

## 1.3.7 - 2026-03-30

### Changed
- Settings: made “Payroll Mail – Subject” bold and set subject input width to 50%.

## 1.3.6 - 2026-03-30

### Changed
- Settings: improved spacing inside “Payroll Mail – Text” section.

## 1.3.5 - 2026-03-30

### Changed
- Settings: jump buttons in the settings layout now use larger text and clearer padding/click height.

## 1.3.4 - 2026-03-30

### Changed
- Settings: “Payroll Mail” card in notifications tab now uses the same card header/spacing as other cards.

## 1.3.3 - 2026-03-30

### Changed
- Settings: reduced clutter in notifications tab (category cards are no longer all open by default).
- Help widget: made “?” icon less bold and better centered/aligned.

## 1.3.2 - 2026-03-30

### Changed
- Settings: restructured tabs/content: moved “Display” into “General”, moved payroll mail fields into “Notifications”, and renamed “Attendance Security” to “Mobile Attendance Module”.
- Settings: added “jump” buttons in each tab to scroll to cards; payroll mail text now shown in two columns (text left, preview right).
- Help widget: changed icon to “?” (about 10px higher), panel shows short info text plus configured email address.
- Help widget: icon is hidden when “Help Requests (Contact Widget)” is disabled.

## 1.3.1 - 2026-03-30

### Changed
- Settings: modernized notifications tab (category-based cards); enable/disable controls now live where recipients and mail texts are managed.
- Settings: removed payroll (PDF via e-mail) toggle; payroll delivery can no longer be disabled via UI switch.
- Settings: added live preview for payroll mail templates.

## 1.3.0 - 2026-03-29

### Added
- Person picker: search matches Sportart taxonomy; selected persons show sport names in parentheses.
- Add New User: optional sports categories (checkboxes) applied to the created member post.
- Telefonbuch „Alle Daten“: Bank and IBAN columns for administrators only; Excel export includes them for administrators only.
- Settings: per-category email toggles (payroll, new member, training completed, new event, welcome mail, help field) with short descriptions; optional help recipient email.
- Settings: Darstellung tab — Mediathek for login logo/background and public attendance logo/background (defaults unchanged when empty).
- Public attendance: phone and e-mail icons next to names when data is present.
- Floating help button (logged-in users) with subject/message and optional rate limit; uses Hilfe settings.
- Training admin attendance: per-cell spinner while saving; checkbox disabled during request; success no longer flashes global „Gespeichert.“

### Changed
- `soe_sanitize_settings` merges with previous options so saving one settings tab does not clear other tabs.
- `wp_send_new_user_notification_to_user` respects the Willkommens-Mail toggle.

## 1.2.7 - 2026-03-25

### Added
- Public attendance page footer: link to WordPress admin dashboard next to the phone book link.

## 1.2.6 - 2026-03-17

### Removed
- Removed unloaded legacy file `post-type-payroll-register.php` (payroll uses custom tables).
- Removed dead Event UI hooks in `event-capabilities.php` (`soe_event_restrict_new_to_admin`, `soe_event_remove_add_new_for_non_admin`) – CPT "event" has `show_ui` false.

### Changed
- Payroll comments in `payroll.php` updated to reflect custom-table usage.
- Corrected `soe_get_training_role_keys` comment in `roles.php` (includes athlete_leader).
- Removed commented debug `error_log` lines in `role-sync.php`.

## 1.2.5 - 2026-03-17

### Changed
- Cron cleanup now runs only on admin pages (`admin_init` instead of `init`) to reduce frontend overhead.
- Special Olympics roles are now filterable via `soe_roles`; use `soe_get_special_olympics_roles()` instead of the removed `SPECIAL_OLYMPICS_ROLES` constant.
- DB init hook moved from `after_setup_theme` to `plugins_loaded` for plugin-appropriate timing.
- Asset versions unified to `SOE_PLUGIN_VERSION` for consistent cache busting.
- ACF save callback in `post-type-mitglied.php` refactored: removed self-remove/add pattern, logic moved to helper `soe_update_mitglied_post_title()`.

### Fixed
- Added deactivation hook to clear `soe_payroll_cleanup_orphaned_pdfs` cron when plugin is deactivated.
- `wp_unslash` added before `sanitize_text_field` for `$_POST` values in custom-trainings.php and payroll.php.

### Removed
- Removed unused files: `post-type-training.php`, `post-type-event.php` (legacy CPT code; UI lives in custom-trainings/custom-events).

### Added
- `uninstall.php`: removes options (`soe_settings`, `soe_db_version`), custom tables, and scheduled cron on plugin deletion.

## 1.2.4 - 2026-03-17

### Added
- Integrated ACF Encrypted Fields (AES-256-GCM): Option to encrypt ACF field values in the database, transparent decrypt on output. Replaces standalone plugin `so-acf-encrypted-fields`.

### Changed
- The standalone plugin `so-acf-encrypted-fields` can be deactivated and removed; its functionality is now part of Special Olympics Extension.

## 1.2.3 - 2026-03-17

### Added
- Sports submenu under Trainings (admin-only) for managing sport taxonomy terms.

### Changed
- Accounting reference numbers moved from settings tab "General" to "Payroll" as the first item.
- Sports submenu access restricted to Administrators (manage_options).

## 1.2.2 - 2026-03-17

### Changed
- Added centralized attendance context validation helpers for strict training/session/person checks across write paths.
- Hardened admin attendance AJAX endpoints to reject invalid session dates and persons not assigned to the training.
- Added offline sync request batch guard with explicit `payload_too_large` error for oversized operation payloads.
- Lockout message on the public attendance page now reflects the configured lockout duration dynamically.
- Attendance token handling now supports hashed token lookup with encrypted token storage and legacy migration on read.
- Added optional CSP hardening for public attendance responses (`frame-ancestors`, `base-uri`, `form-action`).

### Fixed
- Added DB write failure logging context for attendance writes to improve diagnostics when SQL operations fail.

## 1.2.1 - 2026-03-17

### Changed
- Hardened attendance context validation so writes are accepted only for valid combinations of `training_id`, `session_date`, and `person_id`.
- Public attendance sync now rejects invalid session dates explicitly with per-operation reason codes.
- Public attendance rewrite registration no longer performs runtime `flush_rewrite_rules()` on `init`; flushing is kept to activation/migration flows.
- Expanded security response headers for public attendance pages with no-cache and baseline browser hardening headers.

### Fixed
- Attendance DB write helper now returns failure when the underlying SQL write fails instead of always reporting success.
- Admin attendance AJAX handlers now surface DB write failures instead of returning false-positive success responses.
- Added dedicated CSRF nonce validation for the PIN form to prevent cross-site PIN submission attempts from contributing to lockouts.

## 1.2.0 - 2026-02-28

### Added
- Added offline attendance queue model in the public attendance page using IndexedDB (`opId`, `tokenHash`, `trainingId`, `sessionDate`, `personId`, `attended`, timestamps, status).
- Added attendance sync AJAX endpoints (`wp_ajax_soe_attendance_sync` and `wp_ajax_nopriv_soe_attendance_sync`) for batched offline synchronization.
- Added idempotency operation log table `soe_attendance_ops` (DB schema v12) to prevent duplicate processing on retries.
- Added server helpers for attendance sync token context validation and operation result reporting (`applied`, `duplicate`, `rejected`).
- Added UI sync status on attendance page (online/offline badge, pending indicator, sync trigger button, error message area).
- Added end-user notes for offline behavior in `OFFLINE-ATTENDANCE.md`.

### Changed
- Updated plugin version to `1.2.0`.
- Extended attendance page save flow with offline-first behavior:
  - Local queueing while offline
  - Automatic sync when online
  - Retry with backoff for temporary errors
- Attendance sync conflict strategy is now explicitly Last-Write-Wins via ordered operation apply.
- Attendance page now autosaves each checkbox change (instead of requiring a full manual form save) and attempts sync immediately.
- Sync panel visibility is now contextual: it is shown only when offline, when unsynced changes exist, or when a sync/auth error needs user attention.
- Manual `Jetzt synchronisieren` action is now shown only when pending or failed sync operations exist.
- Added per-person inline sync indicator near each name (pending state indicator plus brief green checkmark on successful sync).
- Refined attendance row feedback UX: pending state is now shown as a compact waiting spinner behind each name, without shifting checkbox layout.
- Added a short visibility delay before showing the sync panel for transient pending states to avoid UI jumping during fast successful syncs.

### Fixed
- Improved re-auth behavior for offline sync: queue is preserved and sync returns explicit `auth_required`/`token_expired` states instead of silent data loss.
- Ensured sync retries are safe via idempotent operation processing.
- Fixed undefined variable warning for `token_hash` on attendance page by initializing sync template variables in the correct render function.
- Stabilized chaotic toggle synchronization for a single session by coalescing queued operations per person/session key before sync (latest state wins).
- Prevented stale unsynced session drift by replacing older unsynced queue entries with the newest local state for the same person/session.
- Added retry cap handling for unsynced operations to avoid endless retry loops on permanently failing entries.

## 1.1.0 - 2026-02-28

### Added
- Added plugin version constant `SOE_PLUGIN_VERSION`.
- Added attendance security settings:
  - Max failed PIN attempts
  - Lockout duration in minutes
  - PIN session duration in minutes
- Added role slug constants in `includes/roles.php`.
- Added `Referrer-Policy: no-referrer` headers for public attendance pages.
- Added helper map loader for payroll person posts to reduce repeated queries.

### Changed
- Updated plugin header version from `1.0.0` to `1.1.0`.
- Attendance auth cookie now uses secure options with `samesite=Strict`.
- Attendance rate limit and cookie duration now read values from settings with safe defaults.
- Payroll list rendering now avoids N+1 `get_post()` lookups.
- Reworked plugin settings page into tabs for better usability (`General`, `Payroll`, `Notifications`, `Attendance Security`).

### Fixed
- Added file write error handling and debug logging for generated `.htaccess` files in:
  - `includes/payroll.php`
  - `includes/protected-medical-files.php`
- Added file write error handling for payroll PDF/HTML output writes.
- Added database transaction handling (`START TRANSACTION`, `COMMIT`, `ROLLBACK`) with debug logging for critical multi-step operations:
  - `soe_db_training_delete()`
  - `soe_db_event_delete()`
  - `soe_db_payroll_delete()`
  - `soe_db_payroll_save_rows()`
