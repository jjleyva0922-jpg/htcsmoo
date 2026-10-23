import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/confirmations.js', import.meta.url), 'utf8')
    .replace('export function', 'function');

function browser() {
    class Element extends EventTarget {
        dataset = {};
        isConnected = true;
        disabled = false;
        textContent = '';
        focus() { document.activeElement = this; }
        setAttribute() {}
        hasAttribute(name) {
            return Object.hasOwn(this.dataset, name.replace(/^data-/, '').replace(/-([a-z])/g, (_, letter) => letter.toUpperCase()));
        }
    }
    const children = new Map(['h2', 'p', '.action-confirmation__accept', '.action-confirmation__cancel'].map(key => [key, new Element()]));
    const dialog = Object.assign(new Element(), {
        open: false,
        showModal() { this.open = true; },
        close() { this.open = false; },
        querySelector: selector => children.get(selector),
    });
    const document = Object.assign(new EventTarget(), {
        body: { append() {} },
        activeElement: new Element(),
        createElement: () => dialog,
    });
    const submissions = [];
    class Form extends Element {
        valid = true;
        requestSubmit(submitter) {
            if (!this.valid) return;
            const event = new Event('submit', { cancelable: true });
            Object.defineProperties(event, { target: { value: this }, submitter: { value: submitter } });
            document.dispatchEvent(event);
            if (!event.defaultPrevented) submissions.push({ form: this, submitter });
        }
    }
    const window = new EventTarget();
    const context = vm.createContext({ window, document, HTMLFormElement: Form });
    vm.runInContext(source, context);
    const form = new Form();
    form.dataset.confirm = 'Save changes?';
    const button = new Element();
    return { window, document, dialog, form, button, submissions, children, context };
}

const settle = () => new Promise(resolve => setImmediate(resolve));
const click = element => element.dispatchEvent(new Event('click'));

test('cancel keeps the form editable and restores focus without submitting', async () => {
    const b = browser();
    b.button.focus();
    b.form.requestSubmit(b.button);
    assert.equal(b.dialog.open, true);
    assert.equal(b.document.activeElement, b.children.get('.action-confirmation__cancel'));
    click(b.children.get('.action-confirmation__cancel'));
    await settle();
    assert.equal(b.submissions.length, 0);
    assert.equal(b.document.activeElement, b.button);
    assert.equal(b.button.disabled, false);
    assert.equal(b.dialog.open, false);
});

test('accept submits once with the original named decision button', async () => {
    const b = browser();
    b.button.name = 'status';
    b.button.value = 'rejected';
    Object.assign(b.button.dataset, { confirm: 'Reject request ABC?', confirmTitle: 'Reject?', confirmTone: 'danger' });
    b.form.requestSubmit(b.button);
    b.form.requestSubmit(b.button);
    assert.equal(b.children.get('p').textContent, 'Reject request ABC?');
    assert.equal(b.dialog.dataset.tone, 'danger');
    click(b.children.get('.action-confirmation__accept'));
    click(b.children.get('.action-confirmation__accept'));
    await settle();
    assert.equal(b.submissions.length, 1);
    assert.equal(b.submissions[0].submitter, b.button);
    assert.equal(b.submissions[0].submitter.value, 'rejected');
});

test('Escape cancels and a later submit asks again', async () => {
    const b = browser();
    b.form.requestSubmit(b.button);
    b.dialog.dispatchEvent(new Event('cancel', { cancelable: true }));
    await settle();
    assert.equal(b.submissions.length, 0);
    b.form.requestSubmit(b.button);
    assert.equal(b.dialog.open, true);
});

test('confirmation never bypasses native validation or leaves a one-time approval behind', async () => {
    const b = browser();
    b.form.requestSubmit(b.button);
    b.form.valid = false;
    click(b.children.get('.action-confirmation__accept'));
    await settle();
    assert.equal(b.submissions.length, 0);
    b.form.valid = true;
    b.form.requestSubmit(b.button);
    assert.equal(b.dialog.open, true);
    assert.equal(b.submissions.length, 0);
});

test('a detached form is not submitted after accepting', async () => {
    const b = browser();
    b.form.requestSubmit(b.button);
    b.form.isConnected = false;
    click(b.children.get('.action-confirmation__accept'));
    await settle();
    assert.equal(b.submissions.length, 0);
});

test('ordinary forms pass through without a confirmation', () => {
    const b = browser();
    delete b.form.dataset.confirm;
    b.form.requestSubmit(b.button);
    assert.equal(b.dialog.open, false);
    assert.equal(b.submissions.length, 1);
});

test('simultaneous decisions cannot replace the active dialog', async () => {
    const b = browser();
    const first = b.window.htcConfirm({ message: '<img src=x onerror=alert(1)>' });
    assert.equal(await b.window.htcConfirm({ message: 'Another decision' }), false);
    assert.equal(b.children.get('p').textContent, '<img src=x onerror=alert(1)>');
    click(b.children.get('.action-confirmation__cancel'));
    assert.equal(await first, false);
});

test('initialization is idempotent', () => {
    const b = browser();
    const original = b.window.htcConfirm;
    vm.runInContext('initializeConfirmations()', b.context);
    assert.equal(b.window.htcConfirm, original);
});
