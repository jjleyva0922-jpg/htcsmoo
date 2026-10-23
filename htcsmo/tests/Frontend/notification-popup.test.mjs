import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";

const layout = readFileSync(
    new URL("../../resources/views/layouts/app.blade.php", import.meta.url),
    "utf8",
);
const centerSource = layout
    .slice(
        layout.indexOf("function notificationCenter()"),
        layout.indexOf("// Cart Engine"),
    )
    .replaceAll("{{ Auth::id() }}", "7")
    .replace("{{ (int) Auth::id() }}", "7")
    .replace("{{ (int) ($layoutLatestNotificationId ?? 0) }}", "42")
    .replace("@json(route('notifications'))", "'/notifications'");
const runtimeSource = layout
    .slice(
        layout.indexOf("// Keep every signed-in workspace"),
        layout.indexOf("function countUp("),
    )
    .replace("@json(route('api.runtime-state'))", "'/api/runtime-state'")
    .replaceAll("{{ Auth::id() }}", "7")
    .replace("{{ (int) Auth::id() }}", "7");
const RUNTIME_POLL_INTERVAL = 15_000;

function browser({
    cursor,
    bufferedState,
    storageDisabled = false,
    audioContextClass,
    sharedStorage,
    channelClass,
} = {}) {
    const values = sharedStorage || new Map();
    if (cursor !== undefined)
        values.set("htc-presented-notification-7", String(cursor));
    const storage = {
        getItem: (key) => {
            if (storageDisabled) throw new Error("Storage disabled");
            return values.get(key) ?? null;
        },
        setItem: (key, value) => {
            if (storageDisabled) throw new Error("Storage disabled");
            values.set(key, String(value));
        },
        removeItem: (key) => values.delete(key),
    };
    const timers = new Map();
    let nextTimer = 0;
    const badge = {};
    const region = {
        dataset: { liveRegion: "workspace" },
        innerHTML: "original content",
        querySelectorAll: () => [],
    };
    const elements = new Map();
    const document = Object.assign(new EventTarget(), {
        hidden: false,
        activeElement: null,
        body: { dataset: {} },
        documentElement: { getAttribute: () => null },
        getElementById: (id) => {
            if (id === "notificationUnreadCount") return badge;
            if (!elements.has(id)) elements.set(id, Object.assign(new EventTarget(), {hidden: true, textContent: ''}));
            return elements.get(id);
        },
        querySelector: () => null,
        querySelectorAll: (selector) =>
            selector === "[data-live-region]" ? [region] : [],
    });
    const window = Object.assign(new EventTarget(), {
        setTimeout(callback, delay) {
            const id = ++nextTimer;
            timers.set(id, { callback, delay });
            return id;
        },
        clearTimeout: (id) => timers.delete(id),
        location: {
            href: "http://localhost/dashboard",
            reload() {
                window.reloads++;
            },
        },
        reloads: 0,
        scrollX: 0,
        scrollY: 320,
        scrollTo(position) {
            this.lastScroll = position;
            this.scrollX = position.left;
            this.scrollY = position.top;
        },
        htcRuntimeState: bufferedState,
        AudioContext: audioContextClass,
    });
    const context = vm.createContext({
        window,
        document,
        localStorage: storage,
        sessionStorage: storage,
        CustomEvent,
        AbortController,
        Date,
        console,
        lucide: { createIcons() {} },
        DOMParser: class {
            parseFromString() {
                return {
                    querySelector: () => ({
                        innerHTML: "updated content",
                        querySelectorAll: () => [],
                    }),
                };
            }
        },
    });
    if (channelClass) {
        window.BroadcastChannel = channelClass;
        context.BroadcastChannel = channelClass;
    }
    window.lucide = context.lucide;
    const center = vm.runInContext(`(${centerSource})()`, context);
    center.$nextTick = (callback) => callback();
    center.init();
    const payload = (...ids) => ({
        account_id: 7,
        unread_count: ids.length,
        notifications: ids.map((id) => ({
            id,
            title: `Notice ${id}`,
            message: "Deadline reminder",
            type: "warning",
        })),
    });
    async function runTimer(delay) {
        const entry = [...timers].find(([, timer]) => timer.delay === delay);
        assert.ok(entry, `Expected a timer for ${delay}ms`);
        timers.delete(entry[0]);
        await entry[1].callback();
    }
    return {
        center,
        context,
        document,
        window,
        storage,
        payload,
        runTimer,
        timers,
        region,
    };
}

test("an unread reminder appears when the recipient first opens a page", () => {
    const { center, payload } = browser();
    center.consumeRuntimeState(payload(42));
    assert.equal(center.show, true);
    assert.equal(center.toasts[0].title, "Notice 42");
});

test("navigating before the next poll does not skip a new reminder", () => {
    const { center, payload } = browser({ cursor: 41 });
    center.consumeRuntimeState(payload(42));
    assert.equal(center.show, true);
    assert.equal(center.lastId, 42);
});

test("queued reminders remain eligible until their popup is displayed", () => {
    const { center, payload } = browser();
    center.consumeRuntimeState(payload(43, 44, 45, 46, 47));
    assert.equal(center.toasts.length, 3);
    assert.equal(center.toasts[0].title, "Notice 43");
    assert.equal(center.lastId, 45);
    assert.equal(center.queue[0].id, 46);
});

test("a hidden tab does not consume popups meant for a visible tab", () => {
    const { center, document, payload } = browser();
    document.hidden = true;
    center.consumeRuntimeState(payload(43));
    assert.equal(center.show, false);
    assert.ok(center.lastId < 43);
    document.hidden = false;
    center.consumeRuntimeState(payload(43));
    assert.equal(center.show, true);
});

test("duplicate polls do not enqueue the same reminder twice", () => {
    const { center, payload } = browser();
    center.consumeRuntimeState(payload(43, 44, 45, 46));
    center.consumeRuntimeState(payload(43, 44, 45, 46));
    assert.equal(center.toasts.length, 3);
    assert.equal(center.queue.length, 1);
    assert.equal(center.queue[0].id, 46);
});

test("the next queued reminder appears after dismissal", async () => {
    const { center, payload, runTimer } = browser();
    center.consumeRuntimeState(payload(43, 44, 45, 46));
    center.dismiss(44);
    assert.equal(center.toasts.find((toast) => toast.id === 43).visible, true);
    assert.equal(center.toasts.length, 3);
    await runTimer(300);
    assert.equal(center.show, true);
    assert.equal(center.toasts.length, 3);
    assert.equal(center.toasts[2].title, "Notice 46");
    assert.equal(center.lastId, 46);
    assert.equal(center.queue.length, 0);
});

test("a runtime response received before Alpine initializes is still displayed", () => {
    const { center } = browser({
        bufferedState: {
            account_id: 7,
            unread_count: 1,
            notifications: [{ id: 42, title: "Early reminder" }],
        },
    });
    assert.equal(center.show, true);
    assert.equal(center.toasts[0].title, "Early reminder");
});

test("popups still work with browser storage disabled and icons unavailable", () => {
    const { center, window, payload } = browser({ storageDisabled: true });
    delete window.lucide;
    center.consumeRuntimeState(payload(42));
    assert.equal(center.show, true);
    assert.equal(center.toasts[0].title, "Notice 42");
});

test("a reminder already displayed is not replayed after navigation", () => {
    const { center, payload } = browser({ cursor: 42 });
    center.consumeRuntimeState(payload(42));
    assert.equal(center.show, false);
});

test("notifications from another account are ignored", () => {
    const { center, payload } = browser();
    center.consumeRuntimeState({ ...payload(43), account_id: 8 });
    assert.equal(center.show, false);
});

test('runtime expiry stops polling and preserves the current workspace', async () => {
    const fixture = browser();
    const {window, document, context, region} = fixture;
    window.htcSession = {expired: false, show() {
        this.expired = true;
        document.dispatchEvent(new Event('htc:session-expired'));
    }};
    context.fetch = async () => {
        window.htcSession.show();
        throw Object.assign(new Error('Expired'), {name: 'SessionExpiredError'});
    };
    vm.runInContext(runtimeSource, context);
    await fixture.runTimer(0);
    assert.equal(window.reloads, 0);
    assert.equal(region.innerHTML, 'original content');
    assert.equal([...fixture.timers.values()].some(timer => timer.delay >= RUNTIME_POLL_INTERVAL), false);
});

test('a cross-tab account change offers recovery without reloading unsaved work', async () => {
    const fixture = browser();
    const {window, document, context, region} = fixture;
    window.htcSession = {expired: false, show() {
        this.expired = true;
        document.dispatchEvent(new Event('htc:session-expired'));
    }};
    context.fetch = async () => ({ok: true, json: async () => ({account_id: 8})});
    vm.runInContext(runtimeSource, context);
    await fixture.runTimer(0);
    assert.equal(window.htcSession.expired, true);
    assert.equal(window.reloads, 0);
    assert.equal(region.innerHTML, 'original content');
});

test("background updates are silent and do not wait for notification popups to close", async () => {
    const fixture = browser();
    const { center, context, window, payload, runTimer, region } = fixture;
    let version = "before";
    context.fetch = async (url) =>
        url.startsWith("/api/")
            ? {
                  ok: true,
                  json: async () => ({
                      ...payload(...(version === "before" ? [] : [43, 44, 45])),
                      version,
                  }),
              }
            : { ok: true, text: async () => "fragment" };
    vm.runInContext(runtimeSource, context);
    await runTimer(0);
    version = "after";
    await runTimer(RUNTIME_POLL_INTERVAL);
    assert.equal(center.show, true);
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, true);
    region.querySelectorAll = () => [];
    await runTimer(100);
    assert.equal(window.reloads, 0);
    assert.equal(region.innerHTML, "updated content");
    assert.equal(center.show, true);
    assert.equal(center.toasts.length, 3);
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, true);
    assert.equal(fixture.document.getElementById('liveUpdateStatus').textContent, 'Content updated.');
});

test("each popup expires independently and frees a slot for the queue", async () => {
    const { center, payload, runTimer } = browser();
    center.consumeRuntimeState(payload(43, 44, 45, 46));
    await runTimer(9000);
    assert.equal(center.toasts[0].visible, false);
    assert.equal(center.toasts[1].visible, true);
    await runTimer(300);
    assert.equal(center.toasts.length, 3);
    assert.equal(center.toasts[2].id, 46);
});

function audioFixture({ suspended = false } = {}) {
    const notes = [];
    const pendingResumes = [];
    let audioAllowed = !suspended;
    class AudioContext {
        state = suspended ? "suspended" : "running";
        currentTime = 0;
        destination = {};
        resume() {
            return new Promise((resolve) => {
                const resume = () => {
                    this.state = "running";
                    resolve();
                };
                if (audioAllowed) resume();
                else pendingResumes.push(resume);
            });
        }
        createOscillator() {
            const oscillator = {
                frequency: {},
                connect: (gain) => gain,
                start: () => notes.push(oscillator.frequency.value),
                stop() {},
            };
            return oscillator;
        }
        createGain() {
            return {
                gain: {
                    setValueAtTime() {},
                    exponentialRampToValueAtTime() {},
                },
                connect() {},
            };
        }
    }
    return {
        ...browser({ audioContextClass: AudioContext }),
        notes,
        allowAudio() {
            audioAllowed = true;
            pendingResumes.splice(0).forEach((resume) => resume());
        },
    };
}

test("new stacked popups play one audible chime per burst", async () => {
    const { center, payload, notes } = audioFixture();
    center.consumeRuntimeState(payload(43, 44, 45));
    await new Promise(setImmediate);
    assert.equal(notes.length, 2);
    assert.ok(notes.every((frequency) => frequency > 0));
});

test("the sound toggle mutes arrivals and remembers the preference", async () => {
    const { center, payload, storage, notes } = audioFixture();
    center.toggleSound();
    center.consumeRuntimeState(payload(43));
    await new Promise(setImmediate);
    assert.equal(notes.length, 0);
    assert.equal(storage.getItem("htc-notification-sound"), "off");
    center.toggleSound();
    await new Promise(setImmediate);
    assert.equal(notes.length, 2);
    assert.equal(storage.getItem("htc-notification-sound"), "on");
});

test("a browser-blocked chime plays once after the user interacts", async () => {
    const { center, payload, notes, document, allowAudio } = audioFixture({
        suspended: true,
    });
    center.consumeRuntimeState(payload(43, 44, 45));
    await new Promise(setImmediate);
    assert.equal(notes.length, 0);
    allowAudio();
    document.dispatchEvent(new Event("pointerdown"));
    await new Promise(setImmediate);
    assert.equal(notes.length, 2);
});

test("dismissing a popup before audio is unlocked cancels its delayed chime", async () => {
    const { center, payload, notes, document, allowAudio } = audioFixture({
        suspended: true,
    });
    center.consumeRuntimeState(payload(43));
    center.dismiss(43);
    allowAudio();
    document.dispatchEvent(new Event("pointerdown"));
    await new Promise(setImmediate);
    assert.equal(notes.length, 0);
});

function channels() {
    const instances = [];
    return class extends EventTarget {
        constructor(name) {
            super();
            this.name = name;
            instances.push(this);
        }
        postMessage(data) {
            for (const instance of instances) {
                if (instance !== this && instance.name === this.name) {
                    queueMicrotask(() =>
                        instance.dispatchEvent(
                            new MessageEvent("message", { data }),
                        ),
                    );
                }
            }
        }
    };
}

test("visible tabs share a single poller and take over when its tab is hidden", async () => {
    const sharedStorage = new Map();
    const channelClass = channels();
    const first = browser({ sharedStorage, channelClass });
    const second = browser({ sharedStorage, channelClass });
    let requests = 0;
    const fetch = async () => {
        requests++;
        return {
            ok: true,
            json: async () => ({
                account_id: 7,
                version: "stable",
                latest_id: 0,
                unread_count: 0,
                notifications: [],
            }),
        };
    };
    for (const fixture of [first, second]) {
        fixture.context.fetch = fetch;
        vm.runInContext(runtimeSource, fixture.context);
    }
    await first.runTimer(0);
    await second.runTimer(0);
    assert.equal(requests, 1);
    assert.equal(second.window.htcRuntimeState.version, "stable");
    first.document.hidden = true;
    first.document.dispatchEvent(new Event("visibilitychange"));
    await new Promise(setImmediate);
    await second.runTimer(0);
    assert.equal(requests, 2);
});

test("an unrelated inventory change does not refresh a request page", async () => {
    const fixture = browser();
    fixture.document.body.dataset.runtimeScope = "requests";
    let inventoryVersion = "inventory-before";
    fixture.context.fetch = async () => ({
        ok: true,
        json: async () => ({
            account_id: 7,
            version: inventoryVersion,
            versions: { requests: "requests-unchanged" },
            notifications: [],
        }),
    });
    vm.runInContext(runtimeSource, fixture.context);
    await fixture.runTimer(0);
    inventoryVersion = "inventory-after";
    await fixture.runTimer(RUNTIME_POLL_INTERVAL);
    assert.equal(
        [...fixture.timers.values()].some((timer) => timer.delay === 100),
        false,
    );
    assert.equal(fixture.region.innerHTML, "original content");
});

test("a dashboard clock change refreshes its content even when business data is unchanged", async () => {
    const fixture = browser();
    fixture.document.body.dataset.runtimeScope = "dashboard";
    fixture.document.body.dataset.runtimeVersion = "minute-before";
    fixture.region.querySelectorAll = () => [];
    let dashboardVersion = "minute-before";
    fixture.context.fetch = async (url) => {
        if (url.startsWith("/api/")) return {
            ok: true,
            json: async () => ({
                account_id: 7,
                version: "unchanged-business-data",
                versions: { dashboard: dashboardVersion },
                notifications: [],
            }),
        };
        return { ok: true, text: async () => "fragment" };
    };
    vm.runInContext(runtimeSource, fixture.context);
    await fixture.runTimer(0);
    assert.equal(fixture.region.innerHTML, "original content");
    dashboardVersion = "minute-after";
    await fixture.runTimer(RUNTIME_POLL_INTERVAL);
    await fixture.runTimer(100);
    assert.equal(fixture.region.innerHTML, "updated content");
    assert.equal(fixture.window.reloads, 0);
});

test("an edit begun during a background fetch prevents replacement of unsaved work", async () => {
    const fixture = browser();
    let version = "before";
    fixture.context.fetch = async (url) => {
        if (url.startsWith("/api/"))
            return {
                ok: true,
                json: async () => ({
                    account_id: 7,
                    version,
                    notifications: [],
                }),
            };
        fixture.document.activeElement = { tagName: "INPUT" };
        return { ok: true, text: async () => "fragment" };
    };
    vm.runInContext(runtimeSource, fixture.context);
    await fixture.runTimer(0);
    version = "after";
    await fixture.runTimer(RUNTIME_POLL_INTERVAL);
    await fixture.runTimer(100);
    assert.equal(fixture.region.innerHTML, "original content");
    assert.equal(fixture.window.reloads, 0);
});

function liveWorkspace() {
    const fixture = browser();
    fixture.document.body.dataset.runtimeVersion = 'before';
    let version = 'before';
    const requests = [];
    fixture.context.fetch = async url => {
        requests.push(url);
        if (url.startsWith('/api/')) return {
            ok: true,
            json: async () => ({account_id: 7, version, notifications: []}),
        };
        return {ok: true, text: async () => 'fragment'};
    };
    vm.runInContext(runtimeSource, fixture.context);
    return {...fixture, requests, change: () => { version = 'after'; }};
}

test('idle workspaces poll every fifteen seconds without fetching unchanged page content', async () => {
    const fixture = liveWorkspace();
    await fixture.runTimer(0);
    await fixture.runTimer(RUNTIME_POLL_INTERVAL);
    assert.equal(fixture.requests.length, 2);
    assert.ok(fixture.requests.every(url => url.startsWith('/api/')));
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, true);
});

test('updates defer silently during editing and resume without a reload after focus leaves', async () => {
    const fixture = liveWorkspace();
    await fixture.runTimer(0);
    fixture.document.activeElement = {tagName: 'TEXTAREA'};
    fixture.change();
    await fixture.runTimer(RUNTIME_POLL_INTERVAL);
    assert.equal(fixture.region.innerHTML, 'original content');
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, true);
    assert.equal([...fixture.timers.values()].some(timer => timer.delay === 100), false);
    fixture.document.activeElement = null;
    fixture.document.dispatchEvent(new Event('focusout'));
    await fixture.runTimer(150);
    await fixture.runTimer(100);
    assert.equal(fixture.region.innerHTML, 'updated content');
    assert.equal(fixture.window.reloads, 0);
});

test('background replacement preserves window and notification rail scrolling and link focus', async () => {
    const fixture = liveWorkspace();
    const rail = {id: '', scrollTop: 120, scrollLeft: 0, hasAttribute: () => true};
    fixture.document.querySelectorAll = selector => selector === '[data-live-region]' ? [fixture.region]
        : selector.startsWith('[data-live-region] [id]') ? [rail] : [];
    fixture.document.querySelector = selector => selector === '[data-employee-notifications]' ? rail : null;
    const restored = fixture.document.getElementById('record-link');
    restored.focus = options => { restored.focusOptions = options; };
    fixture.document.activeElement = {id: 'record-link', tagName: 'A', isConnected: false};
    fixture.document.addEventListener('htc:workspace-updated', () => { rail.scrollTop = 0; });
    await fixture.runTimer(0);
    fixture.change();
    await fixture.runTimer(RUNTIME_POLL_INTERVAL);
    await fixture.runTimer(100);
    assert.equal(fixture.window.lastScroll.top, 320);
    assert.equal(fixture.window.lastScroll.behavior, 'instant');
    assert.equal(rail.scrollTop, 120);
    assert.equal(restored.focusOptions.preventScroll, true);
});

test('a navigation or filter URL change during a request cannot replace the new results', async () => {
    const fixture = liveWorkspace();
    const fetch = fixture.context.fetch;
    fixture.context.fetch = async url => {
        if (!url.startsWith('/api/')) fixture.window.location.href = 'http://localhost/dashboard?status=pending';
        return fetch(url);
    };
    await fixture.runTimer(0);
    fixture.change();
    await fixture.runTimer(RUNTIME_POLL_INTERVAL);
    await fixture.runTimer(100);
    assert.equal(fixture.region.innerHTML, 'original content');
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, true);
});

test('failed content updates keep existing results and retry automatically without reloading', async () => {
    const fixture = liveWorkspace();
    const fetch = fixture.context.fetch;
    let failed = true;
    fixture.context.fetch = async url => failed && !url.startsWith('/api/')
        ? {ok: false, status: 503} : fetch(url);
    await fixture.runTimer(0);
    fixture.change();
    await fixture.runTimer(RUNTIME_POLL_INTERVAL);
    await fixture.runTimer(100);
    assert.equal(fixture.region.innerHTML, 'original content');
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, false);
    assert.match(fixture.document.getElementById('liveRefreshMessage').textContent, /Retrying automatically/);
    failed = false;
    fixture.document.getElementById('liveRefreshButton').dispatchEvent(new Event('click'));
    await new Promise(setImmediate);
    await fixture.runTimer(100);
    assert.equal(fixture.region.innerHTML, 'updated content');
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, true);
    assert.equal(fixture.window.reloads, 0);
});

test('connection failures back off, show recovery feedback, and clear it on reconnection', async () => {
    const fixture = browser();
    fixture.context.fetch = async () => { throw new Error('Offline'); };
    vm.runInContext(runtimeSource, fixture.context);
    await fixture.runTimer(0);
    await fixture.runTimer(30000);
    await fixture.runTimer(60000);
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, false);
    fixture.context.fetch = async () => ({ok: true, json: async () => ({account_id: 7, version: 'stable', notifications: []})});
    await fixture.runTimer(60000);
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, true);
    assert.equal(fixture.window.reloads, 0);
});

for (const [label, header, delay] of [
    ['seconds', '45', 45000],
    ['long cooldown', '120', 120000],
    ['HTTP date', 'Fri, 23 Oct 2026 02:01:00 GMT', 60000],
    ['missing header', null, 60000],
    ['invalid header', 'unavailable', 60000],
]) {
    test(`rate limiting respects Retry-After (${label}) and manual retries cannot bypass it`, async () => {
        const fixture = browser();
        let clock = Date.parse('2026-10-23T02:00:00Z');
        fixture.context.Date = class extends Date { static now() { return clock; } };
        let requests = 0;
        fixture.context.fetch = async () => {
            requests++;
            return requests === 1
                ? {ok: false, status: 429, headers: {get: () => header}}
                : {ok: true, json: async () => ({account_id: 7, version: 'stable', notifications: []})};
        };
        vm.runInContext(runtimeSource, fixture.context);
        await fixture.runTimer(0);
        assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, false);
        assert.match(fixture.document.getElementById('liveRefreshMessage').textContent, /paused briefly/);
        for (let click = 0; click < 3; click++) {
            fixture.document.getElementById('liveRefreshButton').dispatchEvent(new Event('click'));
        }
        await new Promise(setImmediate);
        assert.equal(requests, 1);
        assert.equal([...fixture.timers.values()].some(timer => timer.delay === delay), true);
        assert.equal(fixture.region.innerHTML, 'original content');
        clock += delay;
        await fixture.runTimer(delay);
        assert.equal(requests, 2);
        assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, true);
        assert.equal(fixture.window.reloads, 0);
    });
}

test('server cooldown survives navigation without BroadcastChannel', async () => {
    const sharedStorage = new Map();
    const first = browser({sharedStorage});
    const second = browser({sharedStorage});
    let requests = 0;
    let clock = Date.parse('2026-10-23T02:00:00Z');
    const clockDate = class extends Date { static now() { return clock; } };
    first.context.Date = second.context.Date = clockDate;
    first.context.fetch = async () => {
        requests++;
        return {ok: false, status: 429, headers: {get: () => '60'}};
    };
    second.context.fetch = async () => {
        requests++;
        return {ok: true, json: async () => ({account_id: 7, version: 'stable', notifications: []})};
    };
    vm.runInContext(runtimeSource, first.context);
    await first.runTimer(0);
    vm.runInContext(runtimeSource, second.context);
    await second.runTimer(0);
    assert.equal(requests, 1);
    clock += 60000;
    await second.runTimer(60000);
    assert.equal(requests, 2);
});

test('tabs share cooldowns even when browser storage is unavailable', async () => {
    const channelClass = channels();
    const first = browser({storageDisabled: true, channelClass});
    const second = browser({storageDisabled: true, channelClass});
    let requests = 0;
    let clock = Date.parse('2026-10-23T02:00:00Z');
    const clockDate = class extends Date { static now() { return clock; } };
    for (const fixture of [first, second]) {
        fixture.context.Date = clockDate;
        fixture.context.fetch = async () => {
            requests++;
            return requests === 1
                ? {ok: false, status: 429, headers: {get: () => '60'}}
                : {ok: true, json: async () => ({account_id: 7, version: 'stable', notifications: []})};
        };
        vm.runInContext(runtimeSource, fixture.context);
    }
    await first.runTimer(0);
    second.document.getElementById('liveRefreshButton').dispatchEvent(new Event('click'));
    await new Promise(setImmediate);
    assert.equal(requests, 1);
    clock += 60000;
    await second.runTimer(60000);
    assert.equal(requests, 2);
});

test('rate-limit cooldown still works without browser storage or cross-tab APIs', async () => {
    const fixture = browser({storageDisabled: true});
    let requests = 0;
    fixture.context.fetch = async () => {
        requests++;
        return {ok: false, status: 429};
    };
    vm.runInContext(runtimeSource, fixture.context);
    await fixture.runTimer(0);
    fixture.document.getElementById('liveRefreshButton').dispatchEvent(new Event('click'));
    await new Promise(setImmediate);
    assert.equal(requests, 1);
    assert.equal(fixture.window.reloads, 0);
});

test('pages without a safe live region do not show a routine refresh banner or reload', async () => {
    const fixture = liveWorkspace();
    fixture.document.querySelectorAll = () => [];
    await fixture.runTimer(0);
    fixture.change();
    await fixture.runTimer(RUNTIME_POLL_INTERVAL);
    await fixture.runTimer(100);
    assert.ok(fixture.requests.every(url => url.startsWith('/api/')));
    assert.equal(fixture.document.getElementById('liveRefreshNotice').hidden, true);
    assert.equal(fixture.window.reloads, 0);
});
