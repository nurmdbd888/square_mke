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
            return $this->type === self::ROLES ? [] : null;
        }

        return match ($this->type) {
            self::INTEGER => (int) $value,
            self::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            self::ROLES => array_values(array_map('strval', (array) $value)),
            default => (string) $value,
        };
    }

    /**
     * @return list<mixed>
     */
    public function validationRules(): array
    {
        $base = match ($this->type) {
            self::STRING => ['required', 'string', 'max:191'],
            self::INTEGER => ['required', 'integer'],
            self::BOOLEAN => ['boolean'],
            self::DECIMAL => ['required', 'decimal:0,4'],
            self::CHOICE => ['required', Rule::in(array_keys($this->options))],
            self::ROLES => ['array'],
        };

        return array_merge($base, $this->rules);
    }
}
