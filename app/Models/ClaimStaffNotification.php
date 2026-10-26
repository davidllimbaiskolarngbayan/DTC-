<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimStaffNotification extends Model
{
    protected $fillable = [
        'claim_id',
        'user_id',
        'event_key',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
