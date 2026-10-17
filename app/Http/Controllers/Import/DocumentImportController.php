<?php

namespace App\Http\Controllers\Import;

use App\Domain\Import\DocumentImportService;
use App\Domain\Import\InvoiceImportTemplates;
use App\Http\Controllers\Controller;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportLine;
use App\Models\ImportMappingProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentImportController extends Controller
{
    public function __construct(private readonly DocumentImportService $imports) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('purchases.view') || $request->user()?->can('sales.view'), 403);

        return view('import.invoices.index', [
            'batches' => DocumentImportBatch::query()->orderByDesc('id')->limit(20)->get(),
            'profiles' => ImportMappingProfile::query()
                ->whereIn('entity', [InvoiceImportTemplates::PROFILE_PURCHASE, InvoiceImportTemplates::PROFILE_SALE])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function template(string $docType, string $layout, Request $request): mixed
    {
        $this->authorizeDocType($docType, 'view');
        $format = $request->query('format', 'xlsx') === 'csv' ? 'csv' : 'xlsx';

        return $this->imports->templateDownload($docType, $layout, $format);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'doc_type' => 'required|in:purchase,sale',
            'file' => 'required|file|mimes:csv,xlsx,xls|max:51200',
            'mapping_profile_id' => 'nullable|integer|exists:import_mapping_profiles,id',
            'invoice_no' => 'nullable|string|max:64',
            'doc_date' => 'nullable|string|max:32',
        ]);

        $this->authorizeDocType($data['doc_type'], 'manage');

        $meta = array_filter([
            'invoice_no' => $data['invoice_no'] ?? null,
            'doc_date' => $data['doc_date'] ?? null,
        ]);

        $result = $this->imports->preview(
            $data['doc_type'],
            $request->file('file'),
            $request->user(),
            $data['mapping_profile_id'] ?? null,
            $meta,
        );

        $message = $result['queued']
            ? __('Import queued. Open the batch when processing finishes.')
            : __('Import preview ready.');

        return redirect()->route('imports.invoices.show', $result['batch'])->with('status', $message);
    }

    public function show(DocumentImportBatch $documentImportBatch): View
    {
        $this->authorizeDocType($documentImportBatch->doc_type, 'view');
        $documentImportBatch->load(['invoices.lines.product']);

        return view('import.invoices.show', ['batch' => $documentImportBatch]);
    }

    public function updateLine(DocumentImportBatch $documentImportBatch, DocumentImportLine $line, Request $request): RedirectResponse
    {
        $this->authorizeDocType($documentImportBatch->doc_type, 'manage');
        abort_unless($line->invoice->document_import_batch_id === $documentImportBatch->id, 404);

        $data = $request->validate([
            'product_id' => 'nullable|integer|exists:products,id',
            'qty' => 'nullable|numeric',
            'bonus_qty' => 'nullable|numeric',
            'unit_price' => 'nullable|numeric',
            'line_discount' => 'nullable|numeric',
            'line_value' => 'nullable|numeric',
        ]);

        $this->imports->updateLine($line, $data, $request->user());

        return redirect()->route('imports.invoices.show', $documentImportBatch)->with('status', __('Row updated.'));
    }

    public function confirm(DocumentImportBatch $documentImportBatch, Request $request): RedirectResponse
    {
        $this->authorizeDocType($documentImportBatch->doc_type, 'manage');
        $this->imports->confirm($documentImportBatch, $request->user());

        return redirect()->route('imports.invoices.show', $documentImportBatch)->with('status', __('Import batch posted.'));
    }

    public function undo(DocumentImportBatch $documentImportBatch, Request $request): RedirectResponse
    {
        $this->authorizeDocType($documentImportBatch->doc_type, 'manage');
        $this->imports->undo($documentImportBatch, $request->user());

        return redirect()->route('imports.invoices.index')->with('status', __('Import batch undone.'));
    }

    public function storeProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'doc_type' => 'required|in:purchase,sale',
            'name' => 'required|string|max:120',
            'mapping' => 'required|array',
        ]);
        $this->authorizeDocType($data['doc_type'], 'manage');

        ImportMappingProfile::query()->create([
            'entity' => $data['doc_type'] === 'purchase'
                ? InvoiceImportTemplates::PROFILE_PURCHASE
                : InvoiceImportTemplates::PROFILE_SALE,
            'name' => $data['name'],
            'mapping' => $data['mapping'],
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('imports.invoices.index')->with('status', __('Profile saved.'));
    }

    private function authorizeDocType(string $docType, string $ability): void
    {
        $perm = $docType === 'purchase' ? 'purchases.' : 'sales.';
        $perm .= $ability === 'manage' ? 'manage' : 'view';
        abort_unless(request()->user()?->can($perm), 403);
    }
}
