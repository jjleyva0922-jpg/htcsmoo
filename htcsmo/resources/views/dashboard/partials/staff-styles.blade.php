@pushOnce('styles', 'page-styles:dashboard/partials/staff-styles.blade.php')
<style>
    /* Match the employee workspace while retaining each role's existing sections. */
    .dashboard-page.staff-dashboard { display:grid; grid-template-columns:minmax(0,1fr); gap:var(--space-16); padding:0; color:#20392d; }
    .staff-dashboard > * { min-width:0; margin:0; }
    .staff-dashboard .dashboard-hero::after { display:none; }
    .staff-dashboard .hero-btn:hover { transform:none; }
    .staff-dashboard .dashboard-metric-grid { gap:var(--space-16); }
    .staff-dashboard .modern-metric { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; gap:var(--space-4) var(--space-12); padding:var(--space-16); border-color:#e0e8e3; border-top:3px solid #85aa93; border-radius:12px; background:linear-gradient(110deg,#f1f7f3,#fff 85%); box-shadow:0 2px 8px #163b2304; }
    .staff-dashboard .modern-metric.amber { border-top-color:#c9aa6b; background:linear-gradient(110deg,#fff8eb,#fff 85%); }
    .staff-dashboard .modern-metric.green { border-top-color:#74aaa2; background:linear-gradient(110deg,#edf7f5,#fff 85%); }
    .staff-dashboard .modern-metric.red { border-top-color:#bd7a7f; background:linear-gradient(110deg,#fff1f2,#fff 85%); }
    .staff-dashboard .modern-metric::after { display:none; }
    .staff-dashboard .metric-top { grid-column:1/-1; justify-content:flex-start; color:#617268; }
    .staff-dashboard .metric-icon { width:30px; height:30px; border-radius:8px; }
    .staff-dashboard .metric-trend { padding:0; border-radius:0; background:transparent; color:#617268; font-size:var(--text-secondary); font-weight:650; text-transform:none; }
    .staff-dashboard .modern-metric > strong { grid-column:2; grid-row:2/4; margin:0; padding-left:var(--space-8); font-weight:750; font-variant-numeric:tabular-nums; }
    .staff-dashboard .modern-metric > strong.money { grid-column:1/-1; grid-row:auto; padding-left:0; }
    .staff-dashboard .modern-metric h3 { grid-column:1; margin:var(--space-4) 0 0; color:#2b4334; font-weight:750; }
    .staff-dashboard .modern-metric p { grid-column:1; margin:0; color:#677b6d; }
    .staff-dashboard :is(.dashboard-panel,.priority-panel,.approval-deadline-watch) { min-width:0; margin:0; border-radius:12px; box-shadow:0 2px 8px #163b2303; }
    .staff-dashboard .dashboard-panel { border-color:#e0e8e3; background:#fff; }
    .staff-dashboard .panel-heading { min-height:0; flex-wrap:wrap; gap:var(--space-12); padding:var(--space-16); border-bottom-color:#eaf0ec; }
    .staff-dashboard .panel-heading h3 { color:#243e2f; font-size:var(--text-heading-md); font-weight:750; }
    .staff-dashboard .panel-heading p { margin-top:var(--space-8); color:#708074; line-height:var(--line-body); }
    .staff-dashboard .panel-eyebrow { color:#708577; font-size:var(--text-secondary); font-weight:750; }
    .staff-dashboard .panel-heading h3 em { border-radius:7px; color:#3f6850; background:#edf4ef; font-size:var(--text-secondary); }
    .staff-dashboard :is(.panel-action,.activity-footer) { min-height:44px; padding:var(--space-8) var(--space-12); border:1px solid #cbded1; border-radius:8px; background:#edf5ef; color:#2a6843; font-size:var(--text-secondary); font-weight:650; white-space:normal; }
    .staff-dashboard :is(.panel-action,.activity-footer):hover { background:#e0eee5; border-color:#a2bfae; color:#164f31; }
    .staff-dashboard .dashboard-table { min-width:620px; }
    .staff-dashboard .dashboard-table tbody td { padding:var(--space-16) var(--space-12); }
    .staff-dashboard :is(.status-pill,.tier-pill) { border-radius:5px; font-weight:700; }
    .staff-dashboard .priority-watch { gap:var(--space-16); }
    .staff-dashboard .priority-panel { border-top-width:3px; }
    .staff-dashboard .priority-heading { grid-template-columns:34px minmax(0,1fr); }
    .staff-dashboard .priority-heading > a { grid-column:1/-1; min-height:36px; white-space:normal; }
    .staff-dashboard .priority-symbol { width:34px; height:34px; border-radius:9px; }
    .staff-dashboard .priority-detail { min-width:0; overflow-wrap:anywhere; }
    .staff-dashboard .priority-row { grid-template-columns:auto minmax(0,1fr); }
    .staff-dashboard .priority-value { grid-column:2; }
    .staff-dashboard .activity-panel { border-color:#d8e4ef; }
    .staff-dashboard .activity-panel .panel-heading { padding:var(--space-16); background:#eef4fa; border-bottom-color:#dce6ef; }
    .staff-dashboard .activity-panel .panel-heading h3 { font-size:var(--text-heading-sm); }
    .staff-dashboard .activity-panel .panel-eyebrow { color:#526f88; }
    .staff-dashboard .activity-panel .icon-link { color:#3f6b92; border-color:#d6e2ee; background:#e5eef7; }
    .staff-dashboard .activity-list { padding:0; }
    .staff-dashboard .activity-item { grid-template-columns:30px minmax(0,1fr); gap:var(--space-8); padding:var(--space-12); }
    .staff-dashboard .activity-icon { width:30px; height:30px; border-radius:8px; }
    .staff-dashboard .activity-item h4 { color:#35503e; font-size:var(--text-secondary); font-weight:700; line-height:1.4; }
    .staff-dashboard .activity-item p { color:#6b7d70; line-height:1.45; overflow-wrap:anywhere; }
    .staff-dashboard .activity-item time { color:#64766b; }
    .staff-dashboard .activity-panel .activity-footer { margin:var(--space-12); color:#355f83; background:#eaf2fa; border-color:#bfd2e4; }
    .staff-dashboard .activity-panel .activity-footer:hover { background:#dceaf6; border-color:#91b2d0; }
    .staff-dashboard .approval-deadline-heading { padding:var(--space-16); }
    .staff-dashboard .approval-deadline-heading h3 { font-weight:750; }

    /* Keep SMO notifications in their own rail without narrowing individual cards. */
    .dashboard-page.smo-dashboard { align-items:start; }
    .smo-dashboard > .dashboard-metric-grid { grid-row:2; margin:0; }
    .smo-dashboard > .approval-deadline-watch { grid-row:3; }
    .smo-dashboard > .priority-watch { grid-row:4; margin:0; align-items:start; }
    .smo-dashboard > .dashboard-layout { grid-row:5; display:grid; grid-template-columns:minmax(0,1fr); gap:var(--space-16); align-items:start; }
    .smo-dashboard .modern-metric { display:flex; flex-direction:column; align-items:flex-start; gap:0; }
    .smo-dashboard .modern-metric .metric-top { width:100%; gap:var(--space-8); }
    .smo-dashboard .modern-metric > strong { max-width:100%; margin:var(--space-12) 0 var(--space-8); padding:0; font-size:var(--text-heading-lg); overflow-wrap:anywhere; }
    .smo-dashboard .modern-metric h3 { margin:0; }
    .smo-dashboard .modern-metric p { margin-top:var(--space-8); }
    .smo-dashboard .activity-panel { align-self:start; }
    .smo-dashboard .approval-deadline-heading { flex-wrap:wrap; }
    .smo-dashboard .deadline-detail-block { min-width:0; }

    @media screen and (min-width:768px) {
        .dashboard-page.smo-dashboard { grid-template-columns:minmax(0,1fr) clamp(240px,20vw,280px); }
        .smo-dashboard > * { grid-column:1; }
        .smo-dashboard > .dashboard-layout { display:contents; }
        .smo-dashboard .queue-panel { grid-column:1; grid-row:5; }
        .smo-dashboard .activity-panel { grid-column:2; grid-row:1/6; position:sticky; top:88px; max-height:calc(100dvh - 104px); overflow-y:auto; scrollbar-width:thin; }
        .smo-dashboard .dashboard-metric-grid.four { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .smo-dashboard .priority-watch { grid-template-columns:minmax(0,1fr); }
        .smo-dashboard .approval-deadline-item { grid-template-columns:110px minmax(0,1fr) minmax(0,1fr); }
        .smo-dashboard .approval-deadline-age { grid-column:2; }
        .smo-dashboard .approval-deadline-action { grid-column:3; grid-row:2; justify-content:flex-start; flex-wrap:wrap; }
        .smo-dashboard .approval-deadline-details { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media screen and (min-width:1600px) {
        .smo-dashboard .dashboard-metric-grid.four { grid-template-columns:repeat(4,minmax(0,1fr)); }
        .smo-dashboard .priority-watch { grid-template-columns:repeat(2,minmax(0,1fr)); }
        .smo-dashboard .priority-row { grid-template-columns:auto minmax(0,1fr) auto; }
        .smo-dashboard .priority-value { grid-column:auto; }
    }
    @media screen and (min-width:768px) and (max-width:1399px) {
        .staff-dashboard:not(.smo-dashboard) .dashboard-metric-grid.four { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media screen and (max-width:767px) {
        .dashboard-page.staff-dashboard .dashboard-hero { margin-bottom:0; }
        .staff-dashboard .dashboard-layout { grid-template-columns:minmax(0,1fr); }
        .staff-dashboard .panel-heading { padding:var(--space-16); }
        .staff-dashboard .dashboard-table { min-width:0; }
        .staff-dashboard .responsive-dashboard-table thead { display:table-header-group; position:absolute; width:1px; height:1px; margin:-1px; overflow:hidden; clip-path:inset(50%); white-space:nowrap; }
        .staff-dashboard .responsive-dashboard-table tbody tr { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); border-radius:9px; }
        .staff-dashboard .responsive-dashboard-table td { display:block; min-width:0; text-align:left; }
        .staff-dashboard .responsive-dashboard-table td::before { display:block; margin-bottom:var(--space-4); font-size:var(--text-secondary); }
        .staff-dashboard .responsive-dashboard-table td:first-child { grid-column:1/-1; }
        .staff-dashboard .responsive-dashboard-table td:last-child { grid-column:1/-1; background:#fbfcfb; }
        .staff-dashboard .responsive-dashboard-table td[colspan] { grid-column:1/-1; }
        .staff-dashboard .dashboard-table td > small { text-align:left; }
        .staff-dashboard .row-action { margin-left:0; width:38px; height:38px; }
    }
    @media screen and (max-width:575px) {
        .staff-dashboard .dashboard-metric-grid { grid-template-columns:minmax(0,1fr); gap:var(--space-12); }
    }
    @media print {
        .dashboard-page.staff-dashboard { display:block; }
        .staff-dashboard .activity-panel { display:none; }
        .staff-dashboard .dashboard-hero { color:#173b27 !important; background:#fff !important; box-shadow:none !important; }
        .staff-dashboard .dashboard-hero :is(h2,p,span) { color:#173b27 !important; }
    }
</style>
@endPushOnce
