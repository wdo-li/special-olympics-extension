# Special Olympics Extension

> **Wichtig / Important**
>
> **DE:** Dieses Plugin greift tief in WordPress ein (Rollen, Capabilities, Admin-Menüs, Login, eingeschränkte Bereiche, Custom Post Types, Taxonomien, eigene Datenbanktabellen, Rewrite-Regeln, ACF-Integration). Es sollte **nicht zum ersten Mal auf einer produktiven Live-Site** installiert oder aktiviert werden. Bitte zuerst auf einer **Staging-/Entwicklungsumgebung** einspielen, testen, Backups anlegen und erst danach produktiv ausrollen.
>
> **EN:** This plugin **heavily customizes WordPress** (roles, capabilities, admin menus, login, access restrictions, custom post types, taxonomies, custom database tables, rewrite rules, ACF integration). **Do not use a production site for the first install or activation.** Use a **staging or local dev environment** first, verify behaviour, keep backups, then deploy to production.

Special Olympics Liechtenstein WordPress extension plugin for structured member management, training and event operations, attendance workflows, payroll preparation, admin tools, **contacts (sponsors/partners)**, and **contact action campaigns** (checklist-style tracking per action).

## Overview

The plugin extends the WordPress admin and front-end flows used by Special Olympics Liechtenstein. It ships **Composer dependencies** under `vendor/` (see `composer.json`) and **ACF field groups** as JSON under `acf/`.

High-level areas:

| Area | Main files / notes |
|------|---------------------|
| Roles & capabilities | `includes/roles.php`, `mitglied-capabilities.php`, `training-capabilities.php`, `event-capabilities.php`, `role-sync.php` |
| Members (`mitglied`) | `post-type-mitglied-register.php`, `post-type-mitglied.php`, `user-to-mitglied-sync.php`, `account-page.php`, `meine-athleten-page.php` |
| Trainings & attendance | `post-type-training-register.php`, `custom-trainings.php`, `attendance-public.php`, `attendance-token.php` |
| Events | `post-type-event-register.php`, `custom-events.php` |
| Payroll | `custom-payrolls.php`, `payroll.php` |
| Contacts & actions | `post-type-contact-register.php`, `post-type-contact.php`, `contact-archive.php`, `taxonomy-contact-action-type.php`, `contact-actions.php` (admin) |
| Phonebook & dashboard | `telefonbuch.php`, `dashboard.php` |
| Sport / event taxonomies | `taxonomy-sport.php`, `taxonomy-event-type.php` |
| Security & UX | `intranet-restrict.php`, `login-customize.php`, `admin-menu.php`, `protected-medical-files.php`, `acf-encrypted-fields.php` |
| Settings & tools | `settings.php`, `export-xls.php`, `help-widget.php`, `github-updater.php`, `ajax-person-search.php` |

## Main Features

### Members (`mitglied`)

- Custom capabilities and role-aware visibility  
- Account integration for linked WordPress users  
- Profile and emergency contact fields, archive-style status handling  
- Event snapshot display inside member records  

### Trainings

- Admin UI for training lifecycle and session generation (date rules, exclusions)  
- Attendance capture (desktop and mobile-friendly routes)  
- Statistics and XLS exports  

### Events

- Admin / Hauptleiter workflows for planning  
- Participant assignment by role groups  
- Event snapshot sync back to member records  

### Payroll

- Data from trainings/events, manual adjustments, status workflow  
- PDF generation and mail/download flows  

### Contacts (`contact`) and contact actions

- Non-public **contact** CPT for sponsors/partners/etc. (admin-only editing, ACF-driven fields)  
- **Archive / restore** workflow via post meta (`contact-archive.php`)  
- **Contact actions**: per-campaign checklist fields, per-contact status/notes, CSV/XLSX export (admin UI in `contact-actions.php`)  
- **Contact action type** taxonomy for categorizing actions  

### Phonebook / dashboard / tools

- Role-based phonebook (members vs contacts modes for admins)  
- Role-specific dashboard widgets and quick actions  
- Settings for durations, rates, mail templates, BH mappings, encrypted ACF guidance  

## Requirements

- WordPress (current maintained versions)  
- **PHP 8.0+** (target aligned with project standards)  
- **[Advanced Custom Fields (ACF)](https://www.advancedcustomfields.com/)** — required; field groups are loaded from `acf/*.json` via `acf-load-json.php`  

## Installation

1. Copy the `special-olympics-extension` folder into `wp-content/plugins/`.  
2. Ensure **ACF** is installed and active **before** or when activating this plugin.  
3. Activate **Special Olympics Extension** in **Plugins**. Activation runs `soe_create_tables()` and related setup (`includes/database.php`).  
4. Configure options under the plugin’s **Einstellungen** / settings screens as needed (see `settings.php`).  

Again: use **staging first**, not production, for first-time rollout.

## Repository layout (selected)

- `plugin.php` — bootstrap, version constant, conditional admin includes  
- `includes/` — PHP modules (see table above)  
- `assets/` — CSS/JS for admin and public views  
- `acf/` — ACF JSON exports for field groups  
- `snippets/` — optional reference snippets (not auto-loaded)  
- `vendor/` — Composer dependencies (plugin ships with vendor for deployment)  
- `CHANGELOG.md` — release history (Keep a Changelog style)  

## Documentation

- Release and behaviour changes: [`CHANGELOG.md`](CHANGELOG.md)  

## AI-assisted development

Parts of this codebase were implemented or iterated with AI assistance as part of the development workflow.
