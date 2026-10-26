<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimAttachment extends Model
{
    protected $fillable = [
        'claim_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }
}
