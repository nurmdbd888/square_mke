<?php

namespace App\Support\Maintenance;

/**
 * Keeps a deny-all .htaccess in the shared folder and in storage, so .env,
 * logs and uploads stay private even if the subdomain's document root is
 * set too high. Installs from before these files existed get them the next
 * time the owner opens the Maintenance page.
 */
class FolderGuard
{
    /**
     * @return list<string>
     */
    public function folders(): array
    {
        $folders = [storage_path()];
        $envFolder = dirname(app()->environmentFilePath());

        if (str_ends_with($envFolder, '/shared')) {
            array_unshift($folders, $envFolder);
        }

        return array_values(array_unique($folders));
    }

    /**
     * Writes the deny file into every folder that lacks one.
     */
    public function ensure(): void
    {
        $template = @file_get_contents(base_path('deploy/shared.example/.htaccess'));

        if (! is_string($template)) {
            return;
        }

        foreach ($this->unprotected() as $folder) {
            if (is_dir($folder) && is_writable($folder)) {
                @file_put_contents($folder.'/.htaccess', $template);
            }
        }
    }

    /**
     * @return list<string> folders still missing their deny file
     */
    public function unprotected(): array
    {
        return array_values(array_filter($this->folders(), fn (string $folder) => ! is_file($folder.'/.htaccess')));
    }
}
