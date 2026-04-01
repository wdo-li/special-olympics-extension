# Special Olympics Extension

Special Olympics Liechtenstein WordPress extension plugin for structured member management, training and event operations, attendance workflows, payroll preparation, and admin communication tools.

## Overview

This plugin extends the WordPress backend with role-aware workflows used by Special Olympics Liechtenstein:

- `mitglied` management (including account-related fields and role-aware restrictions)
- Training management with generated sessions and attendance capture
- Event management with participant-role assignment and member snapshots
- Payroll preparation and export workflows
- Role-based dashboard and emergency-focused phonebook views
- Shared taxonomies (e.g. `sport`) used across modules

## Main Features

### Members (`mitglied`)
- Custom capabilities and role-aware visibility
- "My Account" integration for linked WP users
- Profile support fields, emergency contact handling, archive status
- Event snapshot display inside member records

### Trainings
- Admin pages for training lifecycle management
- Session generation from date rules (with exclusions)
- Attendance entry (desktop and mobile optimized views)
- Statistics and XLS exports

### Events
- Admin/Hauptleiter workflows for event planning
- Participant assignment by role groups
- Event snapshot sync back to member records
- Event type and sport filtering

### Payroll
- Data collection from trainings/events
- Manual adjustments and status flow (draft/checked/completed)
- PDF generation and download/mail workflows
- History and audit-friendly records

### Phonebook / Dashboard / Tools
- Role-based phonebook access with emergency mode
- Role-specific dashboard views and quick actions
- Settings pages for durations, rates, mail templates, and BH mappings

## Requirements

- WordPress (current maintained versions)
- PHP 8.x
- **Advanced Custom Fields (ACF)** plugin

## Installation

1. Place the `special-olympics-extension` folder in `wp-content/plugins/`.
2. Activate the plugin in WordPress admin.
3. Ensure ACF is active.

## Documentation

- Feature history and releases: [`CHANGELOG.md`](CHANGELOG.md)
- Internal functional specification (detailed): [`Funktionen.md`](Funktionen.md)

## AI-Assisted Development Note

This plugin has been developed with significant AI assistance as part of the implementation workflow.

