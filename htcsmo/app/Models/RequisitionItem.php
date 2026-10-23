<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItem extends Model
{
    protected $fillable = [
        'requisition_id',
        'supply_id', // <--- ADD THIS
        'item_name',
        'specifications',
        'quantity',
        'unit',
        'unit_price',
        'subtotal',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class)->withTrashed();
    }
}
