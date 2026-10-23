# Fulfillment queue

The existing `RequisitionRoleRoutingTest`, `Requisition::initialStatusFor`, submission notifications, and `nextReviewerRole` establish that requests begin after the requestor's own approval office. Provost and president requests start at `approved_president` for both tiers. This policy is preserved. Minor `approved_president` requests now have the same SMO processing eligibility in the queue, model, notification actions, and transition controller. Major requests at `approved_vp` still require their remaining approvals.

## Queue and review

- Minor waiting and Major waiting contain fully approved requests awaiting processing. Active work / pickup contains `processing` and `ready_for_pickup`. Clarification is separate. Groups are disjoint, and counts describe all filtered matches rather than the current 15-row page.
- Existing executive priority remains the default. Explicit oldest, newest, and deadline sorts use submission time (creation time for legacy null submissions), with stable ID ties. Deadlines follow the existing model's SLA rules, including processing and pickup stages.
- Search covers tracking number, requestor, item/specifications, department, purpose, and remarks. Department, tier, status, paired submission dates, and sorting narrow the authorized query before pagination. Department heads cannot see or act on another department's clarification just because its last questioning role is also `dept_head`.
- The existing dynamic-filter helper provides debounce, partial updates, URL history, live announcements, error feedback, and result orientation. GET and pagination links still work without enhancement. Review/actions require the existing JavaScript dialogs. Group navigation uses keyboard-operable links with `aria-current` rather than mixing links with tab-widget keyboard semantics.
- Review shows the stored tracking code, purpose, requested prices/totals, full lines, recorded workflow actions/actors/timestamps/remarks, and a stock snapshot. Laravel `Js::from` serializes review data; names, departments, and remarks are displayed as text.
- Review closure returns focus to its trigger. Canceling the readiness confirmation returns to the review action. Successful POSTs return only to the local queue with validated filters/group/page. A page that becomes out of range displays the last available page.

## Inventory and audit

`FulfillmentInventoryService` builds the same aggregate plan for the advisory snapshot and authoritative transaction. Catalog stock is locked in ID order. Duplicate catalog lines, including exact legacy name matches, share one demand total and one movement. All lines must resolve to active catalog stock with sufficient quantity before any stock is deducted. Missing/deleted stock, unresolved manual lines, or an empty request explicitly block readiness; they are not silently omitted.

Starting processing does not deduct stock. Marking ready atomically deducts all planned stock, records movements, records the SMO actor and readiness audit token, changes status, and sends the existing pickup notification. Failures roll back transaction writes and display a failure message. Completion locks the request, requires `ready_for_pickup`, records the authenticated SMO officer and audit timestamp, sets `closed_at`, and sends the existing completion notification. Repeated readiness/completion cannot deduct twice. Existing queued email handling remains unchanged.

## Decisions still required

- No partial fulfillment or backorder policy is documented. None is added. A policy and data model would be needed to represent prepared/issued/outstanding quantities and subsequent stock movements without falsely declaring the entire request ready.
- Legacy code previously allowed unmatched lines to fall through to a generic "Special requisition processed" log, with no evidence that those items were prepared. Such requests now remain in processing with an explicit error. An approved manual-procurement verification/linking workflow is needed if these requests must be fulfilled outside catalog inventory.
- Pickup currently proves which SMO officer confirmed completion and when. It does not record the collector's identity, delegation authority, or recipient signature. Existing handover behavior is preserved; no new recipient, signature, or identity requirement is invented. The institution must define these requirements before adding recipient evidence fields.

## Validation

`FulfillmentQueueTest` covers every requestor-role/tier initial path, SMO eligibility, queue groups/counts/filtering/sorting/pagination, department isolation, invalid transitions, stock changes after review, duplicate demand, successful inventory/audit/pickup handling, deleted/unresolved legacy stock, rollback on movement/audit failures, safe special-character serialization, and validated local return navigation. `fulfillment-queue.test.mjs` exercises review data, filter state, chronological history, explicit readiness confirmation, duplicate submission prevention, and focus restoration alongside the existing shared filter/runtime/confirmation tests.

Automated tests use SQLite and the project's Node DOM/event fixtures. They do not simulate concurrent MySQL transactions or replace a real narrow-screen and assistive-technology browser check. No schema, dependency, route, dashboard, or archive changes are required.
