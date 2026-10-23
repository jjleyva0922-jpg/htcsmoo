# Signatory notification review

Reviewed October 23, 2026 (Asia/Manila). Scope: the SMO **Notify** button in the approval deadline watch, its delivery service, the scheduled deadline scan, and recipient notification actions/popups.

## Findings and changes

| Finding | Result |
| --- | --- |
| A successful write to SMO or the requestor was enough to claim the current signatory had been notified, even when no active matching signatory existed. | Missing current owners now produce an assignment error. Other active recipients still receive their reminders. |
| Partial notification creation was reported as complete success. | The service reports expected and persisted recipient counts. Partial delivery produces an error; retry creates only missing reminders. |
| Two users without a department could match each other as requestor and department head. | Department reminders require an actual department assignment; null departments do not select unassigned heads. |
| Inactive requestors were included despite inactive SMO and signatory accounts being excluded. | Only active accounts receive these reminders. |
| A route-bound model or a scheduled scan could hold an old workflow stage while approval or closure proceeded. | Delivery reloads and locks the requisition inside a transaction, using the same row lock as approval/closure actions. Closed requests are rechecked before delivery. |
| Same-day duplicate protection could keep the requestor and SMO on the previous stage's reminder. | Duplicate keys include status, reviewer role, and stage start time, as well as deadline state, recipient, and application date. A changed stage gets a current reminder. |
| The scheduled scan did not report missing owners or partial delivery. | It warns for affected tracking codes and returns a failure exit status when delivery/assignment is incomplete. |
| Confirmation and success wording did not identify the delivery channel or repeat-send policy. | The UI now explicitly says in-app and explains the per-stage, per-deadline-state, daily limit. |

## Verified behavior

- Only authenticated, active SMO accounts may trigger the reminder endpoint. Employee and signatory accounts cannot trigger it.
- Recipients are all active SMO officers, the active requestor, and active users in the current reviewing office. Department heads must match the requestor's department. A person occupying multiple recipient categories receives one notification.
- Minor requests route through department review and VP Finance; major requests route through department review, VP Administration, Provost, and President. Fulfillment stages target SMO. Clarification uses the latest recorded questioning office.
- Reminders require a warning/deadline risk. Completed, released, rejected, and withdrawn requests are excluded. Warning and overdue boundaries are inclusive.
- Manual delivery and the scheduler share duplicate protection. Repeating a send does not reopen a read notification. A new application date, stage, or warning-to-overdue transition permits a new reminder.
- Requestors and SMO can open request details. Signatories receive a scoped approval-queue action. Opening rechecks permissions and marks the notification read; an old signatory loses the review link after the workflow advances.
- Visiting a dashboard or notification feed does not mark the reminder read. Existing frontend tests cover initial-page popups, navigation, duplicate polls, hidden tabs, account isolation, browser storage failures, confirmations, and duplicate-submit protection.
- The submission deadline remains fixed when approval stages change. Existing SLA tests verify weekends and configured institutional holidays.

## Current database issue requiring assignment

A read-only check found **24 deadline-risk requests**, including **7 pending requests whose requestors have no department**. They have no valid departmental recipient:

| Tracking code | Required correction |
| --- | --- |
| MIN-HTC-20000003 | Assign the requestor's correct department and verify an active department head for it. |
| MIN-HTC-20000026 | Same. |
| MIN-HTC-20000027 | Same. |
| MIN-HTC-20000034 | Same. |
| MIN-HTC-20000038 | Same. |
| MIN-HTC-20000043 | Same. |
| MIN-HTC-20000044 | Same. |

The database contained active accounts for all signatory roles: one each for President, Provost, VP Administration, VP Finance, and SMO, plus five department heads. The missing department assignments prevent routing these seven requests. Appropriate departments cannot be inferred from the notification feature, so no personnel assignments or live requisitions were changed. No live reminders were sent during this review.

## Validation

- Deadline reminder suite: **30 tests, 232 assertions passed**.
- Complete PHP suite: **258 passed, 6 failed; 2,703 assertions; no errors**. The six failures concern existing dashboard text assertions: the SMO subtitle, employee preview-count disclosure, employee next-step headings, an employee active-request link, and the approval-preview footer. These failures are outside the reminder delivery logic; their expected text is absent from the existing templates.
- Related notification center, business-day SLA, role routing, session recovery, and live-refresh PHP suites passed within that run.
- Frontend suite: **127 tests passed** using `node --test --experimental-test-isolation=none tests/Frontend/*.test.mjs`. The default npm command could not spawn isolated Node processes in the sandbox (`EPERM`); running without process isolation succeeded.
- PHP formatting checks for changed PHP files and `git diff --check` passed.

## Operational limits

- This action persists **in-app notifications**. It does not dispatch email or SMS, and persistence does not prove the recipient has seen the message. An offline recipient sees it after signing in; read state is separate.
- The automatic scan is scheduled for **08:00 Asia/Manila daily** and needs a running Laravel scheduler. A risk arising after that scan waits until the next scan unless SMO sends a reminder manually. Scheduler operation on the deployed host was not verified.
- PHP integration tests use isolated, in-memory SQLite. The row-lock implementation was reviewed, but simultaneous MySQL requests were not exercised. No interactive two-account browser test was available.
- Existing notification records are retained. Since duplicate keys now identify the stage, the first send after this update may create a new notice even if an older-format reminder already exists for that date.

The code checks pass for the reminder flow, but the seven department assignments and a deployed two-account browser check remain necessary before treating the feature as fully verified operationally.
