# Architecture audit

**Reviewed:** 2026-10-23  
**Scope:** First-party application code and project configuration in this repository. This is a source-level architecture review, not a penetration test or a review of the live deployment.

## Executive summary

The system is a Laravel modular monolith for institutional requisitions, approvals, inventory fulfillment, reporting, and notifications. Its server-rendered Blade interface and Eloquent-backed workflows are a reasonable fit for the current domain. The strongest architectural decisions are the explicit approval workflow, normalized catalog and department data, historical requisition snapshots, and transactional inventory deductions with row locking.

The most important follow-ups are deployment trust configuration and production-database validation. MySQL certificate verification is explicitly disabled in the MySQL connection configuration, and the application trusts forwarded proxy headers from any address. The automated PHP suite is configured for SQLite, so it does not validate production MySQL behavior or concurrent fulfillment. Locally, that suite could not initialize because the PHP runtime lacks the PDO SQLite driver.

**Overall assessment:** sound modular-monolith foundation; moderate operational and maintainability risk. There is no architectural need to split this application into services at its current scale.

## System map

```text
Browser
  └─ Blade / Bootstrap / Alpine / Vite
      └─ Laravel web routes and middleware
          ├─ Controllers: accounts, requisitions, approvals, inventory, reports
          ├─ Services: inventory planning, notifications, SLA, analytics, archives
          ├─ Eloquent models and migrations
          └─ MySQL / MariaDB

Laravel queue ── requisition email jobs
Laravel scheduler ── deadline notifications and notification pruning
DomPDF ── requisition, report, and forecast documents
```

The primary business flow is: an authenticated user submits a catalog-priced requisition; role- and department-aware reviewers advance or reject it; SMO staff process the fully approved requisition; stock is deducted and logged when it is marked ready for pickup; completion closes the workflow. See [ARCHITECTURE.md](./ARCHITECTURE.md), [ERD.md](./database/ERD.md), and [FULFILLMENT-QUEUE.md](./docs/FULFILLMENT-QUEUE.md) for the existing flow and data-model documentation.

## What is working well

- **Clear application boundary:** Laravel routes, middleware, controllers, services, models, Blade views, and scheduled/queued work are recognizable and mostly follow framework conventions. The UI is server-rendered rather than carrying a separate client-side domain implementation.
- **Workflow controls:** Route role checks are supplemented by use-case checks for request ownership, department assignment, approval stage, and allowed transitions. Approval changes lock the requisition row; inventory fulfillment locks the requisition and stock rows.
- **Inventory correctness:** Fulfillment resolves and aggregates all request lines before deductions, checks sufficiency before mutation, writes movement records, and wraps the operation in a transaction. Duplicate lines are counted together. This is an appropriate core invariant for stock management.
- **Historical records:** Requisition items retain price and description snapshots, supplies are soft-deleted, and approval and inventory movements have separate audit records. Recent migrations strengthen ownership retention and numeric inventory constraints.
- **Bounded read/export paths:** Queues and previews paginate; CSV exports use lazy/chunked reads; PDF exports have a row cap. Report output distinguishes request values from actual inventory movements rather than conflating them.
- **Async and scheduled work:** Email is represented by a queued job with retry/backoff and delivery logging. Deadline checks and notification pruning are registered with Laravel's scheduler.
- **Useful regression coverage:** Feature tests cover workflow authorization, fulfillment, schema normalization, reports, session recovery, and frontend interactions. The Node frontend suite completed successfully in this review (135 tests).

## Prioritized findings

| Priority | Finding | Evidence and impact | Recommended action |
|---|---|---|---|
| **High** | **MySQL server-certificate verification is disabled.** | [config/database.php](./config/database.php#L65) sets `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` to `false` for the MySQL connection. When this connection is used, transport encryption alone does not authenticate the database server, weakening protection for database credentials and application data in transit. No live environment values were inspected, so this finding concerns the checked-in connection behavior, not a claim about the current production topology. | Remove the verification bypass. Require server-certificate validation and configure the provider's CA material through deployment configuration. Confirm a valid TLS connection in a deployment-level check before rollout. |
| **Medium** | **All proxy sources are trusted.** | [bootstrap/app.php](./bootstrap/app.php#L23) calls `trustProxies(at: '*')`. The README correctly notes that clients should not bypass the reverse proxy, but the application configuration itself does not restrict trusted proxy addresses. Forwarded scheme, host, and client-IP values can therefore depend on network placement; client IP also informs rate limiting and activity records. | Trust only the actual ingress/load-balancer addresses or configured trusted proxy ranges. Ensure the application cannot be reached directly around that proxy, and verify forwarded headers at the deployed edge. |
| **Medium** | **Production database behavior is not exercised by the normal test suite.** | [phpunit.xml](./phpunit.xml) configures in-memory SQLite, while the application describes MySQL/MariaDB as its normal database. The latest integrity migration adds numeric `CHECK` constraints only for the MySQL driver, and the critical fulfillment path uses `lockForUpdate`. SQLite tests cannot establish production MySQL constraint or concurrent-lock behavior. In this environment, `php artisan test` could not start its SQLite-backed cases because PDO SQLite is unavailable (`could not find driver`). | Keep the fast SQLite suite, but add a MySQL integration job that migrates a clean database and exercises the schema constraints and concurrent attempts to fulfill the same or overlapping inventory. Re-run the current PHP suite in a PHP environment with PDO SQLite enabled. |
| **Medium** | **The largest controllers combine many application responsibilities.** | [RequestController.php](./app/Http/Controllers/RequestController.php) is 710 lines and coordinates approval transitions, authorization, notifications, email, fulfillment, dashboards/queues, and PDFs. [ReportController.php](./app/Http/Controllers/ReportController.php) is 955 lines and contains report selection, query construction, filtering, pagination, CSV, and PDF behavior. [RequisitionController.php](./app/Http/Controllers/RequisitionController.php) is 341 lines and owns validation, catalog resolution, totals, persistence, and submission notifications. This raises change coupling and makes individual behaviors harder to isolate. | Refactor incrementally, preserving current route and response behavior. Extract approval/requisition use cases and report builders/exporters behind small, domain-named services; keep HTTP concerns in controllers. Start with high-change or high-risk operations and add focused tests before moving logic. Avoid a broad rewrite. |
| **Low** | **The architecture reference is behind the current source.** | [ARCHITECTURE.md](./ARCHITECTURE.md#L3) says it reflects a 2026-10-01 inspection, while schema migrations continue through 2026-10-22 and the current tree includes newer SLA, notification, normalization, and inventory-integrity behavior. The existing ERD also explicitly says it is migration/model-derived rather than a live-database inspection. | Update the architecture overview after significant workflow/schema changes and keep its operational details aligned with current configuration. Continue to describe the ERD as source-derived unless it is regenerated from a live schema. |

## Design note: approval-token semantics

The README describes SHA-256 approval “signature tokens.” The code creates a SHA-256 hash over a concatenated requisition/user/role/time value and the application key, then stores it with signer metadata; the reports display a shortened token. This is useful as an internal audit identifier, but it is not, by itself, a standard digital-signature scheme or evidence that can be independently verified by a recipient. No signature-verification flow was found in the application code.

If institutional policy requires non-repudiation or independent verification, define the signing and verification requirements before relying on this token for that purpose. Otherwise, describe it consistently as an internal approval audit token or fingerprint.

## Operational and quality observations

- The [Docker entrypoint](./docker/entrypoint.sh) starts Apache, the queue worker, and the scheduler in one container. This is simple for a small deployment, but couples their scaling and lifecycle: each web replica starts background workers too. For higher availability or independent scaling, run the worker and scheduler as separately managed services, or use a process supervisor with explicit health and shutdown behavior.
- The MySQL and MariaDB connection configurations are not identical. Review the intended TLS behavior for each supported driver and avoid weakening certificate validation to accommodate one provider.
- The release process runs migrations at container startup. This is convenient, but production rollouts should ensure only one deployment actor migrates at a time and that backups/rollback procedures exist for data-changing migrations.
- Business status transitions are application-enforced rather than encoded as a database state machine. This is a reasonable choice for the current workflow, but every mutation path—including maintenance commands and imports—must preserve the same transition and audit invariants.
- The project uses framework migrations as the schema source of truth. SQL imports and updates outside Eloquent model events may bypass cache invalidation and workflow behavior; the README already documents cache clearing for bulk changes.
- The audit did not inspect `.env` contents, production credentials, a live database, or deployed infrastructure. Recommendations above are based on tracked source/configuration only.

## Validation performed

| Check | Result |
|---|---|
| Source/configuration review | Routes, controllers, middleware, models, services, migrations, frontend entry points, deployment files, README, architecture guide, and ERD reviewed. |
| `npm run test:frontend` | **Passed:** 135 tests, 0 failures. |
| `php artisan test` | **Blocked in this environment:** SQLite connection initialization fails because PDO SQLite is not installed. This is an environment limitation; application-level PHP test results are therefore unverified here. |

## Recommended order of work

1. Restore MySQL certificate verification and narrow trusted proxy configuration.
2. Establish a MySQL integration test path for migrations, constraints, and fulfillment concurrency; enable PDO SQLite in the standard PHP test environment.
3. Update the architecture guide when the next workflow/schema changes land.
4. Extract controller responsibilities incrementally as those areas are next changed, without changing the system's modular-monolith deployment model unless scale or operational needs justify it.
