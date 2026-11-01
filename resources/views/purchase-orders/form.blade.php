@extends('layouts.app')

@section('title', __('Create Purchase Order'))

@section('content')
    <x-back-link :href="route('purchases.orders.index')">{{ __('Back to Purchase Orders') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Create Purchase Order') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Submitting the order marks it Pending. Record supplier payments here; receive the goods separately when they arrive.') }}</p>

    <form method="POST" action="{{ route('purchases.orders.store') }}" class="mt-6 space-y-6"
          x-data="{
              catalog: {{ \Illuminate\Support\Js::from($products->map(fn ($product) => ['id' => $product->id, 'code' => $product->code, 'name' => $product->name_en, 'name_bn' => $product->name_bn, 'brand' => $product->brand, 'brand_id' => $product->brand_id, 'product_type_id' => $product->product_type_id, 'pack_size' => $product->pack_size, 'image_url' => $product->image_path ? asset('storage/'.$product->image_path) : null, 'unit' => $product->unit, 'price' => $product->buy_price ?? '0.0000'])->values()) }},
              brands: {{ \Illuminate\Support\Js::from($brands->map(fn ($brand) => ['id' => $brand->id, 'name' => $brand->name, 'image_url' => $brand->image_path ? asset('storage/'.$brand->image_path) : null])->values()) }},
              productTypes: {{ \Illuminate\Support\Js::from($productTypes) }},
              brandFilter: '',
              typeFilter: '',
              productSearch: '',
              items: {{ \Illuminate\Support\Js::from(old('items', [['product_id' => '', 'qty' => '1', 'unit_price' => '']])) }},
              product(line) { return this.catalog.find((item) => String(item.id) === String(line.product_id)); },
              get selectedBrand() { return this.brands.find((brand) => String(brand.id) === String(this.brandFilter)); },
              get typesForSelectedBrand() { return this.productTypes.filter((type) => String(type.brand_id) === String(this.brandFilter)); },
              get filteredProducts() {
                  const query = this.productSearch.trim().toLocaleLowerCase();
                  return this.catalog.filter((product) =>
                      (!this.brandFilter || String(product.brand_id) === String(this.brandFilter)) &&
                      (!this.typeFilter || String(product.product_type_id) === String(this.typeFilter)) &&
                      (!query || `${product.code || ''} ${product.name || ''} ${product.name_bn || ''}`.toLocaleLowerCase().includes(query))
                  ).slice(0, 40);
              },
              addProduct(product) {
                  const existing = this.items.find((line) => String(line.product_id) === String(product.id));
                  if (existing) { existing.qty = String(Number(existing.qty || 0) + 1); return; }
                  this.items.push({ product_id: String(product.id), qty: '1', unit_price: product.price });
              },
              productName(line) { const item = this.product(line); return item ? `${item.code ? item.code + ' · ' : ''}${item.name}` : '—'; },
              total() { return this.items.reduce((sum, line) => sum + Number((Number(line.qty || 0) * Number(line.unit_price || 0)).toFixed(2)), 0).toFixed(2); },
              removeLine(index) { if (this.items.length > 1) this.items.splice(index, 1); }
          }">
        @csrf
        <section class="card grid gap-4 sm:grid-cols-2">
            <label class="block sm:col-span-2">
                <span class="label">{{ __('Supplier') }}</span>
                <select name="supplier_id" class="input min-h-11" required>
                    <option value="">{{ __('Choose supplier') }}</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->code }} · {{ $supplier->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="label">{{ __('Order date') }}</span>
                <x-date-input name="order_date" :value="old('order_date', $orderDate)" required />
            </label>
            <label class="block">
                <span class="label">{{ __('Expected arrival') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <x-date-input name="expected_delivery_date" :value="old('expected_delivery_date')" />
            </label>
            <label class="block sm:col-span-2">
                <span class="label">{{ __('Notes') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                <textarea name="notes" rows="2" maxlength="2000" class="input">{{ old('notes') }}</textarea>
            </label>
        </section>

        <section class="card">
            <div class="mb-3">
                <h2 class="font-semibold text-slate-900">{{ __('Products to order') }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('Choose Brand, then Product Type, then search and add products to the order.') }}</p>
            </div>
            <div class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-3">
                <label>
                    <span class="label">{{ __('Brand') }}</span>
                    <select class="input" x-model="brandFilter" @change="typeFilter = ''">
                        <option value="">{{ __('All brands') }}</option>
                        <template x-for="brand in brands" :key="brand.id">
                            <option :value="String(brand.id)" x-text="brand.name"></option>
                        </template>
                    </select>
                    <template x-if="selectedBrand?.image_url">
                        <img :src="selectedBrand.image_url" :alt="selectedBrand.name" class="mt-2 h-14 w-14 rounded object-cover">
                    </template>
                </label>
                <label>
                    <span class="label">{{ __('Product type') }}</span>
                    <select class="input" x-model="typeFilter" :disabled="!brandFilter">
                        <option value="">{{ __('All product types') }}</option>
                        <template x-for="type in typesForSelectedBrand" :key="type.id">
                            <option :value="String(type.id)" x-text="type.name"></option>
                        </template>
                    </select>
                </label>
                <label>
                    <span class="label">{{ __('Search product') }}</span>
                    <input type="search" class="input" x-model="productSearch" placeholder="{{ __('Code or product name') }}" autocomplete="off">
                </label>
            </div>
            <div class="mb-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <template x-for="product in filteredProducts" :key="product.id">
                    <button type="button" class="flex items-center gap-3 rounded-lg border border-slate-200 p-2 text-left hover:bg-slate-50" @click="addProduct(product)">
                        <img x-show="product.image_url" :src="product.image_url" :alt="product.name" class="h-12 w-12 rounded object-cover">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-slate-900" x-text="product.name"></span>
                            <span class="block truncate text-xs text-slate-500" x-text="`${product.code || '—'} · ${product.pack_size || product.unit}`"></span>
                        </span>
                        <span class="text-xs font-semibold text-brand-700">{{ __('Add') }}</span>
                    </button>
                </template>
            </div>
            <div class="table-wrap">
                <table class="data-table min-w-[42rem]">
                    <thead><tr><th>{{ __('Product') }}</th><th class="w-32">{{ __('Quantity') }}</th><th class="w-40">{{ __('Unit price') }}</th><th class="text-right">{{ __('Line total') }}</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(line, index) in items" :key="index">
                            <tr>
                                <td>
                                    <input type="hidden" :name="`items[${index}][product_id]`" x-model="line.product_id">
                                    <span class="block font-medium" x-text="productName(line)"></span>
                                    <span class="block text-xs text-slate-500" x-text="product(line)?.pack_size || product(line)?.unit || ''"></span>
                                </td>
                                <td><input type="number" :name="`items[${index}][qty]`" x-model="line.qty" min="1" step="1" class="input min-h-10 text-right" required></td>
                                <td><input type="number" :name="`items[${index}][unit_price]`" x-model="line.unit_price" min="0" step="0.0001" class="input min-h-10 text-right font-mono" required></td>
                                <td class="text-right font-mono" x-text="(Number(line.qty || 0) * Number(line.unit_price || 0)).toFixed(2)"></td>
                                <td class="text-right"><button type="button" class="btn-secondary px-2 py-1" @click="removeLine(index)" :disabled="items.length < 2">{{ __('Remove') }}</button></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot><tr><th colspan="3" class="text-right">{{ __('Estimated order total') }}</th><th class="text-right font-mono" x-text="total()"></th><th></th></tr></tfoot>
                </table>
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-2">
            <a href="{{ route('purchases.orders.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-primary">{{ __('Create Purchase Order') }}</button>
        </div>
    </form>
@endsection
