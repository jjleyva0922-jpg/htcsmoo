import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const view = readFileSync(new URL('../../resources/views/admin/approvals.blade.php', import.meta.url), 'utf8');
const table = readFileSync(new URL('../../resources/views/admin/partials/approvals-table.blade.php', import.meta.url), 'utf8');
const source = view.slice(view.indexOf('function approvalManager()'), view.lastIndexOf('</script>'));

function browser({focused = null, tab = 'work'} = {}) {
    const listeners = new Map();
    const state = new Map();
    const calls = [];
    const reviewButton = {focus(options) {calls.push(['focus-review', options]);}};
    const submitButton = {focus() {calls.push(['focus-action']);}};
    function modal(id) {
        const node = {
            addEventListener(event, fn, options = {}) {
                const key = `${id}:${event}`;
                listeners.set(key, [...(listeners.get(key) || []), {fn, once: options.once}]);
            },
            querySelector: () => submitButton,
        };
        const api = {
            show() {state.set(id, 'shown'); calls.push(['show', id]);},
            hide() {state.set(id, 'hidden'); calls.push(['hide', id]);},
        };
        return {node, api};
    }
    const modals = new Map([['reviewModal', modal('reviewModal')], ['releaseConfirmationModal', modal('releaseConfirmationModal')]]);
    const document = {
        activeElement: reviewButton,
        getElementById(id) {
            if (id === 'approvalFilters') return {elements: {namedItem: () => ({value: tab})}};
            return modals.get(id)?.node;
        },
    };
    const context = {
        focusedApprovalRequest: focused,
        approvalActionBase: 'https://app.test/admin/requests',
        window: {location: {search: '?search=paper&dept=CETE&work_page=2&request=12'}},
        document, URLSearchParams, Intl,
        bootstrap: {Modal: {getOrCreateInstance(node) {return [...modals.values()].find(item => item.node === node).api;}}},
        setTimeout() {},
    };
    const manager = vm.runInNewContext(`(${source})()`, context);
    manager.$nextTick = callback => callback();
    function emit(id, event) {
        const key = `${id}:${event}`;
        const handlers = listeners.get(key) || [];
        listeners.set(key, handlers.filter(handler => !handler.once));
        handlers.forEach(handler => handler.fn());
    }
    return {manager, emit, state, calls};
}

test('review keeps special characters, tracking code, filters, group and pagination intact', () => {
    const {manager, emit, calls} = browser();
    const request = {
        id: 12, tracking_code: 'MIN-CETE-2026100012', status: 'approved_president', can_start_processing: true,
        queue_requestor: {name: `O'Neil "<script>" & José`, department: `R&D "O'Neil"`}, items: [{id: 1}],
    };
    manager.openDetails(request);
    assert.equal(manager.requestorName, request.queue_requestor.name);
    assert.equal(manager.requestorDept, request.queue_requestor.department);
    assert.equal(manager.selectedReq.tracking_code, request.tracking_code);
    assert.equal(manager.selectedReq.can_start_processing, true);
    const query = new URLSearchParams(manager.queueQuery);
    assert.equal(query.get('search'), 'paper');
    assert.equal(query.get('work_page'), '2');
    assert.equal(query.get('tab'), 'work');
    assert.equal(query.has('request'), false);
    assert.equal(manager.actionUrl('processing'), 'https://app.test/admin/requests/12/processing');
    emit('reviewModal', 'hidden.bs.modal');
    assert.equal(calls.at(-1)[0], 'focus-review');
    assert.equal(calls.at(-1)[1].preventScroll, true);
});

test('clarification ownership uses the latest clarification and history remains chronological', () => {
    const {manager} = browser({tab: 'clarification'});
    manager.openDetails({id: 1, status: 'for_clarification', logs: [
        {id: 4, action: 'Flagged for Clarification', role: 'president'},
        {id: 1, action: 'Approved', role: 'dept_head'},
        {id: 2, action: 'Flagged for Clarification', role: 'vp_admin'},
    ]});
    assert.equal(manager.questioningRole, 'president');
    assert.deepEqual(Array.from(manager.sortedLogs(), log => log.id), [1, 2, 4]);
    assert.equal(manager.formatTimestamp(null), 'Not recorded');
    assert.equal(manager.formatTimestamp('invalid'), 'Not recorded');
});

test('readiness requires explicit confirmation, submits once, and cancellation restores the review action', () => {
    const {manager, emit, state, calls} = browser();
    manager.openDetails({id: 1, status: 'processing'});
    let prevented = 0;
    let submissions = 0;
    manager.handleReleaseSubmit({preventDefault() {prevented++;}, currentTarget: {requestSubmit() {submissions++;}}});
    assert.equal(prevented, 1);
    assert.equal(submissions, 0);
    emit('reviewModal', 'hidden.bs.modal');
    assert.equal(state.get('releaseConfirmationModal'), 'shown');
    manager.cancelReleaseConfirmation();
    emit('releaseConfirmationModal', 'hidden.bs.modal');
    emit('reviewModal', 'shown.bs.modal');
    assert.equal(state.get('reviewModal'), 'shown');
    assert.deepEqual(calls.at(-1), ['focus-action']);
    assert.equal(manager.releaseForm, null);
    emit('reviewModal', 'hidden.bs.modal');
    assert.equal(calls.at(-1)[0], 'focus-review');
    manager.handleReleaseSubmit({preventDefault() {}, currentTarget: {requestSubmit() {submissions++;}}});
    manager.submitConfirmedRelease();
    manager.submitConfirmedRelease();
    assert.equal(submissions, 1);
    assert.equal(manager.releaseConfirmed, true);
});

test('notification focus opens the scoped request and markup uses safe serialization and accessible group links', () => {
    const request = {id: 7, status: 'approved_president', request_type: 'minor', tracking_code: 'MIN-CETE-2026100007'};
    const {manager, state} = browser({focused: request});
    manager.init();
    assert.equal(manager.selectedReq.id, 7);
    assert.equal(state.get('reviewModal'), 'shown');
    assert.match(table, /Js::from\(\$req->toArray\(\)\)/);
    assert.doesNotMatch(table, /addslashes/);
    assert.match(view, /x-if="selectedReq.can_start_processing"/);
    assert.match(view, /aria-current="page"/);
    assert.match(view, /aria-label="Close requisition review"/);
});

test('Escape or backdrop dismissal of readiness confirmation returns to review without submitting', () => {
    const {manager, emit, state, calls} = browser();
    manager.openDetails({id: 1, status: 'processing'});
    let submissions = 0;
    manager.handleReleaseSubmit({preventDefault() {}, currentTarget: {requestSubmit() {submissions++;}}});
    emit('reviewModal', 'hidden.bs.modal');
    // Bootstrap dispatches this event for Escape and backdrop clicks as well.
    emit('releaseConfirmationModal', 'hidden.bs.modal');
    emit('reviewModal', 'shown.bs.modal');
    assert.equal(state.get('reviewModal'), 'shown');
    assert.equal(manager.releaseForm, null);
    assert.equal(manager.releaseConfirmed, false);
    assert.equal(submissions, 0);
    assert.deepEqual(calls.at(-1), ['focus-action']);
});
