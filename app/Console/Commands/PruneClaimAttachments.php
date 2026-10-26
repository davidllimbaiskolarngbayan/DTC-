<?php

namespace App\Console\Commands;

use App\Models\ClaimAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PruneClaimAttachments extends Command
{
    protected $signature = 'claims:prune-attachments {--dry-run : Show files that would be removed without changing anything}';

    protected $description = 'Delete private claim attachments older than one year';

    public function handle(): int
    {
        $cutoff = now()->subYear();
        $query = ClaimAttachment::where('created_at', '<=', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} attachment(s) older than {$cutoff->toDateString()} would be deleted.");

            return self::SUCCESS;
        }

        $deleted = 0;
        $failed = 0;
        $query->orderBy('id')->chunkById(100, function ($attachments) use (&$deleted, &$failed) {
            foreach ($attachments as $attachment) {
                try {
                    $disk = Storage::disk($attachment->disk);
                    if ($disk->exists($attachment->path) && ! $disk->delete($attachment->path)) {
                        throw new \RuntimeException('The attachment file could not be deleted.');
                    }
                    $attachment->delete();
                    $deleted++;
                } catch (Throwable $exception) {
                    Log::error('Expired claim attachment cleanup failed', [
                        'attachment_id' => $attachment->id,
                        'claim_id' => $attachment->claim_id,
                        'error' => class_basename($exception),
                    ]);
                    $this->error("Could not clean attachment {$attachment->id}.");
                    $failed++;
                }
            }
        });

        $this->info("Deleted {$deleted} expired attachment(s); {$failed} cleanup(s) failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
