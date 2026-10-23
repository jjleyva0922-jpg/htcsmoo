# SMO dashboard review

Reviewed and updated on October 21, 2026. Scope: `/dashboard` for SMO, its shared deadline component, and the runtime polling used to keep this page current.

## Section findings and changes

| Section | Finding | Result |
| --- | --- | --- |
| Welcome and shortcuts | Greeting needed clearer wording. | Uses the officer's first name and retains links to fulfillment and inventory. |
| Total requisitions | Includes institutional history, including archived requests. | Verified as an all-time count. |
| Active pipeline | Includes approvals, clarification, processing, and pickup; excludes completed, released, and rejected requests. | Verified against the active table and archive rules. |
| Deadline metric | Copy described releases although the query covers all active requests. | Describes active requests and explicitly identifies the three-working-day target. |
| Monthly released value | Used `updated_at`, so editing an old completed request could move its value into another month. Rounded away centavos. | Uses `closed_at`, includes the first instant of the month, excludes the next month, and displays two decimals before JavaScript initializes and after refresh. |
| Approval follow-up | Shared the full deadline list with fulfillment. Its count stopped at the six-row preview. | Dashboard approval follow-ups now include administrative approval stages only. The badge shows the full matching count; the preview shows the earliest six. |
| Deadline details and reminders | Age was labeled as time in the current stage although calculated from submission. Reminder copy implied fewer recipients than the service actually notifies. | Labels use working days since submission and the fixed request deadline. Reminder text identifies the current owner, requestor, and SMO. Expanded details defer automatic replacement. |
| Overall deadline monitor | Reused the limited approval collection and did not disclose hidden matches. | Independently includes every active stage, including clarification, processing, and pickup. Shows the earliest four and links to the filtered deadline workspace. |
| Stock warnings | Out-of-stock total was calculated but hidden. The preview hid additional matches and loaded each supply's unit separately. | Displays the empty-stock count, identifies truncated results, and loads units in one query. Empty stock comes first; items at their minimum count as low. Deleted inventory is excluded. |
| Active requisitions | Raw status codes obscured the next action; major `approved_vp` requests looked ready for fulfillment. Equal creation times produced unstable previews. | Tier-aware labels distinguish Provost review from SMO processing. Sorting breaks ties by ID. Displays the latest ten out of the complete active count. |
| Requestor display | Used a byte-based initial and assumed a related user always existed. | Uses Unicode initials and an unavailable-requestor fallback. |
| Alerts and activity | Used raw notification presentation metadata. An isolated new notification did not invalidate the dashboard content. | Uses safe icon/type fallbacks, machine-readable timestamps, links to the notification center, and refresh versions based on this officer's latest notification. Reading the dashboard does not mark alerts read. |
| Live refresh | Changes in clock time could cross warning/deadline or month boundaries without changing the data version. | Dashboard has its own version including the current minute, business data version, and current user's latest notification ID. Existing protection for dialogs, edits, and notification popups remains active. Other workspace versions remain independent. |
| Accessibility and layout | SMO table action heading lacked text; large money totals could overflow. | Adds column scopes, a hidden action heading, visible keyboard focus, and wrapping for long money totals. Existing responsive layouts and reduced-motion rules remain in place. |
| Route and maintainability | Dashboard queries lived in a large route closure. | Moves data assembly into `DashboardController`; authentication and global active-account middleware remain in effect. |

## Verified behavior and limits

- The submission SLA uses the existing business-day service, which excludes weekends and configured institutional holidays. This change does not reset deadlines when a request changes stage.
- Ready-for-pickup requests remain active until completed, matching the current model's SLA and archive rules.
- Approval follow-ups cover pending departmental, VP, Provost, and presidential decisions. Clarification and fulfillment risks remain visible in the overall deadline monitor and full deadline workspace.
- The monthly value continues to mean requests with `completed` or `released` status. Existing archived records were backfilled with `closed_at` by the lifecycle migration; no production migration or data rewrite was needed for this change.
- Automatic refresh runs through the existing poller, so a minute boundary appears on a subsequent successful poll. Hidden tabs, open details, dialogs, and active edits can defer replacement.
- Source review covered responsive CSS, but no interactive browser or device visual inspection was available. The frontend tests exercise JavaScript with simulated browser objects.

## Validation

- Full PHP suite: **161 tests, 1,011 assertions passed**.
- Focused dashboard, deadline, refresh, initial-render, and performance suite after final refinements: **34 tests, 252 assertions passed**.
- Frontend suite: **49 tests passed**, including a dashboard clock-change regression.
- Production Vite build: passed. It reported an existing outdated Browserslist dataset notice.
- PHP style checks, dashboard route middleware inspection, and `git diff --check`: passed.

New regressions cover completion-date month boundaries, centavo precision, approval versus fulfillment previews, total counts, deterministic ordering, tier-specific labels, inventory thresholds/deletions/query count, empty states, authentication, notification isolation, and clock-based invalidation.
