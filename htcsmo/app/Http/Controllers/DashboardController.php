<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Requisition;
use App\Models\Supply;
use App\Services\EmployeeDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->role === 'employee') {
            return view('dashboard', app(EmployeeDashboardService::class)->build($user));
        }

        $activeCount = 0;
        $approvedLast24h = 0;
        $recentRequests = collect();

        if ($user->role !== 'smo') {
            // 1. BASE COUNTERS (Keep these to show overall health)
            $activeCount = Requisition::where('user_id', $user->id)
                ->whereNotIn('status', Requisition::ARCHIVED_STATUSES)
                ->count();

            $approvedLast24h = Requisition::where('user_id', $user->id)
                ->whereIn('status', ['approved_president', 'ready_for_pickup', 'completed', 'released'])
                ->where('updated_at', '>=', now()->subDay())
                ->count();

            // 2. EMPLOYEE TABLE: Filter to show ONLY Pending/In-Progress
            $recentRequests = Requisition::with('items')
                ->where('user_id', $user->id)
                ->whereNotIn('status', Requisition::ARCHIVED_STATUSES)
                ->latest()
                ->take(15)->get();
        }

        // SCHOOL ADMIN DASHBOARD: Show only requests currently assigned to this approver.
        $approvalRequests = collect();
        $approvalDeadlineRequests = collect();
        if (in_array($user->role, ['dept_head', 'vp_finance', 'vp_admin', 'provost', 'president'])) {
            $approvalQuery = Requisition::with(['items', 'user.academicDepartment']);

            switch ($user->role) {
                case 'dept_head':
                    $approvalQuery->where('status', 'pending')
                        ->whereHas('user', fn ($query) => $query->where('department_id', $user->department_id));
                    break;
                case 'vp_finance':
                    $approvalQuery->where('status', 'approved_dept')->where('request_type', 'minor');
                    break;
                case 'vp_admin':
                    $approvalQuery->where('status', 'approved_dept')->where('request_type', 'major');
                    break;
                case 'provost':
                    $approvalQuery->where('status', 'approved_vp')->where('request_type', 'major');
                    break;
                case 'president':
                    $approvalQuery->where('status', 'approved_provost')->where('request_type', 'major');
                    break;
            }

            $approvalRequests = $approvalQuery->latest()->take(5)->get();
            $approvalDeadlineRequests = Requisition::with(['items', 'user.academicDepartment'])
                ->deadlineRisks()
                ->assignedToReviewer($user)
                ->oldest('due_at')
                ->take(6)
                ->get();
        }

        // 3. SMO SPECIFIC LOGIC
        $stats = ['total' => 0, 'pending' => 0, 'urgent' => 0, 'monthly_value' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
        $recentActivity = [];
        $allStaffRequests = [];
        $deadlineRequests = collect();
        $lowStockSupplies = collect();
        $approvalDeadlineCount = null;

        if ($user->role == 'smo') {
            $deadlineQuery = Requisition::query()->deadlineRisks();
            $approvalDeadlineQuery = (clone $deadlineQuery)->awaitingAdministrativeApproval();
            $approvalDeadlineCount = (clone $approvalDeadlineQuery)->count();
            $approvalDeadlineRequests = $approvalDeadlineQuery
                ->with(['items', 'user.academicDepartment'])
                ->oldest('due_at')
                ->orderBy('id')
                ->take(6)
                ->get();
            $stats['total'] = Requisition::count();
            $stats['pending'] = Requisition::whereNotIn('status', Requisition::ARCHIVED_STATUSES)->count();
            $stats['urgent'] = (clone $deadlineQuery)->count();
            $deadlineRequests = (clone $deadlineQuery)
                ->with(['items', 'user.academicDepartment'])
                ->oldest('due_at')->orderBy('id')->take(4)->get();
            $inventoryStats = Supply::query()->selectRaw(
                'COALESCE(SUM(CASE WHEN quantity <= min_stock_level THEN 1 ELSE 0 END), 0) as low_stock,
                 COALESCE(SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END), 0) as out_of_stock'
            )->first();
            $stats['low_stock'] = (int) $inventoryStats->low_stock;
            $stats['out_of_stock'] = (int) $inventoryStats->out_of_stock;
            $lowStockSupplies = Supply::with('supplyUnit')->whereColumn('quantity', '<=', 'min_stock_level')
                ->orderByRaw('CASE WHEN quantity <= 0 THEN 0 ELSE 1 END')
                ->orderByRaw('(min_stock_level - quantity) DESC')
                ->orderBy('id')
                ->take(4)
                ->get();
            $stats['monthly_value'] = Requisition::whereIn('status', Requisition::FULFILLED_STATUSES)
                ->where('closed_at', '>=', now()->startOfMonth())
                ->where('closed_at', '<', now()->startOfMonth()->addMonth())->sum('grand_total');

            // SMO TABLE: Only pull active items that still need to be processed or released
            $allStaffRequests = Requisition::with(['items', 'user.academicDepartment'])
                ->whereNotIn('status', Requisition::ARCHIVED_STATUSES)
                ->latest()
                ->orderByDesc('id')
                ->take(10)
                ->get();

            $recentActivity = Notification::where('user_id', $user->id)->latest('id')->take(5)->get();
        }

        return view('dashboard', compact(
            'activeCount', 'approvedLast24h', 'recentRequests',
            'stats', 'allStaffRequests', 'recentActivity', 'approvalRequests',
            'deadlineRequests', 'approvalDeadlineRequests', 'approvalDeadlineCount', 'lowStockSupplies'
        ));
    }
}
