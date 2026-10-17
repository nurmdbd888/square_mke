<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\Payments\Methods;
use App\Domain\Payments\PartyBalance;
use App\Domain\Payments\PaymentWriter;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Retailer;
use App\Models\SalesRep;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentWriter $writer,
        private readonly PartyBalance $balances,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kind' => ['nullable', Rule::in(['receipt', 'payment', 'all'])],
            'status' => ['nullable', Rule::in(['draft', 'posted', 'void', 'all'])],
        ]);
        $kind = $filters['kind'] ?? 'all';
        $status = $filters['status'] ?? 'all';

        $payments = Payment::query()
            ->with(['retailer', 'supplier', 'expenseCategory'])
            ->when($kind !== 'all', fn ($q) => $q->where('kind', $kind))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(function ($w) use ($like) {
                    $w->where('number', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhere('cheque_number', 'like', $like)
                        ->orWhereHas('retailer', fn ($r) => $r->where('name', 'like', $like))
                        ->orWhereHas('supplier', fn ($r) => $r->where('name', 'like', $like));
                });
            })
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $view = $request->header('HX-Request') && ! $request->header('HX-Boosted')
            ? 'payments._table'
            : 'payments.index';

        return view($view, [
            'payments' => $payments,
            'filters' => $filters + ['kind' => $kind, 'status' => $status],
            'canManage' => $request->user()->can('payments.manage'),
        ]);
    }

    public function create(Request $request): View
    {
        $kind = $request->query('kind', Payment::KIND_RECEIPT);
        abort_unless(in_array($kind, [Payment::KIND_RECEIPT, Payment::KIND_PAYMENT], true), 404);

        return view('payments.form', $this->formData(new Payment([
            'kind' => $kind,
            'status' => Payment::STATUS_DRAFT,
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'amount' => '0',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $payment = $this->writer->save($data['header'], $data['allocations'], $request->user());

        return redirect()->route('payments.show', $payment)
            ->with('status', $payment->isPosted()
                ? __('Payment :number was posted.', ['number' => $payment->number])
                : __('Payment :number was saved as a draft.', ['number' => $payment->number]));
    }

    public function show(Payment $payment): View
    {
        $payment->load(['allocations.document', 'retailer', 'supplier', 'expenseCategory', 'salesRep']);

        return view('payments.show', [
            'payment' => $payment,
            'canManage' => request()->user()->can('payments.manage'),
        ]);
    }

    public function edit(Payment $payment): View
    {
        abort_if($payment->isVoid(), 404);
        $payment->load('allocations');

        return view('payments.form', $this->formData($payment));
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        abort_if($payment->isVoid(), 404);
        $data = $this->validated($request, $payment);
        $payment = $this->writer->save($data['header'], $data['allocations'], $request->user(), $payment);

        return redirect()->route('payments.show', $payment)->with('status', __('Saved.'));
    }

    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        $number = $payment->number;
        $this->writer->void($payment, $request->user());

        return redirect()->route('payments.index')
            ->with('status', __('Payment :number was voided.', ['number' => $number]));
    }

    public function post(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->isDraft(), 404);
        $this->writer->post($payment->load('allocations'), $request->user());

        return redirect()->route('payments.show', $payment)
            ->with('status', __('Payment :number was posted.', ['number' => $payment->number]));
    }

    public function clear(Request $request, Payment $payment): RedirectResponse
    {
        $data = $request->validate(['cleared_on' => ['nullable', 'date_format:Y-m-d']]);
        $this->writer->clearPdc($payment, $request->user(), $data['cleared_on'] ?? null);

        return redirect()->route('payments.show', $payment)
            ->with('status', __('Post-dated cheque marked cleared.'));
    }

    public function openInvoices(Request $request): View
    {
        $party = $request->input('party');
        $partyId = $request->input('party_id')
            ?? $request->input('retailer_id')
            ?? $request->input('supplier_id');

        $data = validator([
            'party' => $party,
            'party_id' => $partyId,
        ], [
            'party' => ['required', Rule::in(['retailer', 'supplier'])],
            'party_id' => ['required', 'integer'],
        ])->validate();

        $invoices = $this->balances->openInvoices($data['party'], (int) $data['party_id']);

        return view('payments._open_invoices', ['invoices' => $invoices]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Payment $payment): array
    {
        return [
            'payment' => $payment,
            'retailers' => Retailer::query()->orderBy('name')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
            'reps' => SalesRep::query()->orderBy('name')->get(['id', 'name']),
            'categories' => ExpenseCategory::query()->orderBy('sort')->get(),
            'methods' => Methods::labels(),
            'openInvoices' => $payment->retailer_id
                ? $this->balances->openInvoices('retailer', $payment->retailer_id)
                : ($payment->supplier_id
                    ? $this->balances->openInvoices('supplier', $payment->supplier_id)
                    : collect()),
        ];
    }

    /**
     * @return array{header: array<string, mixed>, allocations: list<array{document_id: int, amount: string}>}
     */
    private function validated(Request $request, ?Payment $existing = null): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['receipt', 'payment'])],
            'number' => ['required', 'string', 'max:80'],
            'retailer_id' => ['nullable', 'integer', 'exists:retailers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'amount' => ['required', 'decimal:0,2'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:120'],
            'cheque_number' => ['nullable', 'string', 'max:80'],
            'sales_rep_id' => ['nullable', 'integer', 'exists:sales_reps,id'],
            'expense_category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'is_advance' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', Rule::in(['save', 'post'])],
            'allocations' => ['nullable', 'array'],
            'allocations.*.document_id' => ['required_with:allocations', 'integer', 'exists:documents,id'],
            'allocations.*.amount' => ['required_with:allocations', 'decimal:0,2'],
        ]);

        $allocations = [];
        foreach ($data['allocations'] ?? [] as $row) {
            if (! isset($row['document_id'])) {
                continue;
            }
            $allocations[] = [
                'document_id' => (int) $row['document_id'],
                'amount' => (string) ($row['amount'] ?? '0'),
            ];
        }

        return [
            'header' => [
                'kind' => $data['kind'],
                'number' => $data['number'],
                'retailer_id' => $data['retailer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'method' => $data['method'],
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'due_date' => $data['due_date'] ?? null,
                'reference' => $data['reference'] ?? null,
                'cheque_number' => $data['cheque_number'] ?? null,
                'sales_rep_id' => $data['sales_rep_id'] ?? null,
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'is_advance' => $request->boolean('is_advance'),
                'notes' => $data['notes'] ?? null,
                'action' => $data['action'] ?? 'save',
            ],
            'allocations' => $allocations,
        ];
    }
}
