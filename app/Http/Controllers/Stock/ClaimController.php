<?php

namespace App\Http\Controllers\Stock;

use App\Domain\Returns\ClaimWriter;
use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClaimController extends Controller
{
    public function __construct(private readonly ClaimWriter $writer) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', ...Claim::STATUSES])],
        ]);
        $status = $filters['status'] ?? 'all';

        $claims = Claim::query()
            ->with('supplier')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn ($w) => $w->where('number', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like)));
            })
            ->orderByDesc('doc_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('claims.index', [
            'claims' => $claims,
            'filters' => $filters + ['status' => $status],
            'canManage' => $request->user()->can('claims.manage'),
            'canDelete' => $request->user()->can('claims.delete'),
        ]);
    }

    public function create(): View
    {
        return view('claims.form', $this->formData(new Claim([
            'status' => Claim::STATUS_OPEN,
            'doc_date' => now()->toDateString(),
            'amount' => '0',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $claim = $this->writer->save($this->validated($request), $request->user());

        return redirect()->route('claims.show', $claim)
            ->with('status', __('Claim :number was saved.', ['number' => $claim->number]));
    }

    public function show(Claim $claim): View
    {
        $claim->load('supplier');

        return view('claims.show', [
            'claim' => $claim,
            'canManage' => request()->user()->can('claims.manage'),
            'canDelete' => request()->user()->can('claims.delete'),
        ]);
    }

    public function edit(Claim $claim): View
    {
        abort_if(in_array($claim->status, [Claim::STATUS_SETTLED, Claim::STATUS_REJECTED], true), 404);

        return view('claims.form', $this->formData($claim));
    }

    public function update(Request $request, Claim $claim): RedirectResponse
    {
        abort_if(in_array($claim->status, [Claim::STATUS_SETTLED, Claim::STATUS_REJECTED], true), 404);
        $claim = $this->writer->save($this->validated($request), $request->user(), $claim);

        return redirect()->route('claims.show', $claim)
            ->with('status', __('Claim :number was updated.', ['number' => $claim->number]));
    }

    public function destroy(Request $request, Claim $claim): RedirectResponse
    {
        abort_unless($request->user()->can('claims.delete'), 403);
        abort_if(in_array($claim->status, [Claim::STATUS_SETTLED, Claim::STATUS_REJECTED], true), 404);

        $number = $claim->number;
        $claim->delete();

        return redirect()->route('claims.index')
            ->with('status', __('Claim :number was deleted.', ['number' => $number]));
    }

    public function bulk(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('claims.manage'), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['integer'],
            'field' => ['required', Rule::in(['status'])],
            'value' => ['required', Rule::in(Claim::STATUSES)],
        ]);

        $updated = 0;
        Claim::query()
            ->whereIn('id', $data['ids'])
            ->limit(100)
            ->get()
            ->each(function (Claim $claim) use ($data, &$updated) {
                if (in_array($claim->status, [Claim::STATUS_SETTLED, Claim::STATUS_REJECTED], true)) {
                    return;
                }
                $claim->update(['status' => $data['value']]);
                $updated++;
            });

        return redirect()->route('claims.index')->with('status', __('Updated :count claims.', ['count' => $updated]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Claim $claim): array
    {
        return [
            'claim' => $claim,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'code', 'name']),
            'statuses' => Claim::STATUSES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'number' => ['required', 'string', 'max:80'],
            'doc_date' => ['required', 'date'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in(Claim::STATUSES)],
        ]);
    }
}
