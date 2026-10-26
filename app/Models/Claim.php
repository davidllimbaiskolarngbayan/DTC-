<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Claim extends Model
{
    public const STATUSES = [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'need_more_info' => 'Need More Info',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'resolved' => 'Resolved',
    ];

    public const TRANSITIONS = [
        'submitted' => ['under_review', 'need_more_info', 'rejected'],
        'under_review' => ['need_more_info', 'approved', 'rejected'],
        'need_more_info' => ['under_review', 'rejected'],
        'approved' => [],
        'rejected' => [],
        'resolved' => [],
    ];

    public const OPEN_STATUSES = ['submitted', 'under_review', 'need_more_info'];

    protected $fillable = [
        'dtc_id',
        'claim_number',
        'subject',
        'description',
        'incident_date',
        'affected_tyres',
        'eligible',
        'eligibility_reason',
        'status',
        'created_by',
        'idempotency_key',
        'idempotency_payload_hash',
    ];

    protected $casts = [
        'eligible' => 'boolean',
        'incident_date' => 'date',
        'assigned_at' => 'datetime',
        'follow_up_reminded_at' => 'datetime',
    ];

    public function registrant(): BelongsTo
    {
        return $this->belongsTo(Dtc::class, 'dtc_id')->withTrashed();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ClaimHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClaimAttachment::class);
    }

    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(ClaimEmailDelivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function staffNotifications(): HasMany
    {
        return $this->hasMany(ClaimStaffNotification::class);
    }

    public static function eligibilityFor(Dtc $dtc): array
    {
        if (! $dtc->date_of_purchase) {
            return [false, 'Purchase date is missing, so warranty eligibility cannot be confirmed.'];
        }

        $timezone = config('dtc.timezone');
        $purchaseDate = Carbon::parse($dtc->date_of_purchase->toDateString(), $timezone)->startOfDay();
        $today = now($timezone)->startOfDay();

        if ($purchaseDate->greaterThan($today)) {
            return [false, 'The purchase date is in the future.'];
        }

        $expiryDate = $purchaseDate->copy()->addYear()->endOfDay();
        if (now($timezone)->greaterThan($expiryDate)) {
            return [false, 'The 12-month warranty from the purchase date has expired.'];
        }

        return [true, 'Within 12 months of the purchase date.'];
    }

    public static function eligibilityForIncident(Dtc $dtc, ?string $incidentDate): array
    {
        if (! $dtc->date_of_purchase) {
            return [false, 'Purchase date is missing, so warranty eligibility cannot be confirmed.'];
        }

        if (! $incidentDate) {
            return [false, 'Issue date is missing, so warranty eligibility cannot be confirmed; manual review is required.'];
        }

        $timezone = config('dtc.timezone');
        $purchaseDate = Carbon::parse($dtc->date_of_purchase->toDateString(), $timezone)->startOfDay();
        $issueDate = Carbon::parse($incidentDate, $timezone)->startOfDay();
        $expiryDate = $purchaseDate->copy()->addYear()->endOfDay();

        if ($issueDate->greaterThan($expiryDate)) {
            return [false, 'The issue occurred after the 12-month warranty expired on '.$expiryDate->toDateString().'; manual review is required.'];
        }

        return [true, 'The issue occurred within the 12-month warranty period.'];
    }

    public static function isOpenStatus(string $status): bool
    {
        return in_array($status, self::OPEN_STATUSES, true);
    }
}
