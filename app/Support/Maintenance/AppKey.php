<?php

namespace App\Support\Maintenance;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;

/**
 * Web replacement for `php artisan key:generate`.
 */
class AppKey
{
    public function isValid(?string $key = null): bool
    {
        $key ??= (string) config('app.key');

        if ($key === '') {
            return false;
        }

        if (Str::startsWith($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        return Encrypter::supported($key, (string) config('app.cipher'));
    }

    public function generate(): string
    {
        return 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));
    }

    /**
     * Writes a new key into the .env file. Never replaces a valid existing key:
     * that would make encrypted data unreadable.
     *
     * @return array{status: 'written'|'exists'|'no_env'|'not_writable', key: string|null, path: string}
     */
    public function generateAndStore(?string $envFile = null): array
    {
        $envFile ??= app()->environmentFilePath();

        if ($this->isValid()) {
            return ['status' => 'exists', 'key' => null, 'path' => $envFile];
        }

        $key = $this->generate();

        if (! is_file($envFile)) {
            return ['status' => 'no_env', 'key' => $key, 'path' => $envFile];
        }

        $contents = (string) file_get_contents($envFile);

        if (preg_match('/^APP_KEY=(.*)$/m', $contents, $m) && $this->isValid(trim($m[1], " \t\"'"))) {
            return ['status' => 'exists', 'key' => null, 'path' => $envFile];
        }

        if (! is_writable($envFile)) {
            return ['status' => 'not_writable', 'key' => $key, 'path' => $envFile];
        }

        $line = 'APP_KEY='.$key;
        $updated = preg_match('/^APP_KEY=.*$/m', $contents)
            ? preg_replace('/^APP_KEY=.*$/m', $line, $contents, 1)
            : rtrim($contents, "\n")."\n".$line."\n";

        if (@file_put_contents($envFile, $updated, LOCK_EX) === false) {
            return ['status' => 'not_writable', 'key' => $key, 'path' => $envFile];
        }

        return ['status' => 'written', 'key' => null, 'path' => $envFile];
    }
}
