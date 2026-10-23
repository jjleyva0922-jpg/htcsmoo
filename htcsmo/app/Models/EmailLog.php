<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'recipient_email',
        'subject',
        'status',
        'error_message',
        'sent_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
