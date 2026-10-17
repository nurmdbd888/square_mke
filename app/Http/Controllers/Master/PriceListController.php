<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PriceListController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
        ]);
        $status = $filters['status'] ?? 'active';

        $priceLists = PriceList::query()
            ->withCount('items')
            ->when($status === 'inactive', fn ($q) => $q->onlyTrashed())
            ->when($status === 'all', fn ($q) => $q->withTrashed())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $q->where('name', 'like', '%'.addcslashes($term, '\\%_').'%');
            })
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $view = $request->header('HX-Request') && ! $request->header('HX-Boosted') ? 'master.price-lists._table' : 'master.price-lists.index';

        return view($view, [
            'lists' => $priceLists,
            'filters' => $filters + ['status' => $status],
        ]);
    }

    public function create(): View
    {
        return view('master.price-lists.form', ['list' => new PriceList]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $priceList = PriceList::query()->create($data);

        return redirect()->route('masters.price-lists.show', $priceList)->with('status', __('Price list :name was saved.', ['name' => $priceList->name]));
    }

    public function show(PriceList $priceList): View
    {
        $priceList->load(['items' => fn ($q) => $q->with('product')->orderByDesc('effective_from')]);

        return view('master.price-lists.show', [
            'list' => $priceList,
            'products' => Product::query()->orderBy('name_en')->get(['id', 'code', 'name_en']),
        ]);
    }

    public function update(Request $request, PriceList $priceList): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $priceList->update($data);

        return redirect()->route('masters.price-lists.show', $priceList)->with('status', __('Saved.'));
    }

    public function destroy(PriceList $priceList): RedirectResponse
    {
        $priceList->delete();

        return redirect()->route('masters.price-lists.index')->with('status', __('Price list :name was deactivated.', ['name' => $priceList->name]));
    }

    public function restore(PriceList $priceList): RedirectResponse
    {
        $priceList->restore();

        return redirect()->route('masters.price-lists.show', $priceList)->with('status', __('Price list :name can be used again.', ['name' => $priceList->name]));
    }

    public function storeItem(Request $request, PriceList $priceList): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'price' => ['required', 'decimal:0,4', 'min:0'],
            'effective_from' => ['required', 'date'],
        ]);

        $item = PriceListItem::query()->updateOrCreate(
            [
                'price_list_id' => $priceList->id,
                'product_id' => $data['product_id'],
                'effective_from' => $data['effective_from'],
            ],
            ['price' => $data['price']],
        );

        return redirect()->route('masters.price-lists.show', $priceList)->with('status', $item->wasRecentlyCreated ? __('Price saved.') : __('Price replaced for that date.'));
    }

    public function destroyItem(PriceList $priceList, PriceListItem $item): RedirectResponse
    {
        abort_unless($item->price_list_id === $priceList->id, 404);
        $item->delete();

        return redirect()->route('masters.price-lists.show', $priceList)->with('status', __('Price removed.'));
    }
}
