# Operational Analytics

Updated October 21, 2026. The overview lives at `/admin/reports`; detailed previews and CSV/PDF exports remain in the separate Report Center. Existing authentication and SMO-only route middleware are unchanged.

## What changed

- Current operational priorities appear first: active requests, overdue and near-deadline requests, and separate low/out-of-stock links.
- Semester, month, year, and inclusive custom ranges control the historical results. Custom ranges require both dates and are limited to ten years to keep monthly displays usable.
- Period results show fulfilled request count, recorded released value, average fulfilled value, monthly fulfillment, department fulfillment, and actual release-ledger quantities.
- Workflow counts and estimated on-hand inventory value are explicitly current snapshots, independent of the period.
- Monthly charts include zero months and an accessible table. Department and item charts use readable tables with links to matching reports. Workflow stages link to relevant queues or filtered reports.
- Uses the existing green design, Bootstrap, and Alpine conventions. Removed the overview's Chart.js CDN dependency; charts use CSS and native HTML. No dependencies or schema changes were added.
- Report Center links carry the selected period. A return link preserves supported periods; an all-time or open-ended detailed report returns to the current semester because the overview requires a bounded range.

## Metric definitions

| Metric | Definition and date basis |
| --- | --- |
| Fulfilled requests | Status `completed` or legacy `released`, filtered by `closed_at`. Completion is the handover after pickup preparation. |
| Recorded released value | Sum of `grand_total` for those fulfilled requests. This is operational request value, not audited accounting expenditure. |
| Average fulfilled value | The same sum divided by the same fulfilled count, or zero for an empty period. |
| Department fulfillment | The same fulfilled count and value, grouped by the requestor's current department, including an Unassigned group. |
| Monthly fulfillment | The same fulfilled count and value, grouped by completion year/month. Partial months contain only dates in the selected range. |
| Top stock items released | Negative sum of signed `quantity_change` for `release_deduction` ledger transactions in the selected transaction-date range. Positive reversal entries offset deductions; only positive net deductions enter the top five. |
| Active pipeline / workflow | Requests outside `completed`, `released`, and `rejected`, across all submission dates. Major VP approval remains Provost review; minor VP approval awaits SMO processing. |
| SLA warnings | Active requests at/after their saved warning timestamp but before deadline; overdue means at/after the saved deadline. The existing three-working-day submission SLA and institutional holiday rules supply those timestamps. |
| Stock warnings | Out of stock: quantity at/below zero. Low stock: positive quantity at/below the minimum. Deleted supplies are excluded from current snapshots. |
| Estimated on-hand value | Current on-hand quantity multiplied by current catalog price, summed over current catalog entries. This is a current estimate, not historical inventory cost. |

The lifecycle was checked against `markReadyForPickup`, `completeRequest`, `Requisition::saving`, the closure migration, and existing approval/archive tests. Stock is deducted at pickup preparation, so ledger releases can occur before completion and in a different reporting period.

Date queries include the beginning of the first selected day and exclude the beginning of the day after the last selected day. No fulfillment metric filters on `updated_at`.

## Detailed reports

- Department Spending is now labeled **Department Fulfillment Value** and uses `closed_at`.
- Item Consumption is now labeled **Fulfilled Request Items**. It uses `closed_at` and explicitly reports requested line quantities/prices on fulfilled requests. It does not claim those quantities are actual deductions.
- Requisition Transactions defaults to submission date (`submitted_at`, falling back to `created_at`). Its new completion-date basis limits results to completed/legacy released requests, filters on `closed_at`, and includes a completion-date column.
- Inventory Movements supports a catalog-item filter for overview links and displays the selected filter with a clear action.
- Preview, CSV, and PDF share the same report construction and filters. Existing pagination still limits preview summaries to the current page; exports include all matches, as labeled in the preview.

## Files changed for this task

- `app/Http/Controllers/ReportController.php`
- `app/Services/OperationalAnalyticsService.php` (new aggregated overview queries)
- `resources/views/admin/reports.blade.php`
- `resources/views/admin/reports/builder.blade.php`
- `resources/views/admin/reports/index.blade.php`
- `tests/Feature/ReportWorkspaceTest.php`
- `tests/Feature/OperationalAnalyticsTest.php` (new regressions)
- This document

Pre-existing dashboard, route, refresh, documentation, and test edits were preserved. No commit was created.

## Verification and remaining limits

- Focused report/lifecycle/archive/performance/render tests: 43 passed, 368 assertions.
- Full `php artisan test --compact`: 169 passed, 1,129 assertions.
- `npm.cmd run test:frontend`: 49 passed.
- `npm.cmd run build`: passed; existing stale Browserslist-data notice remains.
- Pint and `git diff --check`: passed. `php artisan route:list --path=admin/reports -v` confirms SMO-only routes.
- Headless Edge desktop and 390-pixel mobile layout previews used synthetic data and local assets. The mobile document measured exactly 390 pixels wide after correcting table sizing and initial dropdown alignment for this page. Static previews did not exercise authenticated browser interactions or CDN icons.
- Tests use in-memory SQLite; no live MySQL database or production data was changed. Monthly grouping includes driver-specific expressions for the project's supported database drivers.
- Requests without `closed_at` cannot be assigned to a fulfillment period; no fallback to later edit dates is applied.
- Ledger entries do not contain historical unit prices or names. The top-items view reports quantities in each item's own current unit; it does not combine different units into a monetary consumption estimate. Soft-deleted catalog records remain available for historical labels.
- Department attribution uses current account departments. Historical department and catalog-price snapshots cannot be reconstructed from this schema.
