# FortnoxBundle for Kimai

**FortnoxBundle** is a [Kimai](https://www.kimai.org/) plugin designed to streamline accounting, invoicing, and reporting workflows for organizations using Fortnox. It provides dedicated PDF time report generation formatted for Fortnox customer invoice attachments.

---

## Features

- **Fortnox Time Report Generation**:
  - Filter timesheet records by customer, project, date range, billable status, and export state.
  - Generates clean PDF reports formatted with date, consultant/user, activity, description, and duration ready for customer invoice attachments.
- **Fortnox Integration Foundation**:
  - Includes a client scaffold for direct API communication and PDF uploading to the Fortnox customer invoice inbox (`Inbox_kf`).
- **Fine-Grained Permissions**:
  - Secure access controls restricting report generation and exports to authorized roles.

---

## Requirements

- Kimai `>= 2.0.0`
- PHP `>= 8.2`

---

## Installation

### Standard Installation

1. **Clone or copy** the plugin into Kimai's plugin directory:
   ```bash
   # Destination folder: var/plugins/FortnoxBundle
   cd /path/to/kimai/var/plugins
   git clone <repository-url> FortnoxBundle
   ```

2. **Reload Kimai plugins / Install**:
   ```bash
   bin/console kimai:reload -n
   ```

3. **Clear the cache**:
   ```bash
   bin/console cache:clear
   ```

---

### Docker & DDEV Installation

If you are running Kimai inside a Docker / DDEV environment:

1. **Copy the plugin files** into `var/plugins/FortnoxBundle`.

2. **Reload plugins inside the container**:
   ```bash
   ddev exec bin/console kimai:reload -n
   ```

3. **Clear container cache**:
   ```bash
   ddev exec bin/console cache:clear
   ```

---

## Permissions & Roles

The plugin registers dedicated permissions that integrate into Kimai's role and permission management:

| Permission | Description | Default Roles |
| :--- | :--- | :--- |
| `fortnox_export` | Access the Fortnox export report and generate invoice attachments | `ROLE_SUPER_ADMIN` |

Permissions can be customized anytime via the Kimai UI under **System > Role permissions**.

---

## Development & Testing

Run tests and code checks using the provided scripts:

```bash
# Run PHPUnit test suite for FortnoxBundle
vendor/bin/phpunit var/plugins/FortnoxBundle/tests/

# Run static analysis (PHPStan)
./phpstan.sh core

# Run code style fixer (PHP-CS-Fixer)
./php-cs-fixer.sh core
```

*(Prepend `ddev exec` if executing within DDEV)*

---

## Roadmap & Next Milestones

1. **Fortnox OAuth Integration**: OAuth authorization flow and secure token persistence.
2. **Direct Inbox Upload**: One-click upload of generated time report PDFs directly to Fortnox `Inbox_kf`.
3. **Export History & Status Synchronization**: Automatic marking and tracking of exported entries.

---

## License

This bundle is licensed under the [AGPL-3.0-or-later License](https://www.gnu.org/licenses/agpl-3.0.html).
