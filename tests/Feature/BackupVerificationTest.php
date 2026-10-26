<?php

namespace Tests\Feature;

use Tests\TestCase;

class BackupVerificationTest extends TestCase
{
    public function test_backup_verification_requires_a_database_dump_and_attachment_backup(): void
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dtc-backup-'.bin2hex(random_bytes(8));
        mkdir($directory);
        mkdir($directory.DIRECTORY_SEPARATOR.'claims');
        $databaseDump = $directory.DIRECTORY_SEPARATOR.'dtc.sql';
        $attachment = $directory.DIRECTORY_SEPARATOR.'claims'.DIRECTORY_SEPARATOR.'receipt.pdf';
        file_put_contents($databaseDump, 'CREATE TABLE test (id INT);');
        file_put_contents($attachment, 'private attachment snapshot');

        try {
            $this->artisan('backups:verify', [
                'database_dump' => $databaseDump,
                'attachments_backup' => $directory.DIRECTORY_SEPARATOR.'claims',
            ])
                ->expectsOutputToContain('present and readable')
                ->assertSuccessful();

            $this->artisan('backups:verify', [
                'database_dump' => $directory.DIRECTORY_SEPARATOR.'missing.sql',
                'attachments_backup' => $directory.DIRECTORY_SEPARATOR.'claims',
            ])
                ->expectsOutputToContain('readable, non-empty file')
                ->assertFailed();
        } finally {
            unlink($attachment);
            unlink($databaseDump);
            rmdir($directory.DIRECTORY_SEPARATOR.'claims');
            rmdir($directory);
        }
    }
}
