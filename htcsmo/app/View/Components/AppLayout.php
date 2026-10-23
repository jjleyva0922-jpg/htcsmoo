<?php

namespace App\View\Components;

use App\Models\Notification;
use App\Services\WorkspaceVersionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        $scope = match (true) {
            request()->routeIs('dashboard') => 'dashboard',
            request()->routeIs('inventory.*') => 'inventory',
            request()->routeIs('requests.*', 'admin.archive') => 'requests',
            request()->routeIs('admin.approvals') => 'approvals',
            request()->routeIs('admin.users.*') => 'users',
            request()->routeIs('notifications') => 'notifications',
            request()->routeIs('profile.*', 'requisitions.edit') => 'none',
            default => 'all',
        };
        $liveWorkspace = request()->routeIs('dashboard', 'requests.index', 'requests.archive', 'admin.archive', 'inventory.index');
        $workspaceData = [
            'workspaceScope' => $scope,
            'workspaceVersion' => app(WorkspaceVersionService::class)->version($scope),
            'liveWorkspace' => $liveWorkspace && ! request()->boolean('print'),
        ];

        if (request()->header('X-Workspace-Refresh') === 'true') {
            return view('layouts.workspace-fragment', $workspaceData);
        }

        $notificationState = Auth::check()
            ? Notification::query()->forUser(Auth::id())
                ->selectRaw(
                    'COALESCE(MAX(id), 0) as latest_id, COALESCE(SUM(CASE WHEN is_read = ? THEN 1 ELSE 0 END), 0) as unread_count',
                    [false]
                )->first()
            : null;

        if ($scope === 'dashboard') {
            $workspaceData['workspaceVersion'] = app(WorkspaceVersionService::class)
                ->dashboardVersion($workspaceData['workspaceVersion'], (int) ($notificationState?->latest_id ?? 0));
        }

        return view('layouts.app', [
            ...$workspaceData,
            'layoutLatestNotificationId' => (int) ($notificationState?->latest_id ?? 0),
            'layoutUnreadCount' => (int) ($notificationState?->unread_count ?? 0),
        ]);
    }
}
