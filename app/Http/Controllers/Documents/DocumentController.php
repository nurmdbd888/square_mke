<?php

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\DocumentWriter;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Product;
use App\Models\Retailer;
use App\Models\SalesRep;
use App\Models\SalesRoute;
use App\Models\Supplier;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentWriter $writer) {}

    public function index(Request $request, string $type): View
    {
        $this->assertType($type);
        $this->authorizeView($request, $type);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['draft', 'posted', 'void', 'all'])],
        ]);
        $status = $filters['status'] ?? 'all';

        $documents = Document::query()
            ->where('type', $type)
            ->with($type === Document::TYPE_PURCHASE ? 'supplier' : 'retailer')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) use ($type) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(function ($w) use ($like, $type) {
                    $w->where('number', 'like', $like)->orWhere('notes', 'like', $like);
                    if ($type === Document::TYPE_PURCHASE) {
                        $w->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like)->orWhere('code', 'like', $like));
                    } else {
                        $w->orWhereHas('retailer', fn ($s) => $s->where('name', 'like', $like)->orWhere('code', 'like', $like));
                    }
                });
            })
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $view = $request->header('HX-Request') && ! $request->header('HX-Boosted')
            ? 'documents._table'
            : 'documents.index';

        return view($view, [
            'type' => $type,
            'documents' => $documents,
            'filters' => $filters + ['status' => $status],
            'canManage' => $request->user()->can($type === Document::TYPE_PURCHASE ? 'purchases.manage' : 'sales.manage'),
        ]);
    }

    public function create(Request $request, string $type): View
    {
        $this->assertType($type);
        $this->authorizeManage($request, $type);

        return view('documents.form', $this->formData($type, new Document([
            'type' => $type,
            'status' => Document::STATUS_DRAFT,
            'doc_date' => now()->toDateString(),
            'vat_pct_default' => '15',
        ])));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->assertType($type);
        $this->authorizeManage($request, $type);

        $data = $this->validated($request, $type);
        $document = $this->writer->save($type, $data['header'], $data['lines'], $request->user());
        $this->storeAttachments($request, $document);

        return redirect()
            ->route($type === Document::TYPE_PURCHASE ? 'purchases.show' : 'sales.show', $document)
            ->with('status', $document->isPosted()
                ? __('Document :number was posted.', ['number' => $document->number])
                : __('Document :number was saved as a draft.', ['number' => $document->number]));
    }

    public function show(Request $request, Document $document): View
    {
        $this->assertRouteMatches($request, $document);
        $this->authorizeView($request, $document->type);
        $document->load(['lines.product', 'supplier', 'retailer', 'salesRep', 'route', 'vehicle', 'attachments']);

        return view('documents.show', [
            'document' => $document,
            'type' => $document->type,
            'canManage' => $request->user()->can($document->isPurchase() ? 'purchases.manage' : 'sales.manage'),
        ]);
    }

    public function edit(Request $request, Document $document): View
    {
        $this->assertRouteMatches($request, $document);
        $this->authorizeManage($request, $document->type);
        abort_if($document->isVoid(), 404);
        $document->load(['lines.product', 'attachments']);

        return view('documents.form', $this->formData($document->type, $document));
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $this->assertRouteMatches($request, $document);
        $this->authorizeManage($request, $document->type);
        abort_if($document->isVoid(), 404);

        $data = $this->validated($request, $document->type);
        $document = $this->writer->save($document->type, $data['header'], $data['lines'], $request->user(), $document);
        $this->storeAttachments($request, $document);

        return redirect()
            ->route($document->isPurchase() ? 'purchases.show' : 'sales.show', $document)
            ->with('status', __('Saved.'));
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $this->assertRouteMatches($request, $document);
        $this->authorizeManage($request, $document->type);
        $type = $document->type;
        $number = $document->number;
        $this->writer->void($document, $request->user());

        return redirect()
            ->route($type === Document::TYPE_PURCHASE ? 'purchases.index' : 'sales.index')
            ->with('status', __('Document :number was voided.', ['number' => $number]));
    }

    public function post(Request $request, Document $document): RedirectResponse
    {
        $this->assertRouteMatches($request, $document);
        $this->authorizeManage($request, $document->type);
        abort_unless($document->isDraft(), 404);
        $document->load('lines');
        $this->writer->post($document, $request->user());

        return redirect()
            ->route($document->isPurchase() ? 'purchases.show' : 'sales.show', $document)
            ->with('status', __('Document :number was posted.', ['number' => $document->number]));
    }

    /**
     * @return array{type: string, document: Document, products: Collection, parties: Collection, reps: Collection, routes: Collection, vehicles: Collection, canManage: bool}
     */
    private function formData(string $type, Document $document): array
    {
        return [
            'type' => $type,
            'document' => $document,
            'products' => Product::query()->orderBy('name_en')->get(['id', 'code', 'name_en', 'buy_price', 'sell_price', 'sd_pct', 'vat_pct']),
            'parties' => $type === Document::TYPE_PURCHASE
                ? Supplier::query()->orderBy('name')->get(['id', 'code', 'name'])
                : Retailer::query()->orderBy('name')->get(['id', 'code', 'name']),
            'reps' => SalesRep::query()->orderBy('name')->get(['id', 'name']),
            'routes' => SalesRoute::query()->orderBy('name')->get(['id', 'name']),
            'vehicles' => Vehicle::query()->orderBy('registration')->get(['id', 'registration']),
            'canManage' => true,
        ];
    }

    /**
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>}
     */
    private function validated(Request $request, string $type): array
    {
        $partyKey = $type === Document::TYPE_PURCHASE ? 'supplier_id' : 'retailer_id';
        $partyTable = $type === Document::TYPE_PURCHASE ? 'suppliers' : 'retailers';

        $data = $request->validate([
            'number' => ['required', 'string', 'max:80'],
            $partyKey => ['required', 'integer', Rule::exists($partyTable, 'id')],
            'doc_date' => ['required', 'date_format:Y-m-d'],
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'sales_rep_id' => ['nullable', 'integer', 'exists:sales_reps,id'],
            'route_id' => ['nullable', 'integer', 'exists:routes,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'header_discount' => ['nullable', 'decimal:0,2'],
            'slab_adjustment' => ['nullable', 'decimal:0,2'],
            'combo_bonus_discount' => ['nullable', 'decimal:0,2'],
            'rounding_adjustment' => ['nullable', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', Rule::in(['save', 'post'])],
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.kind' => ['nullable', Rule::in(['normal', 'combo_bonus'])],
            'lines.*.qty' => ['required', 'decimal:0,3'],
            'lines.*.bonus_qty' => ['nullable', 'decimal:0,3'],
            'lines.*.unit_price' => ['nullable', 'decimal:0,4'],
            'lines.*.line_discount' => ['nullable', 'decimal:0,2'],
            'lines.*.line_value' => ['nullable', 'decimal:0,2'],
            'lines.*.sd_pct' => ['nullable', 'decimal:0,4'],
            'lines.*.vat_pct' => ['nullable', 'decimal:0,4'],
            'lines.*.sd_amount' => ['nullable', 'decimal:0,2'],
            'lines.*.vat_amount' => ['nullable', 'decimal:0,2'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120'],
        ]);

        $header = [
            'number' => $data['number'],
            'supplier_id' => $data['supplier_id'] ?? null,
            'retailer_id' => $data['retailer_id'] ?? null,
            'doc_date' => $data['doc_date'],
            'delivery_date' => $data['delivery_date'] ?? null,
            'sales_rep_id' => $data['sales_rep_id'] ?? null,
            'route_id' => $data['route_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
            'header_discount' => $data['header_discount'] ?? 0,
            'slab_adjustment' => $data['slab_adjustment'] ?? 0,
            'combo_bonus_discount' => $data['combo_bonus_discount'] ?? 0,
            'rounding_adjustment' => $data['rounding_adjustment'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'action' => $data['action'] ?? 'save',
        ];

        return ['header' => $header, 'lines' => array_values($data['lines'])];
    }

    private function storeAttachments(Request $request, Document $document): void
    {
        /** @var list<UploadedFile>|null $files */
        $files = $request->file('attachments');
        if ($files === null) {
            return;
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('documents/'.$document->id, 'local');
            $document->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'uploaded_by' => $request->user()?->id,
            ]);
        }
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, [Document::TYPE_PURCHASE, Document::TYPE_SALE], true)) {
            abort(404);
        }
    }

    private function assertRouteMatches(Request $request, Document $document): void
    {
        $name = (string) $request->route()?->getName();
        $expected = str_starts_with($name, 'purchases.') ? Document::TYPE_PURCHASE : Document::TYPE_SALE;
        abort_unless($document->type === $expected, 404);
    }

    private function authorizeView(Request $request, string $type): void
    {
        abort_unless($request->user()->can($type === Document::TYPE_PURCHASE ? 'purchases.view' : 'sales.view'), 403);
    }

    private function authorizeManage(Request $request, string $type): void
    {
        abort_unless($request->user()->can($type === Document::TYPE_PURCHASE ? 'purchases.manage' : 'sales.manage'), 403);
    }
}
