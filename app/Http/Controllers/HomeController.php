<?php

namespace App\Http\Controllers;

use App\Models\SmokeHeartbeat;
use App\Support\AppVersion;
use App\Support\Maintenance\DatabaseProbe;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(DatabaseProbe $probe): View
    {
        $database = $probe->probe();
        $heartbeat = $database['connected'] ? SmokeHeartbeat::latestOrNull() : null;

        return view('home', [
            'firmName' => config('sdm.firm_name'),
            'distributorCode' => config('sdm.distributor_code'),
            'version' => AppVersion::label(),
            'databaseOk' => $database['connected'],
            'heartbeat' => $heartbeat,
            'cronOk' => $heartbeat?->isFresh() ?? false,
        ]);
    }
}
