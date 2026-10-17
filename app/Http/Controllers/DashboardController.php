<?php

namespace App\Http\Controllers;

use App\Domain\Calendar\FiscalYear;
use App\Domain\Reports\DashboardMetrics;
use App\Support\Health;
use App\Support\Http\BusinessDaysFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Health $health, DashboardMetrics $metrics): View
    {
        $businessDaysOnly = BusinessDaysFilter::fromRequest($request);

        return view('dashboard', [
            'fiscalYear' => FiscalYear::containing(now(), (int) setting('fiscal_year_start_month')),
            'health' => $request->user()->can('health.view') ? $health->report() : null,
            'metrics' => $request->user()->can('reports.view') ? $metrics->snapshot(businessDaysOnly: $businessDaysOnly) : null,
            'businessDaysOnly' => $businessDaysOnly,
        ]);
    }
}
