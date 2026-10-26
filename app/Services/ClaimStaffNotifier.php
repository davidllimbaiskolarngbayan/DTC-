<?php

namespace App\Services;

use App\Mail\ClaimNeedsStaffReview;
use App\Models\Claim;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ClaimStaffNotifier
{
    public function __construct(private ClaimNotificationRecipients $staffRecipients)
    {
    }

    public function send(Claim $claim): bool
    {
        $claim->staff_notification_attempts++;
        $recipients = $this->staffRecipients->emails();
        if ($recipients === []) {
            $this->failed($claim, 'No staff notification recipients are selected. Choose one or more accounts in Staff Alerts settings, then retry.');

            return false;
        }

        $claim->loadMissing('registrant');

        try {
            Mail::to($recipients)->send(new ClaimNeedsStaffReview(
                $claim->claim_number,
                $claim->subject,
                route('claims.show', $claim),
            ));
        } catch (Throwable $exception) {
            $this->failed($claim, 'Staff notification could not be delivered.');
            Log::warning('Claim staff notification delivery failed', [
                'claim_id' => $claim->id,
                'error' => class_basename($exception),
            ]);

            return false;
        }

        $claim->staff_notification_status = 'sent';
        $claim->staff_notification_error = null;
        $claim->staff_notification_sent_at = now();
        $claim->save();

        $claim->emailDeliveries()->create([
            'type' => 'staff_new_claim',
            'recipient_hash' => hash('sha256', implode(',', $recipients)),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return true;
    }

    private function failed(Claim $claim, string $message): void
    {
        $claim->staff_notification_status = 'failed';
        $claim->staff_notification_error = $message;
        $claim->save();

        $claim->emailDeliveries()->create([
            'type' => 'staff_new_claim',
            'recipient_hash' => null,
            'status' => 'failed',
            'error' => $message,
        ]);
    }
}
