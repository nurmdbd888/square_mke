<?php

namespace App\Support;

/**
 * Locates the persistent "shared" folder that holds .env and storage/.
 *
 * Server layout: ~/mke/app (replaced on every update) next to ~/mke/shared
 * (kept forever). Local checkouts have no sibling "shared" folder and keep
 * using the Laravel defaults inside the project.
 */
final class SharedPath
{
    public static function resolve(string $basePath, ?string $override = null): ?string
    {
        if ($override !== null && $override !== '') {
            return is_dir($override) ? rtrim($override, '/') : null;
        }

        $candidate = dirname($basePath).'/shared';

        if (! is_dir($candidate)) {
            return null;
        }

        if (is_file($candidate.'/.env') || is_dir($candidate.'/storage')) {
            return $candidate;
        }

        return null;
    }
}
