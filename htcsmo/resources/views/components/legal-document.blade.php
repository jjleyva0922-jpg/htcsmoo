@props(['title', 'summary', 'icon'])

<x-guest-layout>
    <article class="legal-page">
        <header class="legal-header">
            <a href="{{ route('login') }}" class="legal-back">
                <i data-lucide="arrow-left" aria-hidden="true"></i>
                Back to sign in
            </a>
            <div class="legal-heading">
                <span class="legal-heading__icon"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span>
                <div>
                    <p>HTC Supply Management System</p>
                    <h1>{{ $title }}</h1>
                    <span>{{ $summary }}</span>
                </div>
            </div>
            <div class="legal-meta">Effective October 7, 2026 · Last updated October 7, 2026</div>
        </header>

        <div class="legal-body">
            {{ $slot }}
        </div>

        <footer class="legal-footer">
            <nav aria-label="Legal documents">
                <a href="{{ route('legal.terms') }}" @if(request()->routeIs('legal.terms')) aria-current="page" @endif>Terms of Service</a>
                <a href="{{ route('legal.privacy') }}" @if(request()->routeIs('legal.privacy')) aria-current="page" @endif>Privacy Policy</a>
            </nav>
            <span>Holy Trinity College Supply Management Office</span>
        </footer>
    </article>

    @pushOnce('styles', 'page-styles:components/legal-document.blade.php')
    <style>
        .login-card:has(> .legal-page) { width: min(100%, 920px); padding: 0; }
        .legal-page { color: #25352b; background: #fff; }
        .legal-header { padding: clamp(var(--space-24), 4vw, calc(var(--space-32) + var(--space-8))); color: #fff; background: linear-gradient(145deg, #052e16, #166534); }
        .legal-back { display: inline-flex; align-items: center; gap: var(--space-8); margin-bottom: var(--space-32); color: rgba(255,255,255,.8); font-size: var(--text-caption); font-weight: 700; text-decoration: none; }
        .legal-back:hover { color: #fff; }
        .legal-back svg { width: 15px; height: 15px; }
        .legal-heading { display: flex; align-items: flex-start; gap: var(--space-16); }
        .legal-heading__icon { width: 48px; height: 48px; flex: 0 0 48px; display: grid; place-items: center; border: 1px solid rgba(255,255,255,.18); border-radius: 14px; background: rgba(255,255,255,.1); }
        .legal-heading__icon svg { width: 23px; height: 23px; }
        .legal-heading p { margin: 0 0 var(--space-4); color: #bbf7d0; font-size: var(--text-secondary); font-weight: 800; letter-spacing: var(--tracking-label); text-transform: uppercase; }
        .legal-heading h1 { margin: 0; font-size: var(--text-heading-lg); font-weight: 800; }
        .legal-heading span { display: block; max-width: 650px; margin-top: var(--space-8); color: rgba(255,255,255,.76); font-size: var(--text-secondary); line-height: var(--line-body); }
        .legal-meta { margin-top: var(--space-24); color: rgba(255,255,255,.62); font-size: var(--text-caption); }
        .legal-body { padding: clamp(var(--space-24), 4vw, calc(var(--space-32) + var(--space-12))); }
        .legal-intro { margin: 0 0 var(--space-32); padding: var(--space-16) var(--space-16); border-left: 3px solid #198754; border-radius: 0 10px 10px 0; color: #405348; background: #f1f8f3; font-size: var(--text-secondary); line-height: var(--line-prose); }
        .legal-section + .legal-section { margin-top: var(--space-32); padding-top: var(--space-32); border-top: 1px solid #e7ede9; }
        .legal-section h2 { display: flex; align-items: center; gap: var(--space-12); margin: 0 0 var(--space-12); color: #163e27; font-size: var(--text-heading-md); font-weight: 800; }
        .legal-section h2 span { width: 26px; height: 26px; flex: 0 0 26px; display: grid; place-items: center; border-radius: 8px; color: #17683f; background: #e5f3ea; font-size: var(--text-caption); }
        .legal-section p, .legal-section li { color: #54635a; font-size: var(--text-body); line-height: var(--line-prose); }
        .legal-section p { margin: 0; }
        .legal-section ul { margin: var(--space-12) 0 0; padding-left: var(--space-16); }
        .legal-section li + li { margin-top: var(--space-4); }
        .legal-footer { display: flex; justify-content: space-between; align-items: center; gap: var(--space-16); padding: var(--space-16) clamp(var(--space-24), 4vw, calc(var(--space-32) + var(--space-12))); border-top: 1px solid #e7ede9; color: #7b887f; background: #f8faf8; font-size: var(--text-caption); }
        .legal-footer nav { display: flex; flex-wrap: wrap; gap: var(--space-16); }
        .legal-footer a { color: #287348; font-weight: 700; text-decoration: none; }
        .legal-footer a[aria-current='page'] { text-decoration: underline; text-underline-offset: 3px; }
        @media (max-width: 575.98px) {
            .legal-heading__icon { width: 42px; height: 42px; flex-basis: 42px; }
            .legal-footer { align-items: flex-start; flex-direction: column; }
        }
    </style>
    @endPushOnce
</x-guest-layout>
