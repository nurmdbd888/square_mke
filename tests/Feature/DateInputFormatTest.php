<?php

namespace Tests\Feature;

use App\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateInputFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_helpers_default_to_dd_mm_yyyy(): void
    {
        $this->assertSame('d/m/Y', app_date_format());
        $this->assertSame('dd/mm/yyyy', app_date_placeholder());
        $this->assertSame('19/10/2026', format_app_date('2026-10-19'));
        $this->assertSame('2026-10-19', parse_app_date('19/10/2026'));
        $this->assertSame('2026-10-19', parse_app_date('2026-10-19'));
        $this->assertNull(parse_app_date('10/19/2026'));
    }

    public function test_helpers_follow_mm_dd_yyyy_setting(): void
    {
        app(SettingsStore::class)->set(['date_display_format' => 'm/d/Y']);

        $this->assertSame('m/d/Y', app_date_format());
        $this->assertSame('mm/dd/yyyy', app_date_placeholder());
        $this->assertSame('10/19/2026', format_app_date('2026-10-19'));
        $this->assertSame('2026-10-19', parse_app_date('10/19/2026'));
        $this->assertNull(parse_app_date('19/10/2026'));
    }

    public function test_helpers_follow_iso_setting(): void
    {
        app(SettingsStore::class)->set(['date_display_format' => 'Y-m-d']);

        $this->assertSame('Y-m-d', app_date_format());
        $this->assertSame('yyyy-mm-dd', app_date_placeholder());
        $this->assertSame('2026-10-19', format_app_date('2026-10-19'));
        $this->assertSame('2026-10-19', parse_app_date('2026-10-19'));
    }

    public function test_purchase_form_uses_app_date_inputs_not_native_mm_dd(): void
    {
        $html = $this->actingAs($this->owner())
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('dd/mm/yyyy', false)
            ->assertSee('data-testid="date-input-doc_date"', false)
            ->assertSee('data-testid="date-input-delivery_date"', false)
            ->assertDontSee('placeholder="mm/dd/yyyy"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'name="doc_date"'));
        $this->assertStringContainsString('appDateInput', $html);
        $this->assertStringNotContainsString('type="date" name="doc_date"', $html);
        // Full calendar chrome is one role=button hit target; the type=date sibling
        // keeps a non-zero layout box for showPicker() (inline width so purge cannot drop it).
        $this->assertStringContainsString('data-testid="date-picker-hit"', $html);
        $this->assertStringContainsString('role="button"', $html);
        $this->assertStringContainsString('data-testid="date-picker-native"', $html);
        $this->assertStringContainsString('openPicker()', $html);
        $this->assertStringContainsString('width: 2.75rem', $html);
        $this->assertStringContainsString('min-width: 2.75rem', $html);
        $this->assertStringContainsString('pointer-events-none', $html);
        $this->assertStringNotContainsString('h-0 w-0 opacity-0', $html);
    }

    public function test_app_js_loads_before_alpine_so_date_factory_exists(): void
    {
        $html = $this->actingAs($this->owner())
            ->get(route('purchases.create'))
            ->assertOk()
            ->getContent();

        $appPos = strpos($html, 'js/app.js');
        $alpinePos = strpos($html, 'js/alpine.min.js');
        $this->assertNotFalse($appPos);
        $this->assertNotFalse($alpinePos);
        $this->assertLessThan($alpinePos, $appPos);
    }

    public function test_receipt_form_uses_app_date_format(): void
    {
        $this->actingAs($this->owner())
            ->get(route('payments.create', ['kind' => 'receipt']))
            ->assertOk()
            ->assertSee('dd/mm/yyyy', false)
            ->assertSee('data-testid="date-input-payment_date"', false);
    }

    public function test_mm_dd_setting_changes_purchase_placeholders(): void
    {
        app(SettingsStore::class)->set(['date_display_format' => 'm/d/Y']);

        $this->actingAs($this->owner())
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('mm/dd/yyyy', false)
            ->assertDontSee('placeholder="dd/mm/yyyy"', false);
    }

    public function test_settings_page_describes_format_for_inputs(): void
    {
        $this->actingAs($this->owner())
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee(__('How dates appear everywhere: lists, printouts, and date fields. Type or pick dates in this format.'), false)
            ->assertDontSee('Date pickers still use the calendar control.', false);
    }
}
