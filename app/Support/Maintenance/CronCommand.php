<?php

namespace App\Support\Maintenance;

/**
 * Builds the cron command the owner pastes into cPanel > Cron Jobs.
 *
 * cPanel cron runs whatever "php" is first on PATH, which is often an older
 * system default instead of the version picked for the subdomain, so we
 * point at the exact binary that matches the PHP serving this page.
 */
class CronCommand
{
    /**
     * @param  list<string>|null  $candidates
     */
    public function __construct(
        private readonly string $phpBinary = PHP_BINARY,
        private readonly string $phpVersion = PHP_VERSION,
        private readonly ?array $candidates = null,
    ) {}

    public function phpPath(): string
    {
        foreach ($this->candidates() as $path) {
            if (@is_file($path) && @is_executable($path)) {
                return $path;
            }
        }

        return '/usr/local/bin/php';
    }

    public function command(?string $artisan = null): string
    {
        $artisan ??= base_path('artisan');

        return $this->phpPath().' '.$artisan.' schedule:run >> /dev/null 2>&1';
    }

    public function cronLine(?string $artisan = null): string
    {
        return '* * * * * '.$this->command($artisan);
    }

    /**
     * @return list<string>
     */
    public function candidates(): array
    {
        if ($this->candidates !== null) {
            return $this->candidates;
        }

        [$major, $minor] = array_map('intval', explode('.', $this->phpVersion));
        $xy = $major.$minor;

        $list = [];

        $derived = $this->cliFromSapiBinary($this->phpBinary);
        if ($derived !== null) {
            $list[] = $derived;
        }

        return array_values(array_unique(array_merge($list, [
            "/opt/cpanel/ea-php{$xy}/root/usr/bin/php",
            "/opt/alt/php{$xy}/usr/bin/php",
            "/usr/local/bin/ea-php{$xy}",
            "/usr/bin/php{$major}.{$minor}",
        ])));
    }

    /**
     * Under LiteSpeed the web binary is ".../bin/lsphp"; under PHP-FPM it is
     * ".../sbin/php-fpm". The CLI binary sits in the matching bin folder.
     */
    private function cliFromSapiBinary(string $binary): ?string
    {
        if ($binary === '') {
            return null;
        }

        $name = basename($binary);
        $dir = dirname($binary);

        if ($name === 'php' || preg_match('/^php\d+(\.\d+)?$/', $name)) {
            return $binary;
        }

        if (in_array($name, ['lsphp', 'php-cgi'], true)) {
            return $dir.'/php';
        }

        if ($name === 'php-fpm') {
            return dirname($dir).'/bin/php';
        }

        return null;
    }
}
