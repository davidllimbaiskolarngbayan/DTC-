<?php

namespace App\Services;

use App\Mail\ClaimNeedsStaffReview;
use App\Mail\ClaimStatusUpdated;
use App\Mail\ClaimSubmitted;
use App\Models\ClaimEmailDelivery;
use App\Models\DtcAuditLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class ClaimEmailRetryService
{
    public const MAX_AUTOMATIC_RETRIES = 3;

    public function __construct(private ClaimNotificationRecipients $staffRecipients)
    {
    }

    public function retry(ClaimEmailDelivery $delivery): bool
    {
        $delivery->refresh();
        if ($delivery->status !== 'failed' || $this->supersedeIfNotLatestFailure($delivery)) {
            return false;
        }

        $claim = $delivery->claim()->with(['registrant', 'histories'])->firstOrFail();
        $attempt = $delivery->retry_attempts + 1;
        $delivery->forceFill([
            'retry_attempts' => $attempt,
            'last_attempt_at' => now(),
            'next_attempt_at' => null,
        ])->save();

        try {
            match ($delivery->type) {
                'claim_submitted' => $this->sendCustomerConfirmation($claim),
                'staff_new_claim' => $this->sendStaffAlert($claim),
                'claim_status_update' => $this->sendStatusUpdate($claim, $delivery),
                default => throw new RuntimeException('Unsupported claim email delivery type.'),
            };

            $delivery->forceFill([
                'status' => 'sent',
                'error' => null,
                'sent_at' => now(),
                'next_attempt_at' => null,
            ])->save();
            $this->supersedeOlderFailures($delivery);
            DtcAuditLog::create([
                'dtc_id' => $claim->dtc_id,
                'entity_type' => 'claim',
                'entity_id' => $claim->id,
                'action' => 'claim.notification.auto_retry_sent',
                'summary' => 'Automatic email retry '.$attempt.' succeeded for claim '.$claim->claim_number,
            ]);

            return true;
        } catch (Throwable $exception) {
            $delayMinutes = [5, 30, 120][$attempt - 1] ?? null;
            $errorMessage = 'Automatic email retry '.$attempt.' failed.';
            $delivery->forceFill([
                'status' => 'failed',
                'error' => $errorMessage,
                'next_attempt_at' => $attempt < self::MAX_AUTOMATIC_RETRIES ? now()->addMinutes($delayMinutes) : null,
            ])->save();

            Log::warning('Automatic claim email retry failed', [
                'delivery_id' => $delivery->id,
                'claim_id' => $claim->id,
                'attempt' => $attempt,
                'error' => class_basename($exception),
            ]);
            DtcAuditLog::create([
                'dtc_id' => $claim->dtc_id,
                'entity_type' => 'claim',
                'entity_id' => $claim->id,
                'action' => 'claim.notification.auto_retry_failed',
                'summary' => $errorMessage.' Claim '.$claim->claim_number,
                'new_values' => ['retry_attempt' => $attempt],
            ]);

            return false;
        }
    }

    private function sendCustomerConfirmation($claim): void
    {
        $registrant = $claim->registrant;
        if (! $registrant?->email) {
            throw new RuntimeException('Customer email is missing.');
        }

        $claim->submission_notification_attempts++;
        $claim->save();
        Mail::to($registrant->email)->send(new ClaimSubmitted(
            $claim->claim_number,
            $registrant->dtc_card_no,
        ));
        $claim->submission_notification_status = 'sent';
        $claim->submission_notification_error = null;
        $claim->submission_notification_sent_at = now();
        $claim->save();
    }

    private function sendStaffAlert($claim): void
    {
        $recipients = $this->staffRecipients->emails();
        if ($recipients === []) {
            throw new RuntimeException('No staff notification recipients are selected.');
        }

        $claim->staff_notification_attempts++;
        $claim->save();
        Mail::to($recipients)->send(new ClaimNeedsStaffReview(
            $claim->claim_number,
            $claim->subject,
            route('claims.show', $claim),
        ));
        $claim->staff_notification_status = 'sent';
        $claim->staff_notification_error = null;
        $claim->staff_notification_sent_at = now();
        $claim->save();
    }

    private function sendStatusUpdate($claim, ClaimEmailDelivery $delivery): void
    {
        $registrant = $claim->registrant;
        $history = $claim->histories->firstWhere('id', $delivery->claim_history_id);
        if (! $registrant?->email || ! $history) {
            throw new RuntimeException('Customer email is missing or status history is unavailable.');
        }

        Mail::to($registrant->email)->send(new ClaimStatusUpdated(
            $claim->claim_number,
            \App\Models\Claim::STATUSES[$history->to_status] ?? $history->to_status,
            $registrant->dtc_card_no,
            $history->customer_message,
        ));
    }

    private function supersedeOlderFailures(ClaimEmailDelivery $successfulDelivery): void
    {
        $query = ClaimEmailDelivery::query()
            ->where('claim_id', $successfulDelivery->claim_id)
            ->where('type', $successfulDelivery->type)
            ->where('status', 'failed')
            ->where('id', '!=', $successfulDelivery->id);

        if ($successfulDelivery->type === 'claim_status_update') {
            $query->where('claim_history_id', $successfulDelivery->claim_history_id);
        }

        $query->update([
            'status' => 'superseded',
            'error' => 'A later automatic notification retry succeeded.',
            'next_attempt_at' => null,
            'updated_at' => now(),
        ]);
    }

    private function supersedeIfNotLatestFailure(ClaimEmailDelivery $delivery): bool
    {
        $query = ClaimEmailDelivery::query()
            ->where('claim_id', $delivery->claim_id)
            ->where('type', $delivery->type)
            ->where('status', 'failed');
        if ($delivery->type === 'claim_status_update') {
            $query->where('claim_history_id', $delivery->claim_history_id);
        }

        if ((int) $query->max('id') === $delivery->id) {
            return false;
        }

        $delivery->forceFill([
            'status' => 'superseded',
            'error' => 'A later failed delivery superseded this automatic retry.',
            'next_attempt_at' => null,
        ])->save();

        return true;
    }
}
