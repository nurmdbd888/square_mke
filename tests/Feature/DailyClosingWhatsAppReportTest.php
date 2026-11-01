<?php

namespace Tests\Feature;

use App\Models\DailyClosing;
use App\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyClosingWhatsAppReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_a_day_sends_the_cash_and_bank_summary_to_the_configured_recipient(): void
    {
        $owner = $this->owner();
        app(SettingsStore::class)->set([
            'day_end_whatsapp_recipient' => '+8801700000000',
            'reminder_whatsapp_token' => 'test-token',
            'reminder_whatsapp_phone_id' => '123456',
        ], $owner);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => []], 200)]);

        $this->actingAs($owner)
            ->post(route('accounts.closings.store'), ['closed_on' => '2026-10-10'])
            ->assertRedirect(route('accounts.closings'))
            ->assertSessionHas('status', 'Day 10-10-2026 was closed and the WhatsApp report was sent.');

        $closing = DailyClosing::query()->sole();
        $this->assertSame('2026-10-10', $closing->closed_on->toDateString());
        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v21.0/123456/messages'
            && $request['to'] === '8801700000000'
            && str_contains($request['text']['body'], 'Day End Report - 10-10-2026')
            && str_contains($request['text']['body'], 'Cash: Opening')
            && str_contains($request['text']['body'], 'Bank: Opening'));
    }

    public function test_report_delivery_failure_does_not_undo_the_day_closing(): void
    {
        $owner = $this->owner();
        app(SettingsStore::class)->set([
            'day_end_whatsapp_recipient' => '+8801700000000',
            'reminder_whatsapp_token' => 'test-token',
            'reminder_whatsapp_phone_id' => '123456',
        ], $owner);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => 'failed'], 500)]);

        $this->actingAs($owner)
            ->post(route('accounts.closings.store'), ['closed_on' => '2026-10-10'])
            ->assertRedirect(route('accounts.closings'))
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('daily_closings', 1);
    }

    public function test_connection_failure_is_reported_without_reversing_the_closing(): void
    {
        $owner = $this->owner();
        app(SettingsStore::class)->set([
            'day_end_whatsapp_recipient' => '+8801700000000',
            'reminder_whatsapp_token' => 'test-token',
            'reminder_whatsapp_phone_id' => '123456',
        ], $owner);
        Http::fake(['graph.facebook.com/*' => Http::failedConnection()]);

        $this->actingAs($owner)
            ->post(route('accounts.closings.store'), ['closed_on' => '2026-10-10'])
            ->assertRedirect(route('accounts.closings'))
            ->assertSessionHas('warning');

        $this->assertDatabaseCount('daily_closings', 1);
    }
}
