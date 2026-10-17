<?php

namespace Tests\Feature;

use App\Support\Maintenance\AppKey;
use Tests\TestCase;

class GenerateAppKeyTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/sdm-env-'.bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->app->useEnvironmentPath($this->dir);
    }

    protected function tearDown(): void
    {
        @chmod($this->dir.'/.env', 0644);
        @unlink($this->dir.'/.env');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_button_writes_a_valid_key_into_an_env_file_with_an_empty_key(): void
    {
        file_put_contents($this->dir.'/.env', "APP_NAME=x\nAPP_KEY=\nDB_CONNECTION=mysql\n");
        config(['app.key' => '']);
        $this->app->forgetInstance('encrypter');

        $this->asMaintenanceUser()
            ->post('/maintenance/generate-key', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertOk()
            ->assertSee('APP_KEY was written to');

        $contents = file_get_contents($this->dir.'/.env');
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{44}$/m', $contents);
        $this->assertStringContainsString("APP_NAME=x\n", $contents);
        $this->assertStringContainsString("DB_CONNECTION=mysql\n", $contents);

        preg_match('/^APP_KEY=(.*)$/m', $contents, $m);
        $this->assertTrue(app(AppKey::class)->isValid($m[1]));
    }

    public function test_key_line_is_appended_when_missing(): void
    {
        file_put_contents($this->dir.'/.env', "APP_NAME=x\n");
        config(['app.key' => '']);

        $result = app(AppKey::class)->generateAndStore();

        $this->assertSame('written', $result['status']);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:/m', file_get_contents($this->dir.'/.env'));
    }

    public function test_existing_valid_key_is_never_replaced(): void
    {
        $existing = 'APP_KEY='.config('app.key');
        file_put_contents($this->dir.'/.env', $existing."\n");

        $this->asMaintenanceUser()
            ->post('/maintenance/generate-key', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertOk()
            ->assertSee('APP_KEY is already set. It was not changed.');

        $this->assertSame($existing."\n", file_get_contents($this->dir.'/.env'));
    }

    public function test_key_in_file_but_not_yet_loaded_is_not_replaced(): void
    {
        $key = app(AppKey::class)->generate();
        file_put_contents($this->dir.'/.env', "APP_KEY={$key}\n");
        config(['app.key' => '']);

        $this->assertSame('exists', app(AppKey::class)->generateAndStore()['status']);
        $this->assertSame("APP_KEY={$key}\n", file_get_contents($this->dir.'/.env'));
    }

    public function test_key_is_shown_when_the_env_file_is_missing(): void
    {
        config(['app.key' => '']);
        $this->app->forgetInstance('encrypter');

        $this->asMaintenanceUser()
            ->post('/maintenance/generate-key', ['_maintenance_token' => $this->maintenanceFormToken()])
            ->assertOk()
            ->assertSee('does not exist')
            ->assertSee('APP_KEY=base64:');
    }

    public function test_key_is_shown_when_the_env_file_is_read_only(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can write read-only files');
        }

        file_put_contents($this->dir.'/.env', "APP_KEY=\n");
        chmod($this->dir.'/.env', 0444);
        config(['app.key' => '']);

        $result = app(AppKey::class)->generateAndStore();

        $this->assertSame('not_writable', $result['status']);
        $this->assertStringStartsWith('base64:', $result['key']);
        $this->assertSame("APP_KEY=\n", file_get_contents($this->dir.'/.env'));
    }
}
