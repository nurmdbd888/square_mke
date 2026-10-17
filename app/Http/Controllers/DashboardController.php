<?php

namespace App\Http\Controllers;

use App\Domain\Calendar\FiscalYear;
use App\Domain\Reports\DashboardMetrics;
use App\Support\Health;
use App\Support\Http\BusinessDaysFilter;
use App\Support\Maintenance\MigrationStatus;
use App\Support\SafeAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Health $health, DashboardMetrics $metrics, MigrationStatus $migrations): View
    {
        $businessDaysOnly = BusinessDaysFilter::fromRequest($request);
        $user = $request->user();

        $metricsSnapshot = null;
        if (SafeAccess::can($user, 'reports.view')) {
            try {
                $metricsSnapshot = $metrics->snapshot(businessDaysOnly: $businessDaysOnly);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $healthReport = null;
        if (SafeAccess::can($user, 'health.view')) {
            try {
                $healthReport = $health->report();
            } catch (Throwable $e) {
                report($e);
            }
        }

        $pendingMigrations = [];
        try {
            $pendingMigrations = $migrations->status()['pending'] ?? [];
        } catch (Throwable) {
            $pendingMigrations = [];
        }

        return view('dashboard', [
            'fiscalYear' => FiscalYear::containing(now(), (int) (setting('fiscal_year_start_month') ?: 7)),
            'health' => $healthReport,
            'metrics' => $metricsSnapshot,
            'businessDaysOnly' => $businessDaysOnly,
            'pendingMigrations' => $pendingMigrations,
        ]);
    }
}
