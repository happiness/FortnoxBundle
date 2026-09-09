# FortnoxBundle for Kimai

First implementation of a Kimai plugin that creates a PDF time report intended to be attached to a Fortnox customer invoice.

## Scope of v0.1

- Adds a `Fortnox export` menu item.
- Adds permission `fortnox_export`, granted to `ROLE_SUPER_ADMIN` by default.
- Lets the user choose customer, project, start date and end date.
- Optionally restricts results to billable and not-yet-exported time entries.
- Uses Kimai's `TimesheetQuery`, including current-user/team filtering.
- Shows a preview in Kimai.
- Generates a Swedish PDF with date, consultant, activity, description and duration.
- Includes a `FortnoxClient` scaffold for the next milestone.

## Requirements

This version targets the current Kimai 2 plugin API and PHP 8.2+. It uses mPDF, which is included by current Kimai installations.

Before production use, test it against your exact Kimai version. Kimai's internal PHP APIs are not as stable as the public REST API.

## Installation

Copy the directory so it becomes:

    var/plugins/FortnoxBundle/FortnoxBundle.php

Then run from the Kimai installation directory:

    bin/console kimai:reload -n

If needed, clear the cache:

    bin/console cache:clear

Log in as a super admin. A new `Fortnox export` entry should appear in the main menu.

## Security / access

The plugin registers a `fortnox_export` permission and grants it only to `ROLE_SUPER_ADMIN` by default. Assign the permission to another Kimai role in Kimai's role/permission administration if required.

The timesheet query calls `setCurrentUser()`, so Kimai's team restrictions are applied by the repository.

## Fortnox upload, next milestone

`Service/FortnoxClient.php` contains the starting point for uploading the generated PDF to the Fortnox customer-invoice inbox, `Inbox_kf`. OAuth token acquisition, refresh-token persistence, encryption and UI settings are intentionally not implemented in v0.1.

The next milestone should add:

1. Fortnox OAuth authorization and callback routes.
2. Encrypted refresh-token storage.
3. An `Upload to Fortnox` action after PDF preview.
4. Export history / duplicate detection.
5. Optional marking of the corresponding Kimai entries as exported.

## Known v0.1 limitation

The project dropdown currently lists all projects visible through Doctrine's entity form loading. The controller validates that the selected project belongs to the selected customer. A follow-up version should make the project selector dynamically depend on the customer.
