import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const template = readFileSync(new URL('../../resources/views/layouts/partials/runtime-guard.blade.php', import.meta.url), 'utf8');
const source = template.slice(template.indexOf('<script>') + 8, template.lastIndexOf('</script>'));

function browser() {
    class Element extends EventTarget {
        dataset = {};
        attributes = new Map();
        hidden = true;
        open = false;
        opens = 0;
        disabled = false;
        isConnected = true;
        innerHTML = 'Save';
        children = [];
        tagName = 'BUTTON';
        classList = { add() {}, remove() {} };
        setAttribute(key, value) { this.attributes.set(key, value); }
        getAttribute(key) { return this.attributes.get(key) ?? null; }
        showModal() { this.open = true; this.opens++; }
        close() { this.open = false; }
        removeAttribute(key) {
            this.attributes.delete(key);
            if (key === 'data-runtime-disabled') delete this.dataset.runtimeDisabled;
        }
        hasAttribute(key) { return this.attributes.has(key); }
        focus() { document.activeElement = this; }
        contains(element) { return this === element || this.children.includes(element); }
        closest(selector) { return selector === 'a[href]' && this.href ? this : null; }
        appendChild(child) { this.children.push(child); }
        replaceChildren(...children) { this.children = children; this.innerHTML = 'Loading'; }
        querySelectorAll(selector) {
            if (selector === '[data-runtime-disabled]') return this.children.filter(child => child.dataset.runtimeDisabled);
            if (selector === '[data-runtime-submitter]') return [];
            return this.children.filter(child => child.tagName === 'BUTTON');
        }
    }
    class Form extends Element { method = 'get'; }
    const elements = new Map(['runtimeLoader', 'runtimeLoaderMessage', 'runtimeLogoutModal', 'runtimeActionModal', 'runtimeActionTitle', 'runtimeActionMessage'].map(id => [id, new Element()]));
    const document = Object.assign(new EventTarget(), {
        body: new Element(), documentElement: new Element(), activeElement: new Element(),
        getElementById: id => elements.get(id), createElement: () => new Element(),
    });
    let nextId = 0;
    const timers = new Map();
    const window = Object.assign(new EventTarget(), {
        location: { href: 'https://example.test/dashboard', origin: 'https://example.test' }, stops: 0,
        stop() { this.stops++; },
        setTimeout(callback, delay) { const id = ++nextId; timers.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
    });
    vm.runInNewContext(source, {
        window, document, HTMLElement: Element, HTMLFormElement: Form, URL, queueMicrotask,
        sessionStorage: { setItem() {} },
    });
    const dispatch = (type, target, properties = {}) => {
        const event = new Event(type, { cancelable: true });
        Object.defineProperty(event, 'target', { value: target });
        const {canceled, ...rest} = properties;
        Object.assign(event, { button: 0, ...rest });
        if (canceled) event.preventDefault();
        document.dispatchEvent(event);
        return event;
    };
    const runTimer = delay => {
        const entry = [...timers].find(([, timer]) => timer.delay === delay);
        assert.ok(entry, `Expected timer ${delay}ms`);
        timers.delete(entry[0]);
        entry[1].callback();
    };
    const link = new Element();
    link.href = 'https://example.test/requests';
    const form = new Form();
    const submitter = new Element();
    form.children = [submitter];
    return { window, document, dispatch, runTimer, timers, link, form, submitter, loader: elements.get('runtimeLoader'),
        actionModal: elements.get('runtimeActionModal'), actionTitle: elements.get('runtimeActionTitle'), actionMessage: elements.get('runtimeActionMessage') };
}

const flush = () => new Promise(resolve => setImmediate(resolve));

test('session expiry releases runtime locks so recovery and copying remain accessible', () => {
    const b = browser();
    b.form.method = 'post';
    b.dispatch('submit', b.form, {submitter: b.submitter});
    assert.equal(b.form.dataset.runtimeSubmitting, 'true');
    b.document.dispatchEvent(new Event('htc:session-expired'));
    assert.equal(b.form.dataset.runtimeSubmitting, undefined);
    assert.equal(b.submitter.disabled, false);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), false);
    assert.equal(b.loader.hidden, true);
});

test('a verified native form retains its runtime loader without redispatching submission', () => {
    const b = browser();
    const event = new Event('htc:session-submit');
    event.detail = {form: b.form};
    b.document.dispatchEvent(event);
    b.runTimer(180);
    assert.equal(b.loader.hidden, false);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), true);
    b.document.dispatchEvent(new Event('htc:session-expired'));
    assert.equal(b.loader.hidden, true);
});

test('downloads and browser reloads do not create a loader from beforeunload', async () => {
    const b = browser();
    b.link.setAttribute('download', '');
    b.dispatch('click', b.link);
    await flush();
    b.window.dispatchEvent(new Event('beforeunload', { cancelable: true }));
    assert.equal(b.timers.size, 0);
    assert.equal(b.loader.hidden, true);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), false);
});

test('CSV exports retain their named submitter without disabling or locking the page', () => {
    const b = browser();
    b.submitter.name = 'export';
    b.submitter.value = 'csv';
    b.submitter.dataset.download = '';
    const event = b.dispatch('submit', b.form, { submitter: b.submitter });
    assert.equal(event.defaultPrevented, false);
    assert.equal(b.submitter.disabled, false);
    assert.equal(b.submitter.value, 'csv');
    assert.equal(b.form.dataset.runtimeSubmitting, undefined);
    assert.equal(b.timers.size, 0);
});

test('a link canceled by a later handler never locks the page', async () => {
    const b = browser();
    b.document.addEventListener('click', event => event.preventDefault());
    b.dispatch('click', b.link);
    await flush();
    assert.equal(b.timers.size, 0);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), false);
});

test('an interrupted page navigation recovers instead of leaving the overlay stuck', async () => {
    const b = browser();
    b.dispatch('click', b.link);
    await flush();
    b.runTimer(180);
    assert.equal(b.loader.hidden, false);
    b.runTimer(15000);
    assert.equal(b.loader.hidden, true);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), false);
    assert.equal(b.dispatch('keydown', b.submitter, { key: 'Tab' }).defaultPrevented, false);
});

test('Escape stops GET navigation and restores its disabled form controls', () => {
    const b = browser();
    b.dispatch('submit', b.form, { submitter: b.submitter });
    assert.equal(b.submitter.disabled, true);
    b.dispatch('keydown', b.submitter, { key: 'Escape' });
    assert.equal(b.window.stops, 1);
    assert.equal(b.submitter.disabled, false);
    assert.equal(b.form.dataset.runtimeSubmitting, undefined);
    assert.equal(b.loader.hidden, true);
});

test('a canceled beforeunload prompt releases an existing navigation lock', async () => {
    const b = browser();
    b.dispatch('click', b.link);
    await flush();
    const event = new Event('beforeunload', { cancelable: true });
    b.window.dispatchEvent(event);
    event.preventDefault();
    b.runTimer(0);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), false);
    assert.equal(b.loader.hidden, true);
});

test('cancellation is tracked when beforeunload fires synchronously during the link click', async () => {
    const b = browser();
    b.document.addEventListener('click', () => {
        const event = new Event('beforeunload', { cancelable: true });
        b.window.dispatchEvent(event);
        event.preventDefault();
    });
    b.dispatch('click', b.link);
    await flush();
    b.runTimer(0);
    assert.equal(b.loader.hidden, true);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), false);
});

test('write submissions stay locked and cannot be repeated or canceled by Escape', () => {
    const b = browser();
    b.form.method = 'post';
    b.dispatch('submit', b.form, { submitter: b.submitter });
    b.dispatch('keydown', b.submitter, { key: 'Escape' });
    const duplicate = b.dispatch('submit', b.form, { submitter: b.submitter });
    assert.equal(duplicate.defaultPrevented, true);
    assert.equal(b.window.stops, 0);
    assert.equal(b.submitter.disabled, true);
    assert.equal(b.form.dataset.runtimeSubmitting, 'true');
});

test('an old navigation timeout cannot unlock a write that starts programmatically', async () => {
    const b = browser();
    b.dispatch('click', b.link);
    await flush();
    b.form.method = 'post';
    b.dispatch('submit', b.form, { submitter: b.submitter });
    b.runTimer(15000);
    b.dispatch('keydown', b.submitter, { key: 'Escape' });
    assert.equal(b.window.stops, 0);
    assert.equal(b.submitter.disabled, true);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), true);
});

test('accepted workflow actions show an immediate top-layer dialog instead of the page skeleton', () => {
    const b = browser();
    b.form.method = 'post'; b.form.dataset.actionProgress = '';
    b.submitter.dataset.loadingText = 'Recording your approval...';
    b.dispatch('submit', b.form, {submitter: b.submitter});
    assert.equal(b.actionModal.open, true); assert.equal(b.actionModal.hidden, false);
    assert.equal(b.document.activeElement, b.actionModal);
    assert.equal(b.actionTitle.textContent, 'Recording your approval...');
    assert.equal(b.loader.hidden, true); assert.equal(b.timers.size, 0);
    assert.equal(b.submitter.disabled, true);
    assert.equal(b.dispatch('submit', b.form, {submitter: b.submitter}).defaultPrevented, true);
    assert.equal(b.actionModal.opens, 1);
    b.dispatch('keydown', b.actionModal, {key: 'Escape'});
    assert.equal(b.actionModal.open, true); assert.equal(b.window.stops, 0);
    const cancel = new Event('cancel', {cancelable: true});
    b.actionModal.dispatchEvent(cancel); assert.equal(cancel.defaultPrevented, true);
});

test('workflow progress stays visible from session checking through native submission', () => {
    const b = browser(); b.form.method = 'post';
    b.form.dataset.actionProgress = ''; b.form.dataset.loadingMessage = 'Confirming pickup and completion...';
    b.form.setAttribute('aria-busy', 'true');
    b.dispatch('submit', b.form, {submitter: b.submitter, canceled: true});
    assert.equal(b.actionModal.open, true); assert.equal(b.actionModal.opens, 1);
    assert.match(b.actionMessage.textContent, /Checking your session/);
    assert.equal(b.actionTitle.textContent, 'Confirming pickup and completion...');
    b.dispatch('htc:session-submit', b.form, {detail: {form: b.form, submitter: b.submitter}});
    assert.equal(b.actionModal.opens, 1); assert.equal(b.loader.hidden, true);
    assert.equal(b.actionMessage.textContent, 'Please wait while we save this action.');
});

test('declined or intercepted workflow confirmations do not show a loading dialog', () => {
    const b = browser(); b.form.dataset.actionProgress = '';
    b.dispatch('submit', b.form, {submitter: b.submitter, canceled: true});
    assert.equal(b.actionModal.open, false); assert.equal(b.loader.hidden, true);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), false);
});

test('failed workflow session checks close progress and return focus to the form', () => {
    const b = browser(); b.form.dataset.actionProgress = ''; b.form.setAttribute('aria-busy', 'true');
    b.submitter.focus();
    b.dispatch('submit', b.form, {submitter: b.submitter, canceled: true});
    assert.equal(b.actionModal.open, true);
    b.dispatch('htc:session-check-failed', b.form);
    assert.equal(b.actionModal.open, false); assert.equal(b.actionModal.hidden, true);
    assert.equal(b.document.activeElement, b.submitter);
    assert.equal(b.document.documentElement.hasAttribute('aria-busy'), false);
});

test('session expiry and browser Back clear workflow progress without leaving the page locked', () => {
    for (const type of ['htc:session-expired', 'pageshow']) {
        const b = browser(); b.form.method = 'post'; b.form.dataset.actionProgress = '';
        b.dispatch('submit', b.form, {submitter: b.submitter});
        if (type === 'pageshow') b.window.dispatchEvent(new Event(type));
        else b.document.dispatchEvent(new Event(type));
        assert.equal(b.actionModal.open, false); assert.equal(b.actionModal.hidden, true);
        assert.equal(b.submitter.disabled, false);
        assert.equal(b.document.documentElement.hasAttribute('aria-busy'), false);
    }
});

test('workflow submission cancels a pending navigation skeleton before it can obscure progress', () => {
    const b = browser();
    b.dispatch('click', b.link);
    b.form.method = 'post'; b.form.dataset.actionProgress = '';
    b.dispatch('submit', b.form, {submitter: b.submitter});
    assert.equal(b.timers.size, 0); assert.equal(b.actionModal.open, true); assert.equal(b.loader.hidden, true);
});
