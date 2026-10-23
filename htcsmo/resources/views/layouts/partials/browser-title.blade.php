@php
    $browserSection = match (true) {
        request()->routeIs('dashboard') => 'Home',
        request()->routeIs('inventory.create') => 'Register Inventory Item',
        request()->routeIs('inventory.edit') => 'Edit Inventory Item',
        request()->routeIs('inventory.index') => 'Manage Stock',
        request()->routeIs('requisitions.edit') => 'Edit Requisition',
        request()->routeIs('requests.show') => 'Requisition Details',
        request()->routeIs('requests.archive') => 'Archive',
        request()->routeIs('requests.index') => 'Active Requisitions',
        request()->routeIs('notifications') => 'Notification Center',
        request()->routeIs('admin.approvals') => Auth::check() && Auth::user()->role === 'smo'
            ? 'Fulfillment Queue'
            : 'Pending Approvals',
        request()->routeIs('admin.users.pending') => 'Access Requests',
        request()->routeIs('admin.users.manage') => 'Manage Personnel',
        request()->routeIs('admin.reports.builder', 'admin.reports.generate') => 'Comprehensive Report Center',
        request()->routeIs('admin.reports.*') => 'Operational Analytics',
        request()->routeIs('admin.archive') => 'Institutional Archive',
        request()->routeIs('profile.edit') => 'Account Settings',
        request()->routeIs('login') => 'Sign In',
        request()->routeIs('approval.pending') => 'Approval Pending',
        request()->routeIs('register') => 'Create Account',
        request()->routeIs('legal.terms') => 'Terms of Service',
        request()->routeIs('legal.privacy') => 'Privacy Policy',
        request()->routeIs('password.request') => 'Forgot Password',
        request()->routeIs('password.reset') => 'Reset Password',
        request()->routeIs('verification.notice') => 'Verify Email',
        request()->routeIs('password.confirm') => 'Confirm Password',
        default => null,
    };
@endphp
<title>{{ $browserSection  }}</title>
