# Screen typography and spacing

The screen UI retains Montserrat and the existing colors, components, routes, and interactions. The shared tokens live in `resources/css/app.css`; both layouts and page styles use them. The `html` size is 16px on desktop and mobile. Earlier 14px desktop and 15px mobile overrides were removed.

| Purpose | Token | Size |
| --- | --- | --- |
| Body and editable form controls | `--text-body` | 16px |
| Secondary text, navigation, standard buttons | `--text-secondary` | 14px |
| Captions, helpers, compact labels and identifiers | `--text-caption` | 12px |
| Small headings | `--text-heading-sm` | 20px |
| Medium headings | `--text-heading-md` | 24px |
| Large headings and prominent figures | `--text-heading-lg` | 32px |

Heading, body, and prose line heights use `--line-heading` (1.25), `--line-body` (1.5), and `--line-prose` (1.75). Normal and uppercase-label tracking use `--tracking-normal` and `--tracking-label` (.05em). Monospace families on tracking numbers and codes are preserved.

The spacing tokens are `--space-4`, `--space-8`, `--space-12`, `--space-16`, `--space-24`, and `--space-32`, expressed in rem. Shared controls, cards, dialogs, and view-level padding/margin/gap declarations use these tokens. Larger structural gutters combine them with `calc`; viewport-based outer positioning, border widths, radii, icon dimensions, and 1–2px optical alignment offsets retain their purpose-specific geometry.

Nested `small` text and Bootstrap badges have a root-relative size, preventing repeated `em` scaling from shrinking them below 12px. Bootstrap heading-size utilities use the same screen scale. Form controls remain 16px on mobile. Request-summary cards wrap and use fewer columns where the larger text needs room; deadline links can wrap at narrow widths.

## Deliberate exceptions

- PDF requisitions, report PDFs, inventory forecast PDFs, and email templates keep their independent typography and were not changed.
- Browser print tables use explicit compact sizing: report cells at 9px, archive headers at 9px and archive body cells at 11px. Screen minimums do not override these document constraints.
- The large decorative quotation mark in request details is a graphic glyph, not reading text.
- The standalone session-expired fallback keeps asset-independent system fonts and literal 16px/32px sizing so it remains readable even if application assets are unavailable.

## Files changed

| Area | Files |
| --- | --- |
| Shared styles and layouts | `resources/css/app.css`; `resources/views/layouts/app.blade.php`; `resources/views/layouts/guest.blade.php` |
| Shared feedback and recovery | `resources/views/layouts/partials/auth-progress.blade.php`; `dynamic-search.blade.php`; `runtime-guard.blade.php`; `session-recovery.blade.php`; `resources/views/errors/session-expired.blade.php` |
| Dashboards | `resources/views/dashboard.blade.php`; `resources/views/dashboard/partials/employee-styles.blade.php` |
| Requests and archives | `resources/views/requests/index.blade.php`; `edit.blade.php`; `show.blade.php`; `resources/views/requests/partials/approval-deadline-watch.blade.php`; `archive-workspace.blade.php`; `history-table.blade.php`; `personal-styles.blade.php` |
| Inventory | `resources/views/inventory/index.blade.php` |
| Administration and fulfillment | `resources/views/admin/approvals.blade.php`; `manage-users.blade.php`; `pending-users.blade.php` |
| Reports | `resources/views/admin/reports.blade.php`; `resources/views/admin/reports/index.blade.php` |
| Notifications and profile | `resources/views/notifications.blade.php`; `resources/views/profile/edit.blade.php`; `resources/views/profile/partials/update-profile-information-form.blade.php`; `update-password-form.blade.php` |
| Legal pages | `resources/views/components/legal-document.blade.php` |
| Regression checks | `tests/Frontend/screen-typography.test.mjs` |

## Review and validation

Source checks cover screen style blocks and inline declarations, exclude PDF/email templates and print media, enforce the 12/14/16/20/24/32px scale, and guard against compounding caption/badge sizes. Existing render, authentication, and fulfillment checks exercise the modified Blade views without changing application logic.

Headless Chrome reviewed 45 server-rendered screen fixtures at 1440px, 390px, and 320px widths (135 checks). All checked roots and bodies were 16px, with no visible text below 12px or outside the shared scale. Screenshots were inspected for desktop and mobile dashboards, inventory, requests, fulfillment, login, and reports. These fixture checks cover layout and computed styles, not a full interactive or assistive-technology audit; existing frontend tests cover the shared interactions.

Validation passed: 44 focused Laravel tests (574 assertions), 60 frontend tests, 45 preview renders, the production Vite build, and `git diff --check`. Temporary browser fixtures and migration helpers were removed after review. No application controllers, routes, data calculations, or workflow rules were changed.
