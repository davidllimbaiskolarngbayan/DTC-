<?php

namespace App\Http\Controllers;

use App\Contracts\DtcMarket;
use App\Mail\DtcThankYou;
use App\Models\Claim;
use App\Models\Dtc;
use App\Models\DtcAuditLog;
use App\Models\DtcCode;
use App\Services\ClaimSubmissionNotifier;
use App\Services\ClaimSubmissionNotificationException;
use App\Services\ClaimStaffNotifier;
use App\Services\ClaimStaffNotifications;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

class DtcRegisterController extends Controller
{
    public function __construct(protected DtcMarket $dtcMarket)
    {
    }

    public function checkExistingRecord(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_reg_id' => ['required', 'string', 'max:20'],
            'quantity' => ['required', 'integer'],
            'date_of_purchase' => ['required', 'date'],
        ]);

        return response()->json(['status' => 'success', 'data' => $this->isDuplicate($data)]);
    }

    // Latest registration for this plate created today, or null.
    public function fetchPlateNumLatestEntryToday(Request $request): JsonResponse
    {
        $data = $request->validate(['vehicle_reg_id' => ['required', 'string', 'max:20']]);

        $record = Dtc::where('vehicle_reg_hash', Dtc::hashVehicleRegId($data['vehicle_reg_id']))
            ->where('created_at', '>=', now()->startOfDay())
            ->latest('created_at')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $record ? ['created_at' => $record->created_at, 'plate_no' => $record->maskedVehicleRegId()] : null,
        ]);
    }

    // Registrations of this plate whose purchase date falls in the same month as the given date.
    public function countAllPurchaseDates(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_reg_id' => ['required', 'string', 'max:20'],
            'date_of_purchase' => ['required', 'date'],
        ]);

        $start = \Illuminate\Support\Carbon::parse($data['date_of_purchase'])->startOfMonth();

        $count = Dtc::where('vehicle_reg_hash', Dtc::hashVehicleRegId($data['vehicle_reg_id']))
            ->where('date_of_purchase', '>=', $start)
            ->where('date_of_purchase', '<', $start->copy()->addMonth())
            ->count();

        return response()->json(['status' => 'success', 'data' => $count]);
    }

    public function store(Request $request): JsonResponse
    {
        // Normalised before validation so the unique rule matches what is stored.
        $request->merge(['dtc_card_no' => strtoupper(trim((string) $request->input('dtc_card_no')))]);

        $validator = Validator::make($request->all(), $this->dtcMarket->rules(), [], [
            'dtc_card_no' => 'DTC Code',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $this->dtcMarket->setUser($request->all());
        $user = $this->dtcMarket->getUser();
        $validated = $validator->validated();

        if ($this->isDuplicate($user)) {
            return response()->json([
                'status' => 'error',
                'message' => ['vehicle_reg_id' => ['A registration for this vehicle with the same quantity and purchase date already exists.']],
            ], 422);
        }

        $invoicePath = null;
        try {
            $record = DB::transaction(function () use ($request, $user, $validated, &$invoicePath) {
                $record = (new Dtc)->createUser($user);
                DtcCode::where('code', $record->dtc_card_no)->update(['is_use' => true]);

                if ($request->boolean('validpromo')) {
                    $promo = $validated['promo'];
                    $promoData = [
                        'promo' => $promo['promo'],
                        'assisted_person' => $promo['assisted_person'],
                        'confirm' => $promo['confirm'],
                        'terms' => $promo['terms'],
                    ];

                    $invoice = $request->file('invoice');
                    $invoicePath = $invoice->store('private/dtc-promos/'.$record->id, 'local');
                    if (! $invoicePath) {
                        throw new \RuntimeException('Unable to save the promotion invoice.');
                    }
                    $promoData['invoice'] = $invoicePath;

                    foreach ($promoData as $key => $value) {
                        $record->promos()->create([
                            'promo_key' => $key,
                            'promo_value' => $value,
                        ]);
                    }
                }

                return $record;
            });
        } catch (Throwable $exception) {
            if ($invoicePath && ! Storage::disk('local')->delete($invoicePath)) {
                Log::error('Promo invoice could not be removed after registration failure', [
                    'path' => $invoicePath,
                ]);
            }

            throw $exception;
        }

        $encId = Crypt::encryptString((string) $record->id);

        if ($record->email) {
            $this->sendThankYou($record, $encId);
        }

        return response()->json([
            'status' => 'success',
            'dtc_card_no' => $record->dtc_card_no,
            'thankyou_url' => route('dtc.thankyou', $encId),
        ], 201);
    }

    public function checkRecordsByDtcCodeOrPlateNum(Request $request): JsonResponse
    {
        $data = $request->validate(['dtc_code' => ['required', 'string', 'max:64']]);
        $code = trim($data['dtc_code']);

        $records = Dtc::where('vehicle_reg_hash', Dtc::hashVehicleRegId($code))
            ->latest('date_of_purchase')
            ->get();
        $matchedByPlate = $records->isNotEmpty();

        if ($records->isEmpty()) {
            $cardRecord = Dtc::where('dtc_card_no', strtoupper($code))->first();
            if ($cardRecord) {
                $records = Dtc::whereKey($cardRecord->id)->get();
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $records->load('claims.histories')->map(function (Dtc $record) use ($matchedByPlate) {
                [$warrantyEligible, $warrantyReason] = Claim::eligibilityFor($record);
                $warrantyExpiryDate = $record->date_of_purchase?->copy()->addYear()->toDateString();
                $warrantyStatus = $warrantyEligible
                    ? 'Eligible'
                    : (str_contains($warrantyReason, 'warranty from the purchase date has expired') ? 'Expired' : 'Needs review');

                return [
                    'dtc_card_no' => $record->dtc_card_no,
                    'vehicle_reg_id' => $record->maskedVehicleRegId(),
                    'brand' => $record->brand,
                    'model' => $record->model,
                    'tyre' => $record->tyre,
                    'tyre_size' => $record->tyre_size,
                    'quantity' => $record->quantity,
                    'date_of_purchase' => $record->date_of_purchase?->toDateString(),
                    'warranty_eligible' => $warrantyEligible,
                    'warranty_status' => $warrantyStatus,
                    'warranty_expiry_date' => $warrantyExpiryDate,
                    'warranty_reason' => $warrantyReason,
                    'state' => $record->state,
                    'store_name' => $record->store_name,
                    'created_at' => $record->created_at?->toDateString(),
                    'claims' => $record->claims->map(fn (Claim $claim) => [
                        'claim_number' => $claim->claim_number,
                        'status' => Claim::STATUSES[$claim->status] ?? $claim->status,
                        'submitted_at' => $claim->created_at?->toDateString(),
                        'is_open' => Claim::isOpenStatus($claim->status),
                        'history' => $claim->histories
                            ->whereIn('event_type', ['submitted', 'status_changed', 'customer_response'])
                            ->map(fn ($history) => [
                                'event' => match ($history->event_type) {
                                    'submitted' => 'Submitted',
                                    'customer_response' => 'Additional information received',
                                    default => 'Status updated',
                                },
                                'status' => Claim::STATUSES[$history->to_status] ?? $history->to_status,
                                'from_status' => Claim::STATUSES[$history->from_status] ?? null,
                                'to_status' => Claim::STATUSES[$history->to_status] ?? null,
                                'date' => $history->created_at?->toDateString(),
                                'customer_message' => $history->customer_message,
                            ])->values(),
                    ])->values(),
                    'matched_by_plate' => $matchedByPlate,
                ];
            })->values(),
        ]);
    }

    public function lookupClaimHistory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dtc_card_no' => ['required', 'string', 'max:64'],
            'vehicle_reg_id' => ['required', 'string', 'max:20'],
        ]);

        $record = Dtc::where('dtc_card_no', strtoupper(trim($data['dtc_card_no'])))->first();
        if (! $record || ! $record->vehicle_reg_hash || ! hash_equals($record->vehicle_reg_hash, Dtc::hashVehicleRegId($data['vehicle_reg_id']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'We could not verify those details against the registration. Check the DTC Code and registered plate number.',
            ], 422);
        }

        $claims = $record->claims()->with('histories')->latest('id')->get()->map(function (Claim $claim) {
            return [
                'claim_number' => $claim->claim_number,
                'status' => Claim::STATUSES[$claim->status] ?? $claim->status,
                'submitted_at' => $claim->created_at?->toDateString(),
                'history' => $claim->histories
                    ->whereIn('event_type', ['submitted', 'status_changed', 'customer_response'])
                    ->map(fn ($history) => [
                    'event' => match ($history->event_type) {
                        'submitted' => 'Submitted',
                        'customer_response' => 'Additional information received',
                        default => 'Status updated',
                    },
                    'status' => Claim::STATUSES[$history->to_status] ?? Claim::STATUSES[$claim->status] ?? $claim->status,
                    'from_status' => Claim::STATUSES[$history->from_status] ?? null,
                    'to_status' => Claim::STATUSES[$history->to_status] ?? null,
                    'date' => $history->created_at?->toDateString(),
                    'customer_message' => $history->customer_message,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'claims' => $claims,
        ]);
    }

    public function respondToClaim(Request $request, ClaimStaffNotifications $staffNotifications): JsonResponse
    {
        $data = $request->validate([
            'dtc_card_no' => ['required', 'string', 'max:64'],
            'vehicle_reg_id' => ['required', 'string', 'max:20'],
            'claim_number' => ['required', 'string', 'max:32'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $record = Dtc::query()
            ->where('dtc_card_no', strtoupper(trim($data['dtc_card_no'])))
            ->first();
        if (! $record || ! $record->vehicle_reg_hash
            || ! hash_equals($record->vehicle_reg_hash, Dtc::hashVehicleRegId($data['vehicle_reg_id']))) {
            return response()->json([
                'status' => 'error',
                'message' => 'We could not verify those details against the registration. Check the DTC Code and registered plate number.',
            ], 422);
        }

        $claim = $record->claims()
            ->where('claim_number', strtoupper(trim($data['claim_number'])))
            ->where('status', 'need_more_info')
            ->first();
        if (! $claim) {
            return response()->json([
                'status' => 'error',
                'message' => 'This claim is not currently waiting for additional information.',
            ], 422);
        }

        $responseCreated = DB::transaction(function () use ($claim, $record, $data, $request, $staffNotifications) {
            $lockedClaim = Claim::query()
                ->whereKey($claim->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($lockedClaim->status !== 'need_more_info') {
                return false;
            }

            $history = $lockedClaim->histories()->create([
                'event_type' => 'customer_response',
                'customer_message' => trim($data['message']),
            ]);
            DtcAuditLog::record(
                $request,
                'claim.customer_response',
                'claim',
                $lockedClaim->id,
                $record->id,
                'Customer provided additional information for claim '.$lockedClaim->claim_number,
                null,
                ['claim_history_id' => $history->id]
            );
            $staffNotifications->notify(
                $lockedClaim,
                'customer_response:'.$history->id,
                'Customer sent additional information'
            );

            return true;
        });

        if (! $responseCreated) {
            return response()->json([
                'status' => 'error',
                'message' => 'This claim is no longer waiting for additional information.',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Your additional information was sent to our team.',
        ]);
    }

    public function submitClaim(
        Request $request,
        ClaimSubmissionNotifier $notifier,
        ClaimStaffNotifier $staffNotifier,
        ClaimStaffNotifications $staffNotifications
    ): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'dtc_card_no' => ['required', 'string', 'max:64'],
            'subject' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
            'incident_date' => ['nullable', 'date', 'before_or_equal:'.now(config('dtc.timezone'))->toDateString()],
            'affected_tyres' => ['nullable', 'string', 'max:160'],
            'attachments' => ['nullable', 'array', 'max:1'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $idempotencyKey = strtolower($data['idempotency_key']);
        $claimPayloadHash = $this->claimPayloadHash($data, $request);
        $existingClaim = Claim::where('idempotency_key', $idempotencyKey)->first();
        if ($existingClaim) {
            return $this->idempotentClaimResponse($existingClaim, $claimPayloadHash);
        }

        $record = Dtc::where('dtc_card_no', strtoupper(trim($data['dtc_card_no'])))->first();
        if (! $record) {
            return response()->json([
                'status' => 'error',
                'message' => 'We could not find a registration with that DTC Code. Check the code and try again.',
            ], 422);
        }

        if (! $record->email) {
            return response()->json([
                'status' => 'error',
                'message' => 'A valid customer email is required before a claim can be submitted. Update the registration email and try again.',
            ], 422);
        }
        if ($record->date_of_purchase) {
            $request->validate([
                'incident_date' => [
                    'nullable',
                    'after_or_equal:'.$record->date_of_purchase->toDateString(),
                ],
            ], [
                'incident_date.after_or_equal' => 'The issue date cannot be before the purchase date.',
            ]);
        }

        [$eligible, $eligibilityReason] = Claim::eligibilityForIncident($record, $data['incident_date'] ?? null);

        try {
            $claim = DB::transaction(function () use ($data, $record, $eligible, $eligibilityReason, $request, $notifier, $idempotencyKey, $claimPayloadHash) {
                $lockedRecord = Dtc::whereKey($record->id)->lockForUpdate()->firstOrFail();
                $existingClaim = Claim::where('idempotency_key', $idempotencyKey)->first();
                if ($existingClaim) {
                    return $this->idempotentClaimResponse($existingClaim, $claimPayloadHash);
                }

                $hasOpenClaims = $lockedRecord->claims()
                    ->whereIn('status', Claim::OPEN_STATUSES)
                    ->exists();

                if ($hasOpenClaims) {
                    $request->validate([
                        'different_issue_confirmed' => ['required', 'accepted'],
                    ], [
                        'different_issue_confirmed.required' => 'An open claim already exists. Confirm that this is a different issue before submitting another claim.',
                        'different_issue_confirmed.accepted' => 'An open claim already exists. Confirm that this is a different issue before submitting another claim.',
                    ]);
                }

                $claim = Claim::create([
                    'dtc_id' => $record->id,
                    'subject' => $data['subject'],
                    'description' => $data['description'],
                    'incident_date' => $data['incident_date'] ?? null,
                    'affected_tyres' => $data['affected_tyres'] ?? null,
                    'eligible' => $eligible,
                    'eligibility_reason' => $eligibilityReason,
                    'status' => 'submitted',
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_payload_hash' => $claimPayloadHash,
                ]);
                $claim->update(['claim_number' => 'CLM-'.str_pad((string) $claim->id, 6, '0', STR_PAD_LEFT)]);
                $claim->histories()->create([
                    'event_type' => 'submitted',
                    'to_status' => 'submitted',
                ]);
                $this->storeAttachments($claim, $request->file('attachments', []));
                DtcAuditLog::record(
                    $request,
                    'claim.submitted',
                    'claim',
                    $claim->id,
                    $record->id,
                    'Customer submitted claim '.$claim->claim_number,
                    null,
                    ['status' => 'submitted']
                );

                try {
                    $claim->submission_notification_status = $notifier->send($lockedRecord, $claim);
                } catch (ClaimSubmissionNotificationException $exception) {
                    $claim->submission_notification_status = 'failed';
                    $claim->submission_notification_error = $exception->getMessage();
                }
                $claim->save();

                return $claim;
            });
        } catch (QueryException $exception) {
            $existingClaim = Claim::where('idempotency_key', $idempotencyKey)->first();
            if (! $existingClaim) {
                throw $exception;
            }

            return $this->idempotentClaimResponse($existingClaim, $claimPayloadHash);
        }

        if ($claim instanceof JsonResponse) {
            return $claim;
        }

        $staffNotifications->notify($claim, 'claim_submitted', 'New claim submitted');
        $staffNotifier->send($claim);

        return response()->json([
            'status' => 'success',
            'claim_number' => $claim->claim_number,
            'email_notification' => $claim->submission_notification_status,
        ], 201);
    }

    private function claimPayloadHash(array $data, Request $request): string
    {
        $attachmentHashes = array_map(
            static fn ($file): string => hash_file('sha256', $file->getRealPath()),
            (array) $request->file('attachments', [])
        );
        $payload = [
            'dtc_card_no' => strtoupper(trim($data['dtc_card_no'])),
            'subject' => trim($data['subject']),
            'description' => trim($data['description']),
            'incident_date' => $data['incident_date'] ?? null,
            'affected_tyres' => trim($data['affected_tyres'] ?? ''),
            'different_issue_confirmed' => $request->boolean('different_issue_confirmed'),
            'attachments' => $attachmentHashes,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function idempotentClaimResponse(Claim $claim, string $payloadHash): JsonResponse
    {
        if (! hash_equals((string) $claim->idempotency_payload_hash, $payloadHash)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This idempotency key was already used for a different claim. Start a new submission.',
            ], 409);
        }

        return response()->json([
            'status' => 'success',
            'claim_number' => $claim->claim_number,
            'email_notification' => $claim->submission_notification_status,
            'idempotent_replay' => true,
        ], 200);
    }

    private function storeAttachments(Claim $claim, array $files): void
    {
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs(
                'private/claims/'.$claim->id,
                Str::uuid().'.'.$extension,
                'local'
            );
            if (! $path) {
                throw new \RuntimeException('Unable to save the claim attachment.');
            }

            try {
                $claim->attachments()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => basename($file->getClientOriginalName()),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $record = Dtc::findOrFail($id);
        DB::transaction(function () use ($request, $record) {
            $record->delete();
            DtcAuditLog::record(
                $request,
                'registrant.deleted',
                'registrant',
                $record->id,
                $record->id,
                'API deleted registrant record'
            );
        });

        return response()->json(['status' => 'success', 'message' => 'Registration deleted.']);
    }

    private function isDuplicate(array $data): bool
    {
        return Dtc::where('vehicle_reg_hash', Dtc::hashVehicleRegId($data['vehicle_reg_id']))
            ->whereDate('date_of_purchase', $data['date_of_purchase'])
            ->where('quantity', (string) $data['quantity'])
            ->exists();
    }

    private function sendThankYou(Dtc $record, string $encId): void
    {
        try {
            Mail::to($record->email)->send(new DtcThankYou(
                $record->thankYouData(),
                $this->dtcMarket->emailDetails()['subject'],
                $record->subscribe ? route('dtc.unsubscribe', $encId) : null,
            ));
        } catch (Throwable $e) {
            // The registration already succeeded; don't fail the request over email.
            Log::warning('Thank-you email failed', ['dtc_id' => $record->id, 'error' => class_basename($e)]);
        }
    }
}
