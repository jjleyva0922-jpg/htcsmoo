<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyOptionValue extends Model
{
    protected $fillable = [
        'supply_option_id',
        'value',
        'sort_order',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(SupplyOption::class, 'supply_option_id');
    }
}
