import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const layout = readFileSync(new URL('../../resources/views/layouts/app.blade.php', import.meta.url), 'utf8');
const start = layout.indexOf('        (() => {', layout.indexOf('<!-- Scripts -->'));
const source = layout.slice(start, layout.indexOf('        function notificationCenter()', start));

function navigation(scrollY = 640) {
    const element = () => {
        const classes = new Set();
        const properties = new Map();
        return {
            events: {},
            attributes: {},
            classList: {
                contains: name => classes.has(name),
                toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); },
            },
            style: {
                setProperty: (name, value) => properties.set(name, value),
                removeProperty: name => properties.delete(name),
                getPropertyValue: name => properties.get(name) ?? '',
            },
            setAttribute(name, value) { this.attributes[name] = value; },
            addEventListener(name, callback) { this.events[name] = callback; },
            focus() {},
        };
    };
    const elements = Object.fromEntries(['appSidebar', 'sidebarToggle', 'sidebarClose', 'sidebarBackdrop'].map(id => [id, element()]));
    const link = element();
    elements.appSidebar.querySelectorAll = () => [link];
    const body = element();
    const document = { body, events: {}, getElementById: id => elements[id], addEventListener(name, callback) { this.events[name] = callback; } };
    const media = { matches: true, addEventListener(name, callback) { this.change = callback; } };
    const scrolls = [];
    const window = { scrollY, matchMedia: () => media, scrollTo: (x, y) => scrolls.push([x, y]) };
    vm.runInNewContext(source, { document, window });
    return { ...elements, body, document, media, scrolls, window, link };
}

test('opening mobile navigation locks the page at its scroll position and closing restores it', () => {
    const nav = navigation();
    assert.deepEqual(nav.scrolls, []);
    nav.sidebarToggle.events.click();
    assert.equal(nav.body.classList.contains('sidebar-open'), true);
    assert.equal(nav.body.style.getPropertyValue('--sidebar-scroll-top'), '-640px');
    assert.equal(nav.sidebarToggle.attributes['aria-expanded'], 'true');
    nav.window.scrollY = 0;
    nav.sidebarClose.events.click();
    assert.equal(nav.body.classList.contains('sidebar-open'), false);
    assert.equal(nav.body.style.getPropertyValue('--sidebar-scroll-top'), '');
    assert.equal(nav.sidebarToggle.attributes['aria-expanded'], 'false');
    assert.deepEqual(nav.scrolls, [[0, 640]]);
});

test('every mobile navigation dismissal releases the scroll lock', () => {
    for (const dismiss of [
        nav => nav.sidebarBackdrop.events.click(),
        nav => nav.link.events.click(),
        nav => nav.document.events.keydown({ key: 'Escape' }),
        nav => { nav.media.matches = false; nav.media.change(); },
    ]) {
        const nav = navigation(1200);
        nav.sidebarToggle.events.click();
        dismiss(nav);
        assert.equal(nav.body.classList.contains('sidebar-open'), false);
        assert.deepEqual(nav.scrolls, [[0, 1200]]);
    }
});

test('desktop navigation stays visible and does not lock page scrolling', () => {
    const nav = navigation();
    nav.media.matches = false;
    nav.media.change();
    nav.sidebarToggle.events.click();
    assert.equal(nav.body.classList.contains('sidebar-open'), false);
    assert.equal(nav.appSidebar.attributes['aria-hidden'], 'false');
    assert.deepEqual(nav.scrolls, []);
});
