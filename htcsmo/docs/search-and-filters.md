# Search and filter behavior

## Surfaces inspected and updated

The route/controller/view inventory covered every search field, GET filter form, and list pagination in `routes/web.php`, `resources/views`, and `resources/js`. Existing profile, archive, notification-layout, and browser-title edits were retained. No routes or dependencies were added.

| Surface | GET route | Automatic behavior | Results target |
| --- | --- | --- | --- |
| Active requisitions, including employee lists | `requests.index` | Search, status, department (SMO only), dates, sort, status tabs, clear, pagination | `#request-results` |
| Personal archive and SMO legacy archive entry | `requests.archive` | Search, final status, department (SMO only), date basis, dates, sort, outcome categories, clear, pagination | `#archive-results` |
| Institutional archive | `admin.archive` | Same archive controls; existing SMO authorization and export scope retained | `#archive-results` |
| Inventory catalog | `inventory.index` | Search, stock health, tier, clear; filters now also run on the server | `#inventory-results` |
| Inventory forecast preview | `inventory.index` | Monthly/quarterly period and reset; catalog query is retained | `#demand-forecast` |
| Pending access requests | `admin.users.pending` | Search, clear, pagination | `#registration-results` |
| Personnel management | `admin.users.manage` | Search, role categories, clear/single-category removal, pagination; existing management scope retained | `#personnel-results` |
| Notifications | `notifications` | Category links and pagination now use shared history, error handling, and orientation | `#notification-results` |
| Operational analytics | `admin.reports.index` | Period, valid custom dates, reset; totals and chart/table sections update together | `#analytics-results` |
| Report Center | `admin.reports.builder` | Period, valid dates, department, role, status, tier, stock/category/transaction filters, clear, item-filter removal, pagination | `#report-preview` |
| Approval/fulfillment queue | `admin.approvals` | Search, department, tier, status, valid paired submission dates, sort, group links, clear, and pagination use the shared helper. Actions preserve validated queue filters/group/page and remain deliberate POST forms. | `#approval-results` |

## Shared implementation

`resources/js/dynamic-search.js` enhances only opted-in GET forms in a `data-filter-workspace`. It debounces search by 350 ms, submits select/radio/date changes, resets pagination for changed form filters, and intercepts marked category/reset/pagination links. Existing authorized GET routes return the HTML; only named, non-nested `data-filter-region` sections are replaced. Filter forms and their keyboard focus remain in place. Query values are synchronized after link navigation and Back/Forward.

Successful interactions push URL history; Back/Forward fetches the destination without creating another entry. New input cancels older requests, including responses whose bodies are still arriving. Notification background refreshes use the same helper without scrolling, focus changes, or history entries, and cannot supersede an active user filter request.

Result sections have IDs, labels, and `tabindex="-1"`. Successful updates expose the first results if they are off-screen. Search typing retains its input and caret; explicit navigation can focus the results. Scrolling uses no smooth animation, and reduced-motion preferences are respected. Counts, loading, and failures are announced through a polite live region. Errors retain the previous results with an explicit warning and an anchored full-page retry link; failed requests never update history as if they had succeeded.

Interactive two-date filters require either both dates or neither, and reject invalid calendar dates and reversed ranges. Custom report periods require both dates on the server as well. Existing open-ended archive/request URLs remain accepted by server validation for compatibility; interactive changes require completing or clearing the pair. Filter-request validation errors return JSON with status 422 instead of silently following a validation redirect. Authentication, employee ownership, department scopes, and SMO-only routes remain enforced server-side.

Without JavaScript, `noscript` submit buttons and custom-date inputs provide server-rendered filtering. Form actions and pagination/category links include result anchors. Inventory tier selection also works without Alpine. Active-request details and archive details provide validated local return links carrying filters, page, and the result anchor.

## Deliberate actions and exceptions

- CSV, PDF generation/download, forecast PDF, print, and archive exports retain explicit controls. Exports use the current filters and still include all matching rows according to the existing report/archive calculations.
- Report-type radio changes automatically navigate using GET and `#report-preview`. A full load is needed to recreate type-dependent fields and the existing Bootstrap PDF-generation modal. Other preview filters update the result fragments in place.
- Create/edit/delete, withdrawal, profile updates, access approval, notifications' mark-read actions, procurement approvals, reminders, stock processing, and pickup confirmation are never auto-submitted by this helper.
- Inventory bulk selections remain in the existing Alpine workspace while catalog result regions change. Forecasts and stock totals continue to use the complete inventory dataset.

## Files changed for this task

- Shared behavior: `resources/js/dynamic-search.js`, `resources/js/app.js`, `resources/views/layouts/partials/dynamic-search.blade.php`, `resources/views/components/filter-submit.blade.php`, `resources/views/components/filter-date-fallback.blade.php`.
- Server validation and return links: `app/Http/Requests/ActiveRequestIndexRequest.php`, `app/Http/Controllers/RequestController.php`, `app/Http/Controllers/SupplyController.php`, `app/Http/Controllers/ReportController.php`, `app/Http/Controllers/NotificationController.php`, `app/Services/ArchiveService.php`, `bootstrap/app.php`.
- Requests/archive views: `resources/views/requests/index.blade.php`, `resources/views/requests/show.blade.php`, `resources/views/requests/partials/history-table.blade.php`, `resources/views/requests/partials/archive-workspace.blade.php`, `resources/views/requests/partials/archive-table.blade.php`.
- Inventory views: `resources/views/inventory/index.blade.php`, `resources/views/inventory/partials/tier-table.blade.php`.
- Administration/report views: `resources/views/admin/manage-users.blade.php`, `resources/views/admin/pending-users.blade.php`, `resources/views/admin/approvals.blade.php`, `resources/views/admin/partials/approvals-table.blade.php`, `resources/views/admin/reports.blade.php`, `resources/views/admin/reports/builder.blade.php`, `resources/views/admin/reports/index.blade.php`.
- Notifications: `resources/views/notifications.blade.php`, `resources/views/notifications/feed.blade.php`.
- Tests: `tests/Frontend/dynamic-search.test.mjs`, `tests/Frontend/session-recovery.test.mjs`, `tests/Feature/DynamicSearchWorkspaceTest.php`, `tests/Feature/UserManagementTest.php`, `tests/Feature/RequisitionArchiveTest.php`.
- This document: `docs/search-and-filters.md`.

## Verification

Focused frontend tests exercise the shared event handlers and asynchronous request lifecycle: debouncing, selects/sort, valid/incomplete dates, request races, URL changes, Back/Forward, pagination, clear/single-filter links, empty results, server/network/missing-fragment errors, session expiry, focus/scroll/reduced motion, exports, and full-page exceptions. Laravel tests exercise real rendered forms/regions, GET fallback markup, validation responses, filtering/counts, report exports, return links, and employee/department/recipient isolation.

Run the relevant checks:

```powershell
php artisan test --compact --filter='DynamicSearchWorkspaceTest|ActiveRequestWorkspaceTest|InventoryWorkspaceTest|ReportWorkspaceTest|RequisitionArchiveTest|RequisitionArchiveLifecycleTest|UserManagementTest|NotificationFeatureTest|OperationalAnalyticsTest|InitialRenderTest'
node --test --experimental-test-isolation=none tests/Frontend/dynamic-search.test.mjs tests/Frontend/session-recovery.test.mjs tests/Frontend/notification-popup.test.mjs
npm run build
```

The Node isolation flag avoids a child-process restriction in the managed Windows workspace. Vite's esbuild subprocess may need permission to run outside that sandbox. No packages need installation.

Limitations: partial updates currently fetch full server-rendered HTML rather than introducing fragment endpoints. Native browser layout and assistive-technology behavior should also be checked in a manual browser pass; the automated frontend suite uses the project's Node DOM/event test pattern. JavaScript-disabled pages require the fallback submit button. Report-type changes use a full load with a result anchor.
