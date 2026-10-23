<a class="employee-notifications-launcher" data-employee-notifications-open href="{{ route('notifications') }}"><i data-lucide="bell" aria-hidden="true"></i></a>
<script>
    (() => {
        if (window.__employeeNotificationsLoaded) return;
        window.__employeeNotificationsLoaded = true;
        const wideScreen = window.matchMedia('(min-width: 768px)');
        const drawer = document.createElement('dialog');
        drawer.className = 'employee-notification-drawer';
        drawer.setAttribute('aria-labelledby', 'personalUpdatesHeading');
        drawer.innerHTML = '<button type="button" class="employee-notifications-close"> &times;</button>';
        const closeButton = drawer.querySelector('button');
        document.body.appendChild(drawer);
        let sidebar = null;
        let launcher = null;

        const placeNotifications = () => {
            const next = document.querySelector('.employee-dashboard [data-employee-notifications]');
            const anchor = document.querySelector('[data-employee-notifications-anchor]');
            if (next && next !== sidebar) {
                sidebar?.remove();
                sidebar = next;
            }
            if (!sidebar || !anchor) return;
            if (wideScreen.matches) {
                if (drawer.open) drawer.close();
                anchor.after(sidebar);
            } else {
                drawer.appendChild(sidebar);
            }
        };

        document.addEventListener('click', event => {
            const trigger = event.target.closest('[data-employee-notifications-open]');
            if (!trigger || wideScreen.matches || typeof drawer.showModal !== 'function') return;
            event.preventDefault();
            launcher = trigger;
            placeNotifications();
            if (!drawer.open) drawer.showModal();
            closeButton.focus({preventScroll: true});
        });
        closeButton.addEventListener('click', () => drawer.close());
        drawer.addEventListener('close', () => {
            if (!wideScreen.matches && !window.htcSession?.expired && launcher?.isConnected) launcher.focus({preventScroll: true});
        });
        drawer.addEventListener('focusin', event => event.stopPropagation());
        document.addEventListener('htc:workspace-updated', placeNotifications);
        document.addEventListener('htc:session-expired', () => {
            if (drawer.open) drawer.close();
        });
        wideScreen.addEventListener('change', placeNotifications);
        placeNotifications();
    })();
</script>
