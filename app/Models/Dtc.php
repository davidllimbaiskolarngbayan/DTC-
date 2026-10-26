<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Dtc extends Model
{
    use SoftDeletes;

    protected $table = 'dtc_users';

    protected $fillable = [
        'firstname', 'lastname', 'email', 'contact_no', 'vehicle_reg_id',
        'brand', 'model', 'tyre', 'tyre_size', 'rim_size', 'quantity', 'date_of_purchase',
        'state', 'store_name', 'dtc_card_no', 'is_dtc', 'tnc_agree', 'subscribe',
        'online', 'mileage', 'batch',
    ];

    protected $casts = [
        'firstname' => 'encrypted',
        'lastname' => 'encrypted',
        'email' => 'encrypted',
        'contact_no' => 'encrypted',
        'vehicle_reg_id' => 'encrypted',
        'date_of_purchase' => 'datetime',
        'is_dtc' => 'boolean',
        'tnc_agree' => 'boolean',
        'subscribe' => 'boolean',
        'online' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $dtc) {
            $dtc->vehicle_reg_hash = static::hashVehicleRegId($dtc->vehicle_reg_id);
            $dtc->email_hash = $dtc->email ? static::hashEmail($dtc->email) : null;
        });
    }

    public function setDtcCardNoAttribute(?string $value): void
    {
        $this->attributes['dtc_card_no'] = strtoupper(trim((string) $value));
    }

    // Creates the registrant from the columns mapped by the market's setUser().
    public function createUser(array $user): static
    {
        return DB::transaction(function () use ($user) {
            $record = new static();
            $record->forceFill($user);
            $record->save();

            return $record->fresh();
        });
    }

    // $user must contain the record's 'id'.
    public function updateUser(array $user): static
    {
        return DB::transaction(function () use ($user) {
            $record = static::findOrFail($user['id']);
            unset($user['id']);

            $record->forceFill($user);
            $record->save();

            return $record->fresh();
        });
    }

    public static function normalizeVehicleRegId(?string $vehicleRegId): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $vehicleRegId));
    }

    // Keyed hash: plates and emails are low-entropy, so a plain hash would be brute-forceable.
    public static function hashVehicleRegId(?string $vehicleRegId): string
    {
        return hash_hmac('sha256', static::normalizeVehicleRegId($vehicleRegId), (string) config('app.key'));
    }

    public static function hashEmail(?string $email): string
    {
        return hash_hmac('sha256', strtolower(trim((string) $email)), (string) config('app.key'));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('dtc_card_no', strtoupper($term))
                ->orWhere('vehicle_reg_hash', static::hashVehicleRegId($term));
        });
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public function promos(): HasMany
    {
        return $this->hasMany(DtcPromo::class, 'user_id');
    }

    public function maskedVehicleRegId(): string
    {
        $plate = static::normalizeVehicleRegId($this->vehicle_reg_id);

        return substr($plate, 0, 2).str_repeat('*', max(strlen($plate) - 2, 0));
    }

    /** Values shown on the thank-you page and in the confirmation email. */
    public function thankYouData(): array
    {
        return [
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'brand' => $this->brand,
            'model' => $this->model,
            'vehicle_reg_id' => $this->vehicle_reg_id,
            'mileage' => $this->mileage,
            'quantity' => $this->quantity,
            'date_of_purchase' => $this->date_of_purchase?->format('Y-m-d'),
            'tyre' => $this->tyre,
            'tyre_size' => $this->tyre_size,
            'rim_size' => $this->rim_size,
            'dtc_card_no' => $this->dtc_card_no,
            'state' => $this->state,
            'store_name' => $this->store_name,
        ];
    }
}
