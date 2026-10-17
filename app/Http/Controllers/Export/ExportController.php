<?php

namespace App\Http\Controllers\Export;

use App\Domain\Export\ExportRegistry;
use App\Domain\Export\ExportService;
use App\Domain\Google\GoogleDriveService;
use App\Http\Controllers\Controller;
use App\Models\AppJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly ExportService $exports,
        private readonly GoogleDriveService $drive,
    ) {}

    public function downloads(Request $request): View
    {
        $jobs = AppJob::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('export.downloads', ['jobs' => $jobs]);
    }

    public function store(string $key, Request $request): RedirectResponse
    {
        $def = ExportRegistry::get($key);
        abort_unless($request->user()->can($def['permission']), 403);

        $data = $request->validate([
            'format' => ['required', 'in:csv,xlsx,pdf'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:40'],
            'filters' => ['nullable', 'array'],
            'save_drive' => ['nullable', 'boolean'],
        ]);

        $filters = array_merge($request->query(), $data['filters'] ?? []);
        $job = $this->exports->start(
            $key,
            $data['format'],
            $filters,
            $data['columns'] ?? [],
            $request->user(),
        );

        if ($request->boolean('save_drive') && $job->status === AppJob::STATUS_DONE && $job->result_path) {
            $path = storage_path('app/'.$job->result_path);
            if (is_file($path)) {
                $this->drive->saveFile($path, basename($path), (string) $job->result_mime);
            }
        }

        if ($job->status === AppJob::STATUS_DONE) {
            return redirect()->route('exports.download', $job)->with('status', __('Export ready.'));
        }

        return redirect()->route('exports.downloads')->with('status', __('Export queued. Check Downloads.'));
    }

    public function download(AppJob $appJob): StreamedResponse
    {
        abort_if($appJob->user_id !== auth()->id(), 403);
        abort_if($appJob->status !== AppJob::STATUS_DONE || ! $appJob->result_path, 404);

        return Storage::download($appJob->result_path, basename($appJob->result_path), [
            'Content-Type' => $appJob->result_mime ?? 'application/octet-stream',
        ]);
    }
}
