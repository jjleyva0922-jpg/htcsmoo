<style>
    .runtime-loader {
        position: fixed;
        top: 72px;
        right: 0;
        bottom: 0;
        left: var(--sidebar-width, 250px);
        z-index: 20000;
        display: block;
        padding: var(--space-24);
        overflow: hidden;
        cursor: wait;
        pointer-events: auto;
        touch-action: none;
        background: #f3f7f4;
    }

    .runtime-loader[hidden] { display: none !important; }
    /* Keep the sticky header attached to the viewport while navigation is locked. */
    html:has(body.runtime-locked) { overflow: hidden; }
    body.runtime-locked { overflow: clip; cursor: wait; }
    .runtime-content-skeleton { min-height: calc(100vh - 120px); padding: var(--space-24); border: 1px solid #e0e9e3; border-radius: 18px; background: #fff; box-shadow: 0 12px 34px rgba(17,62,38,.06); }
    .runtime-content-skeleton__heading { display: flex; justify-content: space-between; gap: var(--space-16); margin-bottom: var(--space-24); }
    .runtime-content-skeleton__cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--space-16); }
    .runtime-content-skeleton__card { min-height: 122px; padding: var(--space-16); border: 1px solid #e5ece7; border-radius: 14px; }
    .runtime-content-skeleton__table { margin-top: var(--space-16); padding: var(--space-16); border: 1px solid #e5ece7; border-radius: 14px; }
    .runtime-content-skeleton__row { display: grid; grid-template-columns: 1.2fr .8fr .65fr; gap: var(--space-16); padding: var(--space-12) 0; border-bottom: 1px solid #edf1ef; }
    .runtime-content-skeleton__row:last-child { border-bottom: 0; }
    .runtime-content-skeleton__block { position: relative; height: 12px; overflow: hidden; border-radius: 8px; background: #e5ece7; }
    .runtime-content-skeleton__block::after { content: ''; position: absolute; inset: 0; transform: translateX(-100%); background: linear-gradient(90deg, transparent, rgba(255,255,255,.72), transparent); animation: runtime-shimmer 1.25s ease-in-out infinite; }

    .runtime-spinner { width: 1rem; height: 1rem; flex: 0 0 1rem; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: runtime-spin .7s linear infinite; }
    button .runtime-spinner { width: .9rem; height: .9rem; flex-basis: .9rem; }
    form[data-runtime-submitting='true'] { cursor: wait; }
    .runtime-logout-modal { position: fixed; inset: 0; z-index: 20001; display: grid; place-items: center; padding: var(--space-16); background: rgba(10, 29, 18, .46); backdrop-filter: blur(2px); }
    .runtime-logout-modal[hidden] { display: none !important; }
    .runtime-logout-modal__dialog { display: flex; align-items: center; gap: var(--space-16); width: min(100%, 320px); padding: var(--space-16) var(--space-24); border: 1px solid rgba(255, 255, 255, .7); border-radius: 16px; background: #fff; color: #173b27; box-shadow: 0 22px 60px rgba(8, 34, 19, .24); }
    .runtime-logout-modal__spinner { width: 1.5rem; height: 1.5rem; flex: 0 0 1.5rem; border: 3px solid #dbe9df; border-top-color: #198754; border-radius: 50%; animation: runtime-spin .75s linear infinite; }
    .runtime-logout-modal__title { display: block; font-size: var(--text-body); font-weight: 700; line-height: var(--line-heading); }
    .runtime-logout-modal__copy { display: block; margin-top: .15rem; color: #66756c; font-size: var(--text-caption); line-height: var(--line-body); }
    .runtime-action-modal { width: min(360px, calc(100vw - 2rem)); margin: auto; padding: var(--space-24); border: 1px solid #dce4de; border-radius: 18px; color: #173b27; background: #fff; text-align: center; box-shadow: 0 22px 60px rgba(8,34,19,.24); }
    .runtime-action-modal::backdrop { background: rgba(10,29,18,.5); backdrop-filter: blur(2px); }
    .runtime-action-modal__spinner { display: block; width: 42px; height: 42px; margin: 0 auto var(--space-16); border: 4px solid #dbe9df; border-top-color: #198754; border-radius: 50%; animation: runtime-spin .75s linear infinite; }
    .runtime-action-modal h2 { margin: 0; font-size: var(--text-heading-md); font-weight: 800; line-height: var(--line-body); }
    .runtime-action-modal p { margin: var(--space-8) 0 0; color: #66756c; font-size: var(--text-body); line-height: var(--line-body); }

    @keyframes runtime-spin { to { transform: rotate(360deg); } }
    @keyframes runtime-shimmer { 100% { transform: translateX(100%); } }

    @media (max-width: 991.98px) {
        .runtime-loader { left: 0; }
        .runtime-content-skeleton__cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) {
        .runtime-loader { top: 64px; padding: var(--space-12); }
        .runtime-content-skeleton { padding: var(--space-16); border-radius: 14px; }
        .runtime-content-skeleton__cards { grid-template-columns: 1fr; }
        .runtime-content-skeleton__card:nth-child(3) { display: none; }
        .runtime-content-skeleton__row { grid-template-columns: 1fr .55fr; }
        .runtime-content-skeleton__row > :last-child { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .runtime-spinner { animation-duration: 2s; }
        .runtime-action-modal__spinner { animation: none; }
        .runtime-content-skeleton__block::after { animation: none; }
    }
</style>

<div id="runtimeLoader" class="runtime-loader" role="status" aria-live="polite" aria-atomic="true" tabindex="-1" hidden>
    <span id="runtimeLoaderMessage" class="visually-hidden">Loading&hellip;</span>
    <div class="runtime-content-skeleton" aria-hidden="true">
        <div class="runtime-content-skeleton__heading">
            <div class="d-grid gap-2" style="width: min(360px, 65%)">
                <div class="runtime-content-skeleton__block" style="width: 62%"></div>
                <div class="runtime-content-skeleton__block" style="width: 88%"></div>
            </div>
            <div class="runtime-content-skeleton__block" style="width: 110px; height: 38px"></div>
        </div>
        <div class="runtime-content-skeleton__cards">
            @for($card = 0; $card < 3; $card++)
                <div class="runtime-content-skeleton__card d-grid align-content-start gap-3">
                    <div class="runtime-content-skeleton__block" style="width: 38%"></div>
                    <div class="runtime-content-skeleton__block" style="width: 54%; height: 28px"></div>
                    <div class="runtime-content-skeleton__block" style="width: 88%"></div>
                </div>
            @endfor
        </div>
        <div class="runtime-content-skeleton__table">
            @for($row = 0; $row < 6; $row++)
                <div class="runtime-content-skeleton__row">
                    <div class="runtime-content-skeleton__block" style="width: 88%"></div>
                    <div class="runtime-content-skeleton__block" style="width: 68%"></div>
                    <div class="runtime-content-skeleton__block" style="width: 68%"></div>
                </div>
            @endfor
        </div>
    </div>
</div>

<div id="runtimeLogoutModal" class="runtime-logout-modal" role="dialog" aria-modal="true" aria-labelledby="runtimeLogoutTitle" tabindex="-1" hidden>
    <div class="runtime-logout-modal__dialog">
        <span class="runtime-logout-modal__spinner" aria-hidden="true"></span>
        <span>
            <span id="runtimeLogoutTitle" class="runtime-logout-modal__title">Logging out…</span>
            <span class="runtime-logout-modal__copy">Please wait while we securely end your session.</span>
        </span>
    </div>
</div>

<dialog id="runtimeActionModal" class="runtime-action-modal" aria-labelledby="runtimeActionTitle" aria-describedby="runtimeActionMessage" tabindex="-1" hidden>
    <span class="runtime-action-modal__spinner" aria-hidden="true"></span>
    <div role="status" aria-live="polite" aria-atomic="true">
        <h2 id="runtimeActionTitle">Saving requisition...</h2>
        <p id="runtimeActionMessage">Please wait while we save this action.</p>
    </div>
</dialog>

<script>
    (() => {
        if (window.__htcRuntimeGuardLoaded) return;
        window.__htcRuntimeGuardLoaded = true;

        const loader = document.getElementById('runtimeLoader');
        const loaderMessage = document.getElementById('runtimeLoaderMessage');
        const logoutModal = document.getElementById('runtimeLogoutModal');
        const actionModal = document.getElementById('runtimeActionModal');
        const actionTitle = document.getElementById('runtimeActionTitle');
        const actionMessage = document.getElementById('runtimeActionMessage');
        let activeActionForm = null;
        const lockedForms = new Set();
        const nativeSubmit = HTMLFormElement.prototype.submit;
        let runtimeLocked = false;
        let logoutPending = false;
        let skipNextUnloadLoader = false;
        let previousFocus = null;
        let loaderTimer = null;
        let navigationTimer = null;
        let recoverableNavigation = false;

        // Legacy form.submit() skips submit events. Route it through requestSubmit()
        // so filter forms receive the same loader and duplicate-submit protection.
        HTMLFormElement.prototype.submit = function () {
            if (typeof this.requestSubmit === 'function') {
                this.requestSubmit();
                return;
            }
            nativeSubmit.call(this);
        };

        const showLoader = (message = 'Loading&hellip;') => {
            if (!loader) return;
            if (!runtimeLocked && document.activeElement instanceof HTMLElement) {
                previousFocus = document.activeElement;
            }
            loaderMessage.textContent = message.replace('&hellip;', '…');
            runtimeLocked = true;
            document.documentElement.setAttribute('aria-busy', 'true');
            if (loaderTimer !== null || !loader.hidden) return;
            loaderTimer = window.setTimeout(() => {
                loaderTimer = null;
                loader.hidden = false;
                document.body.classList.add('runtime-locked');
                loader.focus({preventScroll: true});
            }, 180);
        };

        const showLogoutModal = () => {
            if (!logoutModal) return;
            if (!runtimeLocked && document.activeElement instanceof HTMLElement) {
                previousFocus = document.activeElement;
            }
            logoutPending = true;
            runtimeLocked = true;
            logoutModal.hidden = false;
            document.body.classList.add('runtime-locked');
            document.documentElement.setAttribute('aria-busy', 'true');
            logoutModal.focus({preventScroll: true});
        };

        const showActionProgress = (form, submitter, checking = false) => {
            if (!actionModal || window.htcSession?.expired) return;
            if (typeof actionModal.showModal !== 'function') {
                showLoader(form.dataset.loadingMessage || 'Saving requisition...');
                return;
            }
            if (!runtimeLocked && document.activeElement instanceof HTMLElement) previousFocus = document.activeElement;
            window.clearTimeout(loaderTimer);
            window.clearTimeout(navigationTimer);
            loaderTimer = null;
            navigationTimer = null;
            recoverableNavigation = false;
            if (loader) loader.hidden = true;
            activeActionForm = form;
            actionTitle.textContent = submitter?.dataset.loadingText || form.dataset.loadingMessage || 'Saving requisition...';
            actionMessage.textContent = checking
                ? 'Checking your session before saving this action.'
                : 'Please wait while we save this action.';
            runtimeLocked = true;
            document.body.classList.add('runtime-locked');
            document.documentElement.setAttribute('aria-busy', 'true');
            actionModal.hidden = false;
            if (!actionModal.open) actionModal.showModal();
            actionModal.focus({preventScroll: true});
        };

        const hideLoader = () => {
            window.clearTimeout(loaderTimer);
            window.clearTimeout(navigationTimer);
            loaderTimer = null;
            navigationTimer = null;
            recoverableNavigation = false;
            runtimeLocked = false;
            logoutPending = false;
            document.body.classList.remove('runtime-locked');
            document.documentElement.removeAttribute('aria-busy');
            if (loader) loader.hidden = true;
            if (logoutModal) logoutModal.hidden = true;
            if (actionModal?.open) actionModal.close();
            if (actionModal) actionModal.hidden = true;
            activeActionForm = null;
            if (previousFocus?.isConnected) previousFocus.focus({preventScroll: true});
            previousFocus = null;
        };

        const clearRuntimeCache = () => {
            try {
                Object.keys(sessionStorage)
                    .filter(key => key.startsWith('htc-runtime-state:'))
                    .forEach(key => sessionStorage.removeItem(key));
                sessionStorage.setItem('htc-runtime-mutated-at', String(Date.now()));
            } catch (error) {
                // Storage can be unavailable in private browsing; submission still works.
            }
        };

        const unlockForm = form => {
            delete form.dataset.runtimeSubmitting;
            form.querySelectorAll('[data-runtime-disabled]').forEach(button => {
                button.disabled = false;
                button.innerHTML = button.__runtimeOriginalHtml || button.innerHTML;
                button.removeAttribute('aria-busy');
                button.removeAttribute('data-runtime-disabled');
            });
            form.querySelectorAll('[data-runtime-submitter]').forEach(input => input.remove());
            lockedForms.delete(form);
        };

        const canRecoverNavigation = () => recoverableNavigation && !logoutPending
            && [...lockedForms].every(form => (form.method || 'get').toLowerCase() === 'get');

        const recoverNavigation = () => {
            if (!canRecoverNavigation()) return;
            lockedForms.forEach(unlockForm);
            hideLoader();
        };

        const watchNavigation = () => {
            recoverableNavigation = true;
            window.clearTimeout(navigationTimer);
            // Downloads or interrupted GET navigations may never fire pageshow.
            // Keep write submissions locked; only navigation can recover this way.
            navigationTimer = window.setTimeout(recoverNavigation, 15000);
        };

        document.addEventListener('submit', event => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            const submitter = event.submitter;
            const actionProgress = form.dataset.actionProgress !== undefined;
            // A canceled event is an in-flight session check only when the form
            // is busy. Declined confirmations must not open a progress dialog.
            if (actionProgress && event.defaultPrevented && form.getAttribute('aria-busy') === 'true') {
                showActionProgress(form, submitter, true);
            }
            if (event.defaultPrevented) return;
            if (form.dataset.download !== undefined || submitter?.dataset.download !== undefined) {
                skipNextUnloadLoader = true;
                return;
            }
            if (form.dataset.noLoader !== undefined) {
                skipNextUnloadLoader = form.dataset.noUnloadLoader !== undefined;
                return;
            }
            skipNextUnloadLoader = false;

            if (form.dataset.runtimeSubmitting === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.runtimeSubmitting = 'true';
            lockedForms.add(form);

            if (submitter?.name) {
                const value = document.createElement('input');
                value.type = 'hidden';
                value.name = submitter.name;
                value.value = submitter.value;
                value.dataset.runtimeSubmitter = 'true';
                form.appendChild(value);
            }

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(button => {
                if (button.disabled) return;
                button.__runtimeOriginalHtml = button.innerHTML;
                button.dataset.runtimeDisabled = 'true';
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');

                if (button.tagName === 'BUTTON') {
                    const label = button.dataset.loadingText || 'Please wait…';
                    const spinner = document.createElement('span');
                    const text = document.createElement('span');
                    spinner.className = 'runtime-spinner';
                    spinner.setAttribute('aria-hidden', 'true');
                    text.textContent = label;
                    button.replaceChildren(spinner, text);
                }
            });

            if ((form.method || 'get').toLowerCase() !== 'get') clearRuntimeCache();
            if (form.dataset.logoutForm !== undefined) {
                showLogoutModal();
            } else if (actionProgress) {
                showActionProgress(form, submitter);
            } else {
                showLoader(form.dataset.loadingMessage || 'Processing request…');
                if ((form.method || 'get').toLowerCase() === 'get') watchNavigation();
            }

            queueMicrotask(() => {
                if (!event.defaultPrevented) return;
                unlockForm(form);
                hideLoader();
            });

        });

        // The overlay absorbs pointer input. Capture keyboard-triggered actions too,
        // so users cannot start another operation while a request is in flight.
        document.addEventListener('click', event => {
            if (!runtimeLocked || loader?.contains(event.target) || logoutModal?.contains(event.target)) return;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);

        document.addEventListener('keydown', event => {
            if (!runtimeLocked) return;
            if (event.key === 'Escape' && canRecoverNavigation()) {
                window.stop();
                recoverNavigation();
            }
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);

        document.addEventListener('click', event => {
            const link = event.target.closest('a[href]');
            if (!link || event.defaultPrevented || link.dataset.noLoader !== undefined || link.target === '_blank' || link.hasAttribute('download')) return;
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

            const destination = new URL(link.href, window.location.href);
            const sameDocumentAnchor = destination.origin === window.location.origin
                && destination.pathname === window.location.pathname
                && destination.search === window.location.search
                && destination.hash;
            if (!['http:', 'https:'].includes(destination.protocol)
                || destination.origin !== window.location.origin
                || destination.href === window.location.href
                || sameDocumentAnchor
                || link.dataset.bsToggle) return;
            // Track navigation before its synchronous beforeunload event, then
            // roll back if a later click handler cancels the link instead.
            skipNextUnloadLoader = false;
            showLoader('Opening page…');
            watchNavigation();
            queueMicrotask(() => {
                if (event.defaultPrevented) recoverNavigation();
            });
        });

        window.addEventListener('beforeunload', event => {
            // Downloads and canceled reloads can leave the current document open.
            // This event must never create a new lock on its own.
            if (logoutPending || skipNextUnloadLoader || !recoverableNavigation) return;
            window.setTimeout(() => {
                if (event.defaultPrevented || (typeof event.returnValue === 'string' && event.returnValue !== '')) {
                    recoverNavigation();
                }
            }, 0);
        });
        window.addEventListener('pageshow', () => {
            skipNextUnloadLoader = false;
            lockedForms.forEach(unlockForm);
            hideLoader();
        });
        actionModal?.addEventListener('cancel', event => event.preventDefault());
        // Bootstrap's underlying review modal must not reclaim dialog focus.
        actionModal?.addEventListener('focusin', event => event.stopPropagation());
        document.addEventListener('htc:session-check-failed', event => {
            if (event.target === activeActionForm) hideLoader();
        });
        document.addEventListener('htc:session-expired', () => {
            lockedForms.forEach(unlockForm);
            hideLoader();
        });
        document.addEventListener('htc:session-submit', event => {
            const {form, submitter} = event.detail;
            clearRuntimeCache();
            if (form.dataset.noLoader !== undefined) {
                skipNextUnloadLoader = form.dataset.noUnloadLoader !== undefined;
                return;
            }
            if (form.dataset.logoutForm !== undefined) showLogoutModal();
            else if (form.dataset.actionProgress !== undefined) showActionProgress(form, submitter);
            else showLoader(form.dataset.loadingMessage || 'Processing request…');
        });
    })();
</script>
