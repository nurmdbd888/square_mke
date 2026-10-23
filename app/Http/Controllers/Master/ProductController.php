<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
        ]);
        $status = $filters['status'] ?? 'active';
        $columns = $this->columns($request);

        $products = Product::query()
            ->when($status === 'inactive', fn ($q) => $q->onlyTrashed())
            ->when($status === 'all', fn ($q) => $q->withTrashed())
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn ($w) => $w->where('name_en', 'like', $like)->orWhere('name_bn', 'like', $like)->orWhere('code', 'like', $like)->orWhere('brand', 'like', $like));
            })
            ->when($filters['brand'] ?? null, fn ($q, $brand) => $q->where('brand', $brand))
            ->orderBy('name_en')
            ->paginate(50)
            ->withQueryString();

        $view = $request->header('HX-Request') && ! $request->header('HX-Boosted') ? 'master.products._table' : 'master.products.index';

        return view($view, [
            'products' => $products,
            'filters' => $filters + ['status' => $status],
            'columns' => $columns,
            'brands' => Product::query()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
        ]);
    }

    public function create(): View
    {
        return view('master.products.form', [
            'product' => new Product(['unit' => 'Pcs', 'vat_pct' => '15', 'sd_pct' => '0']),
            'fields' => CustomField::query()->where('entity', 'product')->orderBy('sort')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $product = Product::query()->create($this->validated($request));
        $this->syncAliases($product, (string) $request->input('aliases', ''));

        if ($request->expectsJson()) {
            $product->load('aliases');
            $searchParts = array_filter([
                $product->code,
                $product->name_en,
                $product->name_bn,
                ...$product->aliases->pluck('name')->all(),
            ]);

            return response()->json([
                'id' => $product->id,
                'code' => (string) ($product->code ?? ''),
                'brand' => (string) ($product->brand ?? ''),
                'pack_size' => (string) ($product->pack_size ?? ''),
                'name' => (string) $product->name_en,
                'name_bn' => (string) ($product->name_bn ?? ''),
                'searchText' => mb_strtolower(implode(' ', $searchParts)),
                'buy' => (string) ($product->buy_price ?? ''),
                'sell' => (string) ($product->sell_price ?? ''),
                'sd' => (string) ($product->sd_pct ?? '0'),
                'vat' => (string) ($product->vat_pct ?? '15'),
            ], 201);
        }

        return redirect()->route('products.index')->with('status', __('Product :name was saved.', ['name' => $product->name_en]));
    }

    public function edit(Product $product): View
    {
        $product->load('aliases');

        return view('master.products.form', [
            'product' => $product,
            'fields' => CustomField::query()->where('entity', 'product')->orderBy('sort')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $product->update($this->validated($request, $product));
        $this->syncAliases($product, (string) $request->input('aliases', ''));

        return redirect()->route('products.index')->with('status', __('Saved.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('products.index')->with('status', __('Product :name was deactivated.', ['name' => $product->name_en]));
    }

    public function restore(Product $product): RedirectResponse
    {
        $product->restore();

        return redirect()->route('products.index')->with('status', __('Product :name can be used again.', ['name' => $product->name_en]));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
            'field' => ['required', Rule::in(['brand', 'sd_pct', 'vat_pct', 'reorder_level', 'tracks_expiry'])],
            'value' => ['nullable', 'string', 'max:80'],
        ]);

        $value = match ($data['field']) {
            'tracks_expiry' => filter_var($data['value'], FILTER_VALIDATE_BOOLEAN),
            'sd_pct', 'vat_pct', 'reorder_level' => $data['value'] === null || $data['value'] === '' ? null : $data['value'],
            default => $data['value'] !== '' ? $data['value'] : null,
        };

        if (in_array($data['field'], ['sd_pct', 'vat_pct'], true)) {
            $request->validate(['value' => ['required', 'decimal:0,2', 'min:0', 'max:100']]);
        } elseif ($data['field'] === 'reorder_level') {
            $request->validate(['value' => ['nullable', 'decimal:0,2', 'min:0']]);
            $value = $value === '' || $value === null ? null : $value;
        }

        Product::withTrashed()->whereIn('id', $data['ids'])->limit(100)->get()->each(fn (Product $product) => $product->update([$data['field'] => $value]));

        return redirect()->route('products.index')->with('status', __('Saved.'));
    }

    public function columns(Request $request): array
    {
        $all = ['code', 'brand', 'pack', 'buy', 'sell', 'sd', 'vat'];

        if ($request->boolean('cols_set')) {
            $chosen = array_values(array_intersect($all, (array) $request->input('cols', [])));
            $request->session()->put('cols.products', $chosen);

            return $chosen;
        }

        return $request->session()->get('cols.products', $all);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', Rule::unique('products', 'code')->ignore($product?->id)],
            'name_en' => ['required', 'string', 'max:191'],
            'name_bn' => ['nullable', 'string', 'max:191'],
            'brand' => ['nullable', 'string', 'max:80'],
            'pack_size' => ['nullable', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:20'],
            'units_per_carton' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'buy_price' => ['nullable', 'decimal:0,4'],
            'buy_price_exact' => ['nullable', 'decimal:0,4'],
            'sell_price' => ['nullable', 'decimal:0,4'],
            'reorder_level' => ['nullable', 'decimal:0,2'],
            'sd_pct' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'vat_pct' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'tracks_expiry' => ['boolean'],
            'custom' => ['nullable', 'array'],
            'aliases' => ['nullable', 'string', 'max:5000'],
        ]);

        $data['tracks_expiry'] = $request->boolean('tracks_expiry');
        $data['code'] = isset($data['code']) && $data['code'] !== '' ? $data['code'] : null;
        $data['custom'] = $this->customValues($data['custom'] ?? []);
        unset($data['aliases']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @return array<string, string>|null
     */
    private function customValues(array $submitted): ?array
    {
        $allowed = CustomField::query()->where('entity', 'product')->pluck('key');
        $custom = [];

        foreach ($allowed as $key) {
            $value = trim((string) ($submitted[$key] ?? ''));

            if ($value !== '') {
                $custom[$key] = $value;
            }
        }

        return $custom === [] ? null : $custom;
    }

    private function syncAliases(Product $product, string $raw): void
    {
        $names = collect(preg_split('/\r\n|\r|\n/', $raw) ?: [])
            ->map(fn ($name) => trim($name))
            ->push((string) $product->name_bn)
            ->filter()
            ->unique()
            ->values();

        $product->aliases()->whereNotIn('name', $names)->get()->each->delete();

        foreach ($names as $name) {
            $product->aliases()->firstOrCreate(['name' => $name]);
        }
    }
}
