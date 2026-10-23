<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    public $timestamps = false; // Uses created_at timestamp only

    protected $fillable = [
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
