<?php

namespace Tests\Feature\Export;

use App\Jobs\RunTableExportJob;
use App\Models\AppJob;
use App\Models\GoogleConnection;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class M7ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtered_product_csv_export_contains_only_matching_rows(): void
    {
        Product::query()->create(['name_en' => 'Alpha Soap', 'code' => 'A1', 'unit' => 'Pcs', 'sd_pct' => '0', 'vat_pct' => '15']);
        Product::query()->create(['name_en' => 'Beta Soap', 'code' => 'B1', 'unit' => 'Pcs', 'sd_pct' => '0', 'vat_pct' => '15']);

        $owner = $this->owner();
        $this->actingAs($owner)->post(route('exports.store', 'products'), [
            'format' => 'csv',
            'filters' => ['q' => 'Alpha', 'status' => 'active'],
        ])->assertRedirect();

        $job = AppJob::query()->where('user_id', $owner->id)->latest('id')->first();
        $this->assertNotNull($job);
        $this->assertSame(AppJob::STATUS_DONE, $job->status);
        $path = storage_path('app/'.$job->result_path);
        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString('Alpha Soap', $contents);
        $this->assertStringNotContainsString('Beta Soap', $contents);
    }

    public function test_large_export_is_queued(): void
    {
        Queue::fake();
        Config::set('sdm.export_inline_limit', 0);
        Product::query()->create(['name_en' => 'Queued Product', 'code' => 'Q1', 'unit' => 'Pcs', 'sd_pct' => '0', 'vat_pct' => '15']);

        $owner = $this->owner();
        $this->actingAs($owner)->post(route('exports.store', 'products'), [
            'format' => 'csv',
            'filters' => ['status' => 'active'],
        ])->assertRedirect(route('exports.downloads'));

        Queue::assertPushed(RunTableExportJob::class);
        $job = AppJob::query()->where('user_id', $owner->id)->latest('id')->first();
        $this->assertSame(AppJob::STATUS_PENDING, $job->status);
    }

    public function test_google_token_uses_encrypted_cast(): void
    {
        $conn = GoogleConnection::query()->create([
            'connected_by' => $this->owner()->id,
            'google_email' => 'owner@example.com',
            'refresh_token' => 'secret-refresh-token',
            'access_token' => 'secret-access',
        ]);

        $this->assertNotSame('secret-refresh-token', $conn->getAttributes()['refresh_token']);
        $this->assertSame('secret-refresh-token', $conn->refresh_token);
    }

    public function test_settings_shows_google_connect_when_configured(): void
    {
        Config::set('google.client_id', 'test-client');
        Config::set('google.client_secret', 'test-secret');

        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('data-testid="google-integration"', false)
            ->assertSee(__('Connect Google'), false);
    }
}
