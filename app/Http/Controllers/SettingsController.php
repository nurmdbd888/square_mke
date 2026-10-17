<?php

namespace App\Http\Controllers;

use App\Domain\Google\GoogleOAuthService;
use App\Models\GoogleConnection;
use App\Models\User;
use App\Settings\SettingDefinition;
use App\Settings\SettingsRegistry;
use App\Settings\SettingsStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsStore $store,
    ) {}

    public function index(Request $request): View
    {
        return view('settings.index', [
            'groups' => $this->registry->groups(),
            'store' => $this->store,
            'canManage' => $request->user()->can('settings.manage'),
            'googleConnection' => GoogleConnection::current(),
            'googleConfigured' => app(GoogleOAuthService::class)->isConfigured(),
        ]);
    }

    /**
     * The form posts every non-dated setting; an effective-dated setting is
     * posted on its own with an effective_from date.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $datedKey = $request->input('dated_key');

        if (is_string($datedKey) && $datedKey !== '') {
            abort_unless($this->registry->has($datedKey) && $this->registry->get($datedKey)->effectiveDated, 404);
            $definition = $this->registry->get($datedKey);

            $data = $request->validate([
                'effective_from' => ['required', 'date_format:Y-m-d'],
                'value' => $definition->validationRules(),
            ]);

            $this->store->set([$datedKey => $data['value'] ?? null], $user, $data['effective_from']);

            return redirect()->route('settings.index')->with('status', __('Saved.'));
        }

        $definitions = array_filter($this->registry->all(), fn (SettingDefinition $d) => ! $d->effectiveDated);
        $rules = [];

        foreach ($definitions as $key => $definition) {
            if ($definition->type === SettingDefinition::DATE_LIST) {
                $lines = $request->input("settings_lines.{$key}", '');
                $dates = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) $lines) ?: [])));
                $request->merge(["settings.{$key}" => $dates]);
            }

            $rules["settings.{$key}"] = $definition->validationRules();

            if ($definition->type === SettingDefinition::ROLES) {
                $rules["settings.{$key}.*"] = [Rule::in(array_keys($definition->options))];
            }
            if ($definition->type === SettingDefinition::WEEKDAYS) {
                $rules["settings.{$key}.*"] = ['integer', Rule::in(array_map('intval', array_keys($definition->options)))];
            }
            if ($definition->type === SettingDefinition::DATE_LIST) {
                $rules["settings.{$key}.*"] = ['required', 'date_format:Y-m-d', 'distinct'];
            }
        }

        $data = $request->validate($rules, [], $this->attributeNames($definitions));

        $values = [];

        foreach ($definitions as $key => $definition) {
            $values[$key] = match ($definition->type) {
                SettingDefinition::BOOLEAN => $request->boolean("settings.{$key}"),
                SettingDefinition::ROLES => $data['settings'][$key] ?? [],
                SettingDefinition::WEEKDAYS => $data['settings'][$key] ?? [],
                SettingDefinition::DATE_LIST => $data['settings'][$key] ?? [],
                default => $data['settings'][$key] ?? null,
            };
        }

        $changes = $this->store->set($values, $user);

        return redirect()->route('settings.index')->with('status', $changes === [] ? __('Nothing changed.') : __('Saved.'));
    }

    public function destroyDated(Request $request, string $key, string $date): RedirectResponse
    {
        abort_unless($this->registry->has($key) && $this->registry->get($key)->effectiveDated, 404);

        if ($date <= now()->toDateString()) {
            return back()->withErrors(['value' => __('Only future dates can be removed. Add a new value from today instead.')]);
        }

        $this->store->deleteDated($key, $date, $request->user());

        return redirect()->route('settings.index')->with('status', __('Removed.'));
    }

    /**
     * @param  array<string, SettingDefinition>  $definitions
     * @return array<string, string>
     */
    private function attributeNames(array $definitions): array
    {
        $names = [];

        foreach ($definitions as $key => $definition) {
            $names["settings.{$key}"] = __($definition->label);
        }

        return $names;
    }
}
