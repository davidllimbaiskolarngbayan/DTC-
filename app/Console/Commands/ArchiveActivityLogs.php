<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Throwable;

class ArchiveActivityLogs extends Command
{
    protected $signature = 'activity:archive {--dry-run : Show how many records would be archived without changing anything}';

    protected $description = 'Compress activity logs older than two years and remove them from the active table';

    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function handle(): int
    {
        $cutoff = now()->subYears(2);
        $maxId = DB::table('dtc_audit_logs')->where('created_at', '<', $cutoff)->max('id');

        if (! $maxId) {
            $this->info('No activity logs are old enough to archive.');

            return self::SUCCESS;
        }

        $query = DB::table('dtc_audit_logs')
            ->where('created_at', '<', $cutoff)
            ->where('id', '<=', $maxId);
        $recordCount = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$recordCount} activity log(s) older than {$cutoff->toDateString()} would be archived.");

            return self::SUCCESS;
        }

        $relativeDirectory = 'activity-archives';
        $filename = sprintf(
            'activity-logs-before-%s-through-id-%d.jsonl.gz',
            $cutoff->toDateString(),
            $maxId
        );
        $relativePath = $relativeDirectory.'/'.$filename;
        $disk = Storage::disk('local');

        if (! $disk->makeDirectory($relativeDirectory)) {
            throw new RuntimeException('Unable to create the private activity archive directory.');
        }

        $archiveDirectory = $disk->path($relativeDirectory);
        if (! is_dir($archiveDirectory)) {
            throw new RuntimeException('The private activity archive directory is not available.');
        }

        $archivePath = $disk->path($relativePath);
        if (! $disk->exists($relativePath)) {
            $temporaryPath = tempnam($archiveDirectory, 'activity-archive-');
            if ($temporaryPath === false) {
                throw new RuntimeException('Unable to create a temporary activity archive file.');
            }

            $gzip = gzopen($temporaryPath, 'wb9');
            if ($gzip === false) {
                @unlink($temporaryPath);
                throw new RuntimeException('Unable to open the temporary activity archive file.');
            }

            $writtenRecords = 0;
            try {
                (clone $query)->orderBy('id')->chunkById(500, function ($rows) use ($gzip, &$writtenRecords) {
                    foreach ($rows as $row) {
                        $line = json_encode((array) $row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
                        if (gzwrite($gzip, $line) !== strlen($line)) {
                            throw new RuntimeException('Writing an activity record to the archive failed.');
                        }
                        $writtenRecords++;
                    }
                });
            } catch (Throwable $exception) {
                gzclose($gzip);
                @unlink($temporaryPath);
                throw $exception;
            }

            if (! gzclose($gzip)) {
                @unlink($temporaryPath);
                throw new RuntimeException('Finishing the compressed activity archive failed.');
            }

            if ($writtenRecords !== $recordCount) {
                @unlink($temporaryPath);
                throw new RuntimeException('The activity archive record count did not match the source records.');
            }

            if (! rename($temporaryPath, $archivePath)) {
                @unlink($temporaryPath);
                throw new RuntimeException('Unable to save the completed private activity archive.');
            }
        }

        $deletedRecords = DB::transaction(fn () => DB::table('dtc_audit_logs')
            ->where('created_at', '<', $cutoff)
            ->where('id', '<=', $maxId)
            ->delete());

        if ($deletedRecords !== $recordCount) {
            throw new RuntimeException(
                "Archived {$recordCount} record(s), but removed {$deletedRecords} from the active table. The archive was retained."
            );
        }

        $this->info("Archived {$recordCount} activity log(s) to storage/app/{$relativePath}.");

        return self::SUCCESS;
    }
}
