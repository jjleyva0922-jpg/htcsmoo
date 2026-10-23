<style>
    [data-filter-results] { scroll-margin-top: 6rem; }
    [data-filter-results][aria-busy="true"] { opacity: .65; }
    [data-filter-status] { flex-basis: 100%; margin: var(--space-8) 0; font-size: var(--text-secondary); color: #365a43; }
    [data-filter-status]:empty { margin: 0; }
    [data-filter-state="error"] > [data-filter-status], [data-filter-state="invalid"] > [data-filter-status] { color: #a12d36; }
    [data-filter-status] a { margin-left: var(--space-8); text-decoration: underline; }
    [data-filter-date-error] { display: block; margin-top: var(--space-4); color: #a12d36; font-size: var(--text-secondary); }
    [data-filter-date-error]:empty { display: none; }
    [data-filter-results]:focus { outline: 2px solid #33744c; outline-offset: 3px; }
    @media print { [data-filter-status] { display: none; } }
</style>
