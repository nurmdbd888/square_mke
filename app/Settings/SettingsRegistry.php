<?php

namespace App\Settings;

use InvalidArgumentException;

class SettingsRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    public function define(SettingDefinition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): SettingDefinition
    {
        return $this->definitions[$key] ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, list<SettingDefinition>>
     */
    public function groups(): array
    {
        $groups = [];

        foreach ($this->definitions as $definition) {
            $groups[$definition->group][] = $definition;
        }

        return $groups;
    }
}
