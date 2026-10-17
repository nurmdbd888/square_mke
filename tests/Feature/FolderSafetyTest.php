<?php

namespace Tests\Feature;

use App\Support\Maintenance\CheckResult;
use App\Support\Maintenance\EnvironmentCheck;
use App\Support\Maintenance\FolderGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class FolderSafetyTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $server;

    private string $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = $_SERVER;
        $this->home = sys_get_temp_dir().'/sdm-guard-'.uniqid();
        File::ensureDirectoryExists($this->home.'/mke/shared/storage');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        File::deleteDirectory($this->home);
        parent::tearDown();
    }

    private function docroot(): CheckResult
    {
        return app(EnvironmentCheck::class)->documentRoot();
    }

    public function test_document_root_at_the_public_folder_is_ok(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = public_path().'/';

        $this->assertSame(CheckResult::OK, $this->docroot()->status);
    }

    public function test_document_root_one_level_too_high_warns_with_the_fix(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = dirname(base_path());
        $_SERVER['SCRIPT_NAME'] = '/app/public/index.php';

        $result = $this->docroot();

        $this->assertSame(CheckResult::WARN, $result->status);
        $this->assertStringContainsString('it must be '.public_path(), $result->detail);
        $this->assertStringContainsString('cPanel > Domains > Manage', $result->detail);
        $this->assertStringContainsString('only works through '.dirname(base_path()).'/.htaccess', $result->detail);
    }

    public function test_dashboard_shows_a_banner_when_the_document_root_is_wrong(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = dirname(base_path());

        $this->asMaintenanceUser()->get('/maintenance')
            ->assertOk()
            ->assertSee('data-testid="docroot-warning"', false)
            ->assertSee('data-check="docroot" data-status="warn"', false);
    }

    public function test_dashboard_has_no_banner_when_the_document_root_is_right(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = public_path();

        $this->asMaintenanceUser()->get('/maintenance')
            ->assertOk()
            ->assertDontSee('data-testid="docroot-warning"', false)
            ->assertSee('data-check="docroot" data-status="ok"', false);
    }

    public function test_guard_covers_shared_and_storage(): void
    {
        $this->app->useEnvironmentPath($this->home.'/mke/shared');
        $this->app->useStoragePath($this->home.'/mke/shared/storage');

        $this->assertSame([$this->home.'/mke/shared', $this->home.'/mke/shared/storage'], app(FolderGuard::class)->folders());
    }

    public function test_missing_deny_files_warn_and_are_restored_by_the_dashboard(): void
    {
        $this->app->useEnvironmentPath($this->home.'/mke/shared');
        $this->app->useStoragePath($this->home.'/mke/shared/storage');

        $results = collect(app(EnvironmentCheck::class)->folders())->keyBy('key');
        $this->assertSame(CheckResult::WARN, $results['folder_guard']->status);

        $this->asMaintenanceUser()->get('/maintenance')
            ->assertOk()
            ->assertSee('data-check="folder_guard" data-status="ok"', false);

        $template = File::get(base_path('deploy/shared.example/.htaccess'));
        $this->assertSame($template, File::get($this->home.'/mke/shared/.htaccess'));
        $this->assertSame($template, File::get($this->home.'/mke/shared/storage/.htaccess'));
    }

    public function test_existing_deny_files_are_left_alone(): void
    {
        $this->app->useStoragePath($this->home.'/mke/shared/storage');
        File::put($this->home.'/mke/shared/storage/.htaccess', "# custom\n");

        app(FolderGuard::class)->ensure();

        $this->assertSame("# custom\n", File::get($this->home.'/mke/shared/storage/.htaccess'));
    }

    public function test_shipped_deny_files_are_identical(): void
    {
        $template = File::get(base_path('deploy/shared.example/.htaccess'));

        $this->assertSame($template, File::get(base_path('deploy/shared.example/storage/.htaccess')));
        $this->assertSame($template, File::get(base_path('storage/.htaccess')));
        $this->assertStringContainsString('Require all denied', $template);
        $this->assertStringContainsString('RewriteRule ^ - [F,L]', $template);
    }
}
