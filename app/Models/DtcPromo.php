<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DtcPromo extends Model
{
    protected $table = 'dtc_promo';

    protected $fillable = [
        'user_id',
        'promo_key',
        'promo_value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(Dtc::class, 'user_id');
    }
}
