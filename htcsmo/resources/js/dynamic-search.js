const formSelector = 'form[data-dynamic-search]';
const workspaceSelector = '[data-filter-workspace]';
const linkSelector = 'a[data-filter-link], [data-filter-links] a, [data-filter-pagination] a';
const exportKeys = ['export', 'print', 'download_pdf'];

export function filterUrl(form, base, FormDataClass = FormData) {
    const url = new URL(form.action || base, base);
    url.search = '';
    url.hash = '';
    new FormDataClass(form).forEach((value, key) => {
        if (key === 'page' || key.endsWith('_page') || exportKeys.includes(key)) return;
        const normalized = String(value).trim();
        if (normalized !== '') url.searchParams.append(key, normalized);
    });
    return url;
}

export function dateRangeError(from, to, required = false) {
    if (!from && !to && !required) return '';
    if (!from || !to) return 'Choose both a start date and an end date, or clear both dates.';
    const validDate = value => /^\d{4}-\d{2}-\d{2}$/.test(value)
        && !Number.isNaN(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
    if (!validDate(from) || !validDate(to)) return 'Enter valid start and end dates.';
    return to < from ? 'The end date must be on or after the start date.' : '';
}

// Reveal the first results without replacing a typing user's input or caret.
export function revealResults(target, {typing = false, window: browser = window} = {}) {
    if (!target) return;
    const rect = target.getBoundingClientRect();
    if (rect.top < 90 || rect.top > browser.innerHeight - 120) {
        const behavior = browser.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'auto';
        target.scrollIntoView({block: 'start', behavior});
    }
    if (!typing) target.focus({preventScroll: true});
}

export function initializeDynamicSearch(browser = window, doc = document) {
    if (browser.htcFilters) return browser.htcFilters;
    const states = new WeakMap();
    const stateFor = root => {
        if (!states.has(root)) states.set(root, {sequence: 0, timer: null, controller: null});
        return states.get(root);
    };
    const safeForm = form => form?.matches(formSelector) && form.method.toLowerCase() === 'get';
    const status = (root, message, state = '', fallback = null) => {
        let region = root.querySelector('[data-filter-status]');
        if (!region) {
            region = doc.createElement('p');
            region.dataset.filterStatus = '';
            region.id = `filter-status-${root.dataset.filterWorkspace}`;
            region.setAttribute('role', 'status');
            region.setAttribute('aria-live', 'polite');
            region.setAttribute('aria-atomic', 'true');
            root.prepend(region);
        }
        root.dataset.filterState = state;
        region.textContent = message;
        if (fallback) {
            const link = doc.createElement('a');
            link.href = fallback;
            link.textContent = 'Open results with a full page load';
            region.append(link);
        }
    };
    const cancel = root => {
        const state = stateFor(root);
        browser.clearTimeout(state.timer);
        state.controller?.abort();
        state.timer = null;
        state.controller = null;
        state.sequence++;
        root.querySelectorAll('[aria-busy="true"]').forEach(node => node.removeAttribute('aria-busy'));
        return state;
    };
    const customDates = form => {
        const period = form.elements.namedItem('period');
        if (!period) return;
        form.querySelectorAll('[data-custom-date-field]').forEach(field => {
            const input = field.querySelector('input');
            field.hidden = period.value !== 'custom';
            input.disabled = field.hidden;
            input.required = !field.hidden;
        });
    };
    const validate = (form, root) => {
        customDates(form);
        const from = form.elements.namedItem('date_from');
        const to = form.elements.namedItem('date_to');
        if (from && to && !from.disabled && !to.disabled) {
            const error = dateRangeError(from.value, to.value, from.required || to.required);
            let feedback = form.querySelector('[data-filter-date-error]');
            if (!feedback && error) {
                feedback = doc.createElement('small');
                feedback.dataset.filterDateError = '';
                feedback.id = `filter-date-error-${form.id}`;
                (to.parentElement || form).append(feedback);
            }
            if (feedback) feedback.textContent = error;
            [from, to].forEach(input => {
                input.setCustomValidity(error);
                error ? input.setAttribute('aria-invalid', 'true') : input.removeAttribute('aria-invalid');
                if (feedback) input.setAttribute('aria-describedby', feedback.id);
            });
            if (error) {
                status(root, `${error} Results have not been updated.`, 'invalid');
                return false;
            }
        } else {
            [from, to].filter(Boolean).forEach(input => {
                input.setCustomValidity('');
                input.removeAttribute('aria-invalid');
            });
            const feedback = form.querySelector('[data-filter-date-error]');
            if (feedback) feedback.textContent = '';
        }
        if (!form.checkValidity()) {
            status(root, 'Check the filter values. Results have not been updated.', 'invalid');
            return false;
        }
        return true;
    };
    const targetFor = (root, selector) => root.querySelector(selector || '[data-filter-results]');
    const anchored = (url, target) => {
        const destination = new URL(url, browser.location.href);
        if (target?.id) destination.hash = target.id;
        return destination;
    };
    const syncForms = (root, nextRoot) => {
        root.querySelectorAll(formSelector).forEach(form => {
            const source = nextRoot.querySelector(`#${form.id}`);
            if (!source) return;
            form.querySelectorAll('input[type="hidden"]').forEach(input => input.remove());
            source.querySelectorAll('input[type="hidden"]').forEach(input => form.append(input.cloneNode(true)));
            [...form.elements].filter(input => input.name && input.type !== 'hidden').forEach(input => {
                input.setCustomValidity?.('');
                input.removeAttribute('aria-invalid');
                const matches = [...source.elements].filter(candidate => candidate.name === input.name);
                if (['radio', 'checkbox'].includes(input.type)) {
                    input.checked = matches.some(candidate => candidate.value === input.value && candidate.checked);
                } else if (matches[0] && input.value !== matches[0].value) input.value = matches[0].value;
            });
            customDates(form);
            form.querySelectorAll('[data-filter-date-error]').forEach(feedback => feedback.textContent = '');
        });
    };
    const refresh = async (root, destination, options = {}) => {
        const url = new URL(destination, browser.location.href);
        if (url.origin !== browser.location.origin || exportKeys.some(key => url.searchParams.has(key))) return;
        if (options.background && (stateFor(root).controller || stateFor(root).timer || ['invalid', 'error'].includes(root.dataset.filterState))) return;
        const target = targetFor(root, options.target);
        const fallback = anchored(url, target);
        const state = cancel(root);
        const sequence = state.sequence;
        if (options.navigate || !target) {
            browser.location.assign(fallback.href);
            return;
        }
        const controller = new AbortController();
        state.controller = controller;
        const timeout = browser.setTimeout(() => controller.abort('timeout'), 20000);
        target.setAttribute('aria-busy', 'true');
        if (!options.background) status(root, 'Updating results...', 'loading');
        try {
            const response = await browser.fetch(url, {
                method: 'GET', credentials: 'same-origin', signal: controller.signal,
                headers: {Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest', 'X-Filter-Request': 'true', 'X-Background-Request': options.background ? 'true' : 'false'},
            });
            if (!response.ok) {
                let message = `The server returned HTTP ${response.status}.`;
                if (response.status === 422) {
                    const data = await response.json();
                    message = Object.values(data.errors || {}).flat().join(' ') || data.message || message;
                }
                throw new Error(message);
            }
            if (response.redirected) throw new Error('The server redirected the filter request.');
            const html = await response.text();
            if (sequence !== state.sequence || browser.htcSession?.expired) return;
            const nextDoc = new browser.DOMParser().parseFromString(html, 'text/html');
            const nextRoot = nextDoc.querySelector(`[data-filter-workspace="${root.dataset.filterWorkspace}"]`);
            if (!nextRoot) throw new Error('The response did not include the expected results.');
            const changes = [...root.querySelectorAll('[data-filter-region]')].map(current => {
                const next = nextRoot.querySelector(`[data-filter-region="${current.dataset.filterRegion}"]`);
                if (!next) throw new Error('The response was incomplete.');
                next.querySelectorAll('script').forEach(script => script.remove());
                return [current, next];
            });
            if (!changes.length) throw new Error('This page requires a full page load.');
            if (root.querySelector('.modal.show, details[open] form')) throw new Error('Finish or close the open action before filtering.');
            const focused = doc.activeElement;
            const replace = () => {
                changes.forEach(([current, next]) => {
                    browser.Alpine?.destroyTree(current);
                    current.replaceWith(next);
                    browser.Alpine?.initTree(next);
                });
                syncForms(root, nextRoot);
            };
            if (browser.Alpine?.mutateDom) browser.Alpine.mutateDom(replace);
            else replace();
            const historyMode = options.history || 'push';
            if (historyMode !== 'none' && url.href !== browser.location.href) {
                browser.history[historyMode === 'replace' ? 'replaceState' : 'pushState']({htcFilters: true}, '', url.href);
            }
            const updatedTarget = targetFor(root, options.target);
            const count = updatedTarget?.dataset.resultCount;
            status(root, count === undefined ? 'Results updated.' : `${count} ${updatedTarget.dataset.resultLabel || 'matching results'}.`, 'ready');
            browser.lucide?.createIcons();
            root.dispatchEvent(new browser.CustomEvent('htc:filters-applied', {detail: {url: url.href}}));
            doc.dispatchEvent(new browser.CustomEvent('htc:dynamic-search-updated', {detail: {url: url.href}}));
            if (options.orient !== false) {
                const typing = options.typing || Boolean(doc.activeElement?.matches('input, select, textarea'));
                revealResults(updatedTarget, {typing, window: browser});
                if (!focused?.isConnected && focused?.name && typing) root.querySelector(`[name="${focused.name}"]`)?.focus({preventScroll: true});
            }
        } catch (error) {
            if (sequence !== state.sequence || browser.htcSession?.expired || error.name === 'SessionExpiredError') return;
            if (error.name === 'AbortError' && controller.signal.reason !== 'timeout') return;
            const message = controller.signal.reason === 'timeout' ? 'The request timed out. Try again.' : (error.message || 'A network error occurred.');
            status(root, `Results were not updated. Previous results are still shown. ${message}`, 'error', fallback.href);
        } finally {
            browser.clearTimeout(timeout);
            if (sequence === state.sequence) {
                state.controller = null;
                targetFor(root, options.target)?.removeAttribute('aria-busy');
            }
        }
    };
    const run = (form, input, typing = false) => {
        const root = form.closest(workspaceSelector);
        if (!root || !validate(form, root)) return;
        refresh(root, filterUrl(form, browser.location.href, browser.FormData), {
            target: form.dataset.filterTarget, typing, navigate: input?.hasAttribute('data-filter-navigate'),
        });
    };
    doc.addEventListener('input', event => {
        const input = event.target;
        if (!safeForm(input.form) || !['search', 'date'].includes(input.type)) return;
        const root = input.form.closest(workspaceSelector);
        if (!root) return;
        const state = cancel(root);
        state.timer = null;
        if (input.type === 'search' && !event.isComposing) {
            state.timer = browser.setTimeout(() => {state.timer = null; run(input.form, input, true);}, 350);
        }
    });
    doc.addEventListener('compositionend', event => {
        if (event.target.type === 'search') event.target.dispatchEvent(new browser.Event('input', {bubbles: true}));
    });
    doc.addEventListener('change', event => {
        const input = event.target;
        if (!safeForm(input.form) || !input.matches('select, input[type="date"], input[type="radio"], input[type="checkbox"]')) return;
        const root = input.form.closest(workspaceSelector);
        if (!root) return;
        cancel(root);
        run(input.form, input);
    });
    // Capture before the global form loader. Exports retain deliberate native submission.
    doc.addEventListener('submit', event => {
        const form = event.target;
        if (!safeForm(form)) return;
        const root = form.closest(workspaceSelector);
        if (!root) return;
        if (event.submitter?.hasAttribute('data-download') || exportKeys.includes(event.submitter?.name)) {
            cancel(root);
            if (!validate(form, root)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        cancel(root);
        run(form, null, doc.activeElement?.type === 'search');
    }, true);
    doc.addEventListener('click', event => {
        const link = event.target.closest(linkSelector);
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        const root = link.closest(workspaceSelector);
        if (!root) return;
        const url = new URL(link.href, browser.location.href);
        if (url.origin !== browser.location.origin || url.pathname !== browser.location.pathname || exportKeys.some(key => url.searchParams.has(key))) return;
        event.preventDefault();
        refresh(root, url, {target: link.dataset.filterTarget});
    }, true);
    browser.addEventListener('popstate', () => {
        const root = doc.querySelector(workspaceSelector);
        if (root) refresh(root, new URL(browser.location.href), {history: 'none'});
    });
    doc.addEventListener('htc:session-expired', () => doc.querySelectorAll(workspaceSelector).forEach(cancel));
    doc.querySelectorAll(formSelector).forEach(form => {if (safeForm(form)) customDates(form);});
    doc.querySelectorAll(workspaceSelector).forEach(root => status(root, ''));
    browser.htcFilters = {refresh, validate, cancel};
    return browser.htcFilters;
}
