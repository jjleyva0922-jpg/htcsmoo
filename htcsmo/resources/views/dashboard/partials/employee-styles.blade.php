@pushOnce('styles', 'page-styles:dashboard/partials/employee-styles.blade.php')
<style>
    .employee-dashboard { --employee-border:#e0e8e3; --employee-muted:#607469; display:grid; grid-template-columns:minmax(0,1fr) clamp(250px,23vw,300px); gap:var(--space-16); align-items:start; }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero { grid-column:1; grid-row:1; min-width:0; min-height:0; margin:0; padding:var(--space-24) var(--space-24); gap:var(--space-24); border:1px solid #225e43; border-radius:16px; background:linear-gradient(115deg,#123d2c,#1f5c41); color:#fff; box-shadow:0 6px 18px #163b2310; }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-kicker { color:#bde1c9; font-size:var(--text-caption); letter-spacing:var(--tracking-label); }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero h2 { margin:var(--space-4) 0 var(--space-8); color:#fff; font-size:var(--text-heading-md); letter-spacing:var(--tracking-normal); }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero-copy > p { max-width:560px; color:#e1eee5; font-size:var(--text-secondary); line-height:var(--line-prose); }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero-meta { gap:var(--space-12) var(--space-16); margin-top:var(--space-12); }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero-meta > span { color:#cee2d5; font-size:var(--text-caption); }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero-meta svg { width:14px; height:14px; color:#a8cfb7; }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero-actions { min-width:174px; gap:var(--space-8); }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .hero-btn { min-height:38px; padding:var(--space-8) var(--space-12); border-radius:8px; font-size:var(--text-secondary); }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .hero-btn.primary { border-color:#fff; background:#fff; color:#174c33; box-shadow:0 2px 5px #08251815; }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .hero-btn.primary:hover { border-color:#e4f1e8; background:#e4f1e8; color:#123d2c; }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .hero-btn.secondary { border-color:#ffffff42; background:#ffffff0b; color:#f0f7f2; }
    .dashboard-page:is(.employee-dashboard,.staff-dashboard) .hero-btn.secondary:hover { border-color:#ffffff70; background:#ffffff18; color:#fff; }

    .employee-dashboard .personal-summary { grid-column:1; grid-row:2; min-width:0; gap:var(--space-16); margin:0; }
    .employee-dashboard .personal-summary > a { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:var(--space-4) var(--space-12); padding:var(--space-16) var(--space-16); border-top:3px solid #85aa93; border-radius:12px; background:linear-gradient(110deg,#f1f7f3,#fff 85%); box-shadow:0 2px 8px #163b2304; }
    .employee-dashboard .personal-summary > .personal-summary-clarification { border-top-color:#c9aa6b; background:linear-gradient(110deg,#fff8eb,#fff 85%); }
    .employee-dashboard .personal-summary > .personal-summary-pickup { border-top-color:#74aaa2; background:linear-gradient(110deg,#edf7f5,#fff 85%); }
    .employee-dashboard .personal-summary-top { grid-column:1/-1; font-size:var(--text-secondary); color:#617268; }
    .employee-dashboard .personal-summary-icon { width:30px; height:30px; border-radius:8px; }
    .employee-dashboard .personal-summary-icon svg { width:16px; height:16px; }
    .employee-dashboard .personal-summary strong { grid-column:2; grid-row:2/4; margin:0; padding-left:var(--space-8); font-size:var(--text-heading-lg); }
    .employee-dashboard .personal-summary h3 { grid-column:1; margin-top:var(--space-4); font-size:var(--text-heading-sm); }
    .employee-dashboard .personal-summary p { grid-column:1; margin-top:0; font-size:var(--text-secondary); }
    .employee-dashboard .personal-workspace,.employee-dashboard .personal-columns { display:contents; }
    .employee-dashboard .personal-main-stack { grid-column:1; grid-row:3; min-width:0; }
    .employee-dashboard .personal-panel { margin-bottom:var(--space-16); border-radius:12px; box-shadow:0 2px 8px #163b2303; }
    .employee-dashboard .personal-panel > header { padding:var(--space-16) var(--space-16); gap:var(--space-12); }
    .employee-dashboard .personal-attention-panel > header { background:#fffcf5; border-bottom-color:#eee6d6; }
    .employee-dashboard .personal-panel h2 { font-size:var(--text-heading-md); }
    .employee-dashboard .personal-eyebrow { color:#5a7062; font-size:var(--text-secondary); letter-spacing:var(--tracking-label); margin-bottom:var(--space-4); }
    .employee-dashboard .personal-panel header p { max-width:650px; margin:var(--space-8) 0 0; color:var(--employee-muted); font-size:var(--text-secondary); line-height:var(--line-body); }
    .employee-dashboard .personal-panel footer { padding:var(--space-12) var(--space-16); font-size:var(--text-secondary); }
    .employee-dashboard .personal-action-grid { gap:var(--space-12); padding:var(--space-12); }
    .employee-dashboard .personal-action-card { padding:var(--space-16) var(--space-16); border-radius:9px; box-shadow:none; }
    .employee-dashboard .personal-action-icon { width:28px; height:28px; border-radius:7px; }
    .employee-dashboard .personal-action-icon svg { width:16px; height:16px; }
    .employee-dashboard .personal-action-card h3 { margin:var(--space-8) 0 var(--space-4); font-size:var(--text-heading-sm); }
    .employee-dashboard .personal-action-card p { margin:var(--space-4) 0 var(--space-12); font-size:var(--text-secondary); line-height:var(--line-body); }
    .employee-dashboard .personal-action-card blockquote { padding:var(--space-8) var(--space-12); margin:var(--space-4) 0 var(--space-12); font-size:var(--text-body); line-height:var(--line-body); }
    .employee-dashboard .personal-button { min-height:34px; padding:var(--space-8) var(--space-12); font-size:var(--text-secondary); }
    .employee-dashboard .personal-all-clear { grid-column:1/-1; padding:var(--space-8) var(--space-8); }
    .employee-dashboard .personal-all-clear .personal-empty-icon { width:34px; height:34px; flex-basis:34px; border-radius:10px; }

    .employee-table-wrap { width:100%; overflow-x:auto; }
    .employee-dashboard .employee-request-table { width:100%; min-width:650px; margin:0; border-collapse:collapse; }
    .employee-dashboard .employee-request-table thead th { padding:var(--space-12) var(--space-16); color:#5a6e61; background:#f5f8f6; border-top:0; border-bottom:1px solid var(--employee-border); font-size:var(--text-caption); letter-spacing:var(--tracking-label); font-weight:750; text-transform:uppercase; white-space:nowrap; }
    .employee-dashboard .employee-request-table tbody :is(th,td) { padding:var(--space-12) var(--space-16); border-bottom:1px solid #eaf0ec; background:transparent; color:#354b3c; font-size:var(--text-secondary); font-weight:400; vertical-align:top; }
    .employee-dashboard .employee-request-table tbody tr:last-child :is(th,td) { border-bottom:0; }
    .employee-request-table tbody tr:hover { background:#f8fbf9; }
    .employee-request-table tbody tr:focus-within { background:#f3f8f4; }
    .employee-request-row.is-clarification .employee-request-cell { box-shadow:inset 3px 0 #c7aa70; }
    .employee-request-row.is-ready .employee-request-cell { box-shadow:inset 3px 0 #7cae99; }
    .employee-request-cell { width:31%; }
    .employee-request-code { display:inline-flex; align-items:center; gap:var(--space-4); font-size:var(--text-secondary); font-weight:750; text-decoration:none; overflow-wrap:anywhere; }
    .employee-request-code svg { width:12px; height:12px; color:#708d7a; }
    .employee-item-name { display:block; max-width:28rem; margin-top:var(--space-4); color:#405948; font-size:var(--text-secondary); line-height:var(--line-body); overflow-wrap:anywhere; }
    .employee-item-meta { display:block; margin-top:.15rem; color:var(--employee-muted); font-size:var(--text-caption); }
    .employee-status-cell { width:29%; }
    .employee-dashboard .personal-status { padding:var(--space-4) var(--space-8); font-size:var(--text-caption); border-radius:5px; font-weight:700; }
    .employee-dashboard .personal-status svg { width:12px; height:12px; }
    .employee-deadline { display:flex; align-items:center; flex-wrap:wrap; gap:var(--space-4) var(--space-4); margin-top:var(--space-8); color:#855d16; font-size:var(--text-caption); line-height:var(--line-body); }
    .employee-deadline.is-overdue { color:#a13b36; }
    .employee-deadline svg { width:12px; height:12px; }
    .employee-deadline :is(time,span) { flex-basis:100%; padding-left:var(--space-16); }
    .employee-dashboard .employee-request-table .employee-date { color:var(--employee-muted); font-size:var(--text-secondary); white-space:nowrap; }
    .employee-dashboard .employee-request-table .employee-value { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
    .employee-dashboard .employee-request-table td.employee-value { color:#294533; font-weight:650; }
    .employee-actions-heading { text-align:right; }
    .employee-row-actions { display:flex; align-items:center; justify-content:flex-end; gap:var(--space-8); }
    .employee-row-actions a { display:inline-flex; align-items:center; justify-content:center; gap:var(--space-4); min-height:30px; padding:var(--space-4) var(--space-8); border-radius:6px; font-size:var(--text-caption); font-weight:650; text-decoration:none; white-space:nowrap; }
    .employee-row-actions .employee-track-link { border:1px solid #dce8df; background:#f0f6f2; color:#245d3b; }
    .employee-track-link:hover { background:#e4f0e8; }
    .employee-row-actions .employee-edit-link { color:#526b5b; }
    .employee-row-actions svg { width:13px; height:13px; }

    .employee-dashboard .personal-side-stack { grid-column:2; grid-row:1/4; display:block; position:sticky; top:88px; max-height:calc(100dvh - 104px); overflow-y:auto; scrollbar-width:thin; border-radius:12px; }
    .employee-dashboard .personal-side-stack:focus-visible { outline:3px solid #7da0bf; outline-offset:3px; }
    .employee-dashboard .personal-side-stack > * { min-width:0; margin:0; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications { border-color:#d8e4ef; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications > header { background:#eef4fa; border-bottom-color:#dce6ef; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications .personal-eyebrow { color:#526f88; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications .personal-section-icon { color:#3f6b92; border-color:#d6e2ee; background:#e5eef7; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications .is-unread { background:#f5f9fd; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications .is-unread::before { background:#6c96ba; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications .personal-unread { color:#335f84; background:#e5eef8; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications :is(a,button):not(.personal-update-open) { color:#355f83; background:#eaf2fa; border-color:#bfd2e4; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications :is(a,button):not(.personal-update-open):hover { background:#dceaf6; border-color:#91b2d0; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-update-form { margin:0; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-updates .personal-update-open { position:absolute; inset:0; z-index:1; display:block; width:100%; height:100%; min-height:0; padding:0; border:0; border-radius:0; background:transparent; cursor:pointer; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-updates .personal-update-open:focus-visible { outline:3px solid #7da0bf; outline-offset:-3px; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-updates > li:has(.personal-update-open:hover) { background:#eaf2fa; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-updates h3 { font-size:var(--text-secondary); line-height:1.4; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-updates p { margin:var(--space-4) 0; line-height:1.45; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-updates time { margin-bottom:0; }
    :is(.employee-dashboard,.employee-notification-drawer) .personal-notifications footer { background:#f4f8fc; border-top-color:#e1e9f1; }
    .employee-guidance-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:var(--space-16); align-items:start; }
    .employee-guidance-grid > * { min-width:0; margin:0; }
    .employee-dashboard .personal-sla,.employee-dashboard .personal-archive-card { padding:var(--space-16) var(--space-16); border-radius:12px; }
    .employee-dashboard .personal-sla { background:#f1f6f2; }
    .employee-dashboard .personal-sla > strong { font-size:var(--text-heading-sm); }
    .employee-dashboard .personal-sla p { font-size:var(--text-secondary); line-height:var(--line-prose); }
    .employee-dashboard .personal-archive-card { border-style:solid; }
    .employee-dashboard .personal-archive-card h3 { font-size:var(--text-heading-sm); }
    .employee-dashboard .personal-archive-card p { margin:var(--space-8) 0 var(--space-12); font-size:var(--text-secondary); line-height:var(--line-body); }
    .employee-dashboard .personal-updates > li { padding:var(--space-8) var(--space-12); }
    .employee-dashboard .personal-updates time { color:#64766b; }

    .employee-notifications-launcher { display:none; }
    .employee-notification-drawer { inset:0 0 0 auto; width:min(360px,calc(100vw - 1rem)); max-width:none; height:100dvh; max-height:100dvh; margin:0; padding:0; overflow-y:auto; border:0; border-left:1px solid #d8e4ef; border-radius:14px 0 0 14px; background:#fff; color:#20392d; font:inherit; }
    .employee-notification-drawer::backdrop { background:rgb(10 29 18 / 42%); }
    .employee-notification-drawer .personal-side-stack { display:block; position:static; max-height:none; overflow:visible; }
    .employee-notification-drawer .personal-panel { margin:0; border:0; border-radius:0; }
    .employee-notification-drawer .personal-panel > header { padding:var(--space-16) var(--space-16); }
    .employee-notification-drawer .personal-panel h2 { font-size:var(--text-heading-sm); }
    .employee-notification-drawer .personal-updates > li { padding:var(--space-12); }
    .employee-notifications-close { display:block; width:100%; min-height:44px; padding:var(--space-12) var(--space-16); border:0; border-bottom:1px solid #dce6ef; background:#f4f8fc; color:#355f83; text-align:right; font:inherit; font-weight:650; }
    .employee-notification-drawer :is(a,button):focus-visible { outline:3px solid #7da0bf; outline-offset:2px; }
    @media screen and (min-width:768px) and (max-width:1199px) {
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero { flex-direction:column; align-items:stretch; padding:var(--space-16); }
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero-actions { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); min-width:0; }
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .hero-btn.primary { grid-column:1/-1; }
        .employee-dashboard .personal-summary { grid-template-columns:minmax(0,1fr); }
        .employee-dashboard .personal-action-grid,.employee-guidance-grid { grid-template-columns:minmax(0,1fr); }
    }
    @media screen and (min-width:768px) and (max-width:991px) {
        .employee-dashboard .personal-side-stack { position:static; max-height:none; overflow:visible; }
    }
    @media screen and (max-width:767px) {
        .employee-dashboard { display:block; }
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero { margin-bottom:var(--space-16); }
        .employee-dashboard .personal-summary { margin-bottom:var(--space-16); }
        .employee-dashboard .personal-side-stack { display:none; }
        .employee-notifications-launcher { position:fixed; right:1rem; bottom:1rem; z-index:1040; display:inline-flex; align-items:center; gap:var(--space-8); min-height:44px; padding:var(--space-12) var(--space-16); border:1px solid #b7cce0; border-radius:10px; background:#eaf2fa; color:#284f72 !important; font-size:var(--text-secondary); font-weight:700; text-decoration:none; box-shadow:0 4px 16px #163b2320; }
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero { padding:var(--space-24); flex-direction:column; align-items:stretch; gap:var(--space-16); }
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero-actions { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); min-width:0; }
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .hero-btn { min-height:42px; padding:var(--space-8) var(--space-8); font-size:var(--text-caption); }
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .hero-btn.primary { grid-column:auto; }
        .employee-dashboard .personal-summary { gap:var(--space-12); }
        .employee-dashboard .personal-summary > a { padding:var(--space-12); gap:var(--space-4); }
        .employee-dashboard .personal-summary-top { font-size:var(--text-secondary); }
        .employee-dashboard .personal-summary strong { font-size:var(--text-heading-lg); }
        .employee-dashboard .personal-summary h3 { font-size:var(--text-heading-sm); }
        .employee-dashboard .personal-summary p { font-size:var(--text-secondary); }
        .employee-dashboard .personal-panel > header { padding:var(--space-16) var(--space-16); }
        .employee-table-wrap { overflow:visible; }
        .employee-dashboard .employee-request-table { min-width:0 !important; display:block; }
        .employee-request-table thead { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; }
        .employee-request-table tbody { display:grid; grid-template-columns:minmax(0,1fr); gap:var(--space-12); padding:var(--space-12); background:#f5f8f6; }
        .employee-request-table tbody tr { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); border:1px solid var(--employee-border); border-radius:9px; background:#fff; overflow:hidden; }
        .employee-dashboard .employee-request-table tbody :is(th,td) { display:block; width:auto; padding:var(--space-12) var(--space-12); border:0; min-width:0; }
        .employee-request-table .employee-request-cell { grid-column:1/-1; border-bottom:1px solid #edf1ee !important; }
        .employee-request-table .employee-status-cell { grid-column:1/-1; }
        .employee-request-table td[data-label]::before { display:block; margin-bottom:var(--space-4); content:attr(data-label); color:#65796c; font-size:var(--text-secondary); font-weight:750; letter-spacing:var(--tracking-label); text-transform:uppercase; }
        .employee-dashboard .employee-request-table .employee-date { white-space:normal; }
        .employee-request-table .employee-actions-cell { grid-column:1/-1; border-top:1px solid #edf1ee !important; background:#fbfcfb !important; }
        .employee-request-table .employee-actions-cell::before { display:none; }
        .employee-row-actions { justify-content:flex-start; }
        .employee-row-actions a { min-height:38px; padding:var(--space-8) var(--space-12); }
        .employee-request-table td[colspan] { grid-column:1/-1; }
        .employee-dashboard .personal-start-state { padding:var(--space-24) var(--space-16) !important; }
        .employee-guidance-grid { grid-template-columns:minmax(0,1fr); }
    }
    @media screen and (max-width:575px) {
        .employee-dashboard .personal-summary > a { grid-template-columns:36px minmax(0,1fr) auto; gap:var(--space-4) var(--space-12); padding:var(--space-16) var(--space-16); }
        .employee-dashboard .personal-summary-top { grid-column:1; grid-row:1/3; }
        .employee-dashboard .personal-summary-icon { width:36px; height:36px; }
        .employee-dashboard .personal-summary strong { grid-column:3; grid-row:1/3; }
        .employee-dashboard .personal-summary h3 { grid-column:2; margin:0; font-size:var(--text-heading-sm); }
        .employee-dashboard .personal-summary p { grid-column:2; }
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .dashboard-hero-actions { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .dashboard-page:is(.employee-dashboard,.staff-dashboard) .hero-btn.primary { grid-column:1/-1; }
    }
    @media print {
        .employee-dashboard { display:block; }
        .employee-notifications-launcher,.employee-notification-drawer { display:none !important; }
        .employee-dashboard .personal-side-stack,.employee-dashboard .employee-actions-cell,.employee-dashboard .employee-actions-heading { display:none !important; }
        .employee-dashboard .dashboard-hero { color:#173b27 !important; background:#fff !important; box-shadow:none !important; }
        .employee-dashboard .dashboard-hero :is(h2,p,span) { color:#173b27 !important; }
        .employee-table-wrap { overflow:visible; }
        .employee-dashboard .employee-request-table { min-width:0; }
        .employee-dashboard .personal-panel { break-inside:auto; }
    }
</style>
@endPushOnce
