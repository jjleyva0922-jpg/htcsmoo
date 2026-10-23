# Supply Management Office Request Tracking and Inventory System

<p align="center">
  <strong>Holy Trinity College of General Santos City</strong><br>
  College of Engineering and Technology Education (CETE)<br>
  Bachelor of Science in Information Technology (BSIT) &bull; Capstone Project
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2 or later">
  <img src="https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL or MariaDB">
  <img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap 5.3">
  <img src="https://img.shields.io/badge/Alpine.js-3.x-8BC0D0?style=for-the-badge&logo=alpinedotjs&logoColor=white" alt="Alpine.js 3">
</p>

## About the project

The Supply Management Office Request Tracking and Inventory System is a web-based platform for managing institutional supply requisitions at Holy Trinity College of General Santos City (HTC).

It replaces a paper-based process with a centralized workflow where employees can submit requests, authorized signatories can review them, and Supply Management Office (SMO) personnel can manage inventory and release approved items. The system also provides status tracking, deadline alerts, notifications, PDF reports, and an auditable approval history.

The project is intended to support transparent electronic records and approval workflows in line with the objectives of the Philippine Electronic Commerce Act of 2000 (Republic Act No. 8792).

## Main features

- Multi-item supply requisitions with automatic totals
- Minor and major request workflows based on the PHP 1,000 threshold
- Role-based approval queues for seven institutional roles
- Request tracking numbers and a visual progress tracker
- Approve, reject, and office-clarification decisions
- Digital approval records with SHA-256 signature tokens
- Inventory management and release deductions
- Inventory transaction logs and low-stock monitoring
- In-app and email notifications
- Day 2 warning and Day 3 overdue SLA alerts
- Filterable reports and PDF generation
- User registration, account approval, and role management
- Request and administrative archives

## Approval workflow

Every standard request begins with the requester's department head.

```text
Employee / Faculty
        |
        v
Department Head
        |
        +------------------------------+
        |                              |
        v                              v
Minor request                    Major request
(PHP 1,000 or less)              (more than PHP 1,000)
        |                              |
        v                              v
VP for Finance                   VP for Administration
        |                              |
        |                              v
        |                         School Provost
        |                              |
        |                              v
        |                         School President
        |                              |
        +---------------+--------------+
                        |
                        v
                 SMO verification
                        |
                        v
                  Item release
```

At each applicable approval stage, a signatory can:

1. **Approve** the requisition and send it to the next stage.
2. **Reject** the requisition and provide an explanatory remark.
3. **Request clarification** and place the requisition in a clarification queue while notifying the requester and SMO.

## User roles

| Role         | Primary responsibility                                    |
| ------------ | --------------------------------------------------------- |
| `employee`   | Creates and tracks supply requisitions                    |
| `dept_head`  | Performs the first departmental review                    |
| `vp_finance` | Reviews minor requisitions                                |
| `vp_admin`   | Reviews major requisitions                                |
| `provost`    | Reviews endorsed major requisitions                       |
| `president`  | Gives final approval to major requisitions                |
| `smo`        | Manages users, inventory, releases, reports, and archives |

## Technology stack

| Layer             | Technology                                   |
| ----------------- | -------------------------------------------- |
| Backend           | PHP 8.2+, Laravel 12, Eloquent ORM           |
| Database          | MySQL or MariaDB                             |
| Frontend          | Blade, Bootstrap 5.3, Alpine.js, Vite        |
| PDF generation    | Barryvdh Laravel DomPDF                      |
| Email             | Laravel Mail (SMTP or log driver)            |
| Testing           | PHPUnit 11                                   |
| Local environment | XAMPP or an equivalent PHP/MySQL environment |

See the [entity-relationship diagram](database/ERD.md) for the current application data model.

## Requirements

Install the following before setting up the application:

- PHP 8.2 or later with the extensions required by Laravel
- Composer 2
- Node.js and npm
- MySQL 8 or MariaDB
- Git

For Windows development, XAMPP can provide Apache, PHP, and MariaDB. Make sure the PHP executable used by Composer and Artisan is PHP 8.2 or later.

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/rivacjayrex-dotcom/htc-supply-management-system.git
cd htc-supply-management-system
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Create the environment file

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

On macOS or Linux:

```bash
cp .env.example .env
```

Generate the application key once for a new installation (never as a deployment step):

```bash
php artisan key:generate
```

### 4. Configure the database

Create a MySQL or MariaDB database named `htc_supply_system`, then update these values in `.env`:

```dotenv
APP_NAME="HTC Supply Management System"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=htc_supply_system
DB_USERNAME=root
DB_PASSWORD=
```

### Broadcast server to all connected wifi

php artisan serve --host=0.0.0.0 --port=8000

Adjust the database username and password for your local environment.

### 5. Configure email

The default `.env.example` writes email to the application log. To deliver email through an SMTP provider, replace the mail values in `.env`, for example:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS="smo-notifications@online.htcgsc.edu.ph"
MAIL_FROM_NAME="${APP_NAME}"
```

Do not commit real SMTP credentials to the repository.

### 6. Create the database tables and seed reference data

```bash
php artisan migrate
php artisan db:seed
php artisan db:seed --class=SupplyCategorySeeder
```

`DatabaseSeeder` creates departments and approved workflow accounts. `SupplyCategorySeeder` creates the initial supply categories.

The seeded accounts use the password stored in `SEED_ACCOUNT_PASSWORD`. The development default in `.env.example` is `Password123!`; change it before seeding any shared or production environment.

### 7. Build frontend assets

```bash
npm run build
```

### 8. Start the application

For a simple local session, run these commands in separate terminals:

```bash
php artisan serve
```

```bash
npm run dev
```

### Render environment settings

Keep `APP_KEY` stable between deployments. Generate it once with
`php artisan key:generate --show`, save that value in Render, and never rotate
it during a build. Rotating the key invalidates sessions and produces 419
responses for forms that were already open.

Recommended Render environment variables:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-service.onrender.com
APP_KEY=base64:your-persistent-key
SESSION_DRIVER=database
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

### Session expiry and deployment checks

Keep the approved idle policy in `SESSION_LIFETIME` (the example remains 120 minutes); increasing it increases the exposure of unattended accounts. `SESSION_EXPIRE_ON_CLOSE=true` requests a browser-session cookie, but browsers may restore those cookies when restoring tabs. Remember-me authentication is a separate, explicit login choice; session expiry can still invalidate an old form's CSRF token.

Runtime/version/notification polls and background workspace/feed refreshes read sessions without saving them or renewing the session cookie. Normal navigation, searches, and explicit actions remain session activity. There is no keep-alive heartbeat. A native write first checks its existing CSRF token and account with `POST /session/check`; the check never returns a fresh token and runs only when the user submits. It adds one request before the write and does not eliminate an expiry race between those requests.

When expiry is detected, an accessible dialog offers sign-in in a new tab and leaves current entries on screen for copying. Background work stops; failed actions are never automatically replayed. After signing in, open a fresh form, copy the entries, and review before submitting. Passwords and file selections must be entered again. Drafts are not saved to browser storage. With JavaScript disabled or if expiry occurs after the check, the server returns a 401/419 recovery page: browser Back may retain entries, but restoration cannot be guaranteed. The UI explains that limitation. Recovery return URLs are restricted to validated local application screens and revalidated after login.

Before deploying or investigating recurring 419s, verify these operator-controlled settings without logging their secret values:

- Keep the same `APP_KEY` on every web instance and across releases. The Docker entrypoint already requires a configured key. `composer run setup` generates a key and is for initial local setup only; do not run it during production deployments. Planned key rotation can invalidate existing cookies and open forms.
- Use one persistent, shared session backend for all instances. `SESSION_DRIVER=database` requires the migrated `sessions` table and the same database/`SESSION_CONNECTION` everywhere; Redis requires the same connection, database, and key prefix. An instance-local file driver or ephemeral SQLite database loses sessions on restarts and does not share sessions between replicas. Shared storage adds an infrastructure dependency; monitor availability and eviction/cleanup policy. Do not use `array` in production.
- Set `APP_URL` to the canonical HTTPS origin, enforce HTTPS, and set `SESSION_SECURE_COOKIE=true`. The production default is secure cookies; HTTP local development must use false. Ensure the trusted reverse proxy supplies the correct forwarded scheme/host and prevent clients from bypassing that proxy (the current bootstrap trusts all proxies). A secure cookie is not sent over HTTP.
- Prefer a host-only `SESSION_DOMAIN` (null/blank); do not put a scheme, port, or path in it. Configure a parent domain only when subdomains intentionally share authentication. Keep `SESSION_PATH` compatible with the application's mount path, `SESSION_HTTP_ONLY=true`, and `SESSION_SAME_SITE=lax` for this first-party app. Cross-site embedding would require a separate policy review; `SameSite=None` requires Secure and broadens cross-site cookie delivery.
- Keep `APP_NAME` or an explicit `SESSION_COOKIE` stable across releases and distinct from other Laravel applications on the same host. Cookie name, domain, path, key, or storage changes can end existing sessions. Refresh configuration caches on every instance after environment changes; deploy compatible code/assets together. Exclude authenticated HTML, auth forms, and session endpoints from shared/CDN caches.

These are deployment risks, not evidence of the current production configuration. Diagnose with HTTP status, cookie attributes, request scheme/host, backend availability, and deployment timing. Never capture passwords, form bodies, CSRF/session tokens, cookies, or `APP_KEY` in diagnostic logs.

First-party browser paths covered by expiry recovery:

| Path | Expiry behavior |
| --- | --- |
| Runtime polling and workspace refresh | Stop polling/replacement, leave work in place, show recovery |
| Requisition catalog, dynamic search, notification feed, PDF fetch | Share 401/419 handling (including a login redirect); expiry bypasses navigation/retry fallbacks |
| Notification mark-read and mark-all-read | Show recovery on expiry; other failures explain uncertain outcome without submitting the form again |
| Requisition, inventory, profile/password, approval/release, user management, notification-open, logout, registration/login/reset/verification forms | Check the existing token/account before native writes; reject stale/missing tokens; preserve entries where possible and never replay after sign-in |
| GET filters, report exports, downloads, and ordinary navigation | Standard authentication redirect for navigation; safe local return URL revalidated after sign-in |

Regression checks: `php artisan test --filter=SessionRecoveryTest` and `npm run test:frontend`.

The test suite is restricted to in-memory SQLite. Clear locally cached
configuration with `php artisan config:clear` before running tests.

Open `http://127.0.0.1:8000` in a browser.

Alternatively, the Composer development script starts the Laravel server, queue listener, log viewer, and Vite together:

```bash
composer run dev
```

## Performance and background email

Open tabs share one runtime poller per signed-in account when BroadcastChannel and browser storage are available. Polls read cached change markers instead of recalculating totals across business tables. Inventory changes do not invalidate request-only workspaces. Dashboard, inventory, request/archive pages, and approval tables refresh their live content in place; open dialogs, selected inventory rows, and edited forms defer replacement. Other pages offer a refresh button when updates are available.

Inventory forecasts aggregate release history by month and cache results for five minutes. Model changes invalidate the cache after their database transaction commits. After importing SQL or making bulk changes outside Eloquent model events, run `php artisan cache:clear` to invalidate forecasts and workspace versions.

Approval queues show 15 records per page. Report previews show 50 source records per page and label summaries as page totals; CSV and PDF exports include all matching records.

Submitting a requisition queues a confirmation email for the requestor and a new-request email for every active SMO user. An SMO user submitting their own request receives one confirmation. Submission, approval, and fulfillment emails run on the `mail` queue with three attempts and delivery/failure logging. Email delivery requires SMTP settings and a running worker; Mailtrap Sandbox captures these emails for testing instead of delivering them to users' inboxes. Docker starts the worker automatically; `composer run dev` also processes this queue. On XAMPP without the Composer development runner, start a hidden worker from PowerShell:

```powershell
.\tools\start-mail-worker.ps1
```

The launcher avoids starting a second copy of its worker. Restart it after restarting Windows. Its output is in `storage/logs/mail-worker.log` and `storage/logs/mail-worker-error.log`. On other hosts, supervise this command:

```bash
php artisan queue:work --queue=mail,default --sleep=5 --tries=3 --timeout=30
```

Restart queue workers after deploying PHP changes with `php artisan queue:restart`. After the existing worker exits, start the XAMPP launcher again or let the process supervisor restart it. Keep local development settings separate from production; the Docker entrypoint already caches configuration, routes, and views.

Performance regressions are covered by `php artisan test --filter=WorkspacePerformanceTest` and `npm run test:frontend`. These checks verify that idle polling avoids business-table queries, warm forecasts avoid history queries, dashboard fragments are less than half the full response size in the test fixture, tabs share polling, and unsaved edits survive background updates.

## Scheduled SLA alerts

The `smo:check-deadlines` command checks every active requisition against one fixed SLA that starts at initial submission. It warns after two working days and marks the request overdue after three working days, excluding weekends and dates stored in `institutional_holidays`. Alerts go to the requestor, all active SMO users, and the approver currently responsible for the request. Laravel schedules the command daily at 8:00 AM in the configured application timezone (`Asia/Manila` by default). Read notifications older than one year are pruned by a separate daily task.

During local development, keep the scheduler running:

```bash
php artisan schedule:work
```

The included Docker entrypoint starts `schedule:work` automatically. Non-Docker deployments must configure the server to execute Laravel's scheduler every minute.

The command can also be run manually:

```bash
php artisan smo:check-deadlines
```

## Testing

Run the automated test suite with either command:

```bash
composer test
```

```bash
php artisan test
```

## Tracking and audit records

The system generates institutional tracking numbers such as:

- Minor: `MIN-CETE-2026100001`
- Major: `MAJ-CETE-2026200001`

The format is `TIER-DEPARTMENT-YEAR` followed by the tier digit (`1` for minor, `2` for major) and the requisition ID padded to at least five digits. The year comes from the submission date, falling back to the creation date for older records. IDs remain unique across requisitions and do not reset by year, department, or tier. New requisitions and the missing-number backfill use this format; already assigned tracking numbers remain unchanged.

Requisition items store a price snapshot so historical totals do not change when catalog prices are updated. Approval actions and inventory movements are stored in separate audit tables. Approved actions also receive a SHA-256 signature token associated with the signer, role, IP address, user agent, and signing time.

### Archive and withdrawal

Employees/faculty see only their own archived requests. The institutional archive is SMO-only, including filtered print and CSV exports. The older `/requests/archive` entry point also shows institutional records for SMO. Details and requisition PDFs remain restricted to the owner or SMO.

The archive separates fulfilled (`completed` and legacy `released`), rejected, and withdrawn records. `completed` confirms handover in the current workflow; legacy `released` does not separately establish pickup confirmation. Only completed and released records contribute to fulfillment/value reports. Archive requested-value summaries include every matching outcome and are explicitly **not expenditure or actual issued supplies**.

The owner can withdraw a request only while it is `pending`, before department approval. The existing DELETE endpoint now records `withdrawn`, an optional reason, the actor, and the closure timestamp in one transaction. It retains the request, items, previous audit events, signatures, and original details URL. Withdrawal cannot be resumed or restored; submit a new request when necessary. No Trash or retention expiry is introduced.

Search includes tracking, request/item content, and recorded audit remarks. Closure date remains the default filter and sort basis; submission date is selectable and falls back to creation date only when the legacy submission timestamp is absent. Both range endpoints are inclusive in the application timezone. Unknown closure dates sort last and are excluded from closure-date ranges. Category counts follow search/date/department filters before category/status selection; result summaries count all matching records, including records beyond the current page. Clear all filters resets search, category, status, department, dates, ordering, and page. Details links retain validated archive filters and page through a local return link. Print and streamed CSV exports use all matching records with the same authorization and filters; spreadsheet formula cells are escaped.

Deploy the two `2026_10_22` migrations with the normal `php artisan migrate --force` release step after taking the usual database backup:

- `000001_backfill_completed_requisition_closures` fills only missing `completed.closed_at` from the latest timestamped `Completed by SMO In-Charge` approval event. Ready-for-pickup events and later edits are not completion evidence. Records with no completion event stay undated; audit data is never invented. A small `requisition_closure_backfills` table records the applied values so rollback clears only those values still unchanged and still completed, without removing audit events.
- `000002_restrict_deletion_of_requisition_owners` changes the owner foreign key from cascade to restrict. Previously, deleting a user could cascade through requisitions, items, approval logs, and signatures. Account management currently suspends accounts; the new constraint also prevents direct account deletion from erasing owned requisitions. Actor references on other historical events retain their existing nullable behavior. Rollback restores the previous cascading foreign key, so review that consequence before rolling back.

Historical limitations: the earlier `2026_10_07` migration omitted completed requests and could use `updated_at`/`created_at` for released/rejected rows lacking audit dates. Already-populated dates have no provenance marker and are preserved; review production audit events before correcting them. Previously deleted requests or missing events cannot be recovered from source code. This change does not inspect or certify production records. Large print views load all matches; narrow filters or use streamed CSV for larger archives.

Regression checks: `php artisan test --filter="RequisitionArchiveTest|RequisitionArchiveLifecycleTest|RequisitionModificationTest|EmployeeRequestExperienceTest|OperationalAnalyticsTest"`.

## Project team

| Name                  | Role / focus                               | Program / institution                           |
| --------------------- | ------------------------------------------ | ----------------------------------------------- |
| Margart Augwie Ampan  | Assistant Documenter and System Tester     | BS Information Technology, Holy Trinity College |
| Segundino Jr. C. Baja | Head Documenter and System Tester          | BS Information Technology, Holy Trinity College |
| Jayrex E. Rivac       | Full-Stack Developer and Quality Assurance | BS Information Technology, Holy Trinity College |

---

<p align="center">
  <strong>Holy Trinity College of General Santos City</strong><br>
</p>
