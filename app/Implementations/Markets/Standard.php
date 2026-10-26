<?php

namespace App\Implementations\Markets;

use App\Contracts\DtcMarket;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class Standard implements DtcMarket
{
    protected array $user = [];

    public function rules(): array
    {
        $cardNoRules = ['required', 'string', 'max:64', 'alpha_dash', 'unique:dtc_users,dtc_card_no'];
        if (config('dtc.require_dtc_code')) {
            $cardNoRules[] = Rule::exists('dtc_codes', 'code')->where('is_use', 0);
        }

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'contact_no' => ['required', 'string', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'vehicle_reg_id' => ['required', 'string', 'max:20'],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'tyre' => ['required', 'string', 'max:150'],
            'tyre_size' => ['required', 'string', 'max:50'],
            'rim_size' => ['required', 'string', 'max:50'],
            'quantity' => ['required', 'integer', 'between:1,8'],
            'date_of_purchase' => ['required', 'date', 'before_or_equal:'.now(config('dtc.timezone'))->toDateString()],
            'find_retailer' => ['required', 'string', 'max:150'],
            'state' => ['required', 'string', 'max:100'],
            'mileage' => ['required', 'integer', 'min:0', 'max:2000000'],
            'dtc_card_no' => $cardNoRules,
            'is_dtc' => ['nullable', 'boolean'],
            'tnc' => ['accepted'],
            'subscribe' => ['nullable', 'boolean'],
            'validpromo' => ['nullable', 'boolean'],
            'promo' => ['nullable', 'array'],
            'promo.promo' => ['required_if:validpromo,1', 'string', 'max:255'],
            'promo.assisted_person' => ['required_if:validpromo,1', 'string', 'max:100'],
            'promo.confirm' => ['required_if:validpromo,1', 'in:Yes,yes,1,true'],
            'promo.terms' => ['required_if:validpromo,1', 'in:Yes,yes,1,true'],
            'invoice' => ['required_if:validpromo,1', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }

    public function updateRules(int $id, ?string $currentCardNo = null): array
    {
        $rules = Arr::except($this->rules(), ['tnc', 'is_dtc', 'subscribe']);
        $cardNoRules = ['required', 'string', 'max:64', 'alpha_dash', "unique:dtc_users,dtc_card_no,{$id}"];
        if (config('dtc.require_dtc_code')) {
            $currentCardNo = strtoupper(trim((string) $currentCardNo));
            $cardNoRules[] = Rule::exists('dtc_codes', 'code')->where(function ($query) use ($currentCardNo) {
                $query->where('is_use', false);
                if ($currentCardNo !== '') {
                    $query->orWhere('code', $currentCardNo);
                }
            });
        }
        $rules['dtc_card_no'] = $cardNoRules;
        $rules['email'] = ['required', 'email:rfc', 'max:190'];

        // Older records may lack these; only new registrations must provide them.
        foreach (['brand', 'model', 'tyre_size', 'rim_size', 'state', 'mileage'] as $field) {
            $rules[$field] = array_map(fn ($rule) => $rule === 'required' ? 'nullable' : $rule, $rules[$field]);
        }

        return $rules;
    }

    public function setUser(array $request): void
    {
        $this->user = [
            'firstname' => $request['first_name'] ?? null,
            'lastname' => $request['last_name'] ?? null,
            'email' => $request['email'] ?? null,
            'contact_no' => $request['contact_no'] ?? null,
            'vehicle_reg_id' => $request['vehicle_reg_id'] ?? null,
            'brand' => $request['brand'] ?? null,
            'model' => $request['model'] ?? null,
            'tyre' => $request['tyre'] ?? null,
            'tyre_size' => $request['tyre_size'] ?? null,
            'rim_size' => $request['rim_size'] ?? null,
            'quantity' => $request['quantity'] ?? null,
            'date_of_purchase' => $request['date_of_purchase'] ?? null,
            'store_name' => $request['find_retailer'] ?? null,
            'state' => $request['state'] ?? null,
            'mileage' => $request['mileage'] ?? null,
            'dtc_card_no' => strtoupper(trim((string) ($request['dtc_card_no'] ?? ''))),
            'is_dtc' => filter_var($request['is_dtc'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'tnc_agree' => filter_var($request['tnc'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'subscribe' => filter_var($request['subscribe'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'online' => true,
        ];
    }

    public function getUser(): array
    {
        return $this->user;
    }

    public function emailDetails(): array
    {
        return [
            'subject' => 'Thank you for registering your tyres with '.config('dtc.brand_name'),
            'sender_name' => config('mail.from.name'),
            'sender_email' => config('mail.from.address'),
            'category' => 'registration',
        ];
    }

    public function editableFields(): array
    {
        return [
            'firstname', 'lastname', 'email', 'contact_no', 'vehicle_reg_id', 'brand', 'model',
            'tyre', 'tyre_size', 'rim_size', 'quantity', 'date_of_purchase', 'store_name', 'state',
            'mileage', 'dtc_card_no',
        ];
    }
}
