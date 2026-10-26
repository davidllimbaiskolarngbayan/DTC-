<?php

namespace App\Console\Commands;

use App\Mail\ClaimFollowUpReminder;
use App\Models\Claim;
use App\Models\DtcAuditLog;
use App\Services\ClaimNotificationRecipients;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendClaimFollowUpReminders extends Command
{
    protected $signature = 'claims:send-follow-up-reminders';

    protected $description = 'Email staff about open claims with no activity for three days';

    public function handle(ClaimNotificationRecipients $staffRecipients): int
    {
        $recipients = $staffRecipients->emails();
        if ($recipients === []) {
            $this->warn('No staff notification recipients are selected; no claim reminders were sent.');

            return self::SUCCESS;
        }

        $cutoff = now(config('dtc.timezone'))->subDays(3);
        $reminderCooldown = now(config('dtc.timezone'))->subDays(3);
        $reminded = 0;

        Claim::query()
            ->whereIn('status', Claim::OPEN_STATUSES)
            ->where('updated_at', '<=', $cutoff)
            ->where(function ($query) use ($reminderCooldown) {
                $query->whereNull('follow_up_reminded_at')
                    ->orWhere('follow_up_reminded_at', '<=', $reminderCooldown);
            })
            ->orderBy('id')
            ->chunkById(100, function ($claims) use ($recipients, &$reminded) {
                foreach ($claims as $claim) {
                    try {
                        Mail::to($recipients)->send(new ClaimFollowUpReminder(
                            $claim->claim_number,
                            $claim->subject,
                            route('claims.show', $claim),
                            $claim->updated_at->timezone(config('dtc.timezone'))->format('Y-m-d H:i'),
                        ));
                    } catch (Throwable $exception) {
                        Log::warning('Claim follow-up reminder failed', [
                            'claim_id' => $claim->id,
                            'error' => class_basename($exception),
                        ]);
                        $this->error("Could not send reminder for {$claim->claim_number}.");

                        continue;
                    }

                    $claim->forceFill(['follow_up_reminded_at' => now()])->save();
                    DtcAuditLog::create([
                        'dtc_id' => $claim->dtc_id,
                        'entity_type' => 'claim',
                        'entity_id' => $claim->id,
                        'action' => 'claim.follow_up_reminder_sent',
                        'summary' => 'Sent a staff follow-up reminder for claim '.$claim->claim_number,
                        'new_values' => ['reminded_at' => now()->toIso8601String()],
                    ]);
                    $reminded++;
                }
            });

        $this->info("Sent {$reminded} follow-up reminder(s).");

        return self::SUCCESS;
    }
}
