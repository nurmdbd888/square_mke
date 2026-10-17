<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\OpeningBalance;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OpeningBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'kind' => ['nullable', Rule::in(OpeningBalance::KINDS)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $rows = OpeningBalance::query()
            ->with(['product', 'retailer', 'supplier'])
            ->when($filters['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->orderByDesc('as_of')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $view = $request->header('HX-Request') && ! $request->header('HX-Boosted') ? 'master.opening-balances._table' : 'master.opening-balances.index';

        return view($view, [
            'rows' => $rows,
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('master.opening-balances.form', ['row' => new OpeningBalance(['kind' => 'stock', 'as_of' => now()->toDateString()])]);
    }

    public function store(Request $request): RedirectResponse
    {
        OpeningBalance::query()->create($this->validated($request));

        return redirect()->route('masters.opening-balances.index')->with('status', __('Opening balance saved.'));
    }

    public function edit(OpeningBalance $openingBalance): View
    {
        $openingBalance->load(['product', 'retailer', 'supplier']);

        return view('master.opening-balances.form', ['row' => $openingBalance]);
    }

    public function update(Request $request, OpeningBalance $openingBalance): RedirectResponse
    {
        $openingBalance->update($this->validated($request));

        return redirect()->route('masters.opening-balances.index')->with('status', __('Saved.'));
    }

    public function destroy(OpeningBalance $openingBalance): RedirectResponse
    {
        $openingBalance->delete();

        return redirect()->route('masters.opening-balances.index')->with('status', __('Opening balance removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(OpeningBalance::KINDS)],
            'as_of' => ['required', 'date'],
            'amount' => ['required', 'decimal:0,2'],
            'qty' => ['nullable', 'required_if:kind,stock', 'decimal:0,2'],
            'product' => ['nullable', 'required_if:kind,stock', 'string', 'max:191'],
            'retailer' => ['nullable', 'required_if:kind,receivable', 'string', 'max:191'],
            'supplier' => ['nullable', 'required_if:kind,payable', 'string', 'max:191'],
            'note' => ['nullable', 'string', 'max:191'],
        ]);

        $product = $data['kind'] === 'stock' ? $this->findParty(Product::class, $data['product'], 'product', 'name_en') : null;
        $retailer = $data['kind'] === 'receivable' ? $this->findParty(Retailer::class, $data['retailer'], 'retailer', 'name') : null;
        $supplier = $data['kind'] === 'payable' ? $this->findParty(Supplier::class, $data['supplier'], 'supplier', 'name') : null;

        return [
            'kind' => $data['kind'],
            'as_of' => $data['as_of'],
            'amount' => $data['amount'],
            'qty' => $data['kind'] === 'stock' ? $data['qty'] : null,
            'product_id' => $product?->getKey(),
            'retailer_id' => $retailer?->getKey(),
            'supplier_id' => $supplier?->getKey(),
            'note' => isset($data['note']) && $data['note'] !== '' ? $data['note'] : null,
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function findParty(string $model, string $input, string $field, string $nameColumn): Model
    {
        $input = trim($input);
        $matches = $model::query()
            ->where(fn ($q) => $q->where('code', $input)->orWhere($nameColumn, $input))
            ->limit(2)
            ->get();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                $field => __('Type one exact code or name.'),
            ]);
        }

        return $matches->first();
    }
}
