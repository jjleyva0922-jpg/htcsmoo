<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyOption extends Model
{
    protected $fillable = [
        'supply_id',
        'name',
        'sort_order',
    ];

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(SupplyOptionValue::class)->orderBy('sort_order')->orderBy('id');
    }
}
