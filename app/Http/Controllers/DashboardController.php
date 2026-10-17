<?php

namespace App\Http\Controllers;

use App\Domain\Calendar\FiscalYear;
use App\Support\Health;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Health $health): View
    {
        return view('dashboard', [
            'fiscalYear' => FiscalYear::containing(now(), (int) setting('fiscal_year_start_month')),
            'health' => $request->user()->can('health.view') ? $health->report() : null,
        ]);
    }
}
