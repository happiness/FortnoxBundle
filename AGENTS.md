# FortnoxBundle Agent Guide

Use this guide when working on the **FortnoxBundle** plugin in `var/plugins/FortnoxBundle`.

---

## Overview & Scope

- **Plugin Name**: `FortnoxBundle` (`happiness/fortnox-bundle` or `KimaiPlugin\FortnoxBundle`)
- **Description**: Kimai plugin designed to streamline accounting, invoicing, and reporting workflows for organizations integrating with Fortnox.
- **Primary Capabilities**:
  - Dedicated time report generation and PDF exports formatted as Fortnox customer invoice attachments.
  - Export preview enhancements including aggregated User and Activity summary breakdowns.
  - Service foundation for direct Fortnox REST API communication and invoice inbox (`Inbox_kf`) uploads.
- **Scope Boundary**: All plugin development, templates, configuration, and tests must remain strictly isolated within `var/plugins/FortnoxBundle/` and plugin-specific test paths. **Never modify Kimai core files** in `src/`, `templates/`, `config/`, or `migrations/`.

---

## Stack & Requirements

- **Kimai Version**: `>= 2.0.0` (Kimai 2.x)
- **PHP Version**: `8.2` - `8.4` (DDEV default: `8.4`, PHP 8.4 compatible)
- **Framework**: Symfony 6.4 LTS, Twig, Doctrine ORM
- **Frontend**: Bootstrap 5 with Tabler UI framework

---

## Repository Map

```text
var/plugins/FortnoxBundle/
├── AGENTS.md                          # This agent guide
├── composer.json                      # Plugin metadata and Kimai version requirements
├── FortnoxBundle.php                  # Bundle entry point (implements App\Plugin\PluginInterface)
├── README.md                          # User & developer documentation
├── Controller/
│   └── FortnoxController.php          # Time report and export controller actions
├── DependencyInjection/
│   └── FortnoxExtension.php          # Container extension (prepends Twig views path)
├── EventSubscriber/
│   └── MenuSubscriber.php            # Injects navigation items into Kimai main/admin menus
├── Form/
│   ├── TimeReportType.php             # Filter form for generating time reports
│   └── Model/
│       └── TimeReportFilter.php       # Data model for report criteria
├── Resources/
│   ├── config/
│   │   ├── routes.yaml                # Plugin routes definitions
│   │   └── services.yaml              # Service registration & autowiring
│   ├── translations/
│   │   ├── messages.en.yaml           # English translation strings
│   │   └── messages.sv.yaml           # Swedish translation strings
│   └── views/
│       ├── fortnox/                   # Plugin views (reports, configuration)
│       └── export/
│           └── index.html.twig        # Overridden core export preview template
├── Service/
│   ├── FortnoxClient.php              # Fortnox API communication client
│   ├── PdfGenerator.php               # Time report PDF rendering service
│   └── TimeReportService.php          # Timesheet data filtering and report aggregation
└── tests/
    └── FortnoxBundleTest.php          # Unit and integration test suite
```

---

## Architectural Rules & Key Decisions

1. **Non-Intrusive Template Overrides**:
   - `FortnoxExtension` implements `Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface` to prepend `var/plugins/FortnoxBundle/Resources/views` to `twig.paths`.
   - Templates like `Resources/views/export/index.html.twig` cleanly override core templates without modifying `templates/` in the Kimai core repository.
2. **Export Preview User/Activity Aggregation**:
   - In `FortnoxTwigExtension` / services, user and activity summaries must use $O(N)$ single-pass aggregation over `ExportableItem` entries.
   - Grouping uses composite keys `sprintf('%s_%s_%s', $userId, $activityId, $currency)` so multi-currency query results are never erroneously summed together.
   - Respect user permission flags (`show_rates` governed by `view_rate_other_timesheet` / `view_rate_own_timesheet`) so financial rate columns remain hidden from unauthorized users.
   - Null / unassigned activities must be safely handled with clean fallbacks.
3. **Permissions & Security**:
   - Dedicated permission: `fortnox_export` (default role: `ROLE_SUPER_ADMIN`).
   - Secure all controller endpoints and menu items with permission checks (`#[IsGranted('fortnox_export')]`).

---

## Validation & Quality Assurance

Always validate changes using containerized DDEV or host CLI commands:

```bash
# Run FortnoxBundle PHPUnit test suite
ddev exec vendor/bin/phpunit var/plugins/FortnoxBundle/tests/
# (or on host): vendor/bin/phpunit var/plugins/FortnoxBundle/tests/

# Run static analysis (PHPStan)
ddev exec ./phpstan.sh core
# (or on host): ./phpstan.sh core

# Run code style fixer (PHP-CS-Fixer)
ddev exec ./php-cs-fixer.sh core
# (or on host): ./php-cs-fixer.sh core

# Clear Kimai cache after adding or modifying routes/templates
ddev exec bin/console cache:clear
```

---

## Coding Conventions

- Add `declare(strict_types=1);` at the top of every PHP file.
- Use constructor property promotion for dependency injection.
- Use native PHP 8 attributes for routing and authorization.
- Use strict comparisons (`===`, `!==`).
- Keep English translations (`messages.en.yaml`) in sync whenever new translation keys are introduced (Swedish in `messages.sv.yaml`).
- Always follow Bootstrap 5 and Tabler UI markup conventions.
