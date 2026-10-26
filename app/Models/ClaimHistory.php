<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimHistory extends Model
{
    protected $fillable = [
        'claim_id',
        'changed_by',
        'event_type',
        'from_status',
        'to_status',
        'note',
        'customer_message',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
