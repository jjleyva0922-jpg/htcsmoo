<?php

namespace App\Services;

use App\Models\Supply;

class InventoryAlertService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function notifyIfThresholdCrossed(Supply $supply, bool $wasLow, bool $wasOut): void
    {
        $isOut = $supply->quantity <= 0;
        $isLow = $supply->quantity <= $supply->min_stock_level;

        if ((! $isOut || $wasOut) && (! $isLow || $wasLow)) {
            return;
        }

        $alert = $isOut
            ? [
                'title' => "Out of Stock: {$supply->item_name}",
                'message' => "{$supply->item_name} has reached zero stock. Review its demand forecast and arrange replenishment before the next request.",
                'icon' => 'package-x',
                'type' => 'danger',
            ]
            : [
                'title' => "Low Stock Warning: {$supply->item_name}",
                'message' => "Only {$supply->quantity} {$supply->unit} remain; the restock threshold is {$supply->min_stock_level}. Review the forecast and replenish soon.",
                'icon' => 'triangle-alert',
                'type' => 'warning',
            ];

        $state = $isOut ? 'out' : 'low';
        $this->notifications->sendToRole('smo', "inventory.{$state}", $alert['title'], $alert['message'], [
            'icon' => $alert['icon'],
            'type' => $alert['type'],
            'action_type' => 'inventory',
            'action_id' => $supply->id,
            'dedupe_key' => "inventory:{$supply->id}:{$state}:{$supply->updated_at?->format('U.u')}",
        ]);
    }
}
