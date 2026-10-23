<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyInventoryLog extends Model
{
    protected $fillable = [
        'supply_id',
        'user_id',
        'requisition_id',
        'transaction_type',
        'quantity_change',
        'balance_after',
        'remarks',
    ];

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }
}
