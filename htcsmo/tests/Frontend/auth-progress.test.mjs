import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const template = readFileSync(new URL('../../resources/views/layouts/partials/auth-progress.blade.php', import.meta.url), 'utf8');
const source = template.slice(template.indexOf('<script>') + 8, template.lastIndexOf('</script>'));

function browser(kind = 'login') {
    class Element extends EventTarget {
        dataset = {}; attributes = new Map(); open = false; isConnected = true; textContent = '';
        getAttribute(key) { return this.attributes.get(key) ?? null; }
        focus() { document.activeElement = this; }
        showModal() { this.open = true; }
        close() { this.open = false; }
    }
    class Form extends Element {}
    const dialog = new Element(); const title = new Element(); const message = new Element();
    const button = new Element(); const form = new Form(); form.dataset.authProgress = kind;
    const document = Object.assign(new EventTarget(), {
        activeElement: button,
        getElementById: id => ({authProgressDialog: dialog, authProgressTitle: title, authProgressMessage: message})[id],
    });
    const window = Object.assign(new EventTarget(), {htcSession: {expired: false}});
    vm.runInNewContext(source, {window, document, HTMLFormElement: Form});
    const dispatch = (type, {target = form, canceled = false, detail} = {}) => {
        const event = new Event(type, {cancelable: true});
        Object.defineProperty(event, 'target', {value: target});
        event.detail = detail;
        if (canceled) event.preventDefault();
        document.dispatchEvent(event);
        return event;
    };
    return {window, document, dialog, title, message, form, button, dispatch};
}

test('login progress appears during the session check and updates when the write starts', () => {
    const b = browser();
    b.form.attributes.set('aria-busy', 'true');
    b.dispatch('submit', {canceled: true});
    assert.equal(b.dialog.open, true);
    assert.equal(b.document.activeElement, b.dialog);
    assert.match(b.title.textContent, /Signing you in/);
    assert.match(b.message.textContent, /Checking your session/);
    b.dispatch('htc:session-submit', {detail: {form: b.form}});
    assert.match(b.message.textContent, /securely sign you in/);
    assert.equal(b.dialog.open, true);
    const escape = new Event('cancel', {cancelable: true});
    b.dialog.dispatchEvent(escape);
    assert.equal(escape.defaultPrevented, true);
});

test('signup progress explains account review rather than promising immediate access', () => {
    const b = browser('register');
    b.dispatch('htc:session-submit', {detail: {form: b.form}});
    assert.equal(b.dialog.open, true);
    assert.match(b.title.textContent, /Creating your account/);
    assert.match(b.message.textContent, /for review/);
});

test('canceled submissions and other forms do not open the progress dialog', () => {
    const b = browser();
    b.dispatch('submit', {canceled: true});
    assert.equal(b.dialog.open, false);
    b.form.dataset.authProgress = 'other';
    b.dispatch('submit');
    assert.equal(b.dialog.open, false);
});

test('a failed session check closes progress and returns focus to the form', () => {
    const b = browser();
    b.dispatch('submit');
    b.dispatch('htc:session-check-failed');
    assert.equal(b.dialog.open, false);
    assert.equal(b.document.activeElement, b.button);
});

test('expiry removes the progress dialog without stealing focus from recovery', () => {
    const b = browser();
    b.dispatch('submit');
    const recovery = {};
    b.window.htcSession.expired = true;
    b.document.activeElement = recovery;
    b.dispatch('htc:session-expired');
    b.dispatch('htc:session-check-failed');
    assert.equal(b.dialog.open, false);
    assert.equal(b.document.activeElement, recovery);
    b.dispatch('htc:session-submit', {detail: {form: b.form}});
    assert.equal(b.dialog.open, false);
});

test('navigation and browser Back clear the progress dialog', () => {
    const b = browser();
    b.dispatch('submit');
    b.window.dispatchEvent(new Event('pagehide'));
    assert.equal(b.dialog.open, false);
    b.dispatch('submit');
    b.window.dispatchEvent(new Event('pageshow'));
    assert.equal(b.dialog.open, false);
});
