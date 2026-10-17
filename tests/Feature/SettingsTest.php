<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Settings\SettingDefinition;
use App\Settings\SettingsRegistry;
use App\Settings\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return ['settings' => $overrides + [
            'firm_name' => 'M/s Khaza Enterprise',
            'distributor_code' => '4831',
            'default_locale' => 'en',
            'fiscal_year_start_month' => '7',
            'require_2fa_roles' => [],
            'vat_recoverable' => '1',
            'sd_in_cost' => '1',
            'costing_mode' => 'weighted_average',
            'block_negative_stock' => '0',
            'purchase_bonus_cost' => 'zero',
            'near_expiry_days' => '60',
            'count_commission' => '1',
            'commission_pct' => '5',
            'commission_vat_pct' => '0.62',
            'commission_base' => 'sales',
        ]];
    }

    private function defineDatedSetting(): void
    {
        app(SettingsRegistry::class)->define(new SettingDefinition(
            key: 'test_rate',
            type: SettingDefinition::DECIMAL,
            default: '15',
            group: 'firm',
            label: 'Test rate',
            effectiveDated: true,
        ));
    }

    public function test_defaults_come_from_configuration(): void
    {
        $this->assertSame('M/s Khaza Enterprise', setting('firm_name'));
        $this->assertSame('4831', setting('distributor_code'));
        $this->assertSame(7, setting('fiscal_year_start_month'));
        $this->assertSame([], setting('require_2fa_roles'));
    }

    public function test_changing_a_setting_writes_an_audit_row_with_old_and_new_values(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put('/settings', $this->form(['firm_name' => 'M/s Khaza Traders']))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('status', 'Saved.');

        $this->assertSame('M/s Khaza Traders', setting('firm_name'));

        $log = AuditLog::query()->where('event', 'settings.updated')->sole();
        $this->assertSame(['firm_name' => 'M/s Khaza Enterprise'], $log->old_values);
        $this->assertSame(['firm_name' => 'M/s Khaza Traders'], $log->new_values);
        $this->assertSame($owner->id, $log->user_id);
        $this->assertSame($owner->username, $log->user_name);
        $this->assertSame('setting', $log->auditable_type);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('PUT settings', $log->url);
        $this->assertNotNull($log->created_at);

        $this->actingAs($owner)->get('/')->assertSee('M/s Khaza Traders');
    }

    public function test_saving_unchanged_values_writes_nothing(): void
    {
        $this->actingAs($this->owner())->put('/settings', $this->form())
            ->assertSessionHas('status', 'Nothing changed.');

        $this->assertDatabaseCount('settings', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'settings.updated']);
    }

    public function test_several_changes_make_one_audit_row(): void
    {
        $this->actingAs($this->owner())->put('/settings', $this->form([
            'distributor_code' => '5000',
            'fiscal_year_start_month' => '1',
            'require_2fa_roles' => ['owner', 'accountant'],
        ]));

        $log = AuditLog::query()->where('event', 'settings.updated')->sole();
        // MySQL's JSON type may reorder object keys; compare as maps, not sequences.
        $this->assertEqualsCanonicalizing(['distributor_code', 'fiscal_year_start_month', 'require_2fa_roles'], array_keys($log->old_values));
        $this->assertSame('4831', $log->old_values['distributor_code']);
        $this->assertSame(7, $log->old_values['fiscal_year_start_month']);
        $this->assertSame([], $log->old_values['require_2fa_roles']);
        $this->assertSame('5000', $log->new_values['distributor_code']);
        $this->assertSame(1, $log->new_values['fiscal_year_start_month']);
        $this->assertSame(['owner', 'accountant'], $log->new_values['require_2fa_roles']);
        $this->assertSame(1, setting('fiscal_year_start_month'));
    }

    public function test_validation(): void
    {
        $this->actingAs($this->owner())->from('/settings')->put('/settings', $this->form([
            'firm_name' => '',
            'fiscal_year_start_month' => '13',
            'default_locale' => 'fr',
            'require_2fa_roles' => ['god'],
        ]))->assertSessionHasErrors([
            'settings.firm_name', 'settings.fiscal_year_start_month', 'settings.default_locale', 'settings.require_2fa_roles.0',
        ]);
    }

    public function test_settings_page_shows_the_values_and_groups(): void
    {
        $this->actingAs($this->owner())->get('/settings')
            ->assertOk()
            ->assertSee('Firm name')
            ->assertSee('value="M/s Khaza Enterprise"', false)
            ->assertSee('Save settings');
    }

    public function test_effective_dated_setting_uses_the_row_in_force_on_each_date(): void
    {
        $this->defineDatedSetting();
        $store = app(SettingsStore::class);
        $owner = $this->owner();

        $this->assertSame('15', $store->get('test_rate', '2026-01-01'));

        $store->set(['test_rate' => '10'], $owner, '2026-07-01');
        $store->set(['test_rate' => '7.5'], $owner, '2027-07-01');

        $this->assertSame('15', $store->get('test_rate', '2026-06-30'));
        $this->assertSame('10', $store->get('test_rate', '2026-07-01'));
        $this->assertSame('10', $store->get('test_rate', '2027-06-30'));
        $this->assertSame('7.5', $store->get('test_rate', '2030-01-01'));
        $this->assertCount(2, $store->history('test_rate'));

        $log = AuditLog::query()->where('event', 'settings.updated')->latest('id')->first();
        $this->assertSame('10', $log->old_values['test_rate']);
        $this->assertSame('7.5', $log->new_values['test_rate']);
        $this->assertSame('2027-07-01', $log->new_values['effective_from']);
    }

    public function test_effective_dated_setting_from_the_form(): void
    {
        $this->defineDatedSetting();
        $this->travelTo('2026-10-17');
        $owner = $this->owner();

        $this->actingAs($owner)->put('/settings', ['dated_key' => 'test_rate', 'effective_from' => '2026-11-01', 'value' => '12'])
            ->assertSessionHas('status', 'Saved.');
        $this->assertSame('12', setting('test_rate', '2026-11-01'));
        $this->assertSame('15', setting('test_rate'));

        $this->actingAs($owner)->get('/settings')->assertSee('2026-11-01');

        $this->actingAs($owner)->delete('/settings/test_rate/2026-11-01')->assertSessionHas('status', 'Removed.');
        $this->assertDatabaseMissing('settings', ['key' => 'test_rate']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'settings.deleted']);
    }

    public function test_past_dated_rows_cannot_be_removed(): void
    {
        $this->defineDatedSetting();
        $this->travelTo('2026-10-17');
        app(SettingsStore::class)->set(['test_rate' => '10'], null, '2026-07-01');

        $this->actingAs($this->owner())->delete('/settings/test_rate/2026-07-01')->assertSessionHasErrors('value');
        $this->assertDatabaseHas('settings', ['key' => 'test_rate']);
    }

    public function test_a_non_dated_setting_cannot_be_posted_with_a_date(): void
    {
        $this->actingAs($this->owner())->put('/settings', ['dated_key' => 'firm_name', 'effective_from' => '2026-11-01', 'value' => 'X'])
            ->assertNotFound();
    }

    public function test_fiscal_year_start_setting_changes_the_dashboard(): void
    {
        $this->travelTo('2026-10-17');
        app(SettingsStore::class)->set(['fiscal_year_start_month' => 1]);

        $this->actingAs($this->owner())->get('/')->assertSeeInOrder(['data-testid="fiscal-year"', '2026'], false)
            ->assertDontSee('2026-27');
    }
}
