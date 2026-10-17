<?php

namespace App\Support\Maintenance;

final class CheckResult
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public const INFO = 'info';

    public function __construct(
        public readonly string $group,
        public readonly string $key,
        public readonly string $label,
        public readonly string $status,
        public readonly string $detail = '',
    ) {}

    /**
     * @return array{group: string, key: string, label: string, status: string, detail: string}
     */
    public function toArray(): array
    {
        return [
            'group' => $this->group,
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status,
            'detail' => $this->detail,
        ];
    }
}
