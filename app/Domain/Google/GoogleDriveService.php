<?php

namespace App\Domain\Google;

use App\Models\GoogleConnection;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use RuntimeException;

final class GoogleDriveService
{
    public function __construct(private readonly GoogleOAuthService $oauth) {}

    public function saveFile(string $localPath, string $filename, string $mime): string
    {
        $connection = GoogleConnection::current();
        if ($connection === null) {
            throw new RuntimeException(__('Connect Google first in Settings.'));
        }

        $client = $this->oauth->client($connection);
        $drive = new Drive($client);

        $meta = new DriveFile([
            'name' => $filename,
            'parents' => $connection->drive_folder_id ? [$connection->drive_folder_id] : null,
        ]);

        $created = $drive->files->create($meta, [
            'data' => file_get_contents($localPath),
            'mimeType' => $mime,
            'uploadType' => 'multipart',
        ]);

        return (string) $created->id;
    }

    public function setFolderId(?string $folderId): void
    {
        $connection = GoogleConnection::current();
        if ($connection === null) {
            throw new RuntimeException(__('Connect Google first.'));
        }
        $connection->update(['drive_folder_id' => $folderId !== '' ? $folderId : null]);
    }
}
