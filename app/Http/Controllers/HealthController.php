<?php

namespace App\Http\Controllers;

use App\Support\Health;
use Illuminate\View\View;

class HealthController extends Controller
{
    public function __invoke(Health $health): View
    {
        return view('health', $health->report());
    }
}
