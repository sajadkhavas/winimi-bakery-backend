<?php

namespace Tests\Feature;

use Tests\TestCase;

class BackupConfigurationTest extends TestCase
{
    public function test_persistent_storage_is_covered_without_recursive_backup_archives(): void
    {
        $storageAppPath = realpath(storage_path('app')) ?: storage_path('app');
        $backupArchivePath = $storageAppPath.'/private/'.env('APP_NAME', 'laravel-backup');
        $files = config('backup.backup.source.files');

        $this->assertIsArray($files);
        $this->assertContains(base_path(), $files['include']);
        $this->assertContains($storageAppPath, $files['include']);
        $this->assertContains(base_path('vendor'), $files['exclude']);
        $this->assertContains(base_path('node_modules'), $files['exclude']);
        $this->assertContains($storageAppPath.'/backup-temp', $files['exclude']);
        $this->assertContains($storageAppPath.'/public/livewire-tmp', $files['exclude']);
        $this->assertContains($backupArchivePath, $files['exclude']);
        $this->assertFalse($files['follow_links']);
    }
}
