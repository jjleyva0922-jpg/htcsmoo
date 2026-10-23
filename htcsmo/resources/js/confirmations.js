// One shared dialog, including forms inserted by live workspace updates.
export function initializeConfirmations() {
    if (window.htcConfirm) return;

    const dialog = document.createElement('dialog');
    dialog.className = 'action-confirmation';
    dialog.setAttribute('aria-labelledby', 'actionConfirmationTitle');
    dialog.setAttribute('aria-describedby', 'actionConfirmationMessage');
    dialog.innerHTML = `
        <div class="action-confirmation__body">
            <span class="action-confirmation__icon" aria-hidden="true">!</span>
            <h2 id="actionConfirmationTitle"></h2>
            <p id="actionConfirmationMessage"></p>
        </div>
        <div class="action-confirmation__actions">
            <button type="button" class="action-confirmation__cancel" autofocus>Cancel</button>
            <button type="button" class="action-confirmation__accept">Confirm</button>
        </div>`;
    document.body.append(dialog);

    const title = dialog.querySelector('h2');
    const message = dialog.querySelector('p');
    const accept = dialog.querySelector('.action-confirmation__accept');
    const cancel = dialog.querySelector('.action-confirmation__cancel');
    const approvedForms = new WeakSet();
    const pendingForms = new WeakSet();
    let pending = null;

    const finish = accepted => {
        if (!pending) return;
        const { resolve, focus } = pending;
        pending = null;
        dialog.close();
        if (focus?.isConnected) focus.focus({ preventScroll: true });
        resolve(accepted);
    };

    // Keep Bootstrap's underlying edit/review modal from taking focus back.
    dialog.addEventListener('focusin', event => event.stopPropagation());
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        finish(false);
    });
    cancel.addEventListener('click', () => finish(false));
    accept.addEventListener('click', () => finish(true));
    window.addEventListener('pagehide', () => finish(false));

    window.htcConfirm = (options = {}) => {
        // Never replace an outstanding decision or open multiple dialogs.
        if (pending) return Promise.resolve(false);
        title.textContent = options.title || 'Confirm this action?';
        message.textContent = options.message || 'Do you want to continue?';
        accept.textContent = options.accept || 'Confirm';
        cancel.textContent = options.cancel || 'Cancel';
        dialog.dataset.tone = options.tone === 'danger' ? 'danger' : 'default';
        return new Promise(resolve => {
            pending = { resolve, focus: document.activeElement };
            dialog.showModal();
            cancel.focus({ preventScroll: true });
        });
    };

    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
        if (approvedForms.delete(form)) return;

        const submitter = event.submitter;
        const source = submitter?.hasAttribute('data-confirm') ? submitter : form;
        if (!source.hasAttribute('data-confirm')) return;

        // Stop before Alpine handlers or the runtime guard lock the form.
        event.preventDefault();
        event.stopImmediatePropagation();
        if (pendingForms.has(form) || form.dataset.runtimeSubmitting === 'true') return;
        pendingForms.add(form);
        try {
            const accepted = await window.htcConfirm({
                title: source.dataset.confirmTitle,
                message: source.dataset.confirm,
                accept: source.dataset.confirmAccept,
                cancel: source.dataset.confirmCancel,
                tone: source.dataset.confirmTone,
            });
            if (!accepted || !form.isConnected || (submitter && (!submitter.isConnected || submitter.disabled))) return;
            approvedForms.add(form);
            try {
                // Retain native validation and the clicked approval/status value.
                form.requestSubmit(submitter || undefined);
            } finally {
                approvedForms.delete(form);
            }
        } finally {
            pendingForms.delete(form);
        }
    }, true);
}

initializeConfirmations();
