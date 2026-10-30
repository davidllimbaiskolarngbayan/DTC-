# DTC Local

Market-neutral Warranty (DTC) app: Laravel admin + API, with a WordPress connector plugin. Free tools only (PHP, MySQL/MariaDB, WordPress, Mailpit).

## Setup (XAMPP)

1. `composer install`, copy `.env.example` to `.env`, then `php artisan key:generate`.
2. Create the `dtc` database, then `php artisan migrate`.
3. Set `DTC_API_KEY` in `.env` (generate with `php -r "echo bin2hex(random_bytes(32));"`).
4. `php artisan dtc:create-admin you@example.com` (prompts for password), then `php artisan serve`.
5. Run [Mailpit](https://github.com/axllent/mailpit) (SMTP 1025, UI 8025) to view outgoing mail.
6. WordPress: install it in XAMPP, copy `wordpress/plugins/dtc-connector` into `wp-content/plugins`, activate it, and add to `wp-config.php`:
   ```php
   define('DTC_API_URL', 'http://localhost:8000/api');
   define('DTC_API_KEY', '<same key as .env>');
   ```
7. Add `[dtc_register]` and `[dtc_check]` shortcodes to WordPress pages.

## Setup (Docker/Podman)

Set `DB_ROOT_PASSWORD` and `DTC_API_KEY` in `.env`, run `docker compose up -d --build`, then `docker compose exec laravel php artisan dtc:create-admin you@example.com`.
Laravel: http://localhost:8000, WordPress: http://localhost:8080, Mailpit: http://localhost:8025.

For a public Docker VM deployment with HTTPS and persistent data, follow [DEPLOY.md](./DEPLOY.md).

## API (header `X-DTC-Key` required)

- `POST /api/register` - fields as in `app/Support/WarrantyRules.php`, optional `invoice` file.
- `POST /api/check` - `query` = card number or vehicle plate; returns non-personal warranty data and claims.
- `DELETE /api/dtc/{id}` - soft-deletes a registration; requires both `X-DTC-Key` and an admin Sanctum bearer token.

## Tests

`php vendor/bin/phpunit` (uses in-memory SQLite).

## Activity log retention

Activity records stay in the active database for two years. The monthly `activity:archive` task writes older records as compressed JSON Lines files under the private `storage/app/activity-archives` directory, then removes them from the active table only after the archive succeeds. Archived files are retained and are not publicly served.

To preview or run the archive manually:

```sh
php artisan activity:archive --dry-run
php artisan activity:archive
```

For automatic monthly archiving, configure the host scheduler to run `php artisan schedule:run` every minute. On a local development machine, `php artisan schedule:work` can be used while the app is running.

## Claim follow-up, deadlines, assignment, and reports

The scheduler retries failed claim emails up to three times (after 5, 30, and 120 minutes), sends staff reminders for open claims with no activity for three days, and removes private claim attachments after one year. Submitted claims still awaiting review after three days are marked overdue in the staff portal. Admins can assign claims to staff from either the claims list or claim details; staff can assign an unassigned claim to themselves. The assigned staff member receives an email when an assignment is created or changed. **Claims reports** provides date-filtered status totals and monthly submission counts. Detailed claim CSV exports remain admin-only.

Choose one or more staff accounts from **Staff Alerts** in the authenticated dashboard; the selected accounts receive email alerts and follow-up reminders. In the portal, each staff member has independent persistent unread claim alerts. Opening an alert marks it read for that staff member; new customer responses create a separate alert. New staff accounts receive alerts for currently submitted claims.

Claim attachments are stored privately under `storage/app/private/claims`. Configure the host's backup system to take a consistent database dump and encrypted backup of the private claim attachment directory, transfer them off-machine, and restrict access to authorized operators. Do not store backup credentials in this repository or in shell history. The app does not assume a backup destination or credentials.

After the host creates each snapshot pair, check that both parts are present and readable:

```powershell
php artisan backups:verify "D:\DtcBackups\dtc.sql.gz" "D:\DtcBackups\claims"
```

`backups:verify` is a read-only presence/readability check; it is not a database integrity check or a restore test. At least periodically, restore the dump to an isolated staging database, restore attachments to a separate private storage location, verify the application can read test records and attachments, then remove the staging data using the host's approved process. Never test a restore over the live database or live attachment directory. Keep a written record of the snapshot date and restore-test result.

Customers can check claim updates only after matching both their DTC Code and registered plate number. When a claim is marked **Need More Info**, the customer can submit additional information from Check Warranty after the same two details and claim number are verified. Customer responses are shown in claim history and the public status history; internal staff notes remain private.

## Staff and administrator accounts

There is no public staff sign-up. An administrator creates accounts from **Staff accounts** and assigns either **Staff** or **Admin**. Staff can view registrants, download the date-filtered DTC user report, create/review claims, assign unassigned claims to themselves, review the activity log, receive persistent claim alerts, and view date-filtered claim reports; they cannot edit or delete registrations, export claim data, manage DTC Codes, change email alert recipients, assign claims to other staff, or create accounts. Admins can access all portal features, assign claims to staff, and manage staff accounts. The first existing account is promoted to admin by the bootstrap migration only if no admin exists, so an initial administrator can access account management.
