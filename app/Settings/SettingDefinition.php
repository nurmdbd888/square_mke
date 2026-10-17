<?php

namespace App\Settings;

use Closure;
use Illuminate\Validation\Rule;

/**
 * One configurable business rule. Each milestone registers the settings it
 * reads (see CoreSettings for M1's list).
 */
final class SettingDefinition
{
    public const STRING = 'string';

    public const INTEGER = 'integer';

    public const BOOLEAN = 'boolean';

    public const DECIMAL = 'decimal';

    public const CHOICE = 'choice';

    public const ROLES = 'roles';

    public const WEEKDAYS = 'weekdays';

    public const DATE_LIST = 'date_list';

    /**
     * @param  array<string, string>  $options  value => label (English, translated in the view)
     * @param  list<mixed>  $rules  extra validation rules for one value
     */
    public function __construct(
        public readonly string $key,
        public readonly string $type,
        public readonly mixed $default,
        public readonly string $group,
        public readonly string $label,
        public readonly ?string $help = null,
        public readonly bool $effectiveDated = false,
        public readonly array $options = [],
        public readonly array $rules = [],
    ) {}

    public function defaultValue(): mixed
    {
        return $this->cast($this->default instanceof Closure ? ($this->default)() : $this->default);
    }

    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return match ($this->type) {
                self::ROLES, self::WEEKDAYS, self::DATE_LIST => [],
                self::STRING => in_array('nullable', $this->rules, true) ? '' : null,
                default => null,
            };
        }

        return match ($this->type) {
            self::INTEGER => (int) $value,
            self::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::ROLES => array_values(array_map('strval', (array) $value)),
            self::WEEKDAYS => array_values(array_unique(array_map('intval', array_filter((array) $value, fn ($v) => $v !== '' && $v !== null)))),
            self::DATE_LIST => array_values(array_map('strval', (array) $value)),
            default => (string) $value,
        };
    }

    /**
     * @return list<mixed>
     */
    public function validationRules(): array
    {
        $extra = $this->rules;
        $nullable = in_array('nullable', $extra, true);
        $extra = array_values(array_filter($extra, fn ($rule) => $rule !== 'nullable'));

        $base = match ($this->type) {
            self::STRING => array_merge([$nullable ? 'nullable' : 'required', 'string', 'max:191'], $extra),
            self::INTEGER => ['required', 'integer'],
            self::BOOLEAN => ['boolean'],
            self::DECIMAL => ['required', 'decimal:0,4'],
            self::CHOICE => ['required', Rule::in(array_keys($this->options))],
            self::ROLES => ['array'],
            self::WEEKDAYS => ['array'],
            self::DATE_LIST => ['array'],
            default => [],
        };

        if ($this->type === self::STRING) {
            return $base;
        }

        return array_merge($base, $extra);
    }
}
