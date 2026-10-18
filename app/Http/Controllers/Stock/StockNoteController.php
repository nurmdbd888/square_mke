<?php

namespace App\Http\Controllers\Stock;

use App\Domain\Returns\StockNoteWriter;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\StockNote;
use App\Support\Bulk\BulkActionResult;
use App\Support\Bulk\BulkSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockNoteController extends Controller
{
    public function __construct(private readonly StockNoteWriter $writer) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(['all', StockNote::TYPE_SALES_RETURN, StockNote::TYPE_STOCK_COUNT, StockNote::TYPE_ADJUSTMENT, StockNote::TYPE_LOSS])],
            'status' => ['nullable', Rule::in(['draft', 'posted', 'void', 'all'])],
        ]);
        $type = $filters['type'] ?? StockNote::TYPE_SALES_RETURN;
        $status = $filters['status'] ?? 'all';

        $notes = StockNote::query()
            ->with(['retailer'])
            ->when($type !== 'all', fn ($q) => $q->where('type', $type))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)
                    ->orWhereHas('retailer', fn ($r) => $r->where('name', 'like', $like)));
            })
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('returns.index', [
            'notes' => $notes,
            'filters' => $filters + ['type' => $type, 'status' => $status],
            'canManage' => $request->user()->can('returns.manage'),
            'canDelete' => $request->user()->can('returns.delete'),
        ]);
    }

    public function create(Request $request): View
    {
        $type = $request->query('type', StockNote::TYPE_SALES_RETURN);
        abort_unless(in_array($type, [StockNote::TYPE_SALES_RETURN, StockNote::TYPE_STOCK_COUNT, StockNote::TYPE_ADJUSTMENT, StockNote::TYPE_LOSS], true), 404);

        return view('returns.form', $this->formData($type, new StockNote([
            'type' => $type,
            'status' => StockNote::STATUS_DRAFT,
            'doc_date' => now()->toDateString(),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $request->input('type', StockNote::TYPE_SALES_RETURN);
        $data = $this->validated($request, $type);
        $note = $this->writer->save($type, $data['header'], $data['lines'], $request->user());

        return redirect()->route('returns.show', $note)
            ->with('status', $note->isPosted()
                ? __('Return :number was posted.', ['number' => $note->number])
                : __('Return :number was saved as a draft.', ['number' => $note->number]));
    }

    public function show(StockNote $stockNote): View
    {
        $stockNote->load(['lines.product', 'retailer', 'sourceDocument']);

        return view('returns.show', [
            'note' => $stockNote,
            'canManage' => request()->user()->can('returns.manage'),
            'canDelete' => request()->user()->can('returns.delete'),
        ]);
    }

    public function edit(StockNote $stockNote): View
    {
        abort_if($stockNote->isVoid(), 404);
        $stockNote->load('lines');

        return view('returns.form', $this->formData($stockNote->type, $stockNote));
    }

    public function update(Request $request, StockNote $stockNote): RedirectResponse
    {
        abort_if($stockNote->isVoid(), 404);
        $data = $this->validated($request, $stockNote->type);
        $note = $this->writer->save($stockNote->type, $data['header'], $data['lines'], $request->user(), $stockNote);

        return redirect()->route('returns.show', $note)
            ->with('status', __('Return :number was updated.', ['number' => $note->number]));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $action = $request->validate([
            'action' => ['required', Rule::in(['post', 'void'])],
        ])['action'];

        if ($action === 'void') {
            abort_unless($request->user()->can('returns.delete'), 403);
        } else {
            abort_unless($request->user()->can('returns.manage'), 403);
        }

        $ids = BulkSelection::ids($request);
        $result = new BulkActionResult;

        StockNote::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->each(function (StockNote $note) use ($action, $request, $result) {
                try {
                    if ($action === 'post') {
                        if (! $note->isDraft()) {
                            $result->skipped++;

                            return;
                        }
                        $this->writer->post($note->fresh('lines'), $request->user());
                        $result->applied++;

                        return;
                    }

                    if ($note->isVoid()) {
                        $result->skipped++;

                        return;
                    }
                    $this->writer->void($note, $request->user());
                    $result->applied++;
                } catch (\Throwable $e) {
                    $result->skipped++;
                    $result->errors[] = $note->number.': '.$e->getMessage();
                }
            });

        return redirect()->route('returns.index')->with('status', $result->message());
    }

    public function post(Request $request, StockNote $stockNote): RedirectResponse
    {
        $this->writer->post($stockNote->fresh('lines'), $request->user());

        return back()->with('status', __('Return :number was posted.', ['number' => $stockNote->number]));
    }

    public function destroy(Request $request, StockNote $stockNote): RedirectResponse
    {
        abort_unless($request->user()->can('returns.delete'), 403);
        $this->writer->void($stockNote, $request->user());

        return redirect()->route('returns.index')
            ->with('status', __('Return :number was removed.', ['number' => $stockNote->number]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(string $type, StockNote $note): array
    {
        return [
            'type' => $type,
            'note' => $note,
            'products' => Product::query()->orderBy('name_en')->get(['id', 'code', 'name_en', 'unit']),
            'retailers' => Retailer::query()->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    /**
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    private function validated(Request $request, string $type): array
    {
        $validated = $request->validate([
            'number' => ['required', 'string', 'max:80'],
            'doc_date' => ['required', 'date'],
            'retailer_id' => [$type === StockNote::TYPE_SALES_RETURN ? 'required' : 'nullable', 'integer', 'exists:retailers,id'],
            'source_document_id' => ['nullable', 'integer', 'exists:documents,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'action' => ['nullable', Rule::in(['save', 'post'])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric'],
            'lines.*.unit_price' => ['nullable', 'numeric'],
            'lines.*.line_value' => ['nullable', 'numeric'],
            'lines.*.line_condition' => ['nullable', Rule::in(['good', 'damaged', 'expired'])],
            'lines.*.linked_document_line_id' => ['nullable', 'integer', 'exists:document_lines,id'],
            'lines.*.expected_qty' => ['nullable', 'numeric'],
            'lines.*.batch_no' => ['nullable', 'string', 'max:80'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ]);

        $lines = [];
        foreach ($validated['lines'] as $line) {
            if ((float) ($line['qty'] ?? 0) == 0.0 && ($type !== StockNote::TYPE_STOCK_COUNT)) {
                continue;
            }
            $lines[] = $line;
        }

        return [
            'header' => [
                'number' => $validated['number'],
                'doc_date' => $validated['doc_date'],
                'retailer_id' => $validated['retailer_id'] ?? null,
                'source_document_id' => $validated['source_document_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'action' => $validated['action'] ?? 'save',
            ],
            'lines' => $lines,
        ];
    }
}
