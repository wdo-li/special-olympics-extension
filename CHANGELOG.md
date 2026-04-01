# Changelog

All notable changes to this plugin are documented in this file.

## 1.3.34 - 2026-04-01

### Added
- Release-Konsistenzprüfung ergänzt: Im Backend erscheint auf Plugin-/Update-Seiten ein Hinweis, wenn `SOE_PLUGIN_VERSION` und das neueste GitHub-Release-Tag voneinander abweichen.

## 1.3.33 - 2026-04-01

### Added
- GitHub-Updatefunktion ergänzt: Das Plugin prüft öffentliche Releases aus `wdo-li/special-olympics-extension` und meldet neue Versionen im WordPress-Backend.

### Notes
- Empfohlen ist ein Release-Asset `special-olympics-extension.zip` mit korrekt benanntem Plugin-Ordner; alternativ wird auf GitHub-Zipball zurückgefallen.

## 1.3.32 - 2026-04-01

### Changed
- README vollständig überarbeitet und mit den zentralen Plugin-Funktionen konsolidiert (öffentliche GitHub-Dokumentation).
- KI-Hinweis in die README aufgenommen (kein entsprechender Hinweis im Code).

## 1.3.31 - 2026-03-31

### Changed
- Mitglied-Editor: Primärer Button in der vereinfachten Submit-Box wurde auf `Jetzt speichern` vereinheitlicht (statt Publish/Update/Submit for Review).

## 1.3.30 - 2026-03-31

### Changed
- Mitglied-Editor: Die vereinfachte Submit-Box für Nicht-Admins wurde von `Veröffentlichen` auf `Speichern` umbenannt.

## 1.3.29 - 2026-03-31

### Changed
- Rollen-Priorität im Mitglied-Editor angepasst: Bei kombinierten Rollen `ansprechperson` + `leiter_in`/`hauptleiter_in` werden `Events` und `Sportarten` nicht mehr ausgeblendet.
- Notfallkontakt-Autofill/Read-only-Logik auf reine `ansprechperson` eingeschränkt, damit kombinierte Leiter/Hauptleiter nicht durch Ansprechperson-Sonderlogik eingeschränkt werden.

## 1.3.28 - 2026-03-31

### Added
- ACF: Field-Gruppen für `Mitglied` und `Contact` werden nun automatisch aus allen JSON-Dateien im Ordner `acf/` geladen (kein manueller Import mehr nötig).
- Contact-Editor: Neue ACF-Felder für den CPT `contact` werden über `acf/acf-export-contact.json` eingebunden.
- Taxonomie: Neue, nicht-öffentliche Taxonomie `SOLie-Status` (`solie_status`) für den CPT `contact` zum Kategorisieren von Kontakten.

### Changed
- ACF-Loader: Statt einer einzelnen Export-Datei (`acf-export-2026-02-14.json` bzw. `acf-export-mitglied.json`) werden nun alle JSON-Exporte im Unterordner `acf/` geladen, um zukünftige Feldgruppen einfacher zu ergänzen.

## 1.3.27 - 2026-03-30

### Changed
- Mitglied-Editor: Für Datensätze mit Rolle `ansprechperson` werden die Meta-Box `Events` sowie die Taxonomie-Box `Sportarten` ausgeblendet.

## 1.3.26 - 2026-03-30

### Fixed
- ACF Checkbox: `disabled`-Wert für Feld „Ich bin“ korrigiert (Array statt bool), plus globaler Guard für ungültige Checkbox-`disabled`-Werte.

## 1.3.25 - 2026-03-30

### Fixed
- ACF: Zusätzliche Normalisierung für Checkbox-`default_value` und globalen `prepare_field`-Fallback ergänzt, um `array_map(..., true)`-Fatals zu vermeiden.

## 1.3.24 - 2026-03-30

### Fixed
- ACF: Globale Absicherung für Checkbox-Felder ergänzt, damit fehlerhafte boolesche Werte (z.B. `true`) vor dem Rendern auf Arrays normalisiert werden.

## 1.3.23 - 2026-03-30

### Fixed
- Mitglied-Editor: Fatal Error beim Rendern des ACF-Checkbox-Felds „Ich bin“ behoben (Wert wird robust auf Array normalisiert).

## 1.3.22 - 2026-03-30

### Changed
- Mitglied-Editor: Feld „Ich bin“ auch für Admins read-only/disabled gesetzt.

## 1.3.21 - 2026-03-30

### Changed
- Mitglied-Editor: Weg A umgesetzt – Feld „Ich bin“ wieder sichtbar für stabile ACF-Logik; für Nicht-Admins nur read-only/disabled.

## 1.3.20 - 2026-03-30

### Changed
- Mitglied-Editor: ACF-Feld „Ich bin“ wieder wie zuvor ausgeblendet.

## 1.3.19 - 2026-03-30

### Changed
- Mitglied-Editor: ACF-Feld „Ich bin“ für Tests wieder eingeblendet.

## 1.3.18 - 2026-03-30

### Changed
- Help/Kontakt-Widget: Farbe/Branding auf SOLIE-Rot umgestellt (statt blau).

## 1.3.17 - 2026-03-30

### Changed
- Help/Kontakt-Widget: Ecken des geöffneten Panels abgerundet (clipped).

## 1.3.16 - 2026-03-30

### Changed
- Help/Kontakt-Widget: Info-Text an gewünschte Formulierung angepasst.

## 1.3.15 - 2026-03-30

### Changed
- Help/Kontakt-Widget: Info-Text ersetzt und Header-Design (ohne Fotos) angepasst.

## 1.3.14 - 2026-03-30

### Changed
- Help/Kontakt-Widget: „?“ Icon besser zentriert.

## 1.3.13 - 2026-03-30

### Changed
- Help/Kontakt-Widget: Info-Text „Schreib uns kurz…“ weniger fett dargestellt.

## 1.3.12 - 2026-03-30

### Changed
- Help/Kontakt-Widget: „?“-Icon deutlich größer (ca. 40px) und zentriert.

## 1.3.11 - 2026-03-30

### Changed
- Settings: Tab-Überschriften dezenter und als Teil des Jump-Blocks dargestellt (kein zweiter Block).

## 1.3.10 - 2026-03-30

### Changed
- Settings: „Öffentliche Anwesenheit“ Card vom Tab „Allgemein“ in „Mobile-Anwesenheitsmodul“ verschoben.

## 1.3.9 - 2026-03-30

### Changed
- Settings: „Lohnabrechnung Mail – Betreff“ Label und Eingabefeld untereinander ausgerichtet.

## 1.3.8 - 2026-03-30

### Changed
- Settings: In jedem Tab eine Register-Überschrift oberhalb der Jump-Buttons ergänzt.
- Settings: Jump-Buttons in der Schriftgröße/Padding verbessert.

## 1.3.7 - 2026-03-30

### Changed
- Settings: „Lohnabrechnung Mail – Betreff“ fett und Betreff-Input auf 50% Breite gesetzt.

## 1.3.6 - 2026-03-30

### Changed
- Settings: Abstand innerhalb der „Lohnabrechnung Mail – Text“-Sektion verbessert.

## 1.3.5 - 2026-03-30

### Changed
- Settings: Sprung-Buttons (Jump-Buttons) im Settings-Layout haben eine größere Schrift und deutlichere Padding/Click-Höhe.

## 1.3.4 - 2026-03-30

### Changed
- Settings: „Lohnabrechnung Mail“-Karte im Benachrichtigungen-Tab erhält den gleichen Karten-Header/Abstände wie die anderen Cards.

## 1.3.3 - 2026-03-30

### Changed
- Settings: Benachrichtigungen-Tab weniger überladen (Kategorie-Karten nicht mehr standardmäßig alle geöffnet).
- Help Widget: „?“ Icon weniger fett und besser zentriert/ausgerichtet.

## 1.3.2 - 2026-03-30

### Changed
- Settings: Tabs/Content neu strukturiert: „Darstellung“ in „Allgemein“, Lohnabrechnung-Mailfelder in „Benachrichtigungen“, Attendance Security umbenannt zu „Mobile-Anwesenheitsmodul“.
- Settings: In jedem Tab „Jump“-Buttons hinzugefügt, die zu den jeweiligen Cards scrollen; Lohnabrechnung Mailtext nun zweispaltig (Text links, Vorschau rechts).
- Help Widget: Icon auf „?“ umgestellt (ca. 10px höher), Panel zeigt kurzes Info-Textstück + die konfigurierte Mailadresse.
- Help Widget: Icon wird nicht angezeigt, wenn „Hilfe-Anfragen (Kontakt-Widget)“ deaktiviert ist.

## 1.3.1 - 2026-03-30

### Changed
- Settings: Benachrichtigungen-Tab modernisiert (kategoriebezogene Karten); Aktivieren/Deaktivieren steht jetzt dort, wo auch Empfänger und Mailtexte gepflegt werden.
- Settings: Lohnabrechnung (PDF per E-Mail) Toggle entfernt; Versand von Lohnabrechnungen ist nicht mehr über einen UI-Schalter deaktivierbar.
- Settings: Live-Vorschau für Lohnabrechnung Mail-Templates ergänzt.

## 1.3.0 - 2026-03-29

### Added
- Person picker: search matches Sportart taxonomy; selected persons show sport names in parentheses.
- Add New User: optional Sportarten (checkboxes) applied to the created Mitglied post.
- Telefonbuch „Alle Daten“: Bank and IBAN columns for administrators only; Excel export includes them for administrators only.
- Settings: per-category e-mail toggles (Lohnabrechnung, neues Mitglied, Training abgeschlossen, neues Event, Willkommens-Mail, Hilfe-Feld) with short descriptions; optional help recipient e-mail.
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
- Sportarten submenu under Trainings (admin-only) for managing sport taxonomy terms.

### Changed
- Buchhaltungsnummern moved from Einstellungen tab "Allgemein" to "Lohnabrechnung" as first item.
- Sportarten submenu access restricted to Administrators (manage_options).

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
- Reworked plugin settings page into tabs for better usability (`Allgemein`, `Lohnabrechnung`, `Benachrichtigungen`, `Attendance Security`).

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
