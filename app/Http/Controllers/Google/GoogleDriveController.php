<?php

namespace App\Http\Controllers\Google;

use App\Domain\Google\GoogleDriveService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleDriveController extends Controller
{
    public function __construct(private readonly GoogleDriveService $drive) {}

    public function folder(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('google.manage'), 403);
        $data = $request->validate(['drive_folder_id' => ['nullable', 'string', 'max:128']]);
        $this->drive->setFolderId($data['drive_folder_id'] ?? null);

        return redirect()->route('settings.index')->with('status', __('Drive folder saved.'));
    }
}
