import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';
import {initializeDynamicSearch} from '../../resources/js/dynamic-search.js';

const read = path => readFileSync(new URL('../../' + path, import.meta.url), 'utf8');
const template = read('resources/views/layouts/partials/session-recovery.blade.php');
const source = template.slice(template.indexOf('<script>') + 8, template.lastIndexOf('</script>'));
const flush = () => new Promise(resolve => setImmediate(resolve));

function browser(reply = {ok: true, status: 204}) {
    class Element extends EventTarget {
        dataset = {}; children = []; attributes = new Map(); hidden = true; disabled = false; isConnected = true;
        textContent = ''; name = ''; value = '';
        setAttribute(key, value) { this.attributes.set(key, value); }
        getAttribute(key) { return this.attributes.get(key) ?? null; }
        hasAttribute(key) { return this.attributes.has(key); }
        removeAttribute(key) { this.attributes.delete(key); }
        appendChild(child) { this.children.push(child); child.parent = this; }
        remove() { this.parent.children = this.parent.children.filter(child => child !== this); }
        querySelector(selector) {
            if (selector === '[data-session-close]') return close;
            if (selector === '[name="_token"]') return {value: 'old-token'};
            if (selector === '[data-session-status]') return this.children.find(child => 'sessionStatus' in child.dataset);
        }
        querySelectorAll() { return this.children.filter(child => child === button); }
    }
    class Form extends Element {
        method = 'post'; action = 'https://app.test/requisitions'; purpose = 'Unsaved work'; writes = 0;
        submit() { this.writes++; }
    }
    const close = new Element();
    const button = new Element(); button.name = 'status'; button.value = 'approved';
    const dialog = new Element();
    dialog.dataset = {checkUrl: '/session/check', recoveryUrl: '/session/recover', loginUrl: 'https://app.test/login', account: '7'};
    dialog.showModal = () => { dialog.opens = (dialog.opens || 0) + 1; };
    dialog.close = () => dialog.dispatchEvent(new Event('close'));
    const notice = new Element();
    const link = new Element();
    const document = Object.assign(new EventTarget(), {
        getElementById: id => id === 'sessionExpiredDialog' ? dialog : notice,
        querySelectorAll: () => [link], createElement: () => new Element(),
    });
    const calls = [];
    const window = Object.assign(new EventTarget(), {location: {href: 'https://app.test/requests?status=pending', origin: 'https://app.test', pathname: '/requests', search: '?status=pending'},
        fetch: async (...args) => { calls.push(args); if (reply instanceof Error) throw reply; return reply; },
        setTimeout: () => 1, clearTimeout() {},
    });
    vm.runInNewContext(source, {window, document, HTMLFormElement: Form, URL, URLSearchParams, CustomEvent, AbortController});
    const form = new Form(); form.children = [button];
    const submit = () => {
        const event = new Event('submit', {cancelable: true});
        Object.defineProperty(event, 'target', {value: form});
        event.submitter = button;
        document.dispatchEvent(event);
        return event;
    };
    return {window, document, form, button, submit, calls, dialog, link, notice};
}

for (const status of [401, 419]) {
    test(`${status} during a native form check retains work and never sends or replays the write`, async () => {
        const b = browser({status, ok: false});
        assert.equal(b.submit().defaultPrevented, true);
        await flush();
        assert.equal(b.calls.length, 1);
        assert.equal(b.calls[0][0], '/session/check');
        assert.equal(b.calls[0][1].body.get('_token'), 'old-token');
        assert.equal(b.form.writes, 0);
        assert.equal(b.form.purpose, 'Unsaved work');
        assert.equal(b.form.hasAttribute('aria-busy'), false);
        assert.equal(b.window.htcSession.expired, true);
        assert.equal(b.dialog.opens, 1);
        assert.equal(new URL(b.link.href).searchParams.get('return'), '/requests?status=pending');
        b.dialog.close();
        assert.equal(b.notice.hidden, false);
        b.submit();
        await assert.rejects(b.window.fetch('/notifications'), {name: 'SessionExpiredError'});
        assert.equal(b.calls.length, 1);
        assert.equal(b.form.writes, 0);
    });
}

test('a valid form check submits exactly once with the confirmed action value', async () => {
    const b = browser();
    let submitted;
    b.document.addEventListener('htc:session-submit', event => submitted = event.detail);
    b.submit(); b.submit();
    await flush();
    assert.equal(b.calls.length, 1);
    assert.equal(b.calls[0][1].body.get('account'), '7');
    assert.equal(b.form.writes, 1);
    assert.equal(b.form.children.find(child => child.type === 'hidden').value, 'approved');
    assert.equal(submitted.form, b.form);
    assert.equal(submitted.submitter, b.button);
    assert.equal(b.window.htcSession.expired, false);
});

test('browser Back restores submit controls without leaving duplicate action fields', async () => {
    const b = browser();
    b.submit(); await flush();
    assert.equal(b.button.disabled, true);
    b.window.dispatchEvent(new Event('pageshow'));
    assert.equal(b.button.disabled, false);
    assert.equal(b.form.children.filter(child => child.type === 'hidden').length, 0);
    b.submit(); await flush();
    assert.equal(b.form.writes, 2);
    assert.equal(b.form.children.filter(child => child.type === 'hidden').length, 1);
});

test('a network failure checking the session sends no write and allows an explicit retry', async () => {
    const b = browser(new Error('Offline'));
    b.submit(); await flush();
    assert.equal(b.form.writes, 0);
    assert.equal(b.button.disabled, false);
    assert.match(b.form.children.at(-1).textContent, /not submitted/);
    b.submit(); await flush();
    assert.equal(b.calls.length, 2);
    assert.equal(b.form.writes, 0);
});

test('a followed login redirect triggers recovery before HTML or PDF content is consumed', async () => {
    const b = browser({status: 200, ok: true, redirected: true, url: 'https://app.test/login'});
    await assert.rejects(b.window.fetch('/admin/reports/generate'), {name: 'SessionExpiredError'});
    assert.equal(b.dialog.opens, 1);
});

test('first-party expiry handling does not intercept external fetches', async () => {
    const b = browser({status: 401});
    assert.equal((await b.window.fetch('https://external.test/data')).status, 401);
    assert.equal(b.window.htcSession.expired, false);
});

test('an in-flight success cannot replace work after another request detects expiry', async () => {
    let finish;
    const b = browser(new Promise(resolve => finish = resolve));
    const response = b.window.fetch('/dashboard');
    b.window.htcSession.show();
    finish({status: 200, ok: true});
    await assert.rejects(response, {name: 'SessionExpiredError'});
    assert.equal(b.form.purpose, 'Unsaved work');
});

test('an unexpected successful response from the check endpoint cannot submit a write', async () => {
    const b = browser({status: 200, ok: true});
    b.submit(); await flush();
    assert.equal(b.form.writes, 0);
    assert.match(b.form.children.at(-1).textContent, /not submitted/);
});

test('expiry cancels dynamic search without its navigation fallback', async () => {
    const navigations = [];
    const result = {id: 'results', setAttribute() {}, removeAttribute() {}};
    const status = {};
    const root = {dataset: {}, querySelector: selector => selector === '[data-filter-status]' ? status : result, querySelectorAll: () => []};
    const document = {querySelectorAll: () => [], addEventListener() {}};
    const window = {location: {href: 'https://app.test/requests', origin: 'https://app.test', assign: url => navigations.push(url)},
        addEventListener() {}, clearTimeout, setTimeout,
        fetch: async () => {throw Object.assign(new Error('Expired'), {name: 'SessionExpiredError'});},
    };
    await initializeDynamicSearch(window, document).refresh(root, window.location.href);
    assert.deepEqual(navigations, []);
});

for (const name of ['SessionExpiredError', 'TypeError']) {
    test(`notification write failure (${name}) never falls back to submitting the same form`, async () => {
        const template = read('resources/views/notifications.blade.php');
        const start = template.indexOf("center.addEventListener('submit', ") + "center.addEventListener('submit', ".length;
        const callback = template.slice(start, template.indexOf("window.addEventListener('storage'", start)).replace(/\);\s*$/, '');
        let writes = 0; let alerts = 0;
        const button = {disabled: false, isConnected: true};
        const form = {dataset: {}, action: '/notifications/1/read', submit: () => writes++, querySelector: selector => selector.startsWith('button') ? button : {value: 'old-token'}};
        const center = {querySelector: () => null, prepend: () => alerts++};
        const handle = vm.runInNewContext(`(${callback})`, {center, document: {createElement: () => ({dataset: {}, setAttribute() {}})},
            fetch: async () => {throw Object.assign(new Error('Failed'), {name});},
        });
        await handle({target: {closest: selector => selector === '[data-notification-read-form]' ? form : null}, preventDefault() {}});
        assert.equal(writes, 0);
        assert.equal(alerts, name === 'SessionExpiredError' ? 0 : 1);
        assert.equal(button.disabled, false);
    });
}
