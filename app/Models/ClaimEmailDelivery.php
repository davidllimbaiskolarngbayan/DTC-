<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimEmailDelivery extends Model
{
    protected $fillable = [
        'claim_id',
        'claim_history_id',
        'type',
        'recipient_hash',
        'status',
        'error',
        'retry_attempts',
        'next_attempt_at',
        'last_attempt_at',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'next_attempt_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}