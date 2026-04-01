# Changelog

All notable changes to this plugin are documented in this file.

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
