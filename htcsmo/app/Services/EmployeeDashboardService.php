<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EmployeeDashboardService
{
    public function build(User $user): array
    {
        // Explicit ownership is intentional: these are personal figures for every caller.
        $active = Requisition::query()->where('user_id', $user->id)->active();
        $personalCounts = (clone $active)->selectRaw(
            "COUNT(*) as active, COALESCE(SUM(CASE WHEN status = 'for_clarification' THEN 1 ELSE 0 END), 0) as clarification,
             COALESCE(SUM(CASE WHEN status = 'ready_for_pickup' THEN 1 ELSE 0 END), 0) as pickup"
        )->first();
        $activeCount = (int) $personalCounts->active;
        $actionCount = (int) $personalCounts->clarification + (int) $personalCounts->pickup;
        $actionRequests = (clone $active)->needingRequestorAction()
            ->with(['items', 'latestClarificationLog'])->prioritizeForRequestor()->limit(4)->get();
        $recentRequests = (clone $active)->with(['items', 'latestClarificationLog'])
            ->prioritizeForRequestor()->limit(8)->get();
        $personalDeadlineCount = (clone $active)->deadlineRisks()->count();

        $personalNotifications = Notification::query()->forUser($user)
            ->where(function (Builder $notes) {
                $notes->where('event_type', 'like', 'requisition.%')
                    ->orWhere('event_type', 'like', 'deadline.%')
                    ->orWhereIn('action_type', ['requisition', 'approval_queue']);
            })
            // Do not preview a misaddressed notification referring to another person's request.
            ->where(function (Builder $notes) use ($user) {
                $notes->whereNull('action_id')->orWhereNotIn(
                    'action_id', Requisition::query()->where('user_id', '!=', $user->id)->select('id'),
                );
            })->latest('id')->limit(5)->get();
        $personalNotificationActions = app(NotificationActionResolver::class)->resolve($personalNotifications, $user);

        return compact(
            'personalCounts', 'activeCount', 'actionCount', 'actionRequests', 'recentRequests',
            'personalDeadlineCount', 'personalNotifications', 'personalNotificationActions',
        );
    }
}
