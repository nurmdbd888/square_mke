<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\ProductType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductTypeController extends Controller
{
    public function index(): View
    {
        return view('master.product-types.index', [
            'brands' => Brand::query()->with(['productTypes' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:80'],
        ]);
        $this->validateUniqueName($request, (int) $data['brand_id'], $data['name']);
        ProductType::query()->create($data);

        return redirect()->route('masters.product-types.index')->with('status', __('Product type was saved.'));
    }

    public function update(Request $request, ProductType $productType): RedirectResponse
    {
        $data = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:80'],
        ]);
        $this->validateUniqueName($request, (int) $data['brand_id'], $data['name'], $productType);
        $productType->update($data);

        return redirect()->route('masters.product-types.index')->with('status', __('Product type was saved.'));
    }

    private function validateUniqueName(Request $request, int $brandId, string $name, ?ProductType $ignore = null): void
    {
        $request->validate([
            'name' => [
                Rule::unique('product_types', 'name')->where('brand_id', $brandId)->ignore($ignore?->id),
            ],
        ]);
    }
}
