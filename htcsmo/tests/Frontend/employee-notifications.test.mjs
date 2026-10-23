import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const template = readFileSync(new URL('../../resources/views/dashboard/partials/employee-notifications.blade.php', import.meta.url), 'utf8');
const source = template.slice(template.indexOf('<script>') + 8, template.lastIndexOf('</script>'));

function browser(wide = true) {
    class Element extends EventTarget {
        children = []; parent = null; open = false; attributes = new Map();
        get isConnected() { return this === body || Boolean(this.parent?.isConnected); }
        appendChild(child) { child.remove(); this.children.push(child); child.parent = this; }
        remove() { if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this); this.parent = null; }
        after(child) { child.remove(); this.parent.children.splice(this.parent.children.indexOf(this) + 1, 0, child); child.parent = this.parent; }
        setAttribute(name, value) { this.attributes.set(name, value); }
        querySelector() { return closeButton; }
        closest() { return this === launcher ? this : null; }
        focus() { document.activeElement = this; }
        showModal() { this.open = true; }
        close() { this.open = false; this.dispatchEvent(new Event('close')); }
    }
    const body = new Element(); const workspace = new Element(); body.appendChild(workspace);
    let anchor = new Element(); let sidebar = new Element(); workspace.appendChild(anchor); workspace.appendChild(sidebar);
    const launcher = new Element(); workspace.appendChild(launcher);
    const drawer = new Element(); const closeButton = new Element();
    const media = Object.assign(new EventTarget(), {matches: wide});
    const document = Object.assign(new EventTarget(), {
        body, activeElement: launcher, createElement: () => drawer,
        querySelector: selector => selector.includes('anchor') ? anchor : sidebar.parent === workspace ? sidebar : null,
    });
    const window = {matchMedia: () => media, htcSession: {expired: false}};
    vm.runInNewContext(source, {window, document});
    return {workspace, drawer, document, window, launcher, closeButton,
        get sidebar() { return sidebar; },
        resize(wide) { media.matches = wide; media.dispatchEvent(new Event('change')); },
        open() {
            const event = new Event('click', {cancelable: true});
            Object.defineProperty(event, 'target', {value: launcher});
            document.dispatchEvent(event);
            return event;
        },
        refresh() {
            anchor.remove(); if (sidebar.parent === workspace) sidebar.remove();
            anchor = new Element(); sidebar = new Element(); workspace.appendChild(anchor); workspace.appendChild(sidebar);
            document.dispatchEvent(new Event('htc:workspace-updated'));
        },
    };
}

test('desktop keeps one notification panel in its dashboard rail', () => {
    const b = browser();
    assert.equal(b.sidebar.parent, b.workspace);
    assert.equal(b.drawer.open, false);
});

test('phone opens the same notifications in a right drawer and restores launcher focus', () => {
    const b = browser(false);
    assert.equal(b.sidebar.parent, b.drawer);
    assert.equal(b.open().defaultPrevented, true);
    assert.equal(b.drawer.open, true);
    assert.equal(b.document.activeElement, b.closeButton);
    b.closeButton.dispatchEvent(new Event('click'));
    assert.equal(b.drawer.open, false);
    assert.equal(b.document.activeElement, b.launcher);
});

test('resizing restores the rail without duplicating notification forms', () => {
    const b = browser(false); const original = b.sidebar;
    b.open(); b.resize(true);
    assert.equal(b.drawer.open, false);
    assert.equal(b.sidebar, original);
    assert.equal(b.sidebar.parent, b.workspace);
    b.resize(false);
    assert.equal(b.drawer.children.filter(child => child === original).length, 1);
});

test('a workspace update replaces the previous drawer content instead of duplicating it', () => {
    const b = browser(false); const previous = b.sidebar;
    b.refresh();
    assert.equal(previous.isConnected, false);
    assert.equal(b.sidebar.parent, b.drawer);
    assert.equal(b.drawer.children.length, 1);
});

test('expiry dismisses the drawer and leaves focus available for recovery', () => {
    const b = browser(false); b.open();
    const recoveryFocus = {};
    b.document.activeElement = recoveryFocus;
    b.window.htcSession.expired = true;
    b.document.dispatchEvent(new Event('htc:session-expired'));
    assert.equal(b.drawer.open, false);
    assert.equal(b.document.activeElement, recoveryFocus);
});
