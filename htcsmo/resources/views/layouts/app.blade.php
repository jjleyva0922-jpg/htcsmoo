<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @include('layouts.partials.initial-visibility')
    @include('layouts.partials.browser-title')
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- 2. Lucide Icons (Must be in head) -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --htc-green: #185b3b;
            --htc-light-green: #f0f7f1;
            --sidebar-width: 250px;
        }

        html {
            overflow-x: hidden;
            overflow-x: clip;
        }

        body {
            background:
                radial-gradient(circle at 8% 8%, rgba(34, 197, 94, .07), transparent 26rem),
                radial-gradient(circle at 92% 92%, rgba(20, 83, 45, .055), transparent 24rem),
                #f3f7f4;
            font-family: var(--bs-body-font-family);
            color: #334155;
            overflow-x: hidden;
            overflow-x: clip;
            scroll-behavior: auto !important; /* Removes smooth scrolling "anchor" feel */
        }

        :target { background: none; padding-top: 0; }

        /* Sidebar Styling */
        .sidebar { width: var(--sidebar-width); height: 100vh; height: 100dvh; position: fixed; top: 0; left: 0; isolation: isolate; overflow: hidden; background: linear-gradient(145deg, #052e16 0%, #14532d 48%, #166534 100%); border-right: 1px solid rgba(7, 42, 25, .45); z-index: 1000; padding: var(--space-24); display: flex; flex-direction: column; box-shadow: 8px 0 28px rgba(15, 56, 36, .16); }
        .sidebar::before, .sidebar::after { content: ''; position: absolute; z-index: -1; border-radius: 999px; pointer-events: none; background: rgba(255, 255, 255, .055); }
        .sidebar::before { width: 18rem; height: 18rem; top: -10rem; right: -10rem; }
        .sidebar::after { width: 13rem; height: 13rem; right: -7rem; bottom: -5rem; background: rgba(74, 222, 128, .07); }
        .sidebar > * { position: relative; z-index: 1; }
        .sidebar > .brand-section, .sidebar-footer { flex-shrink: 0; }
        .brand-section { display: flex; align-items: center; padding-bottom: var(--space-24); margin-bottom: var(--space-8); border-bottom: 1px solid rgba(255, 255, 255, .14); }
        .brand-logo { width: 45px; height: 45px; object-fit: contain; margin-right: var(--space-12); padding: var(--space-4); border-radius: 12px; background: rgba(255, 255, 255, .96); box-shadow: 0 5px 16px rgba(5, 30, 17, .18); }
        .brand-title { font-size: var(--text-body); font-weight: 800; letter-spacing: var(--tracking-label); line-height: var(--line-heading); color: #fff; text-transform: uppercase; }

        /* This is the magic part: the middle section scrolls, top and bottom stay fixed */
        .sidebar .flex-grow-1 {
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior-y: contain;
            padding-right: var(--space-4); /* Space for the scrollbar */
            margin-right: calc(-1 * var(--space-4)); /* Keeps the links aligned */
        }

        /* Optional: Make the sidebar scrollbar very thin and modern */
        .sidebar .flex-grow-1::-webkit-scrollbar {
            width: 4px;
        }
        .sidebar .flex-grow-1::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar .flex-grow-1::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, .24);
            border-radius: 10px;
        }
        .sidebar .flex-grow-1::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, .5);
        }

        /* Ensure the bottom button area stays at the bottom and has a solid background */
        .sidebar-footer {
            padding-top: var(--space-16);
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, .14);
            background: transparent;
            z-index: 10;
        }

        .nav-section-label { font-size: var(--text-caption); font-weight: 700; line-height: var(--line-heading); color: rgba(225, 242, 232, .58); text-transform: uppercase; letter-spacing: var(--tracking-label); margin: var(--space-24) 0 var(--space-12) var(--space-12); }
        .sidebar .flex-grow-1 > .nav-section-label:first-child { margin-top: var(--space-12); }
        .nav-link-custom { position: relative; display: flex; align-items: center; padding: var(--space-12) var(--space-12); color: rgba(246, 252, 248, .78); text-decoration: none; border: 1px solid transparent; border-radius: 12px; margin-bottom: var(--space-4); font-weight: 600; font-size: var(--text-secondary); transition: background-color .2s ease, border-color .2s ease, color .2s ease, transform .2s ease; }
        .nav-link-custom:hover { background-color: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .08); color: #fff; transform: translateX(3px); }
        .nav-link-custom.active { background-color: rgba(255, 255, 255, .17); border-color: rgba(255, 255, 255, .2); color: #fff; box-shadow: 0 7px 18px rgba(5, 33, 19, .18); }
        .nav-link-custom.active::before { content: ''; position: absolute; top: 22%; bottom: 22%; left: -1px; width: 3px; border-radius: 0 5px 5px 0; background: #f5cf61; }
        .nav-link-custom:focus-visible, .btn-new-request:focus-visible, .sidebar-close:focus-visible { outline: 3px solid rgba(245, 207, 97, .7); outline-offset: 2px; }
        .nav-link-custom [data-lucide] { width: 16px; height: 16px; margin-right: var(--space-12); stroke-width: 2.5px; }
        .sidebar-count { min-width: 21px; height: 21px; display: inline-grid; margin-left: auto; padding: 0 var(--space-4); place-items: center; border: 1px solid rgba(255,255,255,.2); border-radius: 999px; color: #fff; background: #c73a43; font-size: var(--text-caption); font-weight: 900; box-shadow: 0 4px 10px rgba(77,10,15,.2); }

        /* Main Content & Top Bar */
        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); min-width: 0; }
        .top-bar { min-height: 72px; background: radial-gradient(circle at 14% -170%, rgba(74, 222, 128, .26), transparent 21rem), radial-gradient(circle at 92% 180%, rgba(255, 255, 255, .11), transparent 15rem), linear-gradient(120deg, #052e16, #14532d 55%, #166534); border-bottom: 1px solid rgba(255, 255, 255, .12); display: flex; align-items: center; justify-content: space-between; gap: var(--space-16); padding: 0 var(--space-24); position: sticky; top: 0; z-index: 1010; box-shadow: 0 7px 22px rgba(9, 54, 29, .16); }
        .page-context { min-width: 0; display: flex; flex-direction: column; gap: .12rem; }
        .page-eyebrow { color: #a7e7bd; font-size: var(--text-caption); font-weight: 800; letter-spacing: var(--tracking-label); line-height: var(--line-heading); text-transform: uppercase; }
        .page-title { overflow: hidden; color: white; font-weight: 800; font-size: var(--text-heading-sm); line-height: var(--line-heading); text-overflow: ellipsis; white-space: nowrap; }
        .mobile-menu-button, .sidebar-close { display: none; }
        .sidebar-backdrop { display: none; }

        .header-actions { display: flex; align-items: center; gap: var(--space-12); }
        .role-badge { font-size: var(--text-caption); font-weight: 800; text-transform: uppercase; letter-spacing: var(--tracking-label); padding: var(--space-8) var(--space-12); border-radius: 999px; background: rgba(255, 255, 255, .1); color: #d8f5e2; border: 1px solid rgba(255, 255, 255, .16); }
        .user-menu-trigger { min-height: 50px; padding: var(--space-4) var(--space-8) var(--space-4) var(--space-12) !important; border: 1px solid transparent !important; color: #fff; transition: background-color .2s ease, border-color .2s ease; }
        .user-menu-trigger:hover, .user-menu-trigger:focus, .user-menu-trigger[aria-expanded="true"] { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .14) !important; color: #fff; }
        .header-user-copy { text-align: right; line-height: var(--line-heading); }
        .header-user-copy strong { display: block; max-width: 170px; overflow: hidden; color: #fff; font-size: var(--text-caption); text-overflow: ellipsis; white-space: nowrap; }
        .header-user-copy small { color: rgba(230, 247, 236, .7); font-size: var(--text-caption); }
        .header-avatar { width: 40px; height: 40px; flex: 0 0 40px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 2px solid rgba(255, 255, 255, .72); border-radius: 50%; background: #fff; color: #14532d; font-weight: 800; box-shadow: 0 5px 14px rgba(2, 28, 13, .24); }
        .header-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .header-menu-chevron { width: 14px; height: 14px; color: rgba(235, 248, 239, .75); transition: transform .2s ease; }
        .user-menu-trigger[aria-expanded="true"] .header-menu-chevron { transform: rotate(180deg); }
        .top-bar .dropdown-menu { min-width: 220px; padding: var(--space-8) !important; border: 1px solid rgba(20, 83, 45, .1) !important; border-radius: 14px; box-shadow: 0 16px 38px rgba(11, 50, 28, .18) !important; }
        .top-bar .dropdown-item { min-height: 38px; }
        .main-content-inner { background: linear-gradient(145deg, rgba(255, 255, 255, .98), rgba(248, 251, 249, .96)); border-radius: 18px; box-shadow: 0 12px 34px rgba(17, 62, 38, .07); min-height: calc(100vh - 160px); border: 1px solid rgba(24, 91, 59, .1); padding: var(--space-24) !important; }

        /* Shared application component system */
        .main-content-inner .card { border: 1px solid #e0e9e3 !important; border-radius: 16px !important; background: rgba(255, 255, 255, .96); box-shadow: 0 7px 22px rgba(24, 70, 43, .055) !important; transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
        .main-content-inner .card:hover { border-color: rgba(24, 91, 59, .22) !important; box-shadow: 0 12px 28px rgba(24, 70, 43, .085) !important; }
        .main-content-inner .card-header { padding: var(--space-16) var(--space-16); border-bottom: 1px solid #e5ece7; border-radius: 16px 16px 0 0 !important; background: linear-gradient(135deg, #f7fbf8, #eef6f1); color: #213c2d; font-weight: 800; }
        .main-content-inner .card-footer { padding: var(--space-16) var(--space-16); border-top: 1px solid #e7ede9; border-radius: 0 0 16px 16px !important; background: #fafcfb; }

        .main-content-inner .btn { border-radius: 10px; font-weight: 700; transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease; }
        .main-content-inner .btn:hover:not(:disabled) { transform: translateY(-1px); }
        .main-content-inner :is(.btn-success, .btn-primary, .btn-htc) { border-color: #17633e; background: linear-gradient(135deg, #17633e, #218a55); color: #fff; box-shadow: 0 6px 15px rgba(24, 91, 59, .18); }
        .main-content-inner :is(.btn-success, .btn-primary, .btn-htc):hover { border-color: #104a2e; background: linear-gradient(135deg, #104a2e, #176f44); color: #fff; box-shadow: 0 8px 18px rgba(24, 91, 59, .24); }
        .main-content-inner .btn-light { border-color: #dce6df; background: #f8fbf9; color: #42564a; }
        .main-content-inner .btn-light:hover { border-color: #bdcec3; background: #eef5f0; color: #234432; }
        .main-content-inner .btn-outline-success { border-color: #7eaa90; color: #17633e; }
        .main-content-inner .btn-outline-success:hover { border-color: #17633e; background: #17633e; color: #fff; }
        .main-content-inner .btn-danger { border-color: #bd3840; background: linear-gradient(135deg, #bd3840, #dc4d55); box-shadow: 0 5px 14px rgba(189, 56, 64, .16); }
        .main-content-inner .btn:focus-visible { outline: 3px solid rgba(44, 153, 91, .24); outline-offset: 2px; box-shadow: none; }

        .main-content-inner :is(.form-label, label.form-label) { margin-bottom: var(--space-8); color: #53665a !important; font-size: var(--text-secondary); font-weight: 800 !important; letter-spacing: var(--tracking-label); text-transform: uppercase; }
        .main-content-inner :is(.form-control, .form-select) { min-height: 40px; border: 1px solid #d8e4dc; border-radius: 10px; background-color: #f9fbfa; color: #26372d; box-shadow: inset 0 0 0 1px rgba(216, 228, 220, .35); transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease; }
        .main-content-inner :is(.form-control, .form-select):hover:not(:disabled):not([readonly]) { border-color: #b8ccbf; background-color: #fff; }
        .main-content-inner :is(.form-control, .form-select):focus { border-color: #2d8a57; background-color: #fff; box-shadow: 0 0 0 3px rgba(31, 125, 75, .12); }
        .main-content-inner .form-control[readonly], .main-content-inner .form-control:disabled { background-color: #eef2ef; color: #7b887f; }
        .main-content-inner .input-group-text { border-color: #d8e4dc; border-radius: 10px 0 0 10px; background: #eef6f1 !important; color: #39704f; }
        .main-content-inner .form-check-input:checked { border-color: #1b7045; background-color: #1b7045; }
        .main-content-inner .invalid-feedback { font-size: var(--text-caption); font-weight: 600; }

        .main-content-inner .table-responsive { border: 1px solid #e1e9e4; border-radius: 14px; background: #fff; }
        .main-content-inner .table { margin-bottom: 0; --bs-table-bg: transparent; --bs-table-hover-bg: #f4f9f6; }
        .main-content-inner .table thead th { padding: var(--space-12) var(--space-12); border-bottom-color: #dce6df; background: #f3f8f5; color: #607168; font-size: var(--text-caption); font-weight: 800; letter-spacing: var(--tracking-label); text-transform: uppercase; vertical-align: middle; }
        .main-content-inner .table tbody td { padding: var(--space-12) var(--space-12); border-color: #e8eeea; color: #3d4d43; vertical-align: middle; }
        .main-content-inner .table tbody tr:last-child td { border-bottom: 0; }

        .main-content-inner .alert { border: 1px solid transparent !important; border-left-width: 4px !important; border-radius: 12px !important; box-shadow: 0 6px 18px rgba(24, 60, 39, .055); }
        .main-content-inner .alert-success { border-color: #8fc7a5 !important; background: #edf8f1; color: #205f3b; }
        .main-content-inner .alert-warning { border-color: #e1b54e !important; background: #fff9e9; color: #795810; }
        .main-content-inner .alert-danger { border-color: #df7b81 !important; background: #fff1f2; color: #8d2930; }
        .main-content-inner .badge { padding: var(--space-8) var(--space-8); border-radius: 999px; font-weight: 800; letter-spacing: var(--tracking-label); }
        .main-content-inner .nav-pills { gap: var(--space-4); padding: var(--space-4); border: 1px solid #e0e9e3; border-radius: 12px; background: #f4f8f5; }
        .main-content-inner .nav-pills .nav-link { border-radius: 9px; color: #5e7065; font-weight: 700; }
        .main-content-inner .nav-pills .nav-link.active { background: linear-gradient(135deg, #17633e, #218a55); color: #fff; box-shadow: 0 5px 13px rgba(24, 91, 59, .18); }
        .main-content-inner .list-group-item { border-color: #e5ece7; color: #4d5f54; }
        .main-content-inner .list-group-item-action:hover { background: #eef7f1; color: #185b3b; }
        .main-content-inner nav.d-flex.justify-items-center { width: 100%; align-items: center; gap: var(--space-16); }
        .main-content-inner nav.d-flex.justify-items-center p { margin: 0; color: #7b8981 !important; font-size: var(--text-body); }
        .main-content-inner .pagination { flex-wrap: wrap; gap: var(--space-4); margin: 0; }
        .main-content-inner .page-item { margin: 0; }
        .main-content-inner .page-link { min-width: 34px; min-height: 34px; display: inline-flex; align-items: center; justify-content: center; padding: var(--space-8) var(--space-12); border: 1px solid #dce6df; border-radius: 8px !important; color: #286343; background: #fff; font-size: var(--text-caption); font-weight: 700; line-height: var(--line-heading); text-align: center; box-shadow: none; }
        .main-content-inner .page-link:hover { border-color: #76a88a; color: #155d38; background: #edf7f0; }
        .main-content-inner .page-link:focus { border-color: #2e8255; box-shadow: 0 0 0 3px rgba(31, 125, 75, .12); }
        .main-content-inner .page-item.active > .page-link { border-color: #185b3b; background: linear-gradient(135deg, #185b3b, #218a55); color: #fff; box-shadow: 0 5px 12px rgba(24, 91, 59, .2); }
        .main-content-inner .page-item.disabled > .page-link { border-color: #e8ede9; color: #a8b1ab; background: #f6f8f7; opacity: .75; }

        .modal-backdrop.show { opacity: .58; backdrop-filter: blur(3px); }
        .modal-content { border: 1px solid rgba(24, 91, 59, .11) !important; border-radius: 18px !important; box-shadow: 0 24px 65px rgba(8, 42, 23, .22) !important; }
        .modal-header { padding: var(--space-16) var(--space-16); border-bottom-color: #e3ebe6; background: linear-gradient(135deg, #f8fbf9, #eef6f1); }
        .modal-footer { padding: var(--space-12) var(--space-16); border-top-color: #e5ece7; background: #fafcfb; }

        .btn-new-request { background-color: #fff; color: var(--htc-green); border-radius: 12px; padding: var(--space-12); font-weight: 800; border: 1px solid rgba(255, 255, 255, .5); box-shadow: 0 7px 18px rgba(5, 33, 19, .18); transition: all 0.25s ease; }
        .btn-new-request:hover { background-color: #edf8f1; color: #10462e; transform: translateY(-2px); }
        .sidebar-footer .text-muted { color: rgba(235, 247, 239, .7) !important; opacity: 1 !important; }

        .dropdown-menu {
            display: block;
            visibility: hidden;
            opacity: 0;
            transform: translateY(15px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            border: none !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
        }

        .dropdown-menu.show {
            visibility: visible;
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        .dropdown-item {
            transition: all 0.2s ease;
            padding: var(--space-8) var(--space-16);
        }

        .dropdown-item:hover {
            background-color: var(--htc-light-green);
            color: var(--htc-green);
            transform: translateX(5px);
        }


        /* --- UNIVERSAL STEPPER (Shopee-style) --- */
        .tracking-stepper {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            width: 100%;
            margin: 0 auto var(--space-32) auto;
            padding: var(--space-24) 0;
            flex-direction: row !important;
        }

        .step-item {
            flex: 1;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1;
            min-width: 80px;
        }

        .step-item::after {
            content: "";
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 3px;
            background-color: #e2e8f0;
            z-index: -1;
            transition: all 0.5s ease;
        }

        .step-item:last-child::after {
            display: none;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            background: white;
            border: 3px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--space-12);
            transition: all 0.3s ease;
        }

        .step-icon [data-lucide] {
            width: 18px;
            height: 18px;
            color: #94a3b8;
        }

        .step-item.completed .step-icon {
            background-color: var(--htc-green);
            border-color: var(--htc-green);
            color: white !important;
        }

        .step-item.completed .step-icon [data-lucide] {
            color: white !important;
            stroke: currentColor;
        }

        .step-item.completed::after {
            background-color: var(--htc-green);
        }

        .step-item.active .step-icon {
            border-color: #ffc107;
            color: #ffc107;
            box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.1);
        }

        .step-item.active .step-icon [data-lucide] {
            color: #ffc107 !important;
            stroke: currentColor;
        }

        .step-item.active .step-icon {
            border-color: #ffc107;
            color: #ffc107;
            box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.2);
        }

        .step-label {
            font-size: var(--text-secondary);
            font-weight: 800;
            text-transform: uppercase;
            color: #94a3b8;
            letter-spacing: var(--tracking-label);
            text-align: center;
            max-width: 100px;
            line-height: var(--line-heading);
        }

        .step-item.questioning .step-icon {
            border-color: #fd7e14 !important;
            color: #fd7e14 !important;
            background-color: #fff8f3 !important;
            box-shadow: 0 0 0 4px rgba(253, 126, 20, 0.25) !important;
            animation: pulseWarning 2s infinite !important;
        }

        .step-item.questioning .step-icon [data-lucide] {
            color: #fd7e14 !important;
            stroke: currentColor;
        }

        .step-item.questioning .step-label {
            color: #fd7e14 !important;
            font-weight: 800 !important;
        }

        .step-item.active .step-label {
            color: #856404;
        }

        @keyframes lineFlow {
            0% { background-position: 100% 0%; }
            100% { background-position: -100% 0%; }
        }

        @keyframes pulseActive {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }

        @keyframes pulseWarning {
            0% { box-shadow: 0 0 0 0 rgba(253, 126, 20, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(253, 126, 20, 0); }
            100% { box-shadow: 0 0 0 0 rgba(253, 126, 20, 0); }
        }

        .step-item.in-progress::after {
            background: linear-gradient(90deg, var(--htc-green) 0%, #e2e8f0 50%, var(--htc-green) 100%);
            background-size: 200% 100%;
            animation: lineFlow 2s linear infinite;
        }

        .step-item.active .step-icon {
            border-color: #ffc107;
            animation: pulseActive 2s infinite;
        }

        .step-item .step-icon,
        .step-item::after {
            transition: all 0.5s ease-in-out;
        }

        /* --- MODERN PROCUREMENT CARDS --- */
        .procurement-card {
            background: white;
            border: 1px solid #f1f5f9;
            border-radius: 20px;
            padding: var(--space-24);
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            height: 100%;
        }

        .procurement-card:hover {
            border-color: var(--htc-green);
            background-color: #f8fafc;
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 10px 10px -5px rgba(0, 0, 0, 0.02);
        }

        .card-icon-wrapper {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-right: var(--space-24);
            transition: all 0.3s ease;
        }

        .procurement-card:hover .card-icon-wrapper {
            transform: scale(1.1) rotate(-5deg);
        }

        .card-content {
            flex-grow: 1;
        }

        .card-arrow {
            color: #cbd5e1;
            transition: all 0.3s ease;
            padding-left: var(--space-12);
        }

        .procurement-card:hover .card-arrow {
            color: var(--htc-green);
            transform: translateX(5px);
        }

        /* Modal Inner Background Color */
        #globalRequestModal .modal-content {
            background-color: #ffffff;
        }

        #globalRequestModal .bg-light {
            background-color: #f8fafc !important;
        }

        #globalRequestModal .btn-success {
            white-space: nowrap !important; /* Prevents text from stacking vertically */
            min-height: 50px; /* Ensures it has a proper button shape */
        }

        #globalRequestModal .btn-htc {
            border: 1px solid #cbd5cf;
            background: #e8edea;
            color: #7b8981;
            box-shadow: none;
            transition: background-color .18s ease, border-color .18s ease, color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        #globalRequestModal .btn-htc:not(:disabled) {
            border-color: #17633e;
            background: linear-gradient(135deg, #17633e, #218a55);
            color: #fff;
            box-shadow: 0 6px 15px rgba(24, 91, 59, .2);
        }

        #globalRequestModal .btn-htc:not(:disabled):hover {
            border-color: #104a2e;
            background: linear-gradient(135deg, #104a2e, #176f44);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(24, 91, 59, .26);
        }

        #globalRequestModal .btn-htc:disabled {
            cursor: not-allowed;
            opacity: 1;
        }

        #globalRequestModal .btn-htc:focus-visible {
            outline: 3px solid rgba(44, 153, 91, .24);
            outline-offset: 2px;
        }

        .grayscale {
            filter: grayscale(1);
        }

        #smoTabs .nav-link {
            color: #64748b;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        #smoTabs .nav-link.active {
            background-color: var(--htc-green) !important;
            color: white !important;
            border-color: var(--htc-green) !important;
            box-shadow: 0 4px 12px rgba(24, 91, 59, 0.2);
        }

        #smoTabs .badge {
            background-color: rgba(255, 255, 255, 0.2) !important;
        }



        /* --- PROCUREMENT ENGINE STYLES --- */
        .selection-card {
            background: white;
            border: 2px solid #f1f5f9;
            padding: calc(var(--space-32) + var(--space-8)) var(--space-24);
            border-radius: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }

        .selection-card i { width: 50px; height: 50px; transition: all 0.3s ease; }
        .selection-card h4 { font-weight: 800; margin-top: var(--space-16); color: #1e293b; }
        .selection-card p { font-size: var(--text-body); color: #64748b; margin-bottom: 0; }

        .selection-card.minor:hover { border-color: #10b981; background: #f0fdf4; transform: translateY(-10px); }
        .selection-card.major:hover { border-color: #f59e0b; background: #fffbeb; transform: translateY(-10px); }

        .selection-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: var(--text-caption);
            font-weight: 800;
            padding: var(--space-4) var(--space-12);
            border-radius: 20px;
            background: #f1f5f9;
            color: #94a3b8;
        }

        .selection-card.minor:hover .selection-badge { background: #10b981; color: white; }
        .selection-card.major:hover .selection-badge { background: #f59e0b; color: white; }

        .mini-label { font-size: var(--text-secondary); font-weight: 800; text-transform: uppercase; color: #94a3b8; letter-spacing: var(--tracking-label); margin-bottom: var(--space-4); }

        .summary-stat { display: flex; justify-content: space-between; font-size: var(--text-secondary); color: #64748b; }

        .btn-outline-dashed {
            border: 2px dashed #e2e8f0;
            color: #94a3b8;
            transition: all 0.3s;
        }
        .btn-outline-dashed:hover {
            border-color: var(--htc-green);
            color: var(--htc-green);
            background: var(--htc-light-green);
        }

        /* Custom Scrollbar for Chrome/Edge/Safari */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .footer-note { font-size: var(--text-caption); font-weight: bold; margin-top: var(--space-24); opacity: 0.5; }

        /* Requisition marketplace */
        #globalRequestModal { padding: 0 !important; }
        .requisition-dialog {
            width: calc(100% - 3rem);
            max-width: 1440px;
            height: min(840px, calc(100dvh - 4rem));
            margin: var(--space-32) auto;
        }

        .requisition-shell {
            border-radius: 20px !important;
            height: 100%;
            max-height: none;
            min-height: 0;
            background: #fff;
            display: flex;
            flex-direction: column;
            box-shadow: 0 28px 70px rgba(16, 45, 29, .2);
        }

        .requisition-header {
            flex: 0 0 auto;
            min-height: 56px;
            padding: var(--space-8) var(--space-24);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-16);
            border-bottom: 1px solid #e8eeeb;
        }

        .requisition-header h4 { font-size: var(--text-heading-sm); }
        .requisition-header .btn-close { box-sizing: border-box; width: 44px; height: 44px; padding: 0; flex-shrink: 0; background-size: 12px; }
        .requisition-cart-link { display: inline-flex; align-items: center; gap: var(--space-8); margin-left: auto; padding: var(--space-8) var(--space-12); border: 1px solid #bad9c6; border-radius: 10px; background: #edf7f0; color: #185b3b; font-size: var(--text-secondary); font-weight: 700; }
        .requisition-cart-link svg { width: 17px; height: 17px; }
        .requisition-cart-link span { min-width: 22px; padding: .1rem var(--space-4); border-radius: 99px; background: #185b3b; color: #fff; }

        .tier-stage {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            background: radial-gradient(circle at 50% 0, #f0f8f3, #fff 52%);
        }

        .tier-choice {
            min-height: 280px;
            border: 1px solid #dfe8e3;
            border-radius: 22px;
            background: #fff;
            padding: var(--space-24);
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            text-align: left;
            transition: .2s ease;
            box-shadow: 0 12px 30px rgba(15, 45, 29, .05);
        }

        .tier-choice:hover {
            transform: translateY(-5px);
            border-color: var(--htc-green);
            box-shadow: 0 20px 44px rgba(24, 91, 59, .12);
        }

        .tier-choice .tier-icon {
            width: 58px;
            height: 58px;
            border-radius: 17px;
            display: grid;
            place-items: center;
            margin-bottom: var(--space-16);
        }

        .tier-choice .tier-icon svg {
            width: 27px;
            height: 27px;
        }

        .tier-minor .tier-icon {
            background: #e9f7ef;
            color: #137044;
        }

        .tier-major .tier-icon {
            background: #fff5dd;
            color: #a16207;
        }

        .tier-choice .tier-kicker {
            font-size: var(--text-caption);
            font-weight: 800;
            letter-spacing: var(--tracking-label);
            text-transform: uppercase;
            color: #94a3b8;
        }

        .tier-choice strong {
            display: block;
            color: #14251c;
            font-size: var(--text-heading-sm);
            margin: var(--space-4) 0 var(--space-12);
        }

        .tier-choice > span:not(.tier-icon):not(.tier-kicker) {
            color: #64748b;
            line-height: var(--line-prose);
        }

        .tier-choice small {
            margin-top: auto;
            padding-top: var(--space-24);
            border-top: 1px solid #edf1ef;
            width: 100%;
            color: #64748b;
        }

        .requisition-form {
            display: flex;
            flex: 1;
            min-height: 0;
            flex-direction: column;
            overflow: hidden;
        }

        .requisition-confirmation {
            position: fixed;
            inset: 0;
            z-index: 20;
            display: grid;
            place-items: center;
            padding: var(--space-24);
            overflow-y: auto;
            background: rgba(8, 34, 19, .58);
            backdrop-filter: blur(4px);
        }

        .requisition-confirmation__dialog {
            width: min(100%, 470px);
            max-height: calc(100dvh - 3rem);
            overflow-y: auto;
            padding: var(--space-32);
            border: 1px solid rgba(255, 255, 255, .72);
            border-radius: 22px;
            background: #fff;
            text-align: center;
            box-shadow: 0 28px 70px rgba(8, 34, 19, .28);
        }

        .requisition-confirmation__icon {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            margin: 0 auto var(--space-16);
            border-radius: 18px;
            background: #e3f4e9;
            color: #187044;
        }

        .requisition-confirmation__icon svg { width: 28px; height: 28px; }
        .requisition-confirmation__eyebrow { color: #187044; font-size: var(--text-caption); font-weight: 800; letter-spacing: var(--tracking-label); text-transform: uppercase; }
        .requisition-confirmation h5 { margin: var(--space-4) 0 var(--space-8); color: #17291f; font-weight: 800; }
        .requisition-confirmation p { margin-bottom: var(--space-24); color: #6c7c73; font-size: var(--text-body); }
        .requisition-confirmation__summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--space-12); padding: var(--space-16); border: 1px solid #dce9e1; border-radius: 14px; background: #f5faf7; text-align: left; }
        .requisition-confirmation__summary .wide { grid-column: 1 / -1; }
        .requisition-confirmation__summary span { display: block; margin-bottom: var(--space-4); color: #718078; font-size: var(--text-caption); font-weight: 700; letter-spacing: var(--tracking-label); text-transform: uppercase; }
        .requisition-confirmation__summary strong { display: block; color: #26382e; font-size: var(--text-secondary); overflow-wrap: anywhere; }
        .requisition-confirmation__actions { display: flex; justify-content: center; gap: var(--space-12); margin-top: var(--space-24); }
        .requisition-confirmation__actions .btn { min-height: 44px; padding-inline: var(--space-16); }
        .requisition-confirmation__actions svg { width: 16px; margin-right: var(--space-4); }
        .requisition-confirmation__loading { padding-block: calc(var(--space-32) + var(--space-8)); }
        .requisition-loading-circle { width: 52px; height: 52px; display: block; margin: 0 auto var(--space-16); border: 5px solid #dcebe2; border-top-color: #187044; border-radius: 50%; animation: requisition-loading-spin .72s linear infinite; }
        @keyframes requisition-loading-spin { to { transform: rotate(360deg); } }

        .requisition-workspace {
            display: grid;
            grid-template-columns: minmax(0, 1fr) clamp(340px, 30vw, 400px);
            flex: 1;
            min-height: 0;
        }

        .catalog-pane {
            container: requisition-catalog / inline-size;
            min-width: 0;
            padding: var(--space-24) var(--space-32);
            background: #f7f9f8;
            height: 100%;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .catalog-toolbar {
            display: flex;
            align-items: center;
            gap: var(--space-12);
        }

        .catalog-toolbar svg,
        .product-configurator .btn svg,
        .btn-htc svg {
            width: 15px;
            height: 15px;
        }

        .tier-pill {
            padding: var(--space-8) var(--space-12);
            border-radius: 999px;
            font-size: var(--text-caption);
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .tier-pill.minor {
            color: #137044;
            background: #e7f6ed;
        }

        .tier-pill.major {
            color: #946200;
            background: #fff3d1;
        }

        .catalog-search {
            margin-left: auto;
            flex: 1;
            max-width: 320px;
            height: 40px;
            display: flex;
            align-items: center;
            gap: var(--space-8);
            padding: 0 var(--space-12);
            border: 1px solid #dfe7e2;
            border-radius: 12px;
            background: #fff;
        }

        .catalog-search svg {
            width: 16px;
            color: #94a3b8;
        }

        .catalog-search input {
            width: 100%;
            border: 0;
            outline: 0;
            font-size: var(--text-body);
            background: transparent;
        }

        .category-chips {
            display: flex;
            gap: var(--space-8);
            padding: var(--space-24) 0;
            overflow-x: auto;
        }

        .category-chips button {
            border: 1px solid #dfe7e2;
            background: #fff;
            color: #64748b;
            border-radius: 999px;
            padding: var(--space-8) var(--space-12);
            font-size: var(--text-caption);
            font-weight: 700;
            white-space: nowrap;
        }

        .category-chips button.active {
            background: var(--htc-green);
            color: #fff;
            border-color: var(--htc-green);
        }

        .catalog-heading {
            display: flex;
            align-items: end;
            justify-content: space-between;
            margin: var(--space-8) 0 var(--space-16);
            gap: var(--space-16);
            flex-wrap: wrap;
        }

        .catalog-display-controls { display: flex; align-items: center; flex-wrap: wrap; gap: var(--space-12); margin-left: auto; }
        .catalog-layout-toggle { display: inline-flex; gap: var(--space-4); padding: var(--space-4); border: 1px solid #dce6df; border-radius: 11px; background: #fff; }
        .catalog-layout-toggle button { display: inline-flex; align-items: center; justify-content: center; gap: var(--space-8); min-height: 38px; padding: var(--space-8) var(--space-12); border: 0; border-radius: 8px; color: #61746a; background: transparent; font-size: var(--text-caption); font-weight: 700; }
        .catalog-layout-toggle button.active { color: #fff; background: var(--htc-green); }
        .catalog-layout-toggle button:focus-visible, .product-card:focus-visible { outline: 3px solid #80b593; outline-offset: 3px; }
        .catalog-layout-toggle svg { width: 15px; height: 15px; }

        .catalog-notice {
            display: flex;
            align-items: center;
            gap: var(--space-8);
            margin: var(--space-16) 0 0;
            padding: var(--space-12) var(--space-12);
            border: 1px solid #bfe2ca;
            border-radius: 10px;
            background: #edf8f1;
            color: #17613b;
            font-size: var(--text-caption);
            font-weight: 700;
        }

        .catalog-notice svg {
            width: 16px;
            height: 16px;
        }

        .catalog-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: var(--space-16);
        }

        @container requisition-catalog (min-width: 330px) {
            .catalog-grid:not(.is-list) { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @container requisition-catalog (min-width: 560px) {
            .catalog-grid:not(.is-list) { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @container requisition-catalog (min-width: 760px) {
            .catalog-grid:not(.is-list) { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @container requisition-catalog (min-width: 1060px) {
            .catalog-grid:not(.is-list) { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        }

        .product-card {
            display: flex;
            flex-direction: column;
            min-width: 0;
            padding: 0;
            overflow: hidden;
            border: 1px solid #e1e8e4;
            border-radius: 16px;
            background: #fff;
            text-align: left;
            transition: .2s ease;
        }

        .product-card:hover {
            border-color: var(--htc-green);
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(24, 91, 59, .1);
        }

        .product-visual {
            width: 100%;
            flex-shrink: 0;
            min-height: 105px;
            background: linear-gradient(135deg, #e7f2eb, #f9fbfa);
            display: grid;
            place-items: center;
            position: relative;
            color: var(--htc-green);
        }

        .product-visual svg {
            width: 36px;
            height: 36px;
        }

        .product-visual em {
            position: absolute;
            left: .75rem;
            bottom: .6rem;
            font-size: var(--text-caption);
            font-style: normal;
            font-weight: 700;
            color: #5c7567;
        }

        .product-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 0;
            padding: var(--space-16);
        }

        .product-body > small {
            color: #8b9b92;
            font-size: var(--text-caption);
            text-transform: uppercase;
            letter-spacing: var(--tracking-label);
        }

        .product-body > strong {
            color: #15251c;
            margin: var(--space-4) 0;
            font-size: var(--text-secondary);
            overflow-wrap: anywhere;
        }

        .product-spec {
            color: #718078;
            font-size: var(--text-caption);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: end;
            margin-top: auto;
            padding-top: var(--space-12);
            gap: var(--space-4);
            flex-wrap: wrap;
        }

        .catalog-grid.is-list .product-card { flex-direction: row; min-height: 110px; }
        .catalog-grid.is-list .product-visual { width: 100px; min-height: 110px; }
        .catalog-grid.is-list .product-visual em { right: .5rem; left: .5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .catalog-grid.is-list .product-body { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-content: center; column-gap: var(--space-16); }
        .catalog-grid.is-list .product-body > small,
        .catalog-grid.is-list .product-body > strong,
        .catalog-grid.is-list .product-spec { grid-column: 1; }
        .catalog-grid.is-list .product-meta { grid-column: 2; grid-row: 1 / span 3; flex-direction: column; align-items: flex-end; align-self: center; margin-top: 0; padding-top: 0; }

        .product-meta b {
            color: var(--htc-green);
            font-size: var(--text-body);
        }

        .product-meta em {
            color: #87958d;
            font-size: var(--text-caption);
            font-style: normal;
        }

        .empty-catalog,
        .cart-empty {
            min-height: 260px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #94a3b8;
            gap: var(--space-8);
        }

        .empty-catalog svg,
        .cart-empty svg {
            width: 40px;
            height: 40px;
            opacity: .45;
        }

        .empty-catalog strong,
        .cart-empty strong {
            color: #526158;
        }

        .product-configurator {
            padding: var(--space-8) 0;
        }

        .product-hero {
            height: 330px;
            border-radius: 20px;
            background: linear-gradient(145deg, #dceee3, #f5f9f6);
            color: var(--htc-green);
            display: flex;
            flex-direction: column;
            gap: var(--space-16);
            align-items: center;
            justify-content: center;
        }

        .product-hero svg {
            width: 75px;
            height: 75px;
        }

        .product-hero span {
            font-size: var(--text-caption);
            font-weight: 800;
            text-transform: uppercase;
        }

        .product-description {
            color: #6b7c72;
            font-size: var(--text-secondary);
            line-height: var(--line-body);
        }

        .option-group {
            margin-top: var(--space-16);
        }

        .option-group label {
            display: block;
            font-size: var(--text-secondary);
            font-weight: 800;
            margin-bottom: var(--space-8);
            color: #34473c;
        }

        .option-group > div {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-8);
        }

        .option-group button {
            padding: var(--space-8) var(--space-12);
            border: 1px solid #d9e2dd;
            border-radius: 8px;
            background: #fff;
            color: #5a6c62;
            font-size: var(--text-caption);
        }

        .option-group button.active {
            border-color: var(--htc-green);
            color: var(--htc-green);
            background: #eff8f2;
            box-shadow: inset 0 0 0 1px var(--htc-green);
        }

        .quantity-picker,
        .mini-quantity {
            display: flex;
            align-items: center;
            border: 1px solid #dce4df;
            border-radius: 9px;
            overflow: hidden;
            background: #fff;
        }

        .quantity-picker button,
        .mini-quantity button {
            border: 0;
            background: #f5f7f6;
            color: #43544a;
        }

        .quantity-picker button {
            width: 34px;
            height: 42px;
        }

        .quantity-picker input {
            width: 48px;
            height: 42px;
            border: 0;
            text-align: center;
            font-weight: 700;
        }

        .cart-pane {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
            background: #fff;
            border-left: 1px solid #e4ebe7;
            min-width: 0;
            overflow: hidden;
        }

        .cart-title {
            flex: 0 0 auto;
            padding: var(--space-12) var(--space-16);
            border-bottom: 1px solid #c9dfd1;
            background: #185b3b;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cart-title > div {
            display: flex;
            align-items: center;
            gap: var(--space-12);
        }

        .cart-title > span {
            color: #fff;
            background: rgba(255,255,255,.17);
            padding: var(--space-4) var(--space-8);
            border-radius: 99px;
            font-size: var(--text-caption);
        }

        .cart-body { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; scrollbar-gutter: stable; background: #f7f9f8; }
        .cart-continue { display: none; }
        .cart-reason { padding: var(--space-16); background: #edf7f0; border-top: 1px solid #c9dfd1; }
        .cart-reason .reason-field { margin-bottom: 0 !important; }
        .cart-reason .reason-field label { display: flex; align-items: center; justify-content: space-between; gap: var(--space-8); font-size: var(--text-secondary); }
        .cart-reason .reason-field p { margin: 0 0 var(--space-8); color: #526b5d; font-size: var(--text-secondary); line-height: var(--line-body); }
        .reason-required { padding: var(--space-4) var(--space-8); border-radius: 6px; background: #fff1d6; color: #805400; font-size: var(--text-caption); }
        .cart-reason .reason-field textarea { min-height: 74px; border-color: #a7c7b3; font-size: var(--text-body); }
        #globalRequestModal .cart-empty { min-height: 100px; padding-block: var(--space-16); font-size: var(--text-secondary); }

        .cart-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: grid;
            place-items: center;
            color: var(--htc-green);
            background: #eaf5ee;
        }

        .cart-icon svg {
            width: 16px;
        }

        .cart-items {
            padding: var(--space-16);
            display: grid;
            gap: var(--space-12);
        }

        .cart-item {
            border: 1px solid #dce6df;
            border-radius: 12px;
            padding: var(--space-12);
            background: #fff;
            overflow-wrap: anywhere;
        }

        .cart-item.is-recently-added { border-color: #82b695; box-shadow: 0 0 0 1px #82b695; }
        .cart-item-heading { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--space-8); }
        .cart-item-controls { display: flex; align-items: center; justify-content: space-between; gap: var(--space-12); margin-top: var(--space-12); }
        .cart-item-controls b { color: #185b3b; font-size: var(--text-body); }

        .cart-item strong {
            color: #26382e;
            font-size: var(--text-body);
            line-height: var(--line-body);
        }

        .cart-item small {
            display: block;
            color: #52645a;
            font-size: var(--text-caption);
            margin-top: var(--space-4);
        }

        .cart-item .cart-remove {
            display: grid;
            place-items: center;
            flex: 0 0 32px;
            width: 32px;
            height: 32px;
            margin-top: calc(-1 * var(--space-4));
            border: 0;
            border-radius: 8px;
            background: #f3f6f4;
            color: #52645a;
        }

        .cart-item .cart-remove:hover { background: #fff0f1; color: #ae2531; }
        .cart-item .cart-unit-price { margin-top: var(--space-8); text-align: right; }
        .cart-item > div button svg {
            width: 14px;
            height: 14px;
        }

        .mini-quantity button {
            width: 36px;
            height: 36px;
            font-weight: 700;
        }

        .mini-quantity button:disabled { color: #87958d; cursor: not-allowed; }
        .mini-quantity button:not(:disabled):hover { background: #e5f2e9; color: #185b3b; }

        .mini-quantity span {
            min-width: 28px;
            text-align: center;
            font-size: var(--text-secondary);
            font-weight: 700;
        }

        .cart-summary {
            flex: 0 0 auto;
            max-height: none;
            padding: var(--space-12) var(--space-16);
            background: #f8faf9;
            border-top: 1px solid #e4ebe7;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .cart-submit { min-height: 44px; }
        #globalRequestModal button:focus-visible { outline: 3px solid #82b695; outline-offset: 2px; }

        .reason-field {
            display: block !important;
            margin-bottom: var(--space-16) !important;
            color: inherit !important;
        }

        .reason-field label {
            display: block;
            margin-bottom: var(--space-8);
            color: #34473c;
            font-size: var(--text-secondary);
            font-weight: 800;
        }

        .reason-field textarea {
            display: block;
            width: 100%;
            resize: vertical;
            min-height: 74px;
            padding: var(--space-12) var(--space-12);
            border: 1px solid #d7e1db;
            border-radius: 10px;
            background: #fff;
            color: #283a30;
            font: inherit;
            font-size: var(--text-body);
            line-height: var(--line-body);
            outline: none;
            transition: .2s ease;
        }

        .reason-field textarea:focus {
            border-color: var(--htc-green);
            box-shadow: 0 0 0 3px rgba(24, 91, 59, .1);
        }

        .reason-field textarea::placeholder {
            color: #64766b;
        }

        .reason-field > span {
            display: flex;
            justify-content: space-between;
            margin-top: var(--space-4);
            color: #52645a;
            font-size: var(--text-caption);
        }

        .reason-field em {
            font-style: normal;
        }

        .live-refresh-notice {
            position: fixed;
            top: 76px;
            right: 20px;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: var(--space-12);
            max-width: 360px;
            padding: var(--space-12) var(--space-16);
            border: 1px solid #cfe2d6;
            border-radius: 12px;
            background: #fff;
            color: #244c34;
            font-size: var(--text-caption);
            font-weight: 700;
            box-shadow: 0 12px 32px rgba(20, 55, 34, .16);
        }

        .live-refresh-notice[hidden] {
            display: none !important;
        }

        .notification-toast-wrap {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 10050;
            width: min(340px, calc(100vw - 28px));
            display: flex;
            flex-direction: column;
            gap: var(--space-8);
            max-height: calc(100dvh - 100px);
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: var(--space-4);
        }

        .notification-toast {
            position: relative;
            flex-shrink: 0;
            overflow: hidden;
            border: 1px solid #dce7e0;
            border-radius: 14px;
            background: rgba(255,255,255,.98);
            box-shadow: 0 12px 32px rgba(20, 48, 31, .18);
            backdrop-filter: blur(12px);
            animation: notificationToastEnter .25s ease-out;
        }

        .notification-toast-accent {
            height: 4px;
            background: #397a56;
        }

        .notification-toast.type-warning .notification-toast-accent {
            background: #e2a126;
        }

        .notification-toast.type-danger .notification-toast-accent {
            background: #d13e47;
        }

        .notification-toast.type-success .notification-toast-accent {
            background: #21804c;
        }

        .notification-toast-body {
            display: flex;
            align-items: flex-start;
            gap: var(--space-8);
            padding: var(--space-12);
        }

        .notification-toast-icon {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: #276947;
            background: #e6f4eb;
        }

        .notification-toast-icon svg {
            width: 18px;
            height: 18px;
        }

        .type-warning .notification-toast-icon {
            color: #97620a;
            background: #fff0ce;
        }

        .type-danger .notification-toast-icon {
            color: #b32933;
            background: #fde8e9;
        }

        .type-success .notification-toast-icon {
            color: #17643d;
            background: #e2f3e8;
        }

        .notification-toast-content {
            min-width: 0;
            flex: 1;
        }

        .notification-toast-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: var(--space-8);
            padding-right: var(--space-16);
            margin-bottom: var(--space-4);
        }

        .notification-toast-meta span {
            color: #738078;
            font-size: var(--text-caption);
            font-weight: 800;
            letter-spacing: var(--tracking-label);
            text-transform: uppercase;
        }

        .notification-toast-meta time {
            color: #a0aaa4;
            font-size: var(--text-caption);
            white-space: nowrap;
        }

        .notification-toast h6 {
            margin: 0 var(--space-24) var(--space-4) 0;
            color: #203128;
            font-size: var(--text-body);
            font-weight: 800;
        }

        .notification-toast p {
            margin: 0;
            color: #6f7d75;
            font-size: var(--text-secondary);
            line-height: var(--line-body);
        }

        .notification-toast-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: var(--space-8);
            margin-top: var(--space-8);
        }

        .notification-toast-actions a {
            color: var(--htc-green);
            font-size: var(--text-caption);
            font-weight: 800;
            text-decoration: none;
            text-transform: uppercase;
        }

        .notification-toast-actions button {
            padding: 0;
            border: 0;
            background: transparent;
            color: #849088;
            font-size: var(--text-caption);
        }

        .notification-toast-actions .notification-toast-open {
            color: var(--htc-green);
            font-weight: 800;
            text-transform: uppercase;
        }

        .notification-toast-actions button svg {
            width: 14px;
            height: 14px;
        }

        .notification-toast-read-note {
            display: block;
            margin-top: var(--space-4);
            color: #8b968f;
            font-size: var(--text-caption);
            line-height: 1.4;
        }

        .notification-toast-close {
            position: absolute;
            top: .5rem;
            right: .5rem;
            padding: .15rem;
            border: 0;
            background: transparent;
            color: #98a39d;
        }

        .notification-toast-close svg {
            width: 15px;
        }

        .notification-toast-timer {
            height: 3px;
            background: #397a56;
            transform-origin: left;
            animation: notificationCountdown 9s linear forwards;
        }

        .type-warning .notification-toast-timer {
            background: #e2a126;
        }

        .type-danger .notification-toast-timer {
            background: #d13e47;
        }

        .type-success .notification-toast-timer {
            background: #21804c;
        }

        @keyframes notificationCountdown {
            from { transform: scaleX(1); }
            to { transform: scaleX(0); }
        }

        @keyframes notificationToastEnter {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            .notification-toast { animation: none; }
        }

        .cart-summary > div {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-12);
            color: #738178;
            font-size: var(--text-caption);
        }

        .cart-summary > div strong {
            color: #173d27;
            font-size: var(--text-heading-sm);
        }

        .cart-summary p {
            font-size: var(--text-secondary);
            font-weight: 700;
        }

        .cart-summary > small {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-4);
            color: #829087;
            font-size: var(--text-caption);
            margin-top: var(--space-12);
        }

        .cart-summary > small svg {
            width: 13px;
        }

        [x-cloak] {
            display: none !important;
        }

        @media (max-width: 991.98px) {
            .requisition-workspace { grid-template-columns: minmax(0, 1fr); }
        }

        @media (max-width: 575.98px) {
            .catalog-grid {
                grid-template-columns: 1fr;
            }

            .catalog-toolbar {
                flex-wrap: wrap;
            }

            .catalog-search {
                order: 3;
                max-width: none;
                flex-basis: 100%;
            }

        }

        /* Shared responsive application shell */
        @media (max-width: 991.98px) {
            body.sidebar-open {
                position: fixed;
                top: var(--sidebar-scroll-top, 0px);
                left: 0;
                width: 100%;
                overflow: hidden;
            }
            .sidebar {
                width: min(290px, 86vw);
                padding: var(--space-16);
                z-index: 1045;
                transform: translateX(-105%);
                transition: transform .25s ease, box-shadow .25s ease;
                box-shadow: none;
            }
            .sidebar.is-open { transform: translateX(0); box-shadow: 18px 0 50px rgba(15, 37, 25, .22); }
            .sidebar-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 1040;
                border: 0;
                background: rgba(13, 32, 22, .48);
                opacity: 0;
                visibility: hidden;
                transition: opacity .25s ease, visibility .25s ease;
            }
            .sidebar-backdrop.is-open { opacity: 1; visibility: visible; }
            .sidebar-close {
                display: inline-grid;
                width: 38px;
                height: 38px;
                flex: 0 0 38px;
                margin-left: auto;
                place-items: center;
                border: 0;
                border-radius: 10px;
                border: 1px solid rgba(255, 255, 255, .16);
                background: rgba(255, 255, 255, .12);
                color: #fff;
            }
            .sidebar-close svg { width: 18px; height: 18px; }
            .main-content { width: 100%; margin-left: 0; min-width: 0; }
            .top-bar { min-height: 62px; padding: 0 var(--space-16); gap: var(--space-12); }
            .mobile-menu-button {
                display: inline-grid;
                width: 40px;
                height: 40px;
                flex: 0 0 40px;
                place-items: center;
                border: 1px solid rgba(255, 255, 255, .24);
                border-radius: 11px;
                background: rgba(255, 255, 255, .1);
                color: #fff;
            }
            .mobile-menu-button svg { width: 20px; height: 20px; }
            .page-context { min-width: 0; flex: 1; }
            .page-eyebrow { display: none; }
            .page-title { font-size: var(--text-body); }
            .main-content-inner { min-height: calc(100vh - 82px); padding: var(--space-16) !important; overflow-x: auto; }
            .nav-link-custom { min-height: 44px; padding: var(--space-12) var(--space-12); }
            .btn-new-request { min-height: 44px; }
        }

        @media (max-width: 575.98px) {
            .brand-section { padding-bottom: var(--space-16); }
            .brand-logo { width: 39px; height: 39px; }
            .top-bar .dropdown > button { padding: var(--space-4) !important; }
            .top-bar .dropdown-menu { max-width: calc(100vw - 1rem); }
            .header-avatar { width: 38px; height: 38px; flex-basis: 38px; }
            .header-menu-chevron { display: none; }
            .main-content > .p-1 { padding: var(--space-8) !important; }
            .main-content-inner { border-radius: 12px; padding: var(--space-12) !important; }
            .main-content-inner .container,
            .main-content-inner .container-fluid { padding-left: var(--space-4); padding-right: var(--space-4); }
            .main-content-inner h1 { font-size: var(--text-heading-lg); }
            .main-content-inner h2 { font-size: var(--text-heading-md); }
            .main-content-inner .card-body { padding: var(--space-16); }
            .main-content-inner .table { min-width: 680px; }
            .main-content-inner nav.d-flex.justify-items-center { display: block !important; }
            .main-content-inner nav.d-flex.justify-items-center .pagination { width: 100%; flex-wrap: nowrap; justify-content: space-between; }
            .main-content-inner nav.d-flex.justify-items-center .page-item { flex: 1; }
            .main-content-inner nav.d-flex.justify-items-center .page-link { width: 100%; min-height: 40px; }
            .modal-dialog { width: calc(100% - 1rem); margin: var(--space-8); }
            .modal-content { max-height: calc(100dvh - 1rem); }
            .notification-toast-wrap { right: 10px; bottom: 10px; width: min(340px, calc(100vw - 20px)); }
            .live-refresh-notice { top: 68px; right: 10px; left: 10px; max-width: none; }
        }

        @media (prefers-reduced-motion: reduce) {
            .sidebar, .sidebar-backdrop { transition: none; }
        }

        .mobile-cart-jump { display: none; }

        @media (max-height: 650px) and (min-width: 576px) {
            #globalRequestModal .requisition-dialog { height: calc(100dvh - 2rem); margin-block: var(--space-16); }
        }

        @media (max-width: 991.98px) {
            #globalRequestModal .requisition-header { background: #fff; }
            #globalRequestModal .requisition-workspace { min-height: 0; }
            #globalRequestModal .catalog-pane { padding: var(--space-24); }
            #globalRequestModal .cart-pane { display: none; border-left: 0; }
            #globalRequestModal .is-reviewing-cart .catalog-pane { display: none; }
            #globalRequestModal .is-reviewing-cart .cart-pane { display: flex; }
            #globalRequestModal .cart-continue { display: flex; flex: 0 0 auto; align-items: center; gap: var(--space-8); min-height: 44px; padding: var(--space-8) var(--space-16); border: 0; border-bottom: 1px solid #dce6e0; background: #fff; color: #185b3b; font-size: var(--text-secondary); font-weight: 700; }
            #globalRequestModal .cart-continue svg { width: 16px; height: 16px; }
            #globalRequestModal .product-hero { height: 230px; }
            #globalRequestModal .option-group button,
            #globalRequestModal .quantity-picker button,
            #globalRequestModal .mini-quantity button { min-height: 40px; }
            #globalRequestModal .mini-quantity button { width: 38px; }
            #globalRequestModal .mobile-cart-jump {
                flex: 0 0 auto;
                width: 100%;
                min-height: 48px;
                padding: var(--space-12) var(--space-16);
                display: flex;
                align-items: center;
                justify-content: space-between;
                border: 0;
                background: var(--htc-green);
                color: #fff;
                font-size: var(--text-secondary);
                font-weight: 700;
            }
            #globalRequestModal .mobile-cart-jump span { display: flex; align-items: center; gap: var(--space-8); }
            #globalRequestModal .mobile-cart-jump svg { width: 17px; height: 17px; }
            #globalRequestModal .mobile-cart-jump strong { padding: var(--space-4) var(--space-8); border-radius: 999px; background: rgba(255,255,255,.18); }
        }

        @media (max-width: 575.98px) {
            #globalRequestModal .modal-dialog {
                width: calc(100% - 1rem);
                max-width: none;
                height: calc(100dvh - 1rem);
                min-height: 0;
                margin: var(--space-8) auto;
            }
            #globalRequestModal .requisition-shell {
                width: 100%;
                height: 100%;
                max-height: 100%;
                min-height: 0;
                border-radius: 16px !important;
            }
            #globalRequestModal .requisition-header {
                position: sticky;
                top: 0;
                z-index: 6;
                gap: var(--space-12);
                padding: var(--space-8) var(--space-12);
                background: #fff;
            }
            #globalRequestModal .requisition-header h4 { font-size: var(--text-heading-sm); }
            #globalRequestModal .requisition-cart-link { gap: var(--space-4); padding: var(--space-8) var(--space-8); font-size: var(--text-caption); }
            #globalRequestModal .requisition-cart-link svg { display: none; }
            #globalRequestModal .cart-reason { padding: var(--space-16); }
            #globalRequestModal .tier-stage { min-height: 0; padding: var(--space-16) !important; }
            #globalRequestModal .tier-stage .text-center { margin-bottom: var(--space-16) !important; }
            #globalRequestModal .tier-stage .row { --bs-gutter-y: var(--space-12); }
            #globalRequestModal .tier-choice { min-height: 0; padding: var(--space-16); border-radius: 16px; }
            #globalRequestModal .tier-choice .tier-icon { width: 42px; height: 42px; margin-bottom: var(--space-12); border-radius: 12px; }
            #globalRequestModal .tier-choice .tier-icon svg { width: 21px; height: 21px; }
            #globalRequestModal .tier-choice strong { font-size: var(--text-body); margin: var(--space-4) 0 var(--space-4); }
            #globalRequestModal .tier-choice > span:not(.tier-icon):not(.tier-kicker) { line-height: var(--line-body); }
            #globalRequestModal .tier-choice small { padding-top: var(--space-12); margin-top: var(--space-12); }
            #globalRequestModal .catalog-pane { padding: var(--space-12); }
            #globalRequestModal .catalog-toolbar { gap: var(--space-8); }
            #globalRequestModal .catalog-search { height: 44px; }
            #globalRequestModal .category-chips { padding: var(--space-12) 0; scrollbar-width: none; }
            #globalRequestModal .category-chips::-webkit-scrollbar { display: none; }
            #globalRequestModal .category-chips button { min-height: 38px; }
            #globalRequestModal .catalog-heading { align-items: flex-start; gap: var(--space-8); }
            #globalRequestModal .catalog-heading p { display: none; }
            #globalRequestModal .catalog-grid { gap: var(--space-12); }
            #globalRequestModal .product-visual { min-height: 96px; }
            #globalRequestModal .product-visual svg { width: 29px; height: 29px; }
            #globalRequestModal .product-visual em { right: .35rem; left: .35rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            #globalRequestModal .product-body { min-width: 0; padding: var(--space-12); }
            #globalRequestModal .product-meta { margin-top: var(--space-8); gap: var(--space-8); }
            #globalRequestModal .product-meta em { text-align: right; }
            #globalRequestModal .catalog-display-controls { width: 100%; justify-content: space-between; }
            #globalRequestModal .catalog-grid.is-list .product-visual { width: 70px; }
            #globalRequestModal .catalog-grid.is-list .product-visual em { display: none; }
            #globalRequestModal .catalog-grid.is-list .product-body { column-gap: var(--space-8); }
            #globalRequestModal .catalog-grid.is-list .product-meta { margin-top: 0; }
            #globalRequestModal .product-configurator > .btn { margin-bottom: var(--space-16) !important; min-height: 40px; }
            #globalRequestModal .product-hero { height: 125px; border-radius: 14px; }
            #globalRequestModal .product-hero svg { width: 45px; height: 45px; }
            #globalRequestModal .product-configurator .row { --bs-gutter-y: var(--space-16); }
            #globalRequestModal .product-configurator h3 { font-size: var(--text-heading-sm); }
            #globalRequestModal .option-group button { min-height: 42px; padding: var(--space-8) var(--space-12); }
            #globalRequestModal .product-configurator .d-flex.align-items-end { align-items: stretch !important; flex-direction: column; }
            #globalRequestModal .quantity-picker { width: 100%; }
            #globalRequestModal .quantity-picker button { width: 46px; }
            #globalRequestModal .quantity-picker input { flex: 1; width: auto; }
            #globalRequestModal .cart-title,
            #globalRequestModal .cart-items,
            #globalRequestModal .cart-summary { padding: var(--space-16); }
            #globalRequestModal .cart-empty { min-height: 150px; }
            #globalRequestModal .cart-summary { padding-bottom: max(var(--space-16), env(safe-area-inset-bottom)); }
            #globalRequestModal .reason-field textarea { min-height: 92px; font-size: var(--text-body); }
            #globalRequestModal .cart-summary > button[type="submit"] { min-height: 48px; }
        }

        /* Reserve room for complete item controls in short landscape windows. */
        @media (max-height: 450px) {
            #globalRequestModal .requisition-dialog { height: calc(100dvh - 1rem); margin-block: var(--space-8); }
            #globalRequestModal .requisition-header { min-height: 48px; padding-block: var(--space-4); }
            #globalRequestModal .cart-title { display: none; }
            #globalRequestModal .cart-continue { min-height: 36px; padding-block: var(--space-4); }
            #globalRequestModal .cart-items { padding: var(--space-8); }
            #globalRequestModal .cart-summary { padding: var(--space-8) var(--space-16); }
            #globalRequestModal .cart-summary > div:first-child { display: none; }
        }

        /* Responsive request lists, details, and approval workflow */
        .workflow-stepper-scroll { max-width: 100%; }
        .min-w-0 { min-width: 0; }

        @media (max-width: 767.98px) {
            .workflow-page-heading { align-items: flex-start !important; flex-direction: column; }
            .workflow-heading-action { width: 100%; min-height: 42px; }
            .archive-date-range { display: grid; grid-template-columns: auto minmax(0, 1fr) auto minmax(0, 1fr); }
            .archive-date-range > * { min-width: 0; }
            .workflow-stepper-scroll { margin-right: calc(-1 * var(--space-8)); margin-left: calc(-1 * var(--space-8)); overflow-x: auto; overscroll-behavior-x: contain; scrollbar-width: thin; }
            .workflow-stepper-scroll .tracking-stepper { min-width: 520px; margin-bottom: var(--space-8); scroll-snap-type: x proximity; }
            .workflow-stepper-scroll .step-item { scroll-snap-align: center; }

            .table-responsive:has(> .workflow-responsive-table) { overflow: visible; }
            .workflow-responsive-table { display: block; min-width: 0 !important; background: #f5f8f6; }
            .workflow-responsive-table thead { display: none; }
            .workflow-responsive-table tbody { display: grid; gap: var(--space-12); padding: var(--space-12); }
            .workflow-responsive-table tbody tr { display: block; overflow: hidden; border: 1px solid #e0e8e3; border-radius: 14px; background: #fff; box-shadow: 0 3px 12px rgba(24, 58, 37, .05); }
            .workflow-responsive-table tbody tr:hover { background: #fff; }
            .workflow-responsive-table tbody td {
                display: grid;
                grid-template-columns: minmax(82px, 34%) minmax(0, 1fr);
                align-items: center;
                gap: var(--space-12);
                width: 100%;
                padding: var(--space-12) var(--space-12) !important;
                border-color: #edf1ee;
                text-align: right !important;
                overflow-wrap: anywhere;
            }
            .workflow-responsive-table tbody td::before {
                content: attr(data-label);
                color: #7b8981;
                font-size: var(--text-caption);
                font-weight: 800;
                letter-spacing: var(--tracking-label);
                text-align: left;
                text-transform: uppercase;
            }
            .workflow-responsive-table tbody td > * { justify-self: end; max-width: 100%; }
            .workflow-responsive-table tbody td[colspan] { display: block; text-align: center !important; }
            .workflow-responsive-table tbody td[colspan]::before { display: none; }
            .workflow-responsive-table tfoot { display: block; border-top: 1px solid #dce7e0; }
            .workflow-responsive-table tfoot tr { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; }
            .workflow-responsive-table tfoot td { display: block; padding: var(--space-12) !important; }
            .workflow-actions { justify-content: flex-end; flex-wrap: wrap; }
            .workflow-actions .btn { min-height: 36px; }

            .request-detail-actions { align-items: stretch !important; flex-direction: column; }
            .request-pdf-action { width: 100%; min-height: 44px; }
            .request-summary-head { flex-direction: column; }
            .request-summary-head > .text-end { width: 100%; padding-top: var(--space-12); border-top: 1px solid #edf1ef; text-align: left !important; }
            .request-flow-heading { align-items: flex-start !important; flex-direction: column; }
            .request-details-page .card.p-4 { padding: var(--space-16) !important; }
            .request-details-page .card-header { padding: var(--space-16) !important; }
            .request-reason { padding: var(--space-12); }

            #approvalsTabs { margin-right: calc(-1 * var(--space-16)); margin-left: calc(-1 * var(--space-16)); padding: 0 var(--space-16) var(--space-12); overflow-x: auto; scrollbar-width: none; }
            #approvalsTabs::-webkit-scrollbar { display: none; }
            #approvalsTabs .nav-item { flex: 0 0 auto; }
            #approvalsTabs .nav-link { min-height: 42px; white-space: nowrap; }
            .approval-workspace > .card > .card-header { padding: var(--space-16) !important; }
            #reviewModal .modal-header,
            #reviewModal .modal-body { padding: var(--space-16) !important; }
            #reviewModal .modal-title { padding-right: var(--space-8); font-size: var(--text-body); }
            #reviewModal textarea { min-height: 84px; font-size: var(--text-body); }
            #reviewModal form .btn { min-height: 44px; }
        }

        @media (max-width: 575.98px) {
            #reviewModal .modal-dialog { width: 100%; max-width: none; height: 100%; min-height: 100%; margin: 0; }
            #reviewModal .modal-content { height: 100dvh; max-height: 100dvh; border-radius: 0 !important; }
            #reviewModal .modal-body { overscroll-behavior: contain; }
            .request-summary-head > div:first-child { align-items: flex-start !important; }
            .request-summary-head .rounded-circle { padding: var(--space-12) !important; }
            .request-details-page .activity-log-item { padding-left: var(--space-24) !important; }
        }

        /* Inventory create/edit forms */
        @media (max-width: 767.98px) {
            .inventory-form-meta { align-items: flex-start !important; flex-direction: column; }
            .inventory-form-meta .text-end { text-align: left !important; }
            .inventory-status-card { align-items: flex-start !important; flex-direction: column; }
            .inventory-status-card > .text-end { text-align: left !important; }
            .inventory-options-header { align-items: flex-start !important; flex-direction: column; }
            .inventory-options-header > button { min-height: 40px; }
            .inventory-option-row { margin-bottom: var(--space-16) !important; padding: var(--space-12); border: 1px solid #e3eae6; border-radius: 12px; background: #f8faf9; }
            .inventory-option-row input { min-height: 42px; }
            .inventory-option-remove { display: flex !important; justify-content: flex-end; }
            .inventory-option-remove .btn { width: 42px; height: 42px; }
            .inventory-form-actions { flex-direction: column-reverse; }
            .inventory-form-actions .btn { width: 100%; min-height: 46px; padding: var(--space-12) var(--space-16) !important; }
        }

        @media (max-width: 575.98px) {
            .inventory-form-page { padding-top: var(--space-8) !important; }
            .inventory-form-page .card-body { padding: var(--space-16) !important; }
            .inventory-form-page input,
            .inventory-form-page select,
            .inventory-form-page textarea { min-height: 44px; font-size: var(--text-body); }
            .inventory-form-page textarea { min-height: 100px; }
        }
    </style>
    @stack('styles')
</head>
<body data-runtime-scope="{{ $workspaceScope }}" data-runtime-version="{{ $workspaceVersion }}">
    @include('layouts.partials.session-recovery')
    <div class="d-flex">
        <button type="button" class="sidebar-backdrop" id="sidebarBackdrop" aria-label="Close navigation"></button>
        <!-- SIDEBAR -->
        <aside class="sidebar" id="appSidebar" aria-label="Primary navigation">
            <div class="brand-section">
                <img src="{{ asset('images/android-chrome-512x5122.png') }}" alt="Logo" class="brand-logo">
                <div class="brand-title">SUPPLY<br>MANAGEMENT</div>
                <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close navigation">
                    <i data-lucide="x"></i>
                </button>
            </div>

            <div class="flex-grow-1">
                @php
                    $sidebarRole = Auth::user()->role;
                    $isApprovalRole = in_array($sidebarRole, ['dept_head', 'vp_finance', 'vp_admin', 'provost', 'president']);
                    $canManagePersonnel = in_array($sidebarRole, ['dept_head', 'vp_admin', 'provost', 'president', 'smo']);
                @endphp

                <div class="nav-section-label">Overview</div>
                <a href="{{ route('dashboard') }}" class="nav-link-custom {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <i data-lucide="layout-dashboard"></i> Home
                </a>
                <a href="{{ route('notifications') }}" class="nav-link-custom {{ request()->routeIs('notifications') ? 'active' : '' }}">
                    <i data-lucide="bell"></i>
                    <span>Notifications</span>
                    @php
                        $initialUnreadCount = $unreadCount ?? $layoutUnreadCount ?? 0;
                    @endphp
                    <span id="notificationUnreadCount" class="sidebar-count" @if($initialUnreadCount < 1) hidden @endif>{{ $initialUnreadCount > 99 ? '99+' : $initialUnreadCount }}</span>
                </a>

                <div class="nav-section-label">Requisitions</div>
                <a href="{{ route('requests.index') }}" class="nav-link-custom {{ request()->routeIs('requests.index') ? 'active' : '' }}">
                    <i data-lucide="clipboard-list"></i> Active Requests
                </a>
                <a href="{{ route('requests.archive') }}" class="nav-link-custom {{ request()->routeIs('requests.archive') ? 'active' : '' }}">
                    <i data-lucide="archive"></i> Request Archive
                </a>

                @if($isApprovalRole)
                    <div class="nav-section-label">Approval Workflow</div>
                    <a href="{{ route('admin.approvals') }}" class="nav-link-custom {{ request()->routeIs('admin.approvals') ? 'active' : '' }}">
                        <i data-lucide="clipboard-check"></i> Pending Approvals
                    </a>
                @endif

                @if($sidebarRole === 'smo')
                    <div class="nav-section-label">Inventory & Fulfillment</div>
                    <a href="{{ route('admin.approvals') }}" class="nav-link-custom {{ request()->routeIs('admin.approvals') ? 'active' : '' }}">
                        <i data-lucide="package-check"></i> Fulfillment Queue
                    </a>
                    <a href="{{ route('inventory.index') }}" class="nav-link-custom {{ request()->routeIs('inventory.*') ? 'active' : '' }}">
                        <i data-lucide="boxes"></i> Manage Inventory
                    </a>

                    <div class="nav-section-label">Insights</div>
                    <a href="{{ route('admin.reports.index') }}" class="nav-link-custom {{ request()->routeIs('admin.reports.index', 'admin.reports.analytics') ? 'active' : '' }}">
                        <i data-lucide="chart-no-axes-combined"></i> Operational Analytics
                    </a>
                    <a href="{{ route('admin.reports.builder') }}" class="nav-link-custom {{ request()->routeIs('admin.reports.builder', 'admin.reports.generate') ? 'active' : '' }}">
                        <i data-lucide="file-chart-column"></i> Report Center
                    </a>
                @endif

                @if($canManagePersonnel)
                    <div class="nav-section-label">Administration</div>
                    @if($sidebarRole === 'smo')
                        <a href="{{ route('admin.users.pending') }}" class="nav-link-custom {{ request()->routeIs('admin.users.pending') ? 'active' : '' }}">
                            <i data-lucide="user-round-check"></i> Access Requests
                        </a>
                    @endif
                    <a href="{{ route('admin.users.manage') }}" class="nav-link-custom {{ request()->routeIs('admin.users.manage') ? 'active' : '' }}">
                        <i data-lucide="users-round"></i> Manage Personnel
                    </a>
                @endif
            </div>

            <div class="sidebar-footer">
                <button type="button" class="btn btn-new-request w-100 shadow-sm" data-bs-toggle="modal" data-bs-target="#globalRequestModal">
                    + NEW REQUISITION
                </button>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="main-content">
            <header class="top-bar">
                <button type="button" class="mobile-menu-button" id="sidebarToggle" aria-controls="appSidebar" aria-expanded="false" aria-label="Open navigation">
                    <i data-lucide="menu"></i>
                </button>
                <div class="page-context">
                    <div class="page-title">{{ $header ?? 'System Dashboard' }}</div>
                </div>

                <div class="header-actions">
                    @php
                        $position = match(Auth::user()->role) {
                            'employee' => 'Faculty / Staff',
                            'dept_head' => 'Department Head',
                            'vp_finance' => 'VP for Finance',
                            'vp_admin' => 'VP for Administration',
                            'provost' => 'School Provost',
                            'president' => 'School President',
                            'smo' => 'SMO In-Charge',
                            default => 'User'
                        };
                    @endphp
                    <div class="role-badge d-none d-md-block">{{ $position }}</div>

                    <div class="dropdown">
                        <button class="btn d-flex align-items-center gap-3 rounded-3 user-menu-trigger" type="button" data-bs-toggle="dropdown" aria-label="Open account menu">
                            <div class="header-user-copy d-none d-lg-block">
                                <strong>{{ Auth::user()->name }}</strong>
                                <small>{{ Auth::user()->school_id }}</small>
                            </div>
                            <div class="header-avatar">
                                @if(Auth::user()->profile_photo)
                                    <img src="{{ asset('storage/' . Auth::user()->profile_photo) }}" alt="{{ Auth::user()->name }} profile photo">
                                @else
                                    {{ substr(Auth::user()->name, 0, 1) }}
                                @endif
                            </div>
                            <i data-lucide="chevron-down" class="header-menu-chevron"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-2 mt-2">
                            <li class="px-3 py-2 mb-2 border-bottom d-lg-none">
                                <div class="fw-bold">{{ Auth::user()->name }}</div>
                                <small class="text-muted">{{ $position }}</small>
                            </li>
                            <li>
                                <a class="dropdown-item rounded-3 small d-flex align-items-center" href="{{ route('profile.edit') }}">
                                    <i data-lucide="settings" class="me-2 text-muted" style="width:14px"></i> Account Setting
                                </a>
                            </li>
                            <li><hr class="dropdown-divider opacity-50"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}" data-logout-form data-confirm="End your current session? Save any changes you want to keep before signing out." data-confirm-title="Sign out?" data-confirm-accept="Sign out">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger rounded-3 small d-flex align-items-center" data-loading-text="Logging out…">
                                        <i data-lucide="log-out" class="me-2" style="width:14px"></i> Sign Out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <div class="p-1 p-lg-3">
                <div class="main-content-inner p-3 p-md-4" @if($liveWorkspace) data-live-region="workspace" @endif>
                    {{ $slot }}
                </div>
            </div>
        </main>
    </div>

    @if(false)
    <!-- LEGACY REQUISITION ENGINE -->
    <div class="modal fade" id="globalRequestModal" tabindex="-1" aria-hidden="true"
        x-data="cartSystem()"
        @hidden-bs-modal.window="resetModal()">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

                <!-- MODAL HEADER: DYNAMIC ACCENT -->
                <div class="p-4 d-flex justify-content-between align-items-center border-bottom"
                    :class="tier === 'major' ? 'bg-warning-subtle' : 'bg-success-subtle'">
                    <div class="d-flex align-items-center">
                        <div class="p-2 rounded-3 me-3" :class="tier === 'major' ? 'bg-warning text-white' : 'bg-success text-white'">
                            <i :data-lucide="tier === 'major' ? 'shield-alert' : 'zap'" style="width: 20px;"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark">Institutional Requisition</h5>
                            <span class="small fw-bold opacity-75 text-uppercase label-letter-spacing" x-text="view === 'selection' ? 'Select Priority' : tier + ' Tier Active'"></span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <!-- VIEW 1: SELECTION (Modern Grid) -->
                <div x-show="view === 'selection'" x-transition class="p-5">
                    <div class="row g-4 justify-content-center">
                        <div class="col-md-5">
                            <div class="selection-card minor" @click="setTier('minor')">
                                <div class="selection-badge">< 1000</div>
                                <i data-lucide="package-search" class="mb-3"></i>
                                <h4>Minor Request</h4>
                                <p>For standard office supplies and low-value consumables.</p>
                                <div class="footer-note">Approval: Dept Head & VP Finance</div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="selection-card major" @click="setTier('major')">
                                <div class="selection-badge">> 1000</div>
                                <i data-lucide="building-2" class="mb-3"></i>
                                <h4>Major Request</h4>
                                <p>For high-value equipment, IT hardware, and bulk orders.</p>
                                <div class="footer-note">Full Administrative Chain Required</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VIEW 2: DYNAMIC FORM -->
                <div x-show="view === 'form'" x-transition class="p-0 h-100" x-cloak>
                    <div class="row g-0">
                        <!-- LEFT: THE CART WORKSPACE -->
                        <div class="col-lg-8 p-4 border-end" style="background: #fafbfc; min-height: 550px; max-height: 650px; overflow-y: auto;">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <button @click="view = 'selection'" class="btn btn-sm btn-light rounded-pill border px-3">
                                    <i data-lucide="chevron-left" class="me-1" style="width:14px"></i> Back
                                </button>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="manualMode" x-model="manualMode">
                                    <label class="form-check-label small fw-bold text-muted" for="manualMode">ITEM NOT IN LIST? (MANUAL INPUT)</label>
                                </div>
                            </div>

                            <!-- SEARCH / SELECT BOX (Guided Input) -->
                            <div class="mb-4 bg-white p-3 rounded-4 shadow-sm border border-success border-opacity-25" x-show="!manualMode" x-transition>
                                <label class="mini-label mb-2"><i data-lucide="search" class="me-1" style="width:10px"></i> Select Institutional Stock</label>
                                <select class="form-select border-0 bg-light py-2 fw-bold" @change="addItemFromInventory($event.target.value); $event.target.value = ''">
                                    <option value="">Search for items (e.g. Bond Paper, Ink)...</option>
                                </select>
                                <small class="text-muted mt-2 d-block" style="font-size: var(--text-caption);">Official items have pre-validated prices and specifications.</small>
                            </div>

                            <!-- THE CART LIST -->
                            <div class="pe-2">
                                <template x-for="(item, index) in cart" :key="index">
                                    <div class="card border-0 shadow-sm rounded-4 mb-3 p-3 border-start border-4 requisition-item-card"
                                        :class="item.is_manual ? 'border-warning' : 'border-success'">

                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-7">
                                                <span class="badge bg-light text-dark mb-2" style="font-size: var(--text-caption);" x-text="item.is_manual ? 'CUSTOM ITEM' : 'OFFICIAL STOCK'"></span>

                                                <input type="text" x-model="item.name" class="form-control form-control-sm border-0 bg-transparent fw-bold p-0 shadow-none"
                                                    :readonly="!item.is_manual" :placeholder="item.is_manual ? 'Enter Item Name...' : ''" style="font-size: var(--text-body);">

                                                <textarea x-model="item.specs" class="form-control form-control-sm border-0 bg-transparent p-0 text-muted shadow-none mt-1"
                                                        rows="1" :readonly="!item.is_manual" placeholder="Add specifications..."></textarea>
                                            </div>

                                            <div class="col-md-5">
                                                <div class="row g-2">
                                                    <div class="col-7">
                                                        <label class="mini-label text-primary">Input Quantity</label>
                                                        <div class="input-group input-group-sm border rounded-3 overflow-hidden shadow-sm">
                                                            <!-- THE MANUAL QUANTITY INPUT -->
                                                            <input type="number" x-model.number="item.qty"
                                                                class="form-control border-0 text-center fw-bold bg-white"
                                                                min="1" @input="calculateTotal()">
                                                            <span class="input-group-text border-0 bg-white text-muted small px-2" x-text="item.unit"></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-5 text-end">
                                                        <label class="mini-label">Unit Price</label>
                                                        <div class="fw-bold mt-1" x-text="'₱' + formatMoney(item.price)"></div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top border-light">
                                                    <button type="button" @click="removeItem(index)" class="btn btn-sm text-danger p-0 d-flex align-items-center" style="font-size: var(--text-caption);">
                                                        <i data-lucide="trash-2" class="me-1" style="width:12px"></i> Remove
                                                    </button>
                                                    <div class="fw-bold text-success small" style="letter-spacing: var(--tracking-label);">
                                                        SUBTOTAL: ₱<span x-text="formatMoney(item.qty * item.price)"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- MANUAL ENTRY BUTTON -->
                            <div x-show="manualMode" x-transition class="mt-2">
                                <button type="button" @click="addItemManual()" class="btn btn-outline-dashed w-100 py-3 rounded-4">
                                    <i data-lucide="plus-circle" class="me-2" style="width:16px"></i> Add Custom Item Description
                                </button>
                            </div>

                            <!-- EMPTY STATE -->
                            <div x-show="cart.length === 0" class="text-center py-5 opacity-25">
                                <i data-lucide="shopping-basket" style="width: 48px; height: 48px;" class="mb-2"></i>
                                <p class="small">Cart is empty. Select items above to begin.</p>
                            </div>
                        </div>

                        <!-- RIGHT: Summary Sidebar -->
                       <!-- RIGHT: Summary Sidebar (Fixed Version) -->
                        <div class="col-lg-4 p-4 bg-white border-start">
                            <div class="d-flex flex-column h-100">
                                <h6 class="fw-bold text-dark mb-4">Submission Summary</h6>

                                <!-- Total Items -->
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="text-muted small fw-bold">TOTAL ITEMS</span>
                                    <span class="fw-bold h5 mb-0" x-text="cart.length"></span>
                                </div>

                                <!-- Workflow Path (Stacked for better fit) -->
                                <div class="mb-4">
                                    <span class="text-muted small fw-bold d-block mb-2 text-uppercase">Workflow Path</span>
                                    <span class="badge rounded-pill px-3 py-2 w-100 border text-center"
                                        :class="tier === 'major' ? 'bg-warning-subtle text-warning border-warning' : 'bg-success-subtle text-success border-success'"
                                        x-text="tier.toUpperCase() + ' ROUTING'"></span>
                                </div>

                                <hr class="opacity-50">

                                <!-- Grand Total -->
                                <div class="py-3 text-center">
                                    <small class="text-muted text-uppercase fw-bold d-block mb-1" style="font-size: var(--text-caption);">ESTIMATED GRAND TOTAL</small>
                                    <h2 class="fw-black mb-0"
                                        :class="(tier === 'minor' && grandTotal > 1000) ? 'text-danger' : 'text-success'"
                                        style="font-size: var(--text-heading-md);">
                                        ₱<span x-text="formatMoney(grandTotal)"></span>
                                    </h2>

                                    <!-- Warning -->
                                    <template x-if="tier === 'minor' && grandTotal > 1000">
                                        <div class="mt-2 text-danger fw-bold animate__animated animate__shakeX" style="font-size: var(--text-caption); line-height: var(--line-heading);">
                                            ⚠️ MINOR LIMIT EXCEEDED (MAX ₱1,000)<br>SWITCH TO MAJOR TIER
                                        </div>
                                    </template>
                                </div>

                                <!-- Submit Button (Fixed) -->
                                <div class="mt-auto">
                                    <form action="{{ route('requisitions.store') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="request_type" :value="tier">
                                        <template x-for="(item, index) in cart">
                                            <div>
                                                <input type="hidden" :name="'items['+index+'][name]'" :value="item.name">
                                                <input type="hidden" :name="'items['+index+'][specs]'" :value="item.specs">
                                                <input type="hidden" :name="'items['+index+'][qty]'" :value="item.qty">
                                                <input type="hidden" :name="'items['+index+'][unit]'" :value="item.unit">
                                                <input type="hidden" :name="'items['+index+'][price]'" :value="item.price">
                                            </div>
                                        </template>

                                        <button type="submit"
                                                class="btn btn-success w-100 py-3 fw-bold shadow-sm d-block"
                                                style="border-radius: 12px; font-size: var(--text-secondary);"
                                                :disabled="tier === 'minor' && grandTotal > 1000"
                                                :class="tier === 'minor' && grandTotal > 1000 ? 'opacity-50 grayscale request-submit-disabled' : ''">
                                            SUBMIT REQUISITION
                                        </button>
                                    </form>
                                    <p class="text-center text-muted mt-3 mb-0" style="font-size: var(--text-body);">
                                        Secure Institutional Requisition <br> Holy Trinity College SMO
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @endif

    @include('layouts.partials.requisition-modal')

    <!-- LIVE PRIORITY NOTIFICATION -->
    <div x-data="notificationCenter()" x-show="toasts.length > 0" class="notification-toast-wrap" aria-label="Notifications" x-cloak>
        <template x-for="toast in toasts" :key="toast.id">
        <article x-show="toast.visible" x-transition.opacity.duration.250ms class="notification-toast" :class="'type-' + toast.type" role="alert" aria-live="assertive">
            <div class="notification-toast-accent"></div>
            <div class="notification-toast-body">
                <div class="notification-toast-icon">
                    <template x-if="toast.type === 'danger'"><i data-lucide="siren"></i></template>
                    <template x-if="toast.type === 'warning'"><i data-lucide="triangle-alert"></i></template>
                    <template x-if="toast.type === 'success'"><i data-lucide="circle-check"></i></template>
                    <template x-if="!['danger', 'warning', 'success'].includes(toast.type)"><i data-lucide="bell-ring"></i></template>
                </div>
                <div class="notification-toast-content">
                    <div class="notification-toast-meta"><span x-text="toast.label"></span><time>Just now</time></div>
                    <h6 x-text="toast.title"></h6>
                    <p x-text="toast.message"></p>
                    <div class="notification-toast-actions">
                        <form x-show="toast.actionUrl" :action="toast.actionUrl" method="POST">
                            @csrf
                            <button type="submit" class="notification-toast-open" x-text="toast.actionLabel"></button>
                        </form>
                        <button type="button" @click="toggleSound()" :title="soundEnabled ? 'Mute notification sounds' : 'Enable notification sounds'" :aria-label="soundEnabled ? 'Mute notification sounds' : 'Enable notification sounds'" :aria-pressed="soundEnabled">
                            <template x-if="soundEnabled"><i data-lucide="volume-2"></i></template>
                            <template x-if="!soundEnabled"><i data-lucide="volume-x"></i></template>
                        </button>
                        <button type="button" @click="dismiss(toast.id)" title="Hide this toast; it will remain unread">Dismiss toast</button>
                    </div>
                    <small class="notification-toast-read-note">Dismissal hides this toast but keeps the notification unread.</small>
                </div>
                <button type="button" class="notification-toast-close" @click="dismiss(toast.id)" aria-label="Dismiss notification"><i data-lucide="x"></i></button>
            </div>
            <div class="notification-toast-timer"></div>
        </article>
        </template>
    </div>

    <!-- Live updates stay silent unless recovery is needed. -->
    <div id="liveUpdateStatus" class="visually-hidden" role="status" aria-live="polite" aria-atomic="true"></div>
    <div id="liveRefreshNotice" class="live-refresh-notice" role="status" aria-live="polite" hidden>
        <span id="liveRefreshMessage"></span>
        <button type="button" id="liveRefreshButton" class="btn btn-sm btn-outline-success">Retry updates</button>
    </div>

    @include('layouts.partials.runtime-guard')
    @include('layouts.partials.dynamic-search')

    <!-- Scripts -->
    <script>
        // Initialize Icons
        window.lucide?.createIcons();

        (() => {
            const sidebar = document.getElementById('appSidebar');
            const toggle = document.getElementById('sidebarToggle');
            const closeButton = document.getElementById('sidebarClose');
            const backdrop = document.getElementById('sidebarBackdrop');
            const mobileQuery = window.matchMedia('(max-width: 991.98px)');

            if (!sidebar || !toggle || !backdrop) return;

            let sidebarScrollY = 0;
            const setSidebar = open => {
                const shouldOpen = Boolean(open && mobileQuery.matches);
                const wasOpen = document.body.classList.contains('sidebar-open');
                if (shouldOpen && !wasOpen) {
                    sidebarScrollY = window.scrollY;
                    document.body.style.setProperty('--sidebar-scroll-top', `-${sidebarScrollY}px`);
                }
                sidebar.classList.toggle('is-open', shouldOpen);
                backdrop.classList.toggle('is-open', shouldOpen);
                document.body.classList.toggle('sidebar-open', shouldOpen);
                if (!shouldOpen && wasOpen) {
                    document.body.style.removeProperty('--sidebar-scroll-top');
                    window.scrollTo(0, sidebarScrollY);
                }
                toggle.setAttribute('aria-expanded', String(shouldOpen));
                sidebar.setAttribute('aria-hidden', String(mobileQuery.matches && !shouldOpen));

                if (shouldOpen) closeButton?.focus();
            };

            toggle.addEventListener('click', () => setSidebar(!sidebar.classList.contains('is-open')));
            closeButton?.addEventListener('click', () => {
                setSidebar(false);
                toggle.focus();
            });
            backdrop.addEventListener('click', () => setSidebar(false));
            sidebar.querySelectorAll('a, .btn-new-request').forEach(element => {
                element.addEventListener('click', () => setSidebar(false));
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
                    setSidebar(false);
                    toggle.focus();
                }
            });
            mobileQuery.addEventListener('change', () => setSidebar(false));
            setSidebar(false);
        })();

        function notificationCenter() {
            // Only displayed popups advance this cursor. The former key also
            // included notifications skipped during page loads and refreshes.
            const storageKey = 'htc-presented-notification-{{ Auth::id() }}';
            const soundKey = 'htc-notification-sound';
            const accountId = {{ (int) Auth::id() }};
            const pageNotificationBaseline = {{ (int) ($layoutLatestNotificationId ?? 0) }};
            const runtimeCacheKey = `htc-runtime-state:${accountId}`;
            const tabId = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
            const channel = 'BroadcastChannel' in window ? new BroadcastChannel(`htc-notifications-${accountId}`) : null;
            const toastTimers = new Map();
            let audioContext = null;
            let lastSoundAt = -Infinity;
            const readPreference = key => {
                try { return localStorage.getItem(key); } catch (error) { return null; }
            };
            const savePreference = (key, value) => {
                try { localStorage.setItem(key, value); } catch (error) { /* Popups also work without browser storage. */ }
            };

            return {
                toasts: [],
                get show() { return this.toasts.some(toast => toast.visible); },
                soundEnabled: readPreference(soundKey) !== 'off',
                lastId: Math.min(Math.max(0, Number(readPreference(storageKey)) || 0), pageNotificationBaseline),
                queue: [],
                pendingSound: null,

                init() {
                    try {
                        const cachedRuntimeState = JSON.parse(sessionStorage.getItem(runtimeCacheKey) || 'null');
                        if (cachedRuntimeState) this.updateBadge(Number(cachedRuntimeState.unread_count || 0));
                    } catch (error) {
                        // The next runtime poll will populate the badge.
                    }
                    const unlock = async () => {
                        if (await this.unlockAudio() && this.pendingSound && this.show) this.playSound(this.pendingSound);
                    };
                    document.addEventListener('pointerdown', unlock);
                    document.addEventListener('keydown', unlock);
                    document.addEventListener('htc:runtime-state', event => this.consumeRuntimeState(event.detail));
                    channel?.addEventListener('message', event => {
                        if (event.data?.type === 'consumed') this.advanceCursor(Number(event.data.id || 0));
                    });
                    window.addEventListener('storage', event => {
                        if (event.key === storageKey) this.lastId = Math.max(this.lastId, Number(event.newValue || 0));
                    });
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) this.presentNext();
                    });
                    // A fast poll can finish before Alpine initializes this component.
                    if (window.htcRuntimeState) this.consumeRuntimeState(window.htcRuntimeState);
                },
                consumeRuntimeState(data) {
                    if (!data || Number(data.account_id) !== accountId) return;
                    this.updateBadge(Number(data.unread_count || 0));
                    if (document.hidden) return;
                    const notifications = Array.isArray(data.notifications)
                        ? data.notifications
                        : (data.notification ? [data.notification] : []);
                    notifications.forEach(notification => {
                        const id = Number(notification.id || 0);
                        if (id > this.lastId && !this.queue.some(item => Number(item.id) === id)) this.queue.push(notification);
                    });
                    if (!notifications.length && !this.toasts.length && !this.queue.length) this.advanceCursor(Number(data.cursor || data.latest_id || 0));
                    this.presentNext();
                },
                advanceCursor(id) {
                    if (id <= this.lastId) return;
                    this.lastId = id;
                    savePreference(storageKey, String(id));
                    channel?.postMessage({type: 'consumed', id});
                },
                claimToast(id) {
                    const key = `htc-notification-toast-${accountId}-${id}`;
                    try {
                        const existing = JSON.parse(localStorage.getItem(key) || 'null');
                        if (existing && Date.now() - Number(existing.at || 0) < 30000 && existing.tab !== tabId) return false;
                        localStorage.setItem(key, JSON.stringify({tab: tabId, at: Date.now()}));
                        return JSON.parse(localStorage.getItem(key) || 'null')?.tab === tabId;
                    } catch (error) {
                        return true;
                    }
                },
                presentNext() {
                    if (document.hidden) return;
                    while (this.toasts.length < 3 && this.queue.length) {
                        const notification = this.queue.shift();
                        const id = Number(notification.id || 0);
                        if (id > this.lastId && this.claimToast(id)) {
                            this.present(notification);
                        }
                    }
                    this.notifyActivity();
                },
                notifyActivity() {
                    document.dispatchEvent(new CustomEvent('htc:notification-activity', {
                        detail: {active: this.toasts.length > 0 || this.queue.length > 0},
                    }));
                },
                present(notification) {
                    const type = ['success', 'warning', 'danger'].includes(notification.type) ? notification.type : 'info';
                    const toast = {
                        id: Number(notification.id),
                        title: String(notification.title || 'Notification'),
                        message: String(notification.message || ''),
                        type,
                        label: type === 'success' ? 'Action completed' : type === 'danger' ? 'Action required' : type === 'warning' ? 'Attention needed' : 'System update',
                        actionUrl: notification.action_url || null,
                        actionLabel: notification.action_label || 'Open',
                        visible: true,
                    };
                    this.toasts.push(toast);
                    this.advanceCursor(toast.id);
                    this.notifyActivity();
                    toastTimers.set(toast.id, window.setTimeout(() => this.dismiss(toast.id), 9000));
                    this.playSound(type);
                    this.$nextTick(() => window.lucide?.createIcons());
                },
                dismiss(id) {
                    const toast = this.toasts.find(item => item.id === Number(id));
                    if (!toast?.visible) return;
                    toast.visible = false;
                    window.clearTimeout(toastTimers.get(toast.id));
                    toastTimers.delete(toast.id);
                    if (!this.show) this.pendingSound = null;
                    // Leave time for the exit transition before another popup or refresh.
                    window.setTimeout(() => {
                        this.toasts = this.toasts.filter(item => item.id !== toast.id);
                        this.presentNext();
                    }, 300);
                },
                updateBadge(count) {
                    const badge = document.getElementById('notificationUnreadCount');
                    if (!badge) return;
                    badge.hidden = count < 1;
                    badge.textContent = count > 99 ? '99+' : String(count);
                },
                toggleSound() {
                    this.soundEnabled = !this.soundEnabled;
                    savePreference(soundKey, this.soundEnabled ? 'on' : 'off');
                    if (this.soundEnabled) {
                        this.playSound('info');
                    } else this.pendingSound = null;
                    this.$nextTick(() => window.lucide?.createIcons());
                },
                async unlockAudio() {
                    if (!this.soundEnabled) return false;
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext) return false;
                    try {
                        audioContext ??= new AudioContext();
                        if (audioContext.state === 'suspended') await audioContext.resume();
                        return audioContext.state === 'running';
                    } catch (error) {
                        return false;
                    }
                },
                async playSound(type) {
                    if (!this.soundEnabled) return;
                    this.pendingSound = type;
                    if (!await this.unlockAudio() || !this.soundEnabled || !this.pendingSound) return;
                    const pendingType = this.pendingSound;
                    this.pendingSound = null;
                    // A burst of stacked popups gets one chime instead of overlapping sounds.
                    if (Date.now() - lastSoundAt < 300) return;
                    lastSoundAt = Date.now();
                    const patterns = {
                        danger: [[440, 0, .18], [330, .22, .25]],
                        warning: [[660, 0, .16], [520, .2, .24]],
                        success: [[660, 0, .14], [880, .18, .24]],
                        info: [[740, 0, .16], [988, .2, .24]],
                    };
                    const start = audioContext.currentTime;
                    (patterns[pendingType] || patterns.info).forEach(([frequency, offset, duration]) => {
                        const oscillator = audioContext.createOscillator();
                        const gain = audioContext.createGain();
                        oscillator.type = 'sine';
                        oscillator.frequency.value = frequency;
                        gain.gain.setValueAtTime(.0001, start + offset);
                        gain.gain.exponentialRampToValueAtTime(.09, start + offset + .015);
                        gain.gain.exponentialRampToValueAtTime(.0001, start + offset + duration);
                        oscillator.connect(gain).connect(audioContext.destination);
                        oscillator.start(start + offset);
                        oscillator.stop(start + offset + duration + .02);
                    });
                }
            };
        }

        // Cart Engine
        function cartSystem() {
            const previousRequest = {{ Illuminate\Support\Js::from([
                'tier' => old('request_type'),
                'purpose' => old('purpose', ''),
                'items' => old('items', []),
            ]) }};

            return {
                catalog: [],
                catalogLoaded: false,
                catalogLoading: false,
                catalogError: '',
                tier: null,
                view: 'selection',
                search: '',
                category: 'All',
                catalogLayout: 'grid',
                selectedItem: null,
                selectedOptions: {},
                productQty: 1,
                cart: [],
                cartOpen: false,
                lastAddedKey: null,
                purpose: previousRequest.purpose || '',
                notice: '',
                noticeTimer: null,
                confirmationOpen: false,
                submissionConfirmed: false,
                submitting: false,
                pendingForm: null,
                closeConfirmationPending: false,
                discardConfirmed: false,

                init() {
                    try {
                        const savedLayout = localStorage.getItem('htc-requisition-layout');
                        if (['grid', 'list'].includes(savedLayout)) this.catalogLayout = savedLayout;
                    } catch (error) { /* The layout toggle works without browser storage. */ }
                    if (!['minor', 'major'].includes(previousRequest.tier)) return;

                    this.tier = previousRequest.tier;
                    this.view = 'catalog';
                },

                setCatalogLayout(layout) {
                    if (!['grid', 'list'].includes(layout)) return;
                    this.catalogLayout = layout;
                    try {
                        localStorage.setItem('htc-requisition-layout', layout);
                    } catch (error) { /* Keep the selected layout for this session. */ }
                },

                reviewCart() {
                    this.cartOpen = true;
                    this.$nextTick(() => {
                        this.$refs.cartBody.scrollTop = 0;
                        this.$refs.cartBody.focus({preventScroll: true});
                    });
                },
                continueBrowsing() {
                    this.cartOpen = false;
                    this.$nextTick(() => this.$refs.catalogPane.focus({preventScroll: true}));
                },
                completeReason() {
                    this.cartOpen = true;
                    this.$nextTick(() => {
                        this.$refs.purpose.focus({preventScroll: true});
                        this.$refs.purpose.scrollIntoView({block: 'nearest'});
                    });
                },

                async loadCatalog() {
                    if (this.catalogLoaded || this.catalogLoading) return;

                    this.catalogLoading = true;
                    this.catalogError = '';
                    try {
                        const response = await fetch(@json(route('api.requisition-catalog')), {
                            headers: {'Accept': 'application/json'},
                            credentials: 'same-origin',
                        });
                        if (!response.ok) throw new Error('Catalog request failed.');

                        const catalog = await response.json();
                        if (window.htcSession?.expired) return;
                        if (!Array.isArray(catalog)) throw new Error('Invalid catalog response.');

                        this.catalog = catalog;
                        this.catalogLoaded = true;
                        this.restorePreviousRequest(previousRequest);
                    } catch (error) {
                        if (error.name === 'SessionExpiredError') return;
                        this.catalogError = 'Could not load supplies. Please try again.';
                    } finally {
                        this.catalogLoading = false;
                    }
                },

                restorePreviousRequest(previousRequest) {
                    if (!['minor', 'major'].includes(previousRequest.tier)) return;

                    this.cart = (previousRequest.items || []).map((submitted) => {
                        const item = this.catalog.find(catalogItem => catalogItem.id === Number(submitted.supply_id));
                        if (!item || item.tier !== this.tier) return null;
                        const options = submitted.options || {};
                        return {...item, options, qty: Math.min(item.stock, Math.max(1, Number(submitted.qty) || 1)), key: item.id + ':' + JSON.stringify(options)};
                    }).filter(Boolean);
                    this.refreshIcons();
                },

                catalogCount(tier) {
                    return this.catalog.filter(item => item.tier === tier).length;
                },

                setTier(tier) {
                    this.tier = tier;
                    this.view = 'catalog';
                    this.search = '';
                    this.category = 'All';
                    this.cart = [];
                    this.cartOpen = false;
                    this.lastAddedKey = null;
                    this.refreshIcons();
                },
                async backToTiers() {
                    if (this.cart.length && !await window.htcConfirm({
                        title: 'Change requisition tier?',
                        message: 'Changing the tier will clear the items in your cart.',
                        accept: 'Clear cart and change tier',
                        cancel: 'Keep current cart',
                        tone: 'danger',
                    })) return;
                    this.view = 'selection';
                    this.cart = [];
                    this.cartOpen = false;
                    this.lastAddedKey = null;
                    this.selectedItem = null;
                    this.refreshIcons();
                },
                openProduct(item) {
                    this.selectedItem = item;
                    this.selectedOptions = Object.fromEntries(Object.entries(item.options || {})
                        .filter(([, choices]) => choices.length === 1)
                        .map(([label, choices]) => [label, choices[0]]));
                    this.productQty = 1;
                    this.view = 'product';
                    this.refreshIcons();
                },
                addConfiguredItem() {
                    if (!this.canAddProduct) return;
                    const options = {...this.selectedOptions};
                    const key = this.selectedItem.id + ':' + JSON.stringify(options);
                    const existing = this.cart.find(item => item.key === key);
                    if (existing) {
                        existing.qty = Math.min(existing.stock, existing.qty + this.productQty);
                    } else {
                        this.cart.push({...this.selectedItem, key, options, qty: this.productQty});
                    }
                    this.lastAddedKey = key;
                    this.cartOpen = false;
                    this.view = 'catalog';
                    this.notice = `${this.selectedItem.name} was added to your requisition.`;
                    window.clearTimeout(this.noticeTimer);
                    this.noticeTimer = window.setTimeout(() => this.notice = '', 3500);
                    this.refreshIcons();
                    this.$nextTick(() => {
                        if (!window.matchMedia('(min-width: 992px)').matches) return;
                        const addedItem = Array.from(this.$refs.cartBody.querySelectorAll('[data-cart-key]'))
                            .find(element => element.dataset.cartKey === key);
                        addedItem?.scrollIntoView({block: 'nearest'});
                    });
                },
                removeItem(index) { this.cart.splice(index, 1); },
                optionSummary(options) { return Object.entries(options || {}).map(([label, value]) => `${label}: ${value}`).join(' · '); },
                brandCaption(item) {
                    const brands = item?.options?.Brand || [];
                    return brands.length > 1 ? `${brands.length} brands available` : (brands[0] || item?.brand || 'HTC catalog');
                },
                formatMoney(value) { return Number(value || 0).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
                refreshIcons() { setTimeout(() => window.lucide?.createIcons(), 30); },
                handleRequisitionSubmit(event) {
                    if (this.submissionConfirmed) return;

                    event.preventDefault();
                    if (!this.canSubmit || !event.currentTarget.reportValidity()) return;

                    this.pendingForm = event.currentTarget;
                    this.confirmationOpen = true;
                    this.$nextTick(() => {
                        this.refreshIcons();
                        this.$refs.confirmRequisitionButton?.focus();
                    });
                },
                cancelRequisitionConfirmation() {
                    this.confirmationOpen = false;
                    this.pendingForm = null;
                    this.$nextTick(() => this.$refs.requisitionForm?.querySelector('button[type="submit"]')?.focus());
                },
                confirmRequisitionSubmit() {
                    if (!this.pendingForm || this.submissionConfirmed) return;

                    this.submissionConfirmed = true;
                    this.confirmationOpen = false;
                    this.submitting = true;
                    this.pendingForm.dataset.noUnloadLoader = '';
                    this.$nextTick(() => window.requestAnimationFrame(() => this.pendingForm?.requestSubmit()));
                },
                async confirmRequisitionClose(event) {
                    if (this.submitting || this.submissionConfirmed) {
                        event.preventDefault();
                        return;
                    }
                    if (this.discardConfirmed) {
                        this.discardConfirmed = false;
                        return;
                    }
                    event.preventDefault();
                    if (this.closeConfirmationPending) return;
                    const modal = event.currentTarget;
                    this.closeConfirmationPending = true;
                    try {
                        const discard = await window.htcConfirm({
                            title: 'Cancel this requisition?',
                            message: 'Are you sure you want to close this requisition? Any selected items and entered details will be discarded.',
                            accept: 'Discard requisition',
                            cancel: 'Keep editing',
                            tone: 'danger',
                        });
                        if (!discard) return;
                        this.discardConfirmed = true;
                        window.bootstrap.Modal.getOrCreateInstance(modal).hide();
                    } finally {
                        this.closeConfirmationPending = false;
                    }
                },
                resetModal() {
                    this.tier = null;
                    this.view = 'selection';
                    this.cart = [];
                    this.cartOpen = false;
                    this.lastAddedKey = null;
                    this.selectedItem = null;
                    this.purpose = '';
                    this.notice = '';
                    this.confirmationOpen = false;
                    this.submissionConfirmed = false;
                    this.submitting = false;
                    this.pendingForm = null;
                    this.closeConfirmationPending = false;
                    this.discardConfirmed = false;
                    this.$refs.requisitionForm?.setAttribute('data-no-loader', '');
                    this.$refs.requisitionForm?.removeAttribute('data-no-unload-loader');
                },
                get tierLabel() { return this.tier === 'major' ? 'Major requisition' : 'Minor requisition'; },
                get tierItems() { return this.catalog.filter(item => item.tier === this.tier); },
                get categories() { return [...new Set(this.tierItems.map(item => item.category))].sort(); },
                get filteredItems() {
                    const term = this.search.trim().toLowerCase();
                    return this.tierItems.filter(item => {
                        const optionText = Object.entries(item.options || {})
                            .flatMap(([label, choices]) => [label, ...(choices || [])])
                            .join(' ');
                        const searchableText = `${item.name || ''} ${item.brand || ''} ${item.model || ''} ${item.category || ''} ${item.specs || ''} ${optionText}`.toLowerCase();

                        return (this.category === 'All' || item.category === this.category)
                            && (!term || searchableText.includes(term));
                    });
                },
                get allOptionsSelected() {
                    return this.selectedItem && Object.keys(this.selectedItem.options || {}).every(label => this.selectedOptions[label]);
                },
                get canAddProduct() { return this.allOptionsSelected && this.productQty >= 1 && this.productQty <= (this.selectedItem?.stock || 0); },
                get grandTotal() { return this.cart.reduce((sum, item) => sum + (item.qty * item.price), 0); },
                get cartQuantity() { return this.cart.reduce((sum, item) => sum + item.qty, 0); },
                get purposeIsValid() { return this.purpose.trim().length >= 10; },
                get canSubmit() { return this.cart.length > 0 && this.purposeIsValid && !(this.tier === 'minor' && this.grandTotal > 1000); },
                get submitLabel() {
                    if (!this.cart.length) return 'Add at least one item';
                    if (!this.purposeIsValid) return 'Add a reason to continue';
                    if (this.tier === 'minor' && this.grandTotal > 1000) return 'Minor limit exceeded';
                    return 'Submit requisition →';
                }
            };
        }

        const requisitionModal = document.getElementById('globalRequestModal');
        requisitionModal?.addEventListener('show.bs.modal', event => {
            event.currentTarget.scrollTop = 0;
            window.Alpine.$data(event.currentTarget).loadCatalog();
        });
        requisitionModal?.addEventListener('hidden.bs.modal', event => {
            window.Alpine.$data(event.currentTarget).resetModal();
        });
        requisitionModal?.addEventListener('hide.bs.modal', event => {
            window.Alpine.$data(event.currentTarget).confirmRequisitionClose(event);
        });

        @if($errors->any() && old('request_type'))
            window.setTimeout(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('globalRequestModal')).show(), 100);
        @endif

        // Keep every signed-in workspace in sync with requisition and inventory actions.
        (() => {
            const endpoint = @json(route('api.runtime-state'));
            const expectedAccountId = {{ (int) Auth::id() }};
            const scope = document.body?.dataset.runtimeScope || 'all';
            const notificationKey = 'htc-presented-notification-{{ Auth::id() }}';
            const cacheKey = 'htc-runtime-state:{{ Auth::id() }}';
            const notice = document.getElementById('liveRefreshNotice');
            const message = document.getElementById('liveRefreshMessage');
            const status = document.getElementById('liveUpdateStatus');
            const pollInterval = 15000;
            let currentVersion = document.body?.dataset.runtimeVersion || null;
            let pendingVersion = currentVersion;
            let refreshPending = false;
            let checking = false;
            let timer = null;
            let failures = 0;
            let lastCheckedAt = 0;
            let refreshTimer = null;
            let refreshing = false;
            let workspaceFailed = false;
            const tabId = window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;
            const leaseKey = `htc-runtime-poller:${expectedAccountId}`;
            const retryKey = `htc-runtime-retry:${expectedAccountId}`;
            let retryAt = 0;
            const syncChannel = 'BroadcastChannel' in window ? new BroadcastChannel(`htc-runtime-${expectedAccountId}`) : null;

            const retryWait = () => {
                try {
                    const stored = Number(localStorage.getItem(retryKey));
                    if (Number.isFinite(stored)) retryAt = Math.max(retryAt, stored);
                } catch (error) { /* The in-memory cooldown still protects this tab. */ }
                return Math.max(0, retryAt - Date.now());
            };

            const pausePolling = response => {
                const retryAfter = response.headers?.get('Retry-After');
                const seconds = Number(retryAfter);
                const until = seconds > 0 && Number.isFinite(seconds)
                    ? Date.now() + seconds * 1000 : Date.parse(retryAfter || '');
                retryAt = Math.max(retryAt, Date.now() + pollInterval,
                    Number.isFinite(until) ? until : Date.now() + 60000);
                try { localStorage.setItem(retryKey, String(retryAt)); } catch (error) { /* Storage is optional. */ }
                syncChannel?.postMessage({type: 'cooldown', until: retryAt});
                message.textContent = 'Live updates are paused briefly. Retrying automatically.';
                notice.hidden = false;
            };

            const releaseLease = () => {
                if (!syncChannel) return;
                try {
                    if (JSON.parse(localStorage.getItem(leaseKey) || 'null')?.tab === tabId) {
                        localStorage.removeItem(leaseKey);
                        syncChannel.postMessage({type: 'released'});
                    }
                } catch (error) { /* Each tab can poll independently if storage is unavailable. */ }
            };

            const ownPollingLease = () => {
                if (!syncChannel) return true;
                try {
                    const lease = JSON.parse(localStorage.getItem(leaseKey) || 'null');
                    if (lease && lease.tab !== tabId && lease.until > Date.now()) return false;
                    localStorage.setItem(leaseKey, JSON.stringify({tab: tabId, until: Date.now() + pollInterval * 3}));
                    return JSON.parse(localStorage.getItem(leaseKey) || 'null')?.tab === tabId;
                } catch (error) { return true; }
            };

            const userIsWorking = () => {
                const active = document.activeElement;
                const editing = active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName);
                return window.htcSession?.expired || document.hidden || editing
                    || document.documentElement?.getAttribute('aria-busy') === 'true'
                    || document.body?.classList?.contains('sidebar-open')
                    || Boolean(document.querySelector('.modal.show, dialog[open], .main-content-inner [data-dirty], .main-content-inner [aria-busy="true"], .main-content-inner input[type="checkbox"]:checked, [data-filter-state="loading"], [data-filter-state="invalid"], [data-filter-state="error"]'));
            };

            const showUpdateError = () => {
                message.textContent = 'Live updates are temporarily unavailable. Retrying automatically.';
                notice.hidden = false;
            };

            const refreshWorkspace = async () => {
                if (window.htcSession?.expired || refreshing || !refreshPending || userIsWorking()) return;
                const regions = [...document.querySelectorAll('[data-live-region]')];
                if (!regions.length) return;
                refreshing = true;
                const targetVersion = pendingVersion;
                const targetUrl = window.location.href;
                const controller = new AbortController();
                const timeout = window.setTimeout(() => controller.abort(), 10000);
                try {
                    const response = await fetch(targetUrl, {
                        headers: {'Accept': 'text/html', 'X-Workspace-Refresh': 'true', 'X-Requested-With': 'XMLHttpRequest'},
                        credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                    });
                    if (!response.ok || response.redirected) throw new Error('Workspace refresh unavailable');
                    const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const replacements = regions.map(region => ({
                        region,
                        next: parsed.querySelector(`[data-live-region="${region.dataset.liveRegion}"]`),
                    }));
                    if (replacements.some(item => !item.next)) throw new Error('Missing live content');
                    // An action can begin while the response is in flight. Keep the user's work.
                    if (userIsWorking() || window.location.href !== targetUrl) return;
                    const focused = document.activeElement;
                    const focusId = focused?.id;
                    const focusHref = focused?.getAttribute?.('href');
                    const scrollX = window.scrollX;
                    const scrollY = window.scrollY;
                    const scrollContainers = [...document.querySelectorAll('[data-live-region] [id], [data-employee-notifications]')]
                        .filter(element => (element.scrollTop || element.scrollLeft) && (element.id || element.hasAttribute('data-employee-notifications')))
                        .map(element => ({id: element.id, top: element.scrollTop, left: element.scrollLeft}));
                    const activeTabs = [...document.querySelectorAll('[data-bs-toggle="pill"].active')].map(tab => tab.id);
                    replacements.forEach(({region, next}) => {
                        next.querySelectorAll('script').forEach(script => script.remove());
                        const replace = () => {
                            if (window.bootstrap) {
                                region.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => window.bootstrap.Tab.getInstance(tab)?.dispose());
                                region.querySelectorAll('.collapse').forEach(element => window.bootstrap.Collapse.getInstance(element)?.dispose());
                            }
                            window.Alpine?.destroyTree(region);
                            region.innerHTML = next.innerHTML;
                            window.Alpine?.initTree(region);
                        };
                        if (window.Alpine?.mutateDom) window.Alpine.mutateDom(replace);
                        else replace();
                    });
                    activeTabs.forEach(id => {
                        const tab = document.getElementById(id);
                        if (tab && window.bootstrap) window.bootstrap.Tab.getOrCreateInstance(tab).show();
                    });
                    window.lucide?.createIcons();
                    currentVersion = targetVersion;
                    refreshPending = pendingVersion !== currentVersion;
                    workspaceFailed = false;
                    notice.hidden = true;
                    document.dispatchEvent(new CustomEvent('htc:workspace-updated'));
                    scrollContainers.forEach(saved => {
                        const element = saved.id ? document.getElementById(saved.id) : document.querySelector('[data-employee-notifications]');
                        if (element) { element.scrollTop = saved.top; element.scrollLeft = saved.left; }
                    });
                    if (focused && !focused.isConnected) {
                        const replacement = focusId ? document.getElementById(focusId)
                            : focusHref ? [...document.querySelectorAll('a[href]')].find(link => link.getAttribute('href') === focusHref) : null;
                        replacement?.focus({preventScroll: true});
                    }
                    if (Number.isFinite(scrollY) && Number.isFinite(scrollX)) window.scrollTo({left: scrollX, top: scrollY, behavior: 'instant'});
                    status.textContent = 'Content updated.';
                } catch (error) {
                    if (window.htcSession?.expired || error.name === 'SessionExpiredError') return;
                    workspaceFailed = true;
                    showUpdateError();
                } finally {
                    window.clearTimeout(timeout);
                    refreshing = false;
                }
            };

            const refreshWhenSafe = () => {
                if (window.htcSession?.expired || !refreshPending || refreshing) return;
                if (userIsWorking()) {
                    window.clearTimeout(refreshTimer);
                    refreshTimer = null;
                    return;
                }
                if (refreshTimer !== null) return;
                refreshTimer = window.setTimeout(() => {
                    refreshTimer = null;
                    if (userIsWorking()) refreshWhenSafe();
                    else return refreshWorkspace();
                }, 100);
            };

            const scheduleCheck = delay => {
                window.clearTimeout(timer);
                if (window.htcSession?.expired) return;
                timer = window.setTimeout(checkForUpdates, delay);
            };

            const cacheMetadata = data => {
                try {
                    sessionStorage.setItem(cacheKey, JSON.stringify({
                        version: data.version,
                        latest_id: Number(data.latest_id || 0),
                        unread_count: Number(data.unread_count || 0),
                        cached_at: Date.now(),
                    }));
                } catch (error) {
                    // Runtime caching is an optional optimization.
                }
            };

            const acceptRuntimeState = data => {
                if (Number(data.account_id) !== expectedAccountId) return;
                failures = 0;
                lastCheckedAt = Date.now();
                cacheMetadata(data);
                if (!workspaceFailed) notice.hidden = true;
                window.htcRuntimeState = data;
                document.dispatchEvent(new CustomEvent('htc:runtime-state', {detail: data}));
                const version = data.versions?.[scope] || data.version;
                if (currentVersion === null) currentVersion = version;
                else if (version !== currentVersion) {
                    pendingVersion = version;
                    refreshPending = true;
                    refreshWhenSafe();
                }
            };

            syncChannel?.addEventListener('message', event => {
                if (event.data?.type === 'state') acceptRuntimeState(event.data.data);
                if (event.data?.type === 'released' && !document.hidden) scheduleCheck(0);
                if (event.data?.type === 'cooldown' && Number.isFinite(event.data.until)) {
                    retryAt = Math.max(retryAt, event.data.until);
                    scheduleCheck(retryWait());
                }
            });

            const initialDelay = () => {
                try {
                    const cached = JSON.parse(sessionStorage.getItem(cacheKey) || 'null');
                    const age = Date.now() - Number(cached?.cached_at || 0);
                    const mutationAge = Date.now() - Number(sessionStorage.getItem('htc-runtime-mutated-at') || 0);
                    const cacheDelay = age >= 0 && age < pollInterval ? pollInterval - age : 0;
                    const mutationDelay = mutationAge >= 0 && mutationAge < 1000 ? 1000 - mutationAge : 0;
                    return Math.max(cacheDelay, mutationDelay);
                } catch (error) {
                    return 0;
                }
            };

            const checkForUpdates = async () => {
                if (window.htcSession?.expired || checking) return;
                // Navigation, tab takeover, and the Retry button must all obey
                // the server's cooldown instead of extending a 429 failure loop.
                const wait = retryWait();
                if (wait > 0) {
                    scheduleCheck(wait);
                    return;
                }
                if (document.hidden) {
                    releaseLease();
                    scheduleCheck(60000);
                    return;
                }
                if (!ownPollingLease()) {
                    scheduleCheck(pollInterval);
                    return;
                }
                checking = true;
                const controller = new AbortController();
                const timeout = window.setTimeout(() => controller.abort(), 8000);

                try {
                    let afterId = 0;
                    try {
                        afterId = Math.max(0, Number(localStorage.getItem(notificationKey)) || 0);
                    } catch (error) {
                        // The popup component keeps an in-memory cursor when storage is disabled.
                    }
                    const response = await fetch(`${endpoint}?after_id=${afterId}&scope=${scope}`, {
                        headers: {'Accept': 'application/json'},
                        cache: 'no-store',
                        credentials: 'same-origin',
                        signal: controller.signal,
                    });
                    if (response.status === 429) {
                        pausePolling(response);
                        return;
                    }
                    if (!response.ok) throw new Error(`Runtime poll failed with HTTP ${response.status}`);

                    const data = await response.json();
                    if (Number(data.account_id) !== expectedAccountId) {
                        window.htcSession?.show();
                        return;
                    }
                    acceptRuntimeState(data);
                    syncChannel?.postMessage({type: 'state', data});
                } catch (error) {
                    failures++;
                    if (failures >= 3 && !window.htcSession?.expired) showUpdateError();
                } finally {
                    window.clearTimeout(timeout);
                    checking = false;
                    const retryDelay = failures ? Math.min(60000, pollInterval * (2 ** failures)) : pollInterval;
                    scheduleCheck(Math.max(retryDelay, retryWait()));
                }
            };

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) releaseLease();
                if (!document.hidden && Date.now() - lastCheckedAt > pollInterval) checkForUpdates();
                if (!document.hidden) refreshWhenSafe();
            });
            document.addEventListener('focusout', () => window.setTimeout(refreshWhenSafe, 150));
            document.addEventListener('htc:session-expired', () => {
                window.clearTimeout(timer);
                window.clearTimeout(refreshTimer);
                releaseLease();
                notice.hidden = true;
            });
            document.addEventListener('hidden.bs.modal', refreshWhenSafe);
            document.addEventListener('input', event => {
                const form = event.target.closest?.('.main-content-inner form');
                if (form && (form.method || 'get').toLowerCase() !== 'get') form.dataset.dirty = 'true';
            });
            window.addEventListener('pagehide', releaseLease);
            document.getElementById('liveRefreshButton')?.addEventListener('click', async () => {
                await checkForUpdates();
                refreshWhenSafe();
            });
            scheduleCheck(initialDelay());
        })();

        // Server-rendered totals must remain stable when Alpine initializes or a
        // background refresh replaces the dashboard metrics.
        function countUp(target) {
            return {
                current: Number(target) || 0,
            }
        }
    </script>
</body>
</html>
