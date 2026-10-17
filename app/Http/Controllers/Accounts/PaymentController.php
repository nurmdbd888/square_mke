<?php

namespace App\Http\Controllers\Accounts;

use App\Domain\Payments\Methods;
use App\Domain\Payments\PartyBalance;
use App\Domain\Payments\PaymentWriter;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentAttachment;
use App\Models\Retailer;
use App\Models\SalesRep;
use App\Models\Supplier;
use App\Support\Access\PaymentAccess;
use App\Support\Bulk\BulkActionResult;
use App\Support\Bulk\BulkSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

        $user = $request->user();

        return view($view, [
            'payments' => $payments,
            'filters' => $filters + ['kind' => $kind, 'status' => $status],
            'canManage' => PaymentAccess::canManage($user),
            'canCollect' => PaymentAccess::canCollectReceipts($user),
        ]);
    }

    public function create(Request $request): View
    {
        $kind = $request->query('kind', Payment::KIND_RECEIPT);
        abort_unless(in_array($kind, [Payment::KIND_RECEIPT, Payment::KIND_PAYMENT], true), 404);
        if ($kind === Payment::KIND_PAYMENT) {
            abort_unless(PaymentAccess::canManage($request->user()), 403);
        }

        $payment = new Payment([
            'kind' => $kind,
            'status' => Payment::STATUS_DRAFT,
            'payment_date' => now()->toDateString(),
            'method' => 'cash',
            'amount' => '0',
        ]);
        if ($kind === Payment::KIND_RECEIPT && $request->filled('retailer_id')) {
            $payment->retailer_id = (int) $request->query('retailer_id');
        }

        return view('payments.form', $this->formData($payment));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->assertCanWriteHeader($request, $data['header']);
        $payment = $this->writer->save($data['header'], $data['allocations'], $request->user());
        $this->storeAttachments($request, $payment);

        $message = $payment->isPosted()
            ? __('Payment :number was posted.', ['number' => $payment->number])
            : __('Payment :number was saved as a draft.', ['number' => $payment->number]);

        return redirect()->route('payments.show', $payment)
            ->with('status', $message)
            ->with('print_receipt', $payment->isPosted() && $payment->isReceipt())
            ->with('print_receipt_id', $payment->isPosted() && $payment->isReceipt() ? $payment->id : null);
    }

    public function print(Request $request, Payment $payment): View
    {
        abort_unless($payment->isPosted(), 404);
        $payment->load(['allocations.document', 'retailer', 'supplier']);

        return view('payments.print', ['payment' => $payment]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['allocations.document', 'retailer', 'supplier', 'expenseCategory', 'salesRep', 'attachments']);
        $user = request()->user();

        return view('payments.show', [
            'payment' => $payment,
            'canManage' => PaymentAccess::canManage($user),
            'canWrite' => $payment->isReceipt()
                ? PaymentAccess::canWriteReceipt($user, $payment)
                : PaymentAccess::canWritePayment($user, $payment),
        ]);
    }

    public function edit(Payment $payment): View
    {
        abort_if($payment->isVoid(), 404);
        abort_unless(
            $payment->isReceipt()
                ? PaymentAccess::canWriteReceipt(request()->user(), $payment)
                : PaymentAccess::canWritePayment(request()->user(), $payment),
            403
        );
        $payment->load(['allocations', 'attachments']);

        return view('payments.form', $this->formData($payment));
    }

    public function update(Request $request, Payment $payment): RedirectResponse
    {
        abort_if($payment->isVoid(), 404);
        $data = $this->validated($request, $payment);
        $this->assertCanWriteHeader($request, $data['header'], $payment);
        $payment = $this->writer->save($data['header'], $data['allocations'], $request->user(), $payment);
        $this->storeAttachments($request, $payment);

        return redirect()->route('payments.show', $payment)->with('status', __('Saved.'));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(PaymentAccess::canCollectReceipts($user), 403);

        $action = $request->validate([
            'action' => ['required', Rule::in(['post', 'void'])],
        ])['action'];
        $ids = BulkSelection::ids($request);
        $result = new BulkActionResult;

        Payment::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->each(function (Payment $payment) use ($action, $user, $result) {
                try {
                    if ($action === 'post') {
                        if (! $payment->isDraft()) {
                            $result->skipped++;

                            return;
                        }
                        $allowed = $payment->isReceipt()
                            ? PaymentAccess::canWriteReceipt($user, $payment)
                            : PaymentAccess::canWritePayment($user, $payment);
                        if (! $allowed) {
                            $result->skipped++;

                            return;
                        }
                        $this->writer->post($payment->load('allocations'), $user);
                        $result->applied++;

                        return;
                    }

                    if (! PaymentAccess::canManage($user)) {
                        $result->skipped++;

                        return;
                    }
                    if ($payment->isVoid()) {
                        $result->skipped++;

                        return;
                    }
                    $this->writer->void($payment, $user);
                    $result->applied++;
                } catch (\Throwable $e) {
                    $result->skipped++;
                    $result->errors[] = $payment->number.': '.$e->getMessage();
                }
            });

        return redirect()->route('payments.index')->with('status', $result->message());
    }

    public function destroy(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless(PaymentAccess::canManage($request->user()), 403);
        $number = $payment->number;
        $this->writer->void($payment, $request->user());

        return redirect()->route('payments.index')
            ->with('status', __('Payment :number was voided.', ['number' => $number]));
    }

    public function post(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->isDraft(), 404);
        abort_unless(
            $payment->isReceipt()
                ? PaymentAccess::canWriteReceipt($request->user(), $payment)
                : PaymentAccess::canWritePayment($request->user(), $payment),
            403
        );
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

        $partyId = (int) $data['party_id'];
        $invoices = $this->balances->openInvoices($data['party'], $partyId);
        $partyBalance = $data['party'] === 'retailer'
            ? $this->balances->retailer($partyId)
            : $this->balances->supplier($partyId);

        return view('payments._open_invoices', [
            'invoices' => $invoices,
            'partyBalance' => $partyBalance,
            'showInvoicePicker' => $data['party'] === 'retailer',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Payment $payment): array
    {
        $user = request()->user();
        $partyBalance = null;
        $openInvoices = collect();
        if ($payment->retailer_id) {
            $openInvoices = $this->balances->openInvoices('retailer', $payment->retailer_id);
            $partyBalance = $this->balances->retailer($payment->retailer_id);
        } elseif ($payment->supplier_id) {
            $openInvoices = $this->balances->openInvoices('supplier', $payment->supplier_id);
            $partyBalance = $this->balances->supplier($payment->supplier_id);
        }

        $existingAllocations = $payment->relationLoaded('allocations')
            ? $payment->allocations->keyBy('document_id')
            : collect();

        return [
            'payment' => $payment,
            'retailers' => Retailer::query()->orderBy('name')->get(['id', 'code', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
            'reps' => SalesRep::query()->orderBy('name')->get(['id', 'name']),
            'categories' => ExpenseCategory::query()->orderBy('sort')->get(),
            'methods' => Methods::labels(),
            'openInvoices' => $openInvoices,
            'partyBalance' => $partyBalance,
            'existingAllocations' => $existingAllocations,
            'canManage' => PaymentAccess::canManage($user),
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
            'slip_photos' => ['nullable', 'array', 'max:5'],
            'slip_photos.*' => ['image', 'max:5120'],
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

    /**
     * @param  array<string, mixed>  $header
     */
    private function assertCanWriteHeader(Request $request, array $header, ?Payment $existing = null): void
    {
        $user = $request->user();
        $kind = (string) ($header['kind'] ?? $existing?->kind ?? Payment::KIND_RECEIPT);

        if ($kind === Payment::KIND_PAYMENT) {
            abort_unless(PaymentAccess::canWritePayment($user, $existing), 403);

            return;
        }

        abort_unless(PaymentAccess::canWriteReceipt($user, $existing), 403);
    }

    private function storeAttachments(Request $request, Payment $payment): void
    {
        /** @var list<UploadedFile>|null $files */
        $files = $request->file('slip_photos');
        if ($files === null) {
            return;
        }

        $existing = $payment->attachments()->count();
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            if ($existing >= 5) {
                break;
            }
            $path = $file->store('payments/'.$payment->id, 'local');
            PaymentAttachment::query()->create([
                'payment_id' => $payment->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'uploaded_by' => $request->user()?->id,
            ]);
            $existing++;
        }
    }
}
