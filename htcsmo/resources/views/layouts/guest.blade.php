<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.partials.initial-visibility')
    <meta name="theme-color" content="#14532d">
    @include('layouts.partials.browser-title')

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/confirmations.js'])
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --auth-green-950: #052e16;
            --auth-green-900: #14532d;
            --auth-green-800: #166534;
            --auth-green-700: #15803d;
            --auth-text: #17221b;
            --auth-muted: #647067;
            --auth-border: #dce4de;
        }

        *, *::before, *::after { box-sizing: border-box; }
        html, body { min-height: 100%; }
        [hidden] { display: none !important; }

        body {
            min-height: 100dvh;
            margin: 0;
            padding: clamp(var(--space-16), 3vw, calc(var(--space-32) + var(--space-8)));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            color: var(--auth-text);
            font-family: var(--bs-body-font-family);
            background:
                radial-gradient(circle at 8% 12%, rgba(34, 197, 94, .15), transparent 26rem),
                radial-gradient(circle at 92% 88%, rgba(20, 83, 45, .12), transparent 24rem),
                #f4f7f4;
        }

        .login-card {
            width: min(100%, 34rem);
            padding: clamp(var(--space-24), 5vw, calc(var(--space-32) + var(--space-8)));
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(20, 83, 45, .08);
            border-radius: 1.5rem;
            box-shadow: 0 24px 70px rgba(15, 48, 27, .14);
        }

        .login-card:has(> .auth-page) {
            width: min(100%, 900px);
            min-height: min(540px, calc(100dvh - clamp(2rem, 6vw, 5rem)));
            padding: 0;
        }

        .login-card:has(> .auth-register-page) {
            width: min(100%, 1040px);
        }

        .login-card > .auth-page, .auth-page > .row {
            width: 100%;
            min-height: inherit;
        }

        .auth-brand-panel {
            position: relative;
            isolation: isolate;
            min-height: 100%;
            padding: clamp(var(--space-32), 3.5vw, calc(var(--space-32) + var(--space-12)));
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow: hidden;
            color: #fff;
            background: linear-gradient(145deg, var(--auth-green-950), var(--auth-green-800));
        }

        .auth-brand-panel::before, .auth-brand-panel::after {
            content: '';
            position: absolute;
            z-index: -1;
            border-radius: 999px;
            background: rgba(255, 255, 255, .06);
        }

        .auth-brand-panel::before {
            width: 18rem;
            height: 18rem;
            top: -8rem;
            right: -9rem;
        }

        .auth-legal-copy { color: #7b877f; font-size: var(--text-caption); line-height: var(--line-body); }
        .auth-legal-copy a { color: var(--auth-green-800); font-weight: 700; text-decoration: none; }
        .auth-legal-copy a:hover { text-decoration: underline; }

        .auth-brand-panel::after {
            width: 12rem;
            height: 12rem;
            bottom: -5rem;
            left: -5rem;
        }

        .auth-brand-logo {
            width: clamp(3.75rem, 6vw, 4.5rem);
            height: clamp(3.75rem, 6vw, 4.5rem);
            padding: var(--space-8);
            object-fit: contain;
            background: rgba(255, 255, 255, .96);
            border-radius: 1.2rem;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
        }

        .auth-brand-eyebrow {
            margin: var(--space-24) 0 var(--space-12);
            color: #bbf7d0;
            font-size: var(--text-caption);
            font-weight: 800;
            letter-spacing: var(--tracking-label);
            text-transform: uppercase;
        }

        .auth-brand-title {
            max-width: 20rem;
            margin: 0;
            font-size: var(--text-heading-lg);
            font-weight: 800;
            line-height: var(--line-heading);
            letter-spacing: var(--tracking-normal);
        }

        .auth-brand-description {
            max-width: 22rem;
            margin: var(--space-16) 0 0;
            color: rgba(255, 255, 255, .72);
            font-size: var(--text-secondary);
            line-height: var(--line-prose);
        }

        .auth-brand-footer {
            margin-top: var(--space-32);
            color: rgba(255, 255, 255, .55);
            font-size: var(--text-caption);
        }

        .auth-form-panel {
            min-height: 100%;
            padding: clamp(var(--space-32), 3.5vw, calc(var(--space-32) + var(--space-16)));
            display: flex;
            align-items: center;
            background: #fff;
        }

        .auth-form-wrap {
            width: 100%;
            max-width: 35rem;
            margin-inline: auto;
        }

        .auth-register-page .row.g-3 {
            --bs-gutter-y: var(--space-12);
        }

        .auth-form-heading { margin-bottom: var(--space-24); }
        .auth-form-heading h1 {
            margin: 0 0 var(--space-8);
            color: var(--auth-text);
            font-size: var(--text-heading-lg);
            font-weight: 800;
            letter-spacing: var(--tracking-normal);
        }

        .auth-form-heading p {
            margin: 0;
            color: var(--auth-muted);
            font-size: var(--text-secondary);
            line-height: var(--line-body);
        }

        .auth-form-label {
            margin-bottom: var(--space-8);
            color: #35433a;
            font-size: var(--text-secondary);
            font-weight: 700;
        }

        .auth-input .input-group-text, .auth-input .form-control, .auth-input .form-select {
            min-height: 2.75rem;
            border-color: var(--auth-border);
            background-color: #fff;
            font-size: var(--text-body);
        }

        .auth-input .input-group-text {
            padding-inline: var(--space-16);
            color: #6d786f;
            background: #f8faf8;
        }

        .auth-input .form-control { padding-inline: var(--space-12); }
        .auth-input .form-select { padding-left: var(--space-12); }
        .auth-input .form-control:focus, .auth-input .form-select:focus {
            z-index: 3;
            border-color: #22a447;
            box-shadow: 0 0 0 .22rem rgba(34, 164, 71, .12);
        }

        .auth-input .form-control::placeholder { color: #a0aaa3; }
        .password-toggle {
            min-width: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #69756c;
            background: #f8faf8;
            border-color: var(--auth-border);
        }

        .password-toggle:hover, .password-toggle:focus-visible {
            color: var(--auth-green-800);
            background: #eef8f0;
            border-color: #8dcc9c;
        }

        .auth-primary-button {
            min-height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-8);
            color: #fff;
            font-size: var(--text-secondary);
            font-weight: 800;
            background: linear-gradient(135deg, var(--auth-green-800), var(--auth-green-700));
            border: 0;
            border-radius: .65rem;
            box-shadow: 0 10px 22px rgba(21, 128, 61, .2);
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .auth-primary-button:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 13px 26px rgba(21, 128, 61, .26);
        }

        .auth-link {
            color: var(--auth-green-700);
            font-weight: 700;
            text-decoration: none;
        }

        .auth-link:hover { color: var(--auth-green-900); text-decoration: underline; }
        .auth-help { color: #7b857e; font-size: var(--text-caption); line-height: var(--line-body); }
        .invalid-feedback { font-size: var(--text-caption); }

        .auth-note {
            display: flex;
            align-items: flex-start;
            gap: var(--space-12);
            padding: var(--space-12) var(--space-16);
            color: #3e5d47;
            font-size: var(--text-caption);
            line-height: var(--line-body);
            background: #f0f9f2;
            border: 1px solid #d5efdb;
            border-radius: .75rem;
        }

        .auth-note svg { flex: 0 0 auto; margin-top: .08rem; }

        @media (min-width: 768px) and (max-width: 991.98px) {
            .auth-register-page .col-sm-6 { width: 100%; }
        }

        @media (max-width: 767.98px) {
            body { padding: 0; align-items: stretch; background: #fff; }
            .login-card:has(> .auth-page) {
                min-height: 100dvh;
                padding: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .auth-page > .row {
                min-height: 100dvh;
                flex-direction: column;
                flex-wrap: nowrap;
            }
            .auth-brand-panel { min-height: auto; padding: var(--space-24); display: block; }
            .auth-brand-main {
                display: grid;
                grid-template-columns: auto 1fr;
                column-gap: var(--space-16);
                align-items: center;
            }

            .auth-brand-logo {
                grid-row: 1 / span 2;
                width: 3.75rem;
                height: 3.75rem;
                border-radius: .9rem;
            }

            .auth-brand-eyebrow { margin: 0 0 var(--space-4); font-size: var(--text-caption); }
            .auth-brand-title { font-size: var(--text-heading-sm); line-height: var(--line-heading); }
            .auth-brand-description, .auth-brand-footer { display: none; }
            .auth-form-panel {
                min-height: 0;
                flex: 1 0 auto;
                padding: var(--space-32) var(--space-24) calc(var(--space-32) + var(--space-4));
                align-items: flex-start;
            }

            .auth-form-heading { margin-bottom: var(--space-24); }
        }

        @media (max-width: 359.98px) {
            .auth-brand-panel, .auth-form-panel { padding-left: var(--space-16); padding-right: var(--space-16); }
            .auth-brand-logo { width: 3.25rem; height: 3.25rem; }
            .auth-brand-title { font-size: var(--text-body); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
        }
    </style>
    @stack('styles')
</head>
<body>
    @include('layouts.partials.session-recovery')
    <main class="login-card">
        {{ $slot }}
    </main>

    @if(request()->routeIs('login', 'register'))
        @include('layouts.partials.auth-progress')
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.lucide) lucide.createIcons();
        });
    </script>
</body>
</html>
