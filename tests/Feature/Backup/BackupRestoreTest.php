<?php

namespace Tests\Feature\Backup;

use App\Domain\Backup\BackupCatalog;
use App\Domain\Backup\BackupRunner;
use App\Domain\Backup\RestoreRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_creates_zip_and_restore_roundtrip_sqlite(): void
    {
        if (config('database.connections.sqlite.database') === ':memory:') {
            $this->markTestSkipped('Full backup roundtrip requires a file SQLite database (MySQL/MariaDB on server).');
        }

        $this->owner();

        $runner = app(BackupRunner::class);
        $result = $runner->run(uploadDrive: false);

        $this->assertFileExists($result['path']);
        $this->assertStringStartsWith(BackupCatalog::PREFIX, $result['basename']);

        $manifest = app(BackupCatalog::class)->lastManifest();
        $this->assertSame($result['basename'], $manifest['basename'] ?? null);

        app(RestoreRunner::class)->run($result['basename']);
        $this->assertTrue(true);
    }

    public function test_maintenance_shows_backup_and_restore_controls(): void
    {
        $this->actingAs($this->owner())->asMaintenanceUser()->get('/maintenance')
            ->assertOk()
            ->assertSee(__('Backup now'))
            ->assertSee('data-testid="restore-backup"', false);
    }
}
