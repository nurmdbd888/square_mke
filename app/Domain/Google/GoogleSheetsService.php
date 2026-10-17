<?php

namespace App\Domain\Google;

use App\Domain\Export\ExportRegistry;
use App\Models\GoogleConnection;
use App\Models\GoogleSheetSubscription;
use App\Models\User;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use RuntimeException;

final class GoogleSheetsService
{
    public function __construct(private readonly GoogleOAuthService $oauth) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $columns
     */
    public function exportReport(string $exportKey, array $filters, array $columns, User $user, ?string $spreadsheetId, string $title): GoogleSheetSubscription
    {
        $connection = GoogleConnection::current();
        if ($connection === null) {
            throw new RuntimeException(__('Connect Google first in Settings.'));
        }

        $source = ExportRegistry::source($exportKey);
        $data = $source->rows($filters, $columns === [] ? $source->defaultColumns() : $columns);
        $values = [$data['headers']];
        foreach ($data['rows'] as $row) {
            $values[] = $row;
        }

        $client = $this->oauth->client($connection);
        $sheets = new Sheets($client);

        if ($spreadsheetId === null || $spreadsheetId === '') {
            $spreadsheet = new Sheets\Spreadsheet([
                'properties' => ['title' => $title],
            ]);
            $created = $sheets->spreadsheets->create($spreadsheet);
            $spreadsheetId = $created->spreadsheetId;
        }

        $range = 'Sheet1!A1';
        $body = new ValueRange(['values' => $values]);
        $sheets->spreadsheets_values->update($spreadsheetId, $range, $body, ['valueInputOption' => 'RAW']);

        return GoogleSheetSubscription::query()->create([
            'created_by' => $user->id,
            'export_key' => $exportKey,
            'title' => $title,
            'spreadsheet_id' => $spreadsheetId,
            'filters' => $filters,
            'columns' => $columns,
            'refresh' => 'daily',
            'next_refresh_at' => now()->addDay(),
        ]);
    }

    public function refreshSubscription(GoogleSheetSubscription $sub): void
    {
        $connection = GoogleConnection::current();
        if ($connection === null) {
            throw new RuntimeException(__('Google is disconnected.'));
        }

        $source = ExportRegistry::source($sub->export_key);
        $data = $source->rows($sub->filters ?? [], $sub->columns ?? $source->defaultColumns());
        $values = [$data['headers']];
        foreach ($data['rows'] as $row) {
            $values[] = $row;
        }

        $client = $this->oauth->client($connection);
        $sheets = new Sheets($client);
        $body = new ValueRange(['values' => $values]);
        $sheets->spreadsheets_values->update($sub->spreadsheet_id, 'Sheet1!A1', $body, ['valueInputOption' => 'RAW']);
    }
}
