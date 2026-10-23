# Employee / Faculty request experience

Updated October 21, 2026. Faculty and staff use the existing `employee` role. This change covers that role's dashboard and personal request pages. The working tree was clean at the start; the existing SMO, approver, and Operational Analytics implementations remain in place. No database schema, dependencies, or compiled frontend assets were changed. No commit was created.

## Personal metrics and event definitions

- **My active requisitions:** the signed-in user's requests outside completed, legacy released, and rejected archive statuses.
- **Needs clarification:** that user's current `for_clarification` requests.
- **Ready for pickup:** that user's current `ready_for_pickup` requests. Final approval alone does not count as pickup-ready.
- Removed the employee dashboard's “Approved and ready / Last 24 hours” metric. The replacement metrics describe current states, not recent events, and do not use `updated_at` as evidence of approval.
- Submission dates and employee active-list date filters use `submitted_at`, falling back to `created_at` for legacy records. Date boundaries include the first day and exclude the day after the final selected date. Sorting breaks submission-time ties by ID.
- Clarification remarks come from the latest clarification approval-log entry by ID, with the existing requisition remarks as a fallback. The current clarification owner is resolved from clarification logs, rather than an unrelated later audit note.
- Completion/release dates on the employee details page use `closed_at`. Missing dates display “Completion date unavailable”; later edits are not substituted for completion events. Approval-log entries continue to display their own action timestamps.
- Request values and quantities are labeled as requested information. Item-line counts avoid summing unlike units. No institution-wide figures or issued-stock-value metrics are added.

## Navigation and next steps

- Current metric cards link to matching personal active, clarification, and pickup lists.
- The action panel previews four clarification/pickup requests and shows the full matching count and list link.
- The active preview contains up to eight requests: clarification first, pickup next, overdue next, near-deadline next, then recent submissions. It explicitly displays the preview limit and full active count.
- Near/past-deadline warnings include text and timestamps. The three-working-day submission SLA excludes weekends and configured institutional holidays and is described as a target, not a pickup guarantee.
- Shared model helpers supply tier-aware employee labels and next-step text to the dashboard, list, and details. Minor VP approval awaits SMO processing; major VP approval awaits Provost review.
- The details page highlights full clarification remarks or pickup status. It adds no unverified pickup location, office hours, or delivery promise. Pending requests retain their existing edit/delete behavior.
- New requisition, dashboard, active-list, and archive navigation remain available. Empty filtered lists offer Clear filters; empty personal workspaces offer creation or active/archive navigation.
- Employee status and date filters are validated. Pickup, processing, and combined action-required filters are available; pagination preserves the filters. Archive contents remain completed/released/rejected records and use closure-date filters.

## Notifications and access

The dashboard previews the user's five most recent request/deadline notifications, with unread text, safe presentation metadata, and the existing batch action resolver. Misaddressed notifications referring to another user's extant request are excluded. Dashboard viewing does not mark notifications read. Opening an actionable notification uses the existing authorized POST endpoint, which rechecks access and marks it read.

Notifications whose request is unavailable, or whose older metadata does not identify an authorized action, link to the full notification center instead. Free-text messages are not parsed to invent request links. Request list, detail, PDF, archive, and notification ownership checks remain in effect.

## Files changed

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/RequestController.php`
- `app/Models/Requisition.php`
- `app/Services/EmployeeDashboardService.php` (new)
- `resources/views/dashboard.blade.php`
- `resources/views/dashboard/employee.blade.php` (new)
- `resources/views/requests/index.blade.php`
- `resources/views/requests/archive.blade.php`
- `resources/views/requests/show.blade.php`
- `resources/views/requests/partials/history-table.blade.php`
- `resources/views/requests/partials/personal-guidance.blade.php` (new)
- `resources/views/requests/partials/personal-styles.blade.php` (new)
- `tests/Feature/EmployeeRequestExperienceTest.php` (new)
- This document

## Verification

Baseline: 22 dashboard, active-list, archive, and initial-render tests passed before changes.

Focused command:

```powershell
php artisan test --compact --filter="EmployeeRequestExperienceTest|DashboardWorkspaceTest|ActiveRequestWorkspaceTest|RequisitionArchiveTest|NotificationFeatureTest|RequisitionModificationTest|SmoDashboardTest|BusinessDaySlaTest|InitialRenderTest|WorkspacePerformanceTest|RequisitionRoleRoutingTest"
```

Result: **62 tests, 551 assertions passed**.

```powershell
php artisan test --compact
```

Result: **177 tests, 1,276 assertions passed**. The new eight-test employee suite contributes 147 assertions covering personal isolation, current-state metrics, later edits, tier-aware labels, clarification/pickup actions, latest safe remarks, notification access/unread behavior, preview ordering/limits, submission boundaries, filtering, pagination, completion dates, and empty states.

Pint passed for the new service/test, model, and dashboard controller. `php -l app/Http/Controllers/RequestController.php` and `git diff --check` passed. Frontend tests/build were not rerun because no JavaScript or Vite CSS assets changed.

Headless Edge previews used synthetic data and local assets. Dashboard, active list, and details were checked at 390 pixels; the dashboard was also checked at desktop width. Checks confirmed no horizontal page overflow, unclipped status labels, and full-width mobile next-step text. Static previews did not exercise CDN icons or authenticated browser interactions; Laravel tests cover the request and notification actions. Temporary preview data and the rendering harness were removed.

Remaining limits: legacy notifications may lack a direct request action, missing lifecycle dates remain unavailable, and institution-specific pickup arrangements must come from recorded remarks or institution-provided instructions.

## Visual design refinement

The employee pages received an additional presentation pass: a compact welcome header, icon-led summary cards, item-focused action cards, a request-tracking column, and a separate sidebar for notifications, processing guidance, and archive navigation. Consistent spacing, subtle borders, accessible status badges, and restrained amber/green accents follow the existing institutional palette.

The active workspace now includes a clearer page heading and filter tabs with counts and current-page indicators. Mobile summary cards stay compact, controls remain readable, and next-step guidance uses the full card width. Shared employee styles also refine archive tables and the details guidance panel. Existing workflow and access behavior is unchanged.

Validation for this design pass: **39 tests, 365 assertions passed**, covering employee experience, dashboard, active lists, archives, initial rendering, and workspace performance. Desktop (1440px), tablet (1024px), and mobile (390px) synthetic browser previews included populated, empty, and filtered states, plus request details. Existing Lucide icons rendered successfully and all checked pages fit within their viewport. The temporary rendering harness and previews were removed. No compiled frontend assets or new dependencies were added.
