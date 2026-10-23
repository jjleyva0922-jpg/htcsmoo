<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Requisition;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationActionResolver
{
    /**
     * Resolve notification actions in batches to avoid one query per feed row.
     *
     * @param  Collection<int, Notification>  $notifications
     * @return array<int, array{url: string, label: string}|null>
     */
    public function resolve(Collection $notifications, User $user): array
    {
        $requisitionIds = $notifications
            ->whereIn('action_type', ['requisition', 'approval_queue'])
            ->pluck('action_id')->filter()->unique()->values();
        $supplyIds = $notifications
            ->where('action_type', 'inventory')
            ->pluck('action_id')->filter()->unique()->values();

        $requisitions = $requisitionIds->isEmpty()
            ? collect()
            : Requisition::query()
                ->with(['user.academicDepartment', 'logs'])
                ->whereIn('id', $requisitionIds)
                ->get()
                ->keyBy('id');
        $supplies = $supplyIds->isEmpty()
            ? collect()
            : Supply::query()->whereIn('id', $supplyIds)->get()->keyBy('id');

        return $notifications->mapWithKeys(function (Notification $notification) use ($user, $requisitions, $supplies) {
            $target = match ($notification->action_type) {
                'requisition', 'approval_queue' => $requisitions->get($notification->action_id),
                'inventory' => $supplies->get($notification->action_id),
                default => null,
            };

            return [$notification->id => $notification->actionForResolvedTarget($user, $target)];
        })->all();
    }
}
