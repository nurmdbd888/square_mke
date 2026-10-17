<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\Payments\Ageing;
use App\Domain\Payments\PartyBalance;
use App\Http\Controllers\Controller;
use App\Models\Retailer;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LedgerController extends Controller
{
    public function __construct(
        private readonly PartyBalance $balances,
        private readonly Ageing $ageing,
    ) {}

    public function receivables(Request $request): View
    {
        $asOf = $request->input('as_of', now()->toDateString());
        $retailers = Retailer::query()->orderBy('name')->get();
        $rows = $retailers->map(function (Retailer $retailer) use ($asOf) {
            $balance = $this->balances->retailer($retailer->id, $asOf);
            $ageing = $this->ageing->forRetailer($retailer->id, $asOf);

            return [
                'party' => $retailer,
                'balance' => $balance,
                'ageing' => $ageing,
            ];
        })->filter(fn ($row) => $row['balance']['balance'] !== '0.00' || $row['balance']['advances'] !== '0.00');

        return view('accounts.receivables', [
            'rows' => $rows->values(),
            'asOf' => $asOf,
        ]);
    }

    public function payables(Request $request): View
    {
        $asOf = $request->input('as_of', now()->toDateString());
        $suppliers = Supplier::query()->orderBy('name')->get();
        $rows = $suppliers->map(function (Supplier $supplier) use ($asOf) {
            $balance = $this->balances->supplier($supplier->id, $asOf);
            $ageing = $this->ageing->forSupplier($supplier->id, $asOf);

            return [
                'party' => $supplier,
                'balance' => $balance,
                'ageing' => $ageing,
            ];
        })->filter(fn ($row) => $row['balance']['balance'] !== '0.00' || $row['balance']['advances'] !== '0.00');

        return view('accounts.payables', [
            'rows' => $rows->values(),
            'asOf' => $asOf,
        ]);
    }

    public function retailerStatement(Request $request, Retailer $retailer): View
    {
        $asOf = $request->input('as_of', now()->toDateString());

        return view('accounts.statement', [
            'party' => $retailer,
            'partyType' => 'retailer',
            'balance' => $this->balances->retailer($retailer->id, $asOf),
            'ageing' => $this->ageing->forRetailer($retailer->id, $asOf),
            'openInvoices' => $this->balances->openInvoices('retailer', $retailer->id, $asOf),
            'asOf' => $asOf,
            'credit' => $retailer->credit_limit,
        ]);
    }

    public function ageing(Request $request): View
    {
        $data = $request->validate([
            'party' => ['required', Rule::in(['retailer', 'supplier'])],
            'party_id' => ['required', 'integer'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $asOf = $data['as_of'] ?? now()->toDateString();

        $report = $data['party'] === 'retailer'
            ? $this->ageing->forRetailer((int) $data['party_id'], $asOf)
            : $this->ageing->forSupplier((int) $data['party_id'], $asOf);

        $party = $data['party'] === 'retailer'
            ? Retailer::query()->findOrFail($data['party_id'])
            : Supplier::query()->findOrFail($data['party_id']);

        return view('accounts.ageing', [
            'party' => $party,
            'partyType' => $data['party'],
            'report' => $report,
            'asOf' => $asOf,
        ]);
    }
}
