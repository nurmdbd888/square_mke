<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CustomField;
use App\Models\OpeningBalance;
use App\Models\PriceList;
use App\Models\SalesRep;
use App\Models\SalesRoute;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\View\View;

class MastersController extends Controller
{
    public function index(): View
    {
        return view('master.hub', [
            'links' => [
                ['url' => route('masters.directory.index', 'brands'), 'label' => __('Brands'), 'count' => Brand::query()->count()],
                ['url' => route('masters.directory.index', 'suppliers'), 'label' => __('Suppliers'), 'count' => Supplier::query()->count()],
                ['url' => route('masters.directory.index', 'routes'), 'label' => __('Routes'), 'count' => SalesRoute::query()->count()],
                ['url' => route('masters.directory.index', 'sales-reps'), 'label' => __('Sales reps'), 'count' => SalesRep::query()->count()],
                ['url' => route('masters.directory.index', 'vehicles'), 'label' => __('Vehicles'), 'count' => Vehicle::query()->count()],
                ['url' => route('masters.price-lists.index'), 'label' => __('Price lists'), 'count' => PriceList::query()->count()],
                ['url' => route('masters.opening-balances.index'), 'label' => __('Opening balances'), 'count' => OpeningBalance::query()->count()],
                ['url' => route('masters.custom-fields.index'), 'label' => __('Custom fields'), 'count' => CustomField::query()->count()],
            ],
        ]);
    }
}
