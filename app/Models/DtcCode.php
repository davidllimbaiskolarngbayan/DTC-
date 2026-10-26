<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DtcCode extends Model
{
    protected $table = 'dtc_codes';

    protected $fillable = ['code', 'is_use'];

    protected $casts = ['is_use' => 'boolean'];

    public function scopeCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function scopeAvailableCodes(Builder $query): Builder
    {
        return $query->where('is_use', false);
    }
}
