<style>
    #sessionExpiredDialog { max-width:34rem; padding:var(--space-24); border:1px solid #dce4de; border-radius:1rem; color:#173b27; background:#fff; font:var(--text-body)/var(--line-body) var(--bs-body-font-family); }
    #sessionExpiredDialog::backdrop { background:rgb(10 29 18 / 50%); }
    #sessionExpiredNotice { position:fixed; inset:auto 1rem 1rem; z-index:21000; padding:var(--space-16); border:2px solid #14532d; border-radius:.5rem; background:#fff; color:#173b27; }
    #sessionExpiredNotice[hidden] { display:none; }
    #sessionExpiredDialog a,#sessionExpiredNotice a { color:#14532d; text-decoration:underline; }
    #sessionExpiredDialog :focus-visible,#sessionExpiredNotice :focus-visible { outline:3px solid #b38328; outline-offset:3px; }
</style>
<dialog id="sessionExpiredDialog" aria-labelledby="sessionExpiredTitle" aria-describedby="sessionExpiredDescription"
        data-check-url="{{ route('session.check') }}" data-recovery-url="{{ route('session.recover') }}" data-login-url="{{ route('login') }}"
        data-account="{{ Auth::id() }}">
    <h2 id="sessionExpiredTitle">Your session expired</h2>
    <p id="sessionExpiredDescription">Sign in again before continuing. Your entries are still on this page. No action will be retried automatically.</p>
    <p>Sign in in a new tab, then open a fresh form and copy your work into it. Review it before submitting. Passwords and file selections must be entered again. Reloading or leaving this page may lose your entries.</p>
    <p><a data-session-sign-in target="_blank" rel="noopener" href="{{ route('session.recover') }}">Sign in again (opens a new tab)</a></p>
    <button type="button" data-session-close autofocus>Keep this page open to copy my work</button>
</dialog>
<aside id="sessionExpiredNotice" role="alert" hidden>
    Your session expired. Copy your work before leaving this page.
    <a data-session-sign-in target="_blank" rel="noopener" href="{{ route('session.recover') }}">Sign in again (opens a new tab)</a>
</aside>
<script>
    (() => {
        if (window.htcSession) return;
        const dialog = document.getElementById('sessionExpiredDialog');
        const notice = document.getElementById('sessionExpiredNotice');
        const nativeFetch = window.fetch.bind(window);
        const nativeSubmit = HTMLFormElement.prototype.submit;
        const pending = new Map();
        let expired = false;

        const expiryError = () => Object.assign(new Error('Your session expired.'), {name: 'SessionExpiredError'});
        const releaseForm = form => {
            const state = pending.get(form);
            if (!state) return;
            state.buttons.forEach(button => button.disabled = false);
            state.submitterInput?.remove();
            for (const [name, value] of state.attributes) {
                if (value === null) form.removeAttribute(name);
                else form.setAttribute(name, value);
            }
            pending.delete(form);
        };
        window.addEventListener('pageshow', () => {
            [...pending.keys()].forEach(form => {
                releaseForm(form);
                const status = form.querySelector('[data-session-status]');
                if (status) status.textContent = '';
            });
        });
        const show = () => {
            if (expired) return;
            expired = true;
            const recovery = new URL(dialog.dataset.recoveryUrl, window.location.href);
            // The server validates this again against the application screen routes.
            recovery.searchParams.set('return', window.location.pathname + window.location.search);
            document.querySelectorAll('[data-session-sign-in]').forEach(link => link.href = recovery.href);
            document.dispatchEvent(new CustomEvent('htc:session-expired'));
            if (typeof dialog.showModal === 'function') dialog.showModal();
            else notice.hidden = false;
        };
        const inspect = response => {
            const redirectedToLogin = response.redirected && response.url
                && new URL(response.url).pathname === new URL(dialog.dataset.loginUrl).pathname;
            if (response.status === 401 || response.status === 419 || redirectedToLogin) show();
            if (expired) throw expiryError();
            return response;
        };
        window.htcSession = {get expired() { return expired; }, show, inspect};

        // Every first-party fetch uses one expiry contract, including HTML/PDF reads.
        window.fetch = async (input, options) => {
            const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, window.location.href);
            if (url.origin !== window.location.origin) return nativeFetch(input, options);
            if (expired) throw expiryError();
            try {
                return inspect(await nativeFetch(input, options));
            } catch (error) {
                if (expired) throw expiryError();
                throw error;
            }
        };
        dialog.querySelector('[data-session-close]').addEventListener('click', () => dialog.close());
        // Keep an underlying Bootstrap modal from taking keyboard focus back.
        dialog.addEventListener('focusin', event => event.stopPropagation());
        dialog.addEventListener('close', () => notice.hidden = false);
        document.addEventListener('submit', event => {
            if (!expired) return;
            event.preventDefault();
            event.stopImmediatePropagation();
        }, true);

        // Check the existing token before a native write so an idle form stays in
        // the DOM. Run after target handlers/confirmations; do not redispatch them.
        document.addEventListener('submit', async event => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
            const submitter = event.submitter;
            const method = (submitter?.getAttribute('formmethod') || form.method || 'get').toLowerCase();
            const action = submitter?.getAttribute('formaction') || form.action || window.location.href;
            if (method === 'get' || method === 'dialog' || new URL(action, window.location.href).origin !== window.location.origin) return;
            event.preventDefault();
            if (expired) { show(); return; }
            if (pending.has(form)) return;
            const state = {buttons: [], attributes: ['action', 'method', 'enctype', 'target', 'aria-busy'].map(name => [name, form.getAttribute(name)])};
            pending.set(form, state);
            form.setAttribute('aria-busy', 'true');
            let status = form.querySelector('[data-session-status]');
            if (!status) {
                status = document.createElement('p');
                status.dataset.sessionStatus = '';
                status.setAttribute('role', 'status');
                form.appendChild(status);
            }
            status.textContent = 'Checking your session…';
            const buttons = [...form.querySelectorAll('button[type="submit"], input[type="submit"]')].filter(button => !button.disabled);
            state.buttons = buttons;
            buttons.forEach(button => button.disabled = true);
            const controller = new AbortController();
            const timeout = window.setTimeout(() => controller.abort(), 10000);
            try {
                const body = new URLSearchParams({_token: form.querySelector('[name="_token"]')?.value || ''});
                if (dialog.dataset.account) body.set('account', dialog.dataset.account);
                const response = await window.fetch(dialog.dataset.checkUrl, {
                    method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json'}, body, signal: controller.signal,
                });
                if (response.status !== 204) throw new Error('Session check failed.');
                if (expired || !form.isConnected) return;
                // Native submission already passed validation and explicit confirmation.
                // Retain the clicked action value and any button-level form overrides.
                if (submitter?.name) {
                    const value = document.createElement('input');
                    value.type = 'hidden'; value.name = submitter.name; value.value = submitter.value;
                    form.appendChild(value);
                    state.submitterInput = value;
                }
                for (const attribute of ['action', 'method', 'enctype', 'target']) {
                    if (submitter?.hasAttribute('form' + attribute)) form.setAttribute(attribute, submitter.getAttribute('form' + attribute));
                }
                status.textContent = 'Submitting…';
                document.dispatchEvent(new CustomEvent('htc:session-submit', {detail: {form, submitter}}));
                nativeSubmit.call(form);
                // Keep the form locked until navigation completes; never retry a write.
            } catch (error) {
                status.textContent = error.name === 'SessionExpiredError'
                    ? 'Your session expired. Copy your work and sign in again. This form was not submitted.'
                    : 'We could not check your session. This form was not submitted. Keep your work here and try again when connected.';
                releaseForm(form);
                form.dispatchEvent(new CustomEvent('htc:session-check-failed', {bubbles: true}));
            } finally {
                window.clearTimeout(timeout);
            }
        });
    })();
</script>
