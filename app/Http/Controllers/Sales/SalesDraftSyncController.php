<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Documents\DocumentWriter;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\SalesSyncKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesDraftSyncController extends Controller
{
    public function __construct(private readonly DocumentWriter $writer) {}

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('sales.manage'), 403);

        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '' || strlen($key) > 64) {
            throw ValidationException::withMessages(['Idempotency-Key' => __('A valid Idempotency-Key header is required.')]);
        }

        $existing = SalesSyncKey::query()->where('idempotency_key', $key)->with('document')->first();
        if ($existing !== null) {
            return response()->json([
                'document_id' => $existing->document_id,
                'number' => $existing->document?->number,
                'status' => $existing->document?->status,
                'duplicate' => true,
            ]);
        }

        $data = $request->validate([
            'number' => ['required', 'string', 'max:80'],
            'retailer_id' => ['required', 'integer', 'exists:retailers,id'],
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
            'lines.*.kind' => ['nullable', Rule::in(['normal', 'combo_bonus'])], // sale drafts only
            'lines.*.qty' => ['required', 'decimal:0,3'],
            'lines.*.bonus_qty' => ['nullable', 'decimal:0,3'],
            'lines.*.unit_price' => ['nullable', 'decimal:0,4'],
            'lines.*.line_discount' => ['nullable', 'decimal:0,2'],
            'lines.*.line_value' => ['nullable', 'decimal:0,2'],
            'lines.*.sd_pct' => ['nullable', 'decimal:0,4'],
            'lines.*.vat_pct' => ['nullable', 'decimal:0,4'],
            'lines.*.sd_amount' => ['nullable', 'decimal:0,2'],
            'lines.*.vat_amount' => ['nullable', 'decimal:0,2'],
        ]);

        $header = [
            'number' => $data['number'],
            'retailer_id' => $data['retailer_id'],
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

        $document = DB::transaction(function () use ($key, $header, $data, $request) {
            $document = $this->writer->save(Document::TYPE_SALE, $header, array_values($data['lines']), $request->user());
            SalesSyncKey::query()->create([
                'idempotency_key' => $key,
                'document_id' => $document->id,
                'user_id' => $request->user()->id,
            ]);

            return $document;
        });

        return response()->json([
            'document_id' => $document->id,
            'number' => $document->number,
            'status' => $document->status,
            'duplicate' => false,
        ], 201);
    }
}
