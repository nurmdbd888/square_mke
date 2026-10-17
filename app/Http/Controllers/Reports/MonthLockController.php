<?php

namespace App\Http\Controllers\Reports;

use App\Domain\Reports\MonthLockService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonthLockController extends Controller
{
    public function __construct(private readonly MonthLockService $locks) {}

    public function index(Request $request): View
    {
        return view('reports.month-locks', [
            'locks' => $this->locks->activeLocks(),
            'canManage' => $request->user()->can('reports.manage'),
            'isOwner' => $request->user()->isOwner(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $this->locks->lock((int) $data['year'], (int) $data['month'], $request->user());

        return back()->with('status', __('Month locked.'));
    }

    public function destroy(Request $request, int $year, int $month): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $this->locks->unlock($year, $month, $request->user());

        return back()->with('status', __('Month unlocked.'));
    }
}
