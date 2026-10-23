<?php

use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\SessionRecoveryController;
use App\Http\Controllers\SupplyController;
use Illuminate\Support\Facades\Route;

Route::get('/session/recover', [SessionRecoveryController::class, 'recover'])->name('session.recover');
Route::post('/session/check', [SessionRecoveryController::class, 'check'])->name('session.check');

// Welcome Page
Route::get('/', function () {
    return redirect()->route('login');
});

Route::view('/terms-of-service', 'legal.terms')->name('legal.terms');
Route::view('/privacy-policy', 'legal.privacy')->name('legal.privacy');

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')->name('dashboard');

// EMPLOYEE REQUEST & ARCHIVE ROUTES
Route::middleware(['auth'])->group(function () {
    Route::get('/requests', [RequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/archive', [RequestController::class, 'archive'])->name('requests.archive');
    Route::get('/requests/{id}/download', [RequestController::class, 'downloadPDF'])->name('requests.download');
    Route::get('/requests/{id}', [RequestController::class, 'show'])->name('requests.show');
});

// SMO INVENTORY & USER MANAGEMENT ROUTES
Route::middleware(['auth', 'role:smo'])->group(function () {
    // Inventory Management
    Route::get('/inventory', [SupplyController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/forecast/report', [SupplyController::class, 'forecastReport'])->name('inventory.forecast.report');
    Route::get('/inventory/create', [SupplyController::class, 'create'])->name('inventory.create');
    Route::post('/inventory', [SupplyController::class, 'store'])->name('inventory.store');

    // --- ADDED THESE INVENTORY ROUTES ---
    Route::get('/inventory/{id}/edit', [SupplyController::class, 'edit'])->name('inventory.edit');
    Route::patch('/inventory/{id}', [SupplyController::class, 'update'])->name('inventory.update');
    Route::delete('/inventory/bulk', [SupplyController::class, 'destroyMany'])->name('inventory.bulk-destroy');
    Route::delete('/inventory/{id}', [SupplyController::class, 'destroy'])->name('inventory.destroy');
    // -------------------------------------

    // USER ACCESS MANAGEMENT
    Route::get('/admin/users/pending', [ProfileController::class, 'pendingUsers'])->name('admin.users.pending');
    Route::post('/admin/users/{id}/approve', [ProfileController::class, 'approveUser'])->whereNumber('id')->name('admin.users.approve');
});

// PROFILE ROUTES (Standard Laravel)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';

// ADMIN APPROVAL ROUTES
Route::middleware(['auth', 'role:dept_head,vp_finance,vp_admin,provost,president,smo'])->group(function () {
    Route::get('/admin/approvals', [RequestController::class, 'adminIndex'])->name('admin.approvals');
    Route::post('/admin/requests/{id}/status', [RequestController::class, 'updateStatus'])->name('admin.status.update');
    Route::post('/admin/requests/{id}/processing', [RequestController::class, 'startProcessing'])->name('admin.requests.processing');
    Route::post('/admin/requests/{id}/ready-for-pickup', [RequestController::class, 'markReadyForPickup'])->name('admin.requests.ready-for-pickup');
    Route::post('/admin/requests/{id}/complete', [RequestController::class, 'completeRequest'])->name('admin.requests.complete');
    Route::post('/admin/requests/{requisition}/deadline-reminder', [RequestController::class, 'sendApprovalDeadlineReminder'])
        ->middleware('role:smo')->whereNumber('requisition')->name('admin.requests.deadline-reminder');
});

Route::middleware(['auth', 'role:smo,president,provost,vp_admin,dept_head'])->group(function () {
    Route::get('/admin/users/manage', [ProfileController::class, 'manageUsers'])->name('admin.users.manage');
    Route::patch('/admin/users/{user}', [ProfileController::class, 'updateManagedUser'])->whereNumber('user')->name('admin.users.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
});

// Change the submission route to point to the new Requisition Controller
Route::post('/requisitions', [RequisitionController::class, 'store'])->middleware('auth')->name('requisitions.store');
Route::get('/api/requisition-catalog', [SupplyController::class, 'requisitionCatalog'])
    ->middleware('auth')
    ->name('api.requisition-catalog');

Route::get('/api/latest-notification', [NotificationController::class, 'poll'])
    ->middleware(['auth', 'throttle:runtime-polling']);
Route::get('/api/system-version', [RequestController::class, 'systemVersion'])
    ->middleware('auth')
    ->name('api.system-version');
Route::get('/api/runtime-state', [RequestController::class, 'runtimeState'])
    ->middleware(['auth', 'throttle:runtime-polling'])
    ->name('api.runtime-state');

// Requestors may manage only their own pending requisitions.
Route::middleware('auth')->group(function () {
    Route::get('/requisitions/{id}/edit', [RequisitionController::class, 'edit'])->name('requisitions.edit');
    Route::patch('/requisitions/{id}', [RequisitionController::class, 'update'])->name('requisitions.update');
    Route::delete('/requisitions/{id}', [RequisitionController::class, 'destroy'])->name('requisitions.destroy');
});

// Statistics and Reports Page
// SMO REPORTING & ANALYTICS
Route::middleware(['auth', 'role:smo'])->group(function () {
    Route::get('/admin/reports', [ReportController::class, 'index'])->name('admin.reports.index');
    Route::get('/admin/reports/builder', [ReportController::class, 'builder'])->name('admin.reports.builder');
    Route::get('/admin/reports/generate', [ReportController::class, 'generate'])->name('admin.reports.generate');

    // Preserve the old analytics bookmark; the main reports URL is now the analytics page.
    Route::redirect('/admin/reports/analytics', '/admin/reports')->name('admin.reports.analytics');
});

Route::get('/admin/archive', [ArchiveController::class, 'index'])
    ->middleware(['auth', 'role:smo'])
    ->name('admin.archive');
