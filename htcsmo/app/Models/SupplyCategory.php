<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyCategory extends Model
{
    protected $fillable = [
        'category_name',
        'description',
    ];

    /**
     * Supplies belonging to this institutional category.
     */
    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class, 'supply_category_id');
    }
}
