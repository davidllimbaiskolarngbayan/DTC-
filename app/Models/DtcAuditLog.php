<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class DtcAuditLog extends Model
{
    protected $fillable = [
        'dtc_id',
        'entity_type',
        'entity_id',
        'user_id',
        'action',
        'summary',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public static function record(
        Request $request,
        string $action,
        string $entityType,
        int $entityId,
        ?int $dtcId,
        string $summary,
        ?array $oldValues = null,
        ?array $newValues = null
    ): self {
        return static::create([
            'dtc_id' => $dtcId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'action' => $action,
            'summary' => $summary,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function registrant(): BelongsTo
    {
        return $this->belongsTo(Dtc::class, 'dtc_id')->withTrashed();
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'entity_id');
    }

    public function actorLabel(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        if (str_starts_with((string) $this->summary, 'Customer submitted')) {
            return 'Customer';
        }

        if (str_starts_with((string) $this->summary, 'API deleted')) {
            return 'DTC API';
        }

        if (str_starts_with((string) $this->action, 'claim.notification.auto_retry')
            || $this->action === 'claim.follow_up_reminder_sent') {
            return 'System';
        }

        return 'Former user';
    }
}