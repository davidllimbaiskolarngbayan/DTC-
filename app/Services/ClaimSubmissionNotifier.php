<?php

namespace App\Services;

use App\Mail\ClaimSubmitted;
use App\Models\Claim;
use App\Models\Dtc;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ClaimSubmissionNotifier
{
    public function send(Dtc $registrant, Claim $claim): string
    {
        $email = $registrant->email;
        if (! $email) {
            $this->markFailed($claim, null, 'A valid customer email is required before a claim can be submitted. Update the registration email and try again.');
        }

        try {
            Mail::to($email)->send(new ClaimSubmitted(
                $claim->claim_number,
                $registrant->dtc_card_no,
            ));
        } catch (Throwable $exception) {
            $this->markFailed($claim, $email, 'The confirmation email could not be accepted by the mail server. Please retry the notification.');
            Log::warning('Claim submission confirmation email failed', [
                'claim_id' => $claim->id,
                'error' => class_basename($exception),
            ]);

            throw new ClaimSubmissionNotificationException(
                'The claim was submitted, but the confirmation email could not be accepted by the mail server. Please retry the notification.',
                $exception,
            );
        }

        $claim->submission_notification_attempts++;
        $claim->submission_notification_status = 'sent';
        $claim->submission_notification_error = null;
        $claim->submission_notification_sent_at = now();
        $claim->save();
        $claim->emailDeliveries()->create([
            'type' => 'claim_submitted',
            'recipient_hash' => hash('sha256', strtolower(trim($email))),
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return 'sent';
    }

    private function markFailed(Claim $claim, ?string $email, string $message): never
    {
        $claim->submission_notification_attempts++;
        $claim->submission_notification_status = 'failed';
        $claim->submission_notification_error = $message;
        $claim->save();
        $claim->emailDeliveries()->create([
            'type' => 'claim_submitted',
            'recipient_hash' => $email ? hash('sha256', strtolower(trim($email))) : null,
            'status' => 'failed',
            'error' => $message,
        ]);

        throw new ClaimSubmissionNotificationException($message);
    }
}
