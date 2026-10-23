import assert from 'node:assert/strict';
import test from 'node:test';
import {dateRangeError, filterUrl, initializeDynamicSearch, revealResults} from '../../resources/js/dynamic-search.js';

const flush = async () => {for (let n = 0; n < 12; n++) await Promise.resolve();};

function fixture() {
    const listeners = new Map(); const timers = new Map(); const calls = []; const pushes = []; const navigations = [];
    let timerId = 0; let activeRoot; let parsedRoot;
    const location = {href: 'https://app.test/requests?status=pending&page=3', origin: 'https://app.test', pathname: '/requests', assign: url => navigations.push(url)};
    const doc = {
        activeElement: null,
        addEventListener: (name, fn) => listeners.set(name, fn),
        querySelector: () => activeRoot,
        querySelectorAll: selector => selector === 'form[data-dynamic-search]' ? [activeRoot.form] : [activeRoot],
        createElement: tag => new Element(tag),
        dispatchEvent() {},
    };
    class Element {
        attributes = new Map(); dataset = {}; children = []; isConnected = true; hidden = false; disabled = false; required = false;
        constructor(tag = 'div', properties = {}) {this.tag = tag; Object.assign(this, properties);}
        setAttribute(name, value) {this.attributes.set(name, value);}
        removeAttribute(name) {this.attributes.delete(name);}
        hasAttribute(name) {return this.attributes.has(name);}
        append(child) {this.children.push(child);}
        prepend(child) {this.children.unshift(child);}
        remove() {this.isConnected = false;}
        setCustomValidity(value) {this.validationMessage = value;}
        cloneNode() {return new Element(this.tag, {name: this.name, type: this.type, value: this.value});}
        matches(selector) {
            if (selector === 'form[data-dynamic-search]') return this.tag === 'form';
            if (selector === 'input, select, textarea') return ['input', 'select', 'textarea'].includes(this.tag);
            return this.tag === 'select' || ['date', 'radio', 'checkbox'].includes(this.type);
        }
        closest(selector) {return selector === '[data-filter-workspace]' ? this.root : this.link || null;}
        focus(options) {this.focusOptions = options; doc.activeElement = this;}
        scrollIntoView(options) {this.scrollOptions = options;}
        getBoundingClientRect() {return {top: 1500, bottom: 1800};}
        querySelector() {return null;}
        querySelectorAll() {return [];}
        replaceWith(next) {
            this.isConnected = false;
            next.root = this.root;
            this.root.regions.set(this.dataset.filterRegion, next);
        }
    }
    function makeRoot(url = location.href, count = 3) {
        const query = new URL(url).searchParams;
        const root = new Element('div', {dataset: {filterWorkspace: 'requests'}, regions: new Map()});
        const inputs = [['search', 'search', query.get('search') || ''], ['status', 'select', query.get('status') || ''], ['date_from', 'date', query.get('date_from') || ''], ['date_to', 'date', query.get('date_to') || ''], ['order', 'select', query.get('order') || 'desc'], ['category', 'hidden', query.get('category') || '']]
            .map(([name, type, value]) => new Element(type === 'select' ? 'select' : 'input', {name, type, value, root}));
        inputs.namedItem = name => inputs.find(input => input.name === name) || null;
        const form = new Element('form', {id: 'filters', action: 'https://app.test/requests#results', method: 'get', elements: inputs, root});
        inputs.forEach(input => input.form = form);
        form.checkValidity = () => inputs.every(input => input.disabled || !input.validationMessage);
        form.querySelectorAll = selector => selector === 'input[type="hidden"]' ? [] : [];
        const result = new Element('section', {id: 'results', dataset: {filterRegion: 'results', resultCount: String(count)}, root});
        const summary = new Element('div', {dataset: {filterRegion: 'summary', resultCount: String(count)}, root});
        root.regions.set('results', result); root.regions.set('summary', summary); root.form = form;
        root.querySelector = selector => {
            if (selector === '[data-filter-status]') return root.children.find(child => child.dataset.filterStatus !== undefined) || null;
            if (selector === '.modal.show, details[open] form') return root.actionOpen ? {} : null;
            if (selector === '#filters') return form;
            if (selector.startsWith('[data-filter-region=')) return root.regions.get(selector.match(/="(.*?)"/)[1]) || null;
            if (selector.startsWith('[name=')) return inputs.namedItem(selector.match(/="(.*?)"/)[1]);
            return root.regions.get('results');
        };
        root.querySelectorAll = selector => {
            if (selector === 'form[data-dynamic-search]') return [form];
            if (selector === '[data-filter-region]') return [...root.regions.values()];
            if (selector === '[aria-busy="true"]') return [...root.regions.values()].filter(region => region.attributes.has('aria-busy'));
            return [];
        };
        root.dispatchEvent = event => root.lastEvent = event;
        return root;
    }
    activeRoot = makeRoot();
    doc.activeElement = activeRoot.form.elements.namedItem('search');
    const browser = {
        location, innerHeight: 800, matchMedia: () => ({matches: false}),
        history: {pushState(state, title, url) {pushes.push(url); location.href = url;}, replaceState(state, title, url) {location.href = url;}},
        addEventListener: (name, fn) => listeners.set(name, fn),
        clearTimeout: id => timers.delete(id), setTimeout(fn, delay) {const id = ++timerId; timers.set(id, {fn, delay}); return id;},
        CustomEvent: class {constructor(type, options) {this.type = type; this.detail = options.detail;}},
        FormData: class {constructor(form) {this.form = form;} forEach(fn) {this.form.elements.filter(input => !input.disabled).forEach(input => fn(input.value, input.name));}},
        DOMParser: class {parseFromString(url) {parsedRoot = makeRoot(url, new URL(url).searchParams.get('search') === 'none' ? 0 : 3); return {querySelector: () => parsedRoot};}},
        fetch: async (url, options) => {calls.push({url: String(url), options}); return {ok: true, text: async () => String(url)};},
    };
    const api = initializeDynamicSearch(browser, doc);
    return {browser, doc, root: activeRoot, calls, pushes, navigations, api,
        get result() {return activeRoot.regions.get('results');},
        get status() {return activeRoot.querySelector('[data-filter-status]');},
        input: name => activeRoot.form.elements.namedItem(name),
        event(name, target, extra = {}) {
            const event = {target, button: 0, preventDefault() {this.defaultPrevented = true;}, stopImmediatePropagation() {this.stopped = true;}, ...extra};
            listeners.get(name)(event); return event;
        },
        tick(delay = 350) {for (const [id, timer] of timers) if (timer.delay <= delay) {timers.delete(id); timer.fn();}},
        link(url) {const link = new Element('a', {href: new URL(url, location.href).href, root: activeRoot}); link.link = link; return link;},
    };
}

test('search debounces to one GET, retains filters, resets pagination and never steals typing focus', async () => {
    const b = fixture(); const input = b.input('search');
    for (const value of ['p', 'pa', 'paper']) {input.value = value; b.event('input', input);}
    b.tick(349); assert.equal(b.calls.length, 0);
    b.tick(); await flush();
    assert.equal(b.calls.length, 1);
    const url = new URL(b.calls[0].url);
    assert.equal(url.searchParams.get('search'), 'paper'); assert.equal(url.searchParams.get('status'), 'pending');
    assert.equal(url.searchParams.has('page'), false); assert.equal(b.calls[0].options.method, 'GET');
    assert.equal(b.doc.activeElement, input); assert.equal(b.result.focusOptions, undefined);
    assert.deepEqual(b.result.scrollOptions, {block: 'start', behavior: 'auto'});
    assert.equal(b.status.textContent, '3 matching results.');
});

test('select and sort changes update immediately and reflect the query in URL history', async () => {
    const b = fixture(); b.input('order').value = 'asc'; b.event('change', b.input('order')); await flush();
    assert.equal(new URL(b.pushes.at(-1)).searchParams.get('order'), 'asc');
    assert.equal(b.result.attributes.has('aria-busy'), false);
});

test('paired date ranges reject incomplete, invalid calendar, and reversed dates', () => {
    assert.equal(dateRangeError('', ''), '');
    for (const pair of [['2026-10-01', ''], ['', '2026-10-01'], ['2026-02-30', '2026-03-01'], ['2026-10-22', '2026-10-01']]) assert.notEqual(dateRangeError(...pair), '');
    assert.equal(dateRangeError('2026-10-01', '2026-10-01'), '');
    assert.notEqual(dateRangeError('', '', true), '');
});

test('dates wait for a complete valid range and explain why results have not changed', async () => {
    const b = fixture(); b.input('date_from').value = '2026-10-22'; b.event('change', b.input('date_from'));
    assert.equal(b.calls.length, 0); assert.equal(b.root.dataset.filterState, 'invalid');
    assert.match(b.status.textContent, /Choose both/); assert.equal(b.input('date_to').attributes.get('aria-invalid'), 'true');
    b.input('date_to').value = '2026-10-21'; b.event('change', b.input('date_to')); assert.equal(b.calls.length, 0);
    b.input('date_to').value = '2026-10-23'; b.event('change', b.input('date_to')); await flush();
    assert.equal(b.calls.length, 1); assert.equal(b.root.dataset.filterState, 'ready');
});

test('pagination retains page/query, replaces counts, focuses results, and supports Back/Forward without adding entries', async () => {
    const b = fixture(); b.doc.activeElement = null;
    const click = b.event('click', b.link('/requests?search=paper&order=asc&page=2#results')); await flush();
    assert.equal(click.defaultPrevented, true); assert.equal(new URL(b.calls[0].url).searchParams.get('page'), '2');
    assert.equal(b.input('search').value, 'paper'); assert.equal(b.input('order').value, 'asc');
    assert.equal(b.doc.activeElement, b.result);
    b.browser.location.href = 'https://app.test/requests?search=old&status=pending';
    b.event('popstate', null); await flush();
    assert.equal(b.input('search').value, 'old'); assert.equal(b.input('status').value, 'pending');
    assert.equal(b.pushes.length, 1);
});

test('clear-all and single-filter links synchronize inputs and preserve the parameters supplied by the link', async () => {
    const b = fixture();
    b.event('click', b.link('/requests?search=paper')); await flush();
    assert.equal(b.input('search').value, 'paper'); assert.equal(b.input('status').value, '');
    b.event('click', b.link('/requests')); await flush();
    assert.equal(b.input('search').value, ''); assert.equal(b.input('status').value, '');
});

test('empty results replace the old results and announce zero matches', async () => {
    const b = fixture(); const old = b.result;
    b.input('search').value = 'none'; b.event('input', b.input('search')); b.tick(); await flush();
    assert.notEqual(b.result, old); assert.equal(b.status.textContent, '0 matching results.');
});

for (const kind of ['network', 'validation', 'redirect', 'incomplete']) {
    test(`${kind} errors leave old results clearly marked and offer an anchored full-page retry`, async () => {
        const b = fixture(); const original = b.result;
        b.browser.fetch = async () => {
            if (kind === 'network') throw new TypeError('Offline');
            if (kind === 'validation') return {ok: false, status: 422, json: async () => ({errors: {date_to: ['Invalid reporting range.']}})};
            if (kind === 'redirect') return {ok: true, redirected: true};
            return {ok: true, text: async () => 'incomplete'};
        };
        if (kind === 'incomplete') b.browser.DOMParser = class {parseFromString() {return {querySelector: () => null};}};
        await b.api.refresh(b.root, 'https://app.test/requests?search=paper');
        assert.equal(b.result, original); assert.equal(b.root.dataset.filterState, 'error');
        assert.match(b.status.textContent, /Previous results are still shown/);
        assert.equal(b.status.children.at(-1).href, 'https://app.test/requests?search=paper#results');
        assert.equal(b.pushes.length, 0); assert.equal(b.navigations.length, 0);
    });
}

test('a newer keystroke invalidates even a response whose body is still being read', async () => {
    const b = fixture(); let finishBody;
    b.browser.fetch = async () => ({ok: true, text: () => new Promise(resolve => finishBody = resolve)});
    const old = b.result; const request = b.api.refresh(b.root, 'https://app.test/requests?search=old'); await flush();
    b.input('search').value = 'new'; b.event('input', b.input('search'));
    finishBody('https://app.test/requests?search=old'); await request;
    assert.equal(b.result, old); assert.equal(b.input('search').value, 'new'); assert.equal(b.pushes.length, 0);
});

test('an invalid date change cancels an in-flight response without overwriting the edited range', async () => {
    const b = fixture(); let finishBody;
    b.browser.fetch = async () => ({ok: true, text: () => new Promise(resolve => finishBody = resolve)});
    const request = b.api.refresh(b.root, 'https://app.test/requests?status=pending'); await flush();
    b.input('date_from').value = '2026-10-22'; b.event('input', b.input('date_from')); b.event('change', b.input('date_from'));
    finishBody('https://app.test/requests?status=pending'); await request;
    assert.equal(b.input('date_from').value, '2026-10-22'); assert.equal(b.root.dataset.filterState, 'invalid');
});

test('report type exceptions use a complete GET fallback anchored to results', async () => {
    const b = fixture(); const radio = b.input('status'); radio.type = 'radio'; radio.setAttribute('data-filter-navigate', '');
    b.event('change', radio); await flush();
    assert.equal(b.calls.length, 0); assert.equal(new URL(b.navigations[0]).hash, '#results');
});

test('custom periods enable required paired dates and preset periods disable them again', async () => {
    const b = fixture(); const period = b.input('status'); period.name = 'period';
    const fields = [b.input('date_from'), b.input('date_to')].map(input => ({hidden: true, querySelector: () => input}));
    b.root.form.querySelectorAll = selector => selector === '[data-custom-date-field]' ? fields : [];
    period.value = 'custom'; b.event('change', period);
    assert.equal(fields[0].hidden, false); assert.equal(b.input('date_from').required, true);
    assert.equal(b.calls.length, 0); assert.match(b.status.textContent, /Choose both/);
    period.value = 'this_month'; b.event('change', period); await flush();
    assert.equal(b.calls.length, 1); assert.equal(b.input('date_from').disabled, true);
    assert.equal(b.input('date_to').attributes.has('aria-invalid'), false);
});

test('a timed-out request leaves old results and an explicit retry message', async () => {
    const b = fixture(); const old = b.result;
    b.browser.fetch = (url, options) => new Promise((resolve, reject) => {
        options.signal.addEventListener('abort', () => reject(options.signal.reason));
    });
    const request = b.api.refresh(b.root, 'https://app.test/requests?search=paper');
    b.tick(20000); await request;
    assert.equal(b.result, old); assert.equal(b.root.dataset.filterState, 'error');
    assert.match(b.status.textContent, /request timed out/);
});

test('writes and explicit exports never become automatic searches', () => {
    const b = fixture(); const form = b.root.form;
    form.method = 'post'; b.event('input', b.input('search')); b.tick();
    assert.equal(b.event('submit', form).defaultPrevented, undefined);
    form.method = 'get';
    assert.equal(b.event('submit', form, {submitter: {name: 'export', hasAttribute: () => true}}).defaultPrevented, undefined);
    assert.equal(b.calls.length, 0);
    b.event('click', b.link('/requests?export=csv')); assert.equal(b.calls.length, 0);
});

test('modified clicks and external destinations retain normal browser navigation', () => {
    const b = fixture();
    assert.equal(b.event('click', b.link('/requests?page=2'), {ctrlKey: true}).defaultPrevented, undefined);
    assert.equal(b.event('click', b.link('https://other.test/requests')).defaultPrevented, undefined);
    assert.equal(b.calls.length, 0);
});

test('session expiry never navigates or replaces work', async () => {
    const b = fixture(); const old = b.result;
    b.browser.fetch = async () => {throw Object.assign(new Error('Expired'), {name: 'SessionExpiredError'});};
    await b.api.refresh(b.root, 'https://app.test/requests');
    assert.equal(b.result, old); assert.equal(b.navigations.length, 0);
});

test('open action forms prevent result replacement', async () => {
    const b = fixture(); const old = b.result; b.root.actionOpen = true;
    await b.api.refresh(b.root, 'https://app.test/requests');
    assert.equal(b.result, old); assert.match(b.status.textContent, /close the open action/);
});

test('background notification refreshes neither scroll nor focus, and do not supersede a user request', async () => {
    const b = fixture(); const input = b.doc.activeElement;
    await b.api.refresh(b.root, 'https://app.test/requests', {background: true, orient: false, history: 'none'});
    assert.equal(b.doc.activeElement, input); assert.equal(b.result.scrollOptions, undefined); assert.equal(b.pushes.length, 0);
    b.input('search').value = 'paper'; b.event('input', b.input('search'));
    await b.api.refresh(b.root, 'https://app.test/requests', {background: true, orient: false});
    assert.equal(b.calls.length, 1);
});

test('result orientation respects reduced motion and avoids moving results already in view', () => {
    let scroll; let focused = 0;
    const target = {getBoundingClientRect: () => ({top: 200}), focus: () => focused++, scrollIntoView: options => scroll = options};
    const browser = {innerHeight: 800, matchMedia: () => ({matches: true})};
    revealResults(target, {window: browser}); assert.equal(scroll, undefined); assert.equal(focused, 1);
    target.getBoundingClientRect = () => ({top: 1200});
    revealResults(target, {window: browser, typing: true}); assert.equal(scroll.behavior, 'instant'); assert.equal(focused, 1);
});

test('URL serialization excludes export flags and all pagination parameters', () => {
    const Data = class {forEach(fn) {for (const [key, value] of Object.entries({search: ' paper ', status: '', page: 3, minor_page: 2, export: 'csv', print: 1, download_pdf: 1, order: 'asc'})) fn(value, key);}};
    assert.equal(filterUrl({action: '/requests#results'}, 'https://app.test/', Data).href, 'https://app.test/requests?search=paper&order=asc');
});
