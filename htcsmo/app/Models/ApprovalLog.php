<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ApprovalLog extends Model
{
    protected $fillable = [
        'requisition_id',
        'acted_by_user_id',
        'action',
        'role',
        'remarks',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }

    /**
     * The cryptographic digital signature generated for this action.
     */
    public function digitalSignature(): HasOne
    {
        return $this->hasOne(DigitalSignature::class);
    }
}
