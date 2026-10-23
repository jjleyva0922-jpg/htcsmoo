<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalSignature extends Model
{
    protected $fillable = [
        'approval_log_id',
        'signature_token',
        'signer_name',
        'signer_role',
        'ip_address',
        'user_agent',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    /**
     * Get the approval log associated with this digital signature.
     */
    public function approvalLog(): BelongsTo
    {
        return $this->belongsTo(ApprovalLog::class);
    }
}
