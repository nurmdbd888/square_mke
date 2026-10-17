<?php

namespace Tests\Feature\M9;

use App\Domain\Documents\DocumentWriter;
use App\Domain\Reminders\DueReminderService;
use App\Domain\Reminders\ReminderChannel;
use App\Domain\Reminders\ReminderManager;
use App\Jobs\SendDueRemindersJob;
use App\Models\Product;
use App\Models\Retailer;
use App\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DueReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_retailers_past_due_threshold_are_selected(): void
    {
        $owner = $this->owner();
        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $due = Retailer::query()->create(['name' => 'Late payer', 'phone' => '01700000001']);
        $fresh = Retailer::query()->create(['name' => 'On time', 'phone' => '01700000002']);

        $writer = app(DocumentWriter::class);
        $writer->save('sale', [
            'number' => 'S-OLD-1',
            'retailer_id' => $due->id,
            'doc_date' => '2026-09-01',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '1', 'unit_price' => '1000', 'line_value' => '1000']], $owner);

        $writer->save('sale', [
            'number' => 'S-NEW-1',
            'retailer_id' => $fresh->id,
            'doc_date' => now()->subDays(5)->toDateString(),
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '1', 'unit_price' => '500', 'line_value' => '500']], $owner);

        $service = app(DueReminderService::class);
        $ids = $service->retailersPastDue(30)->pluck('retailer.id')->all();

        $this->assertContains($due->id, $ids);
        $this->assertNotContains($fresh->id, $ids);
    }

    public function test_scheduled_job_sends_through_configured_channel(): void
    {
        $owner = $this->owner();
        app(SettingsStore::class)->set([
            'reminder_enabled' => true,
            'reminder_channel' => 'log',
            'reminder_due_days' => 30,
        ], $owner);

        $product = Product::query()->create(['name_en' => 'Soap', 'unit' => 'Pcs', 'sell_price' => '20']);
        $retailer = Retailer::query()->create(['name' => 'Late payer', 'phone' => '01700000001']);

        app(DocumentWriter::class)->save('sale', [
            'number' => 'S-REM-1',
            'retailer_id' => $retailer->id,
            'doc_date' => '2026-09-01',
            'action' => 'post',
        ], [['product_id' => $product->id, 'qty' => '1', 'unit_price' => '1000', 'line_value' => '1000']], $owner);

        $sent = [];
        $channel = new class($sent) implements ReminderChannel
        {
            public function __construct(private array &$sent) {}

            public function send(string $phone, string $message, array $context = []): void
            {
                $this->sent[] = compact('phone', 'message', 'context');
            }
        };

        $this->mock(ReminderManager::class, function ($mock) use ($channel) {
            $mock->shouldReceive('channel')->once()->andReturn($channel);
        });

        app(SendDueRemindersJob::class)->handle(app(DueReminderService::class), app(ReminderManager::class));

        $this->assertCount(1, $sent);
        $this->assertSame('01700000001', $sent[0]['phone']);
    }
}
