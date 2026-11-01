<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\CustomField;
use App\Models\Product;
use App\Models\ProductType;
use App\Support\Images\CatalogImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private readonly CatalogImageStore $images) {}

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
            'brands' => Brand::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function create(): View
    {
        return view('master.products.form', [
            'product' => new Product(['unit' => 'Pcs', 'vat_pct' => '15', 'sd_pct' => '0']),
            'fields' => CustomField::query()->where('entity', 'product')->orderBy('sort')->get(),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::query()->with('brand:id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validated($request);
        $image = $data['image'] ?? null;
        unset($data['image']);
        $product = Product::query()->create($data);
        if ($image instanceof UploadedFile) {
            $product->image_path = $this->images->store($image, 'catalog/products');
            $product->save();
        }
        $this->syncAliases($product, (string) $request->input('aliases', ''));

        if ($request->expectsJson()) {
            $product->load(['aliases', 'brandMaster']);
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
                'brand_id' => $product->brand_id,
                'brandId' => (string) ($product->brand_id ?? ''),
                'pack_size' => (string) ($product->pack_size ?? ''),
                'product_type_id' => $product->product_type_id,
                'product_type' => (string) ($product->productType?->name ?? ''),
                'typeId' => (string) ($product->product_type_id ?? ''),
                'type' => (string) ($product->productType?->name ?? ''),
                'image_url' => $product->image_path ? asset('storage/'.$product->image_path) : null,
                'imageUrl' => $product->image_path ? asset('storage/'.$product->image_path) : null,
                'name' => (string) $product->name_en,
                'name_bn' => (string) ($product->name_bn ?? ''),
                'searchText' => mb_strtolower(implode(' ', $searchParts)),
                'buy' => (string) ($product->buy_price ?? ''),
                'sell' => (string) ($product->sell_price ?? ''),
                'sd' => (string) ($product->sd_pct ?? '0'),
                'vat' => (string) ($product->vat_pct ?? '15'),
                'purchase_discount_pct' => (string) ($product->purchase_discount_pct ?? $product->brandMaster?->purchase_discount_pct ?? '0'),
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
            'brands' => Brand::query()->withTrashed()->orderBy('name')->get(['id', 'name']),
            'productTypes' => ProductType::query()->withTrashed()->with(['brand' => fn ($query) => $query->withTrashed()->select('id', 'name')])->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);
        $image = $data['image'] ?? null;
        unset($data['image']);
        $product->update($data);
        if ($image instanceof UploadedFile) {
            $oldImage = $product->image_path;
            $product->image_path = $this->images->store($image, 'catalog/products');
            $product->save();
            $this->images->delete($oldImage);
        }
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
            'brand' => ['nullable', 'string', 'max:80', Rule::exists('brands', 'name')],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'product_type_id' => ['nullable', 'integer', 'exists:product_types,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'pack_size' => ['nullable', 'string', 'max:80'],
            'unit' => ['required', 'string', 'max:20'],
            'units_per_carton' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'buy_price' => ['nullable', 'decimal:0,4'],
            'purchase_discount_pct' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'buy_price_exact' => ['nullable', 'decimal:0,4'],
            'sell_price' => ['nullable', 'decimal:0,4'],
            'reorder_level' => ['nullable', 'decimal:0,2'],
            'sd_pct' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'vat_pct' => ['required', 'decimal:0,2', 'min:0', 'max:100'],
            'tracks_expiry' => ['boolean'],
            'custom' => ['nullable', 'array'],
            'aliases' => ['nullable', 'string', 'max:5000'],
        ]);

        $brandId = $data['brand_id'] ?? null;
        if ($brandId === null && ! empty($data['brand'])) {
            $brandId = Brand::query()->where('name', $data['brand'])->value('id');
        }
        if ($brandId !== null && empty($data['product_type_id'])) {
            throw ValidationException::withMessages([
                'product_type_id' => __('Choose a product type under the selected brand.'),
            ]);
        }
        if (! empty($data['product_type_id'])) {
            $type = ProductType::query()->withTrashed()->findOrFail($data['product_type_id']);
            if ($brandId === null || (int) $type->brand_id !== (int) $brandId) {
                throw ValidationException::withMessages([
                    'product_type_id' => __('Choose a product type under the selected brand.'),
                ]);
            }
        }
        if ($brandId !== null) {
            $data['brand'] = Brand::query()->withTrashed()->findOrFail($brandId)->name;
        } elseif (($data['brand'] ?? '') === '') {
            $data['brand'] = null;
        }
        $data['brand_id'] = $brandId;

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
