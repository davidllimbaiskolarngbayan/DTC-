<?php

namespace App\Console\Commands;

use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class VerifyBackupSnapshot extends Command
{
    protected $signature = 'backups:verify
        {database_dump : Path to a non-empty .sql or .sql.gz database dump}
        {attachments_backup : Path to a non-empty attachment archive or directory}';

    protected $description = 'Check that a database dump and claim attachment backup are present and readable';

    public function handle(): int
    {
        $databaseDump = $this->argument('database_dump');
        if (! is_file($databaseDump) || ! is_readable($databaseDump) || filesize($databaseDump) < 1) {
            $this->error('The database dump must be a readable, non-empty file.');

            return self::FAILURE;
        }

        if (str_ends_with(strtolower($databaseDump), '.gz')) {
            $compressed = gzopen($databaseDump, 'rb');
            if ($compressed === false) {
                $this->error('The compressed database dump could not be opened.');

                return self::FAILURE;
            }

            $firstByte = gzread($compressed, 1);
            gzclose($compressed);
            if ($firstByte === false) {
                $this->error('The compressed database dump could not be read.');

                return self::FAILURE;
            }
        }

        $attachmentsBackup = $this->argument('attachments_backup');
        if (is_file($attachmentsBackup)) {
            if (! is_readable($attachmentsBackup) || filesize($attachmentsBackup) < 1) {
                $this->error('The attachment archive must be readable and non-empty.');

                return self::FAILURE;
            }
        } elseif (is_dir($attachmentsBackup) && is_readable($attachmentsBackup)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($attachmentsBackup, FilesystemIterator::SKIP_DOTS)
            );
            $hasFile = false;
            foreach ($files as $file) {
                if ($file->isFile() && $file->isReadable() && $file->getSize() > 0) {
                    $hasFile = true;
                    break;
                }
            }
            if (! $hasFile) {
                $this->error('The attachment backup directory contains no readable, non-empty files.');

                return self::FAILURE;
            }
        } else {
            $this->error('The attachment backup must be a readable file or directory.');

            return self::FAILURE;
        }

        $this->info('Database dump and attachment backup are present and readable.');
        $this->warn('This check does not prove the snapshot can be restored. Perform the documented restore drill on an isolated staging system.');

        return self::SUCCESS;
    }
}
