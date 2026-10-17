<?php

namespace App\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Reads and writes settings rows.
 *
 * Settings that are not effective-dated keep one row (effective_from null).
 * Effective-dated settings keep one row per start date; the value on a date
 * is the row with the latest effective_from on or before that date, or the
 * default when none exists. Reads never throw: before migrations, or with
 * the database down, every setting returns its default.
 */
class SettingsStore
{
    /** @var Collection<int, Setting>|null */
    private ?Collection $rows = null;

    public function __construct(private readonly SettingsRegistry $registry) {}

    public function get(string $key, CarbonInterface|string|null $on = null): mixed
    {
        $definition = $this->registry->get($key);
        $row = $this->rowFor($definition, $on);

        return $row === null ? $definition->defaultValue() : $definition->cast(json_decode((string) $row->value, true));
    }

    /**
     * True when the owner saved a value (the default is not in use).
     */
    public function isStored(string $key): bool
    {
        return $this->rows()->contains('key', $key);
    }

    /**
     * Saves several settings in one go and writes one audit row listing the
     * keys whose effective value changed.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, array{old: mixed, new: mixed}> the changes
     */
    public function set(array $values, ?User $by = null, CarbonInterface|string|null $effectiveFrom = null): array
    {
        $from = $effectiveFrom === null ? null : Carbon::parse($effectiveFrom)->startOfDay();
        $changes = [];

        DB::transaction(function () use ($values, $by, $from, &$changes) {
            foreach ($values as $key => $value) {
                $definition = $this->registry->get($key);

                if ($definition->effectiveDated && $from === null) {
                    throw new InvalidArgumentException("Setting [{$key}] needs an effective date.");
                }

                $date = $definition->effectiveDated ? $from : null;
                $old = $this->get($key, $date);
                $new = $definition->cast($value);

                if ($old === $new && ! $definition->effectiveDated) {
                    continue;
                }

                Setting::query()->updateOrCreate(
                    ['key' => $key, 'effective_from' => $date?->toDateString()],
                    ['value' => json_encode($new, JSON_UNESCAPED_UNICODE), 'updated_by' => $by?->getKey()],
                );

                $this->rows = null;

                if ($old !== $new) {
                    $changes[$key] = ['old' => $old, 'new' => $new];
                }
            }

            if ($changes !== []) {
                $old = array_map(fn ($c) => $c['old'], $changes);
                $new = array_map(fn ($c) => $c['new'], $changes);

                if ($from !== null) {
                    $new['effective_from'] = $from->toDateString();
                }

                AuditLogger::log('settings.updated', null, $old, $new, $by, subjectType: 'setting');
            }
        });

        return $changes;
    }

    /**
     * Removes one future-dated row of an effective-dated setting.
     */
    public function deleteDated(string $key, CarbonInterface|string $effectiveFrom, ?User $by = null): bool
    {
        $date = Carbon::parse($effectiveFrom)->toDateString();
        $row = Setting::query()->where('key', $key)->whereDate('effective_from', $date)->first();

        if ($row === null) {
            return false;
        }

        $row->delete();
        $this->rows = null;

        AuditLogger::log('settings.deleted', null, [$key => json_decode((string) $row->value, true), 'effective_from' => $date], null, $by, subjectType: 'setting');

        return true;
    }

    /**
     * @return Collection<int, Setting> newest first
     */
    public function history(string $key): Collection
    {
        return $this->rows()->where('key', $key)->sortByDesc(fn (Setting $row) => $row->effective_from?->toDateString() ?? '')->values();
    }

    public function flush(): void
    {
        $this->rows = null;
    }

    private function rowFor(SettingDefinition $definition, CarbonInterface|string|null $on): ?Setting
    {
        $rows = $this->rows()->where('key', $definition->key);

        if (! $definition->effectiveDated) {
            return $rows->first(fn (Setting $row) => $row->effective_from === null);
        }

        $date = ($on === null ? now() : Carbon::parse($on))->toDateString();

        return $rows
            ->filter(fn (Setting $row) => $row->effective_from !== null && $row->effective_from->toDateString() <= $date)
            ->sortByDesc(fn (Setting $row) => $row->effective_from->toDateString())
            ->first();
    }

    /**
     * @return Collection<int, Setting>
     */
    private function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        try {
            return $this->rows = Setting::query()->get();
        } catch (Throwable) {
            // Remembered until flush() so a down database costs one connection attempt per request.
            return $this->rows = new Collection;
        }
    }
}
