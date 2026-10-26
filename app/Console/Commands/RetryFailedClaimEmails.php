<?php

namespace App\Console\Commands;

use App\Models\ClaimEmailDelivery;
use App\Services\ClaimEmailRetryService;
use Illuminate\Console\Command;

class RetryFailedClaimEmails extends Command
{
    protected $signature = 'claims:retry-email-notifications';

    protected $description = 'Retry failed claim emails up to three times automatically';

    public function handle(ClaimEmailRetryService $retryService): int
    {
        $retried = 0;
        $query = ClaimEmailDelivery::query()
            ->where('status', 'failed')
            ->where('retry_attempts', '<', ClaimEmailRetryService::MAX_AUTOMATIC_RETRIES)
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            });

        $query->orderBy('id')->chunkById(100, function ($deliveries) use ($retryService, &$retried) {
            foreach ($deliveries as $delivery) {
                $retryService->retry($delivery);
                $retried++;
            }
        });

        $this->info("Processed {$retried} automatic claim email retry/retries.");

        return self::SUCCESS;
    }
}
