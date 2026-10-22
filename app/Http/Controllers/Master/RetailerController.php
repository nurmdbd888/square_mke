<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Master\Concerns\RespondsWithPartyJson;
use App\Models\CustomField;
use App\Models\PriceList;
use App\Models\Retailer;
use App\Models\SalesRep;
use App\Models\SalesRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RetailerController extends Controller
{
    use RespondsWithPartyJson;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
        ]);
        $status = $filters['status'] ?? 'active';
        $columns = $this->columns($request);

        $retailers = Retailer::query()
            ->with(['route', 'salesRep', 'priceList'])
            ->when($status === 'inactive', fn ($q) => $q->onlyTrashed())
            ->when($status === 'all', fn ($q) => $q->withTrashed())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->when($filters['route'] ?? null, fn ($q, $route) => $q->where('route_id', $route))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $view = $request->header('HX-Request') && ! $request->header('HX-Boosted') ? 'master.retailers._table' : 'master.retailers.index';

        return view($view, [
            'retailers' => $retailers,
            'filters' => $filters + ['status' => $status],
            'columns' => $columns,
            'routes' => SalesRoute::query()->orderBy('name')->get(['id', 'name']),
            'reps' => SalesRep::query()->orderBy('name')->get(['id', 'name']),
            'priceLists' => PriceList::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('master.retailers.form', $this->formData(new Retailer));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $retailer = Retailer::query()->create($this->validated($request));

        if ($this->wantsPartyJson($request)) {
            return $this->partyJson($retailer);
        }

        return redirect()->route('retailers.index')->with('status', __('Retailer :name was saved.', ['name' => $retailer->name]));
    }

    public function edit(Retailer $retailer): View
    {
        return view('master.retailers.form', $this->formData($retailer));
    }

    public function update(Request $request, Retailer $retailer): RedirectResponse
    {
        $retailer->update($this->validated($request, $retailer));

        return redirect()->route('retailers.index')->with('status', __('Saved.'));
    }

    public function destroy(Retailer $retailer): RedirectResponse
    {
        $retailer->delete();

        return redirect()->route('retailers.index')->with('status', __('Retailer :name was deactivated.', ['name' => $retailer->name]));
    }

    public function restore(Retailer $retailer): RedirectResponse
    {
        $retailer->restore();

        return redirect()->route('retailers.index')->with('status', __('Retailer :name can be used again.', ['name' => $retailer->name]));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
            'field' => ['required', Rule::in(['route_id', 'sales_rep_id', 'price_list_id', 'credit_limit'])],
            'value' => ['nullable', 'string', 'max:40'],
        ]);

        $value = $data['value'] === '' ? null : $data['value'];

        if ($data['field'] === 'credit_limit') {
            $request->validate(['value' => ['nullable', 'decimal:0,2', 'min:0']]);
        } elseif ($value !== null) {
            $table = ['route_id' => 'routes', 'sales_rep_id' => 'sales_reps', 'price_list_id' => 'price_lists'][$data['field']];
            $request->validate(['value' => ['integer', Rule::exists($table, 'id')]]);
        }

        Retailer::withTrashed()->whereIn('id', $data['ids'])->limit(100)->get()->each(
            fn (Retailer $retailer) => $retailer->update([$data['field'] => $value]),
        );

        return redirect()->route('retailers.index')->with('status', __('Saved.'));
    }

    /**
     * @return list<string>
     */
    public function columns(Request $request): array
    {
        $all = ['code', 'phone', 'route', 'rep', 'price_list', 'credit'];

        if ($request->boolean('cols_set')) {
            $chosen = array_values(array_intersect($all, (array) $request->input('cols', [])));
            $request->session()->put('cols.retailers', $chosen);

            return $chosen;
        }

        return $request->session()->get('cols.retailers', $all);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Retailer $retailer): array
    {
        return [
            'retailer' => $retailer,
            'fields' => CustomField::query()->where('entity', 'retailer')->orderBy('sort')->get(),
            'routes' => SalesRoute::withTrashed()->orderBy('name')->get(),
            'reps' => SalesRep::withTrashed()->orderBy('name')->get(),
            'priceLists' => PriceList::withTrashed()->orderBy('name')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Retailer $retailer = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('retailers', 'code')->ignore($retailer?->id)],
            'name' => ['required', 'string', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'route_id' => ['nullable', 'integer', Rule::exists('routes', 'id')],
            'sales_rep_id' => ['nullable', 'integer', Rule::exists('sales_reps', 'id')],
            'price_list_id' => ['nullable', 'integer', Rule::exists('price_lists', 'id')],
            'credit_limit' => ['nullable', 'decimal:0,2', 'min:0'],
            'custom' => ['nullable', 'array'],
            'custom.*' => ['nullable', 'string', 'max:191'],
        ]);

        $data['code'] = isset($data['code']) && $data['code'] !== '' ? $data['code'] : null;
        $allowed = CustomField::query()->where('entity', 'retailer')->pluck('key');
        $custom = [];

        foreach ($allowed as $key) {
            $value = trim((string) ($data['custom'][$key] ?? ''));

            if ($value !== '') {
                $custom[$key] = $value;
            }
        }

        $data['custom'] = $custom === [] ? null : $custom;

        return $data;
    }
}
