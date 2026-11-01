@extends('layouts.app')

@section('title', __('Create Purchase Order'))

@section('content')
    <x-back-link :href="route('purchases.orders.index')">{{ __('Back to Purchase Orders') }}</x-back-link>
    <h1 class="mt-2 text-2xl font-semibold text-slate-900">{{ __('Create Purchase Order') }}</h1>
    <p class="mt-1 text-sm text-slate-600">{{ __('Submitting the order marks it Pending. Record supplier payments here; receive the goods separately when they arrive.') }}</p>

    <form method="POST" action="{{ route('purchases.orders.store') }}" class="mt-6 space-y-6"
          x-data="{
              catalog: {{ \Illuminate\Support\Js::from($products->map(fn ($product) => ['id' => $product->id, 'code' => $product->code, 'name' => $product->name_en, 'unit' => $product->unit, 'price' => $product->buy_price ?? '0.0000'])->values()) }},
              items: {{ \Illuminate\Support\Js::from(old('items', [['product_id' => '', 'qty' => '1', 'unit_price' => '']])) }},
              product(line) { return this.catalog.find((item) => String(item.id) === String(line.product_id)); },
              selectProduct(line) { const product = this.product(line); if (product) line.unit_price = product.price; },
              total() { return this.items.reduce((sum, line) => sum + Number((Number(line.qty || 0) * Number(line.unit_price || 0)).toFixed(2)), 0).toFixed(2); },
              addLine() { this.items.push({ product_id: '', qty: '1', unit_price: '' }); },
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
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="font-semibold text-slate-900">{{ __('Products to order') }}</h2>
                <button type="button" class="btn-secondary" @click="addLine()">{{ __('Add line') }}</button>
            </div>
            <div class="table-wrap">
                <table class="data-table min-w-[42rem]">
                    <thead><tr><th>{{ __('Product') }}</th><th class="w-32">{{ __('Quantity') }}</th><th class="w-40">{{ __('Unit price') }}</th><th class="text-right">{{ __('Line total') }}</th><th></th></tr></thead>
                    <tbody>
                        <template x-for="(line, index) in items" :key="index">
                            <tr>
                                <td>
                                    <select :name="`items[${index}][product_id]`" x-model="line.product_id" @change="selectProduct(line)" class="input min-h-10" required>
                                        <option value="">{{ __('Choose product') }}</option>
                                        <template x-for="product in catalog" :key="product.id">
                                            <option :value="product.id" x-text="`${product.code ? product.code + ' · ' : ''}${product.name}`"></option>
                                        </template>
                                    </select>
                                </td>
                                <td><input type="number" :name="`items[${index}][qty]`" x-model="line.qty" min="0.001" step="0.001" class="input min-h-10 text-right" required></td>
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
