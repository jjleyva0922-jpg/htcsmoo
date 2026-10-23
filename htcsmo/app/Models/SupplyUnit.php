<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplyUnit extends Model
{
    protected $fillable = [
        'unit_name',
        'abbreviation',
    ];

    public function supplies(): HasMany
    {
        return $this->hasMany(Supply::class);
    }
}
