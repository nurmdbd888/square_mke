<?php

namespace App\Domain\Google;

use App\Domain\Backup\BackupCatalog;
use App\Models\GoogleConnection;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Carbon;
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

    public function pruneBackupFiles(int $retentionDays): int
    {
        $connection = GoogleConnection::current();
        if ($connection === null) {
            return 0;
        }

        $client = $this->oauth->client($connection);
        $drive = new Drive($client);
        $cutoff = Carbon::now()->subDays($retentionDays);

        $query = "name contains '".BackupCatalog::PREFIX."' and trashed = false";
        if ($connection->drive_folder_id) {
            $query .= " and '{$connection->drive_folder_id}' in parents";
        }

        $removed = 0;
        $pageToken = null;
        do {
            $response = $drive->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'nextPageToken, files(id, name, createdTime)',
                'pageToken' => $pageToken,
            ]);
            foreach ($response->getFiles() as $file) {
                $created = Carbon::parse($file->getCreatedTime());
                if ($created->gte($cutoff)) {
                    continue;
                }
                $drive->files->delete($file->getId());
                $removed++;
            }
            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $removed;
    }
}
