<?php

namespace App\Services;

use App\Models\Requisition;
use App\Models\Supply;

class FulfillmentInventoryService
{
    /**
     * Resolve all lines before any deduction. The review snapshot is advisory;
     * fulfillment rebuilds the same plan while holding inventory row locks.
     */
    public function plan(Requisition $requisition, bool $lock = false): array
    {
        if ($lock) {
            $linked = Supply::query()->whereIn('id', $requisition->items->pluck('supply_id')->filter()->unique())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        } else {
            $requisition->loadMissing('items.supply');
            $linked = $requisition->items->pluck('supply')->filter(fn ($supply) => $supply && ! $supply->trashed())->keyBy('id');
        }

        $legacy = $plans = $lines = $errors = [];
        foreach ($requisition->items as $item) {
            if ($item->supply_id) {
                $supply = $linked->get($item->supply_id);
            } else {
                $name = mb_strtolower(trim($item->item_name));
                if (! array_key_exists($name, $legacy)) {
                    // Legacy lines resolve by exact name, never LIKE wildcards.
                    $query = Supply::whereRaw('LOWER(item_name) = ?', [$name])->orderBy('id');
                    $legacy[$name] = ($lock ? $query->lockForUpdate() : $query)->first();
                }
                $supply = $legacy[$name];
            }

            $lines[$item->id] = ['supply_id' => $supply?->id, 'available' => $supply?->quantity];
            if (! $supply) {
                $errors[] = "Cannot prepare '{$item->item_name}': no active catalog stock is linked or matched. Resolve this item before marking the full request ready.";

                continue;
            }
            $plans[$supply->id] ??= ['supply' => $supply, 'quantity' => 0];
            $plans[$supply->id]['quantity'] += (int) $item->quantity;
        }

        foreach ($plans as $plan) {
            $supply = $plan['supply'];
            if ($supply->quantity < $plan['quantity']) {
                $errors[] = "Insufficient stock for '{$supply->item_name}'. Available: {$supply->quantity}, Requested: {$plan['quantity']}.";
            }
        }
        foreach ($lines as &$line) {
            $line['requested_total'] = $plans[$line['supply_id']]['quantity'] ?? null;
            $line['shortage'] = $line['available'] === null || $line['available'] < $line['requested_total'];
        }
        unset($line);
        if ($requisition->items->isEmpty()) {
            $errors[] = 'This requisition has no line items. Resolve the item breakdown before marking it ready.';
        }

        return compact('plans', 'lines', 'errors');
    }
}
