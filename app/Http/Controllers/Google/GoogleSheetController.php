<?php

namespace App\Http\Controllers\Google;

use App\Domain\Export\ExportRegistry;
use App\Domain\Google\GoogleSheetsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleSheetController extends Controller
{
    public function __construct(private readonly GoogleSheetsService $sheets) {}

    public function store(string $key, Request $request): RedirectResponse
    {
        $def = ExportRegistry::get($key);
        abort_unless($request->user()->can($def['permission']), 403);
        abort_unless($request->user()->can('google.manage'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'spreadsheet_id' => ['nullable', 'string', 'max:128'],
            'columns' => ['nullable', 'array'],
            'schedule_refresh' => ['nullable', 'boolean'],
        ]);

        $filters = collect($request->input('filters', []))->filter(fn ($v) => is_scalar($v))->all();
        $sub = $this->sheets->exportReport(
            $key,
            $filters,
            $data['columns'] ?? [],
            $request->user(),
            $data['spreadsheet_id'] ?? null,
            $data['title'],
        );

        $url = 'https://docs.google.com/spreadsheets/d/'.$sub->spreadsheet_id;

        return redirect()->away($url);
    }
}
