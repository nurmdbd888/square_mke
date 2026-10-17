<?php

namespace App\Support;

/**
 * Reads version.json, which the release build writes into the app folder.
 */
final class AppVersion
{
    /**
     * @return array{version: string, commit: string|null, built_at: string|null}
     */
    public static function info(?string $file = null): array
    {
        $file ??= base_path('version.json');
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return [
            'version' => is_array($data) && is_string($data['version'] ?? null) ? $data['version'] : 'dev',
            'commit' => is_array($data) && is_string($data['commit'] ?? null) ? $data['commit'] : null,
            'built_at' => is_array($data) && is_string($data['built_at'] ?? null) ? $data['built_at'] : null,
        ];
    }

    public static function label(): string
    {
        $info = self::info();

        return $info['commit'] ? $info['version'].' ('.substr($info['commit'], 0, 7).')' : $info['version'];
    }
}
