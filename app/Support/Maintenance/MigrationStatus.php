<?php

namespace App\Support\Maintenance;

use Illuminate\Database\Migrations\Migrator;
use Throwable;

class MigrationStatus
{
    public function __construct(private readonly Migrator $migrator) {}

    /**
     * @return array{ok: bool, ran: list<string>, pending: list<string>, error: string|null}
     */
    public function status(): array
    {
        try {
            $files = array_keys($this->migrator->getMigrationFiles([database_path('migrations')]));
            $repository = $this->migrator->getRepository();
            $ran = $repository->repositoryExists() ? $repository->getRan() : [];

            return [
                'ok' => true,
                'ran' => array_values($ran),
                'pending' => array_values(array_diff($files, $ran)),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'ran' => [], 'pending' => [], 'error' => mb_substr($e->getMessage(), 0, 300)];
        }
    }
}
