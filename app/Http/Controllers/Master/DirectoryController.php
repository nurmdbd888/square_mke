<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\SalesRep;
use App\Models\SalesRoute;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Bulk\BulkSelection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Suppliers, routes, sales reps and vehicles. They share one list and one form.
 */
class DirectoryController extends Controller
{
    public function index(Request $request, string $kind): View
    {
        $meta = $this->meta($kind);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'all'])],
        ]);
        $status = $filters['status'] ?? 'active';
        $model = $meta['model'];

        $rows = $model::query()
            ->when($status === 'inactive', fn ($q) => $q->onlyTrashed())
            ->when($status === 'all', fn ($q) => $q->withTrashed())
            ->when($filters['q'] ?? null, function ($q, $term) use ($meta) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(function ($w) use ($meta, $like) {
                    foreach ($meta['search'] as $i => $column) {
                        $i === 0 ? $w->where($column, 'like', $like) : $w->orWhere($column, 'like', $like);
                    }
                });
            })
            ->orderBy($meta['order'])
            ->paginate(50)
            ->withQueryString();

        if ($kind === 'sales-reps') {
            $rows->load('user');
        }

        $view = $request->header('HX-Request') && ! $request->header('HX-Boosted') ? 'master.directory._table' : 'master.directory.index';

        return view($view, [
            'kind' => $kind,
            'meta' => $meta,
            'rows' => $rows,
            'filters' => $filters + ['status' => $status],
        ]);
    }

    public function create(string $kind): View
    {
        $meta = $this->meta($kind);

        return view('master.directory.form', [
            'kind' => $kind,
            'meta' => $meta,
            'row' => new $meta['model'],
            'fields' => $this->fields($kind),
        ]);
    }

    public function store(Request $request, string $kind): RedirectResponse
    {
        $meta = $this->meta($kind);
        $row = $meta['model']::query()->create($this->validated($request, $kind));

        return redirect()->route('masters.directory.index', $kind)->with('status', $this->saved($kind, $row));
    }

    public function edit(string $kind, int $id): View
    {
        $meta = $this->meta($kind);

        return view('master.directory.form', [
            'kind' => $kind,
            'meta' => $meta,
            'row' => $this->find($kind, $id),
            'fields' => $this->fields($kind),
        ]);
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $row = $this->find($kind, $id);
        $row->update($this->validated($request, $kind, $row));

        return redirect()->route('masters.directory.index', $kind)->with('status', __('Saved.'));
    }

    public function destroy(string $kind, int $id): RedirectResponse
    {
        $row = $this->find($kind, $id);
        $row->delete();

        return redirect()->route('masters.directory.index', $kind)->with('status', $this->deactivated($kind, $row));
    }

    public function restore(string $kind, int $id): RedirectResponse
    {
        $row = $this->find($kind, $id);
        $row->restore();

        return redirect()->route('masters.directory.index', $kind)->with('status', $this->restored($kind, $row));
    }

    public function bulk(Request $request, string $kind): RedirectResponse
    {
        $meta = $this->meta($kind);
        $allowed = array_keys($this->bulkFields($kind));
        $data = $request->validate([
            'field' => ['required', Rule::in($allowed)],
            'value' => ['nullable', 'string', 'max:255'],
        ]);
        $ids = BulkSelection::ids($request);
        $value = $data['value'] === '' ? null : $data['value'];

        if ($data['field'] === 'user_id' && $value !== null) {
            $request->validate(['value' => ['integer', Rule::exists('users', 'id')]]);
        }

        $meta['model']::withTrashed()
            ->whereIn('id', $ids)
            ->limit(100)
            ->get()
            ->each(fn (Model $row) => $row->update([$data['field'] => $value]));

        return redirect()->route('masters.directory.index', $kind)->with('status', __('Saved.'));
    }

    /**
     * @return array<string, string>
     */
    private function bulkFields(string $kind): array
    {
        return match ($kind) {
            'suppliers' => ['phone' => __('Phone'), 'bin' => __('BIN')],
            'routes' => ['code' => __('Code')],
            'sales-reps' => ['phone' => __('Phone'), 'user_id' => __('Linked user')],
            'vehicles' => ['type' => __('Vehicle type')],
            default => abort(404),
        };
    }

    /**
     * @return array{model: class-string<Model>, title: string, add: string, edit: string, empty: string, search_label: string, order: string, search: list<string>, columns: array<string, string>}
     */
    private function meta(string $kind): array
    {
        return match ($kind) {
            'suppliers' => [
                'model' => Supplier::class,
                'title' => __('Suppliers'),
                'add' => __('Add supplier'),
                'edit' => __('Edit supplier'),
                'empty' => __('No suppliers match.'),
                'search_label' => __('Search name, code or phone'),
                'order' => 'name',
                'search' => ['name', 'code', 'phone'],
                'columns' => ['code' => __('Code'), 'name' => __('Name'), 'phone' => __('Phone'), 'bin' => __('BIN')],
            ],
            'routes' => [
                'model' => SalesRoute::class,
                'title' => __('Routes'),
                'add' => __('Add route'),
                'edit' => __('Edit route'),
                'empty' => __('No routes match.'),
                'search_label' => __('Search name or code'),
                'order' => 'name',
                'search' => ['name', 'code'],
                'columns' => ['code' => __('Code'), 'name' => __('Name')],
            ],
            'sales-reps' => [
                'model' => SalesRep::class,
                'title' => __('Sales reps'),
                'add' => __('Add sales rep'),
                'edit' => __('Edit sales rep'),
                'empty' => __('No sales reps match.'),
                'search_label' => __('Search name, code or phone'),
                'order' => 'name',
                'search' => ['name', 'code', 'phone'],
                'columns' => ['code' => __('Code'), 'name' => __('Name'), 'phone' => __('Phone'), 'user' => __('Linked user')],
            ],
            'vehicles' => [
                'model' => Vehicle::class,
                'title' => __('Vehicles'),
                'add' => __('Add vehicle'),
                'edit' => __('Edit vehicle'),
                'empty' => __('No vehicles match.'),
                'search_label' => __('Search registration or type'),
                'order' => 'registration',
                'search' => ['registration', 'type'],
                'columns' => ['registration' => __('Registration'), 'type' => __('Vehicle type')],
            ],
            default => abort(404),
        };
    }

    /**
     * @return list<array{name: string, label: string, type?: string, required?: bool, max: int, options?: array<int|string, string>}>
     */
    private function fields(string $kind): array
    {
        $code = ['name' => 'code', 'label' => __('Code'), 'max' => 40];
        $name = ['name' => 'name', 'label' => __('Name'), 'required' => true, 'max' => $kind === 'suppliers' ? 191 : 120];
        $phone = ['name' => 'phone', 'label' => __('Phone'), 'type' => 'tel', 'max' => 20];

        return match ($kind) {
            'suppliers' => [
                $code,
                $name,
                $phone,
                ['name' => 'bin', 'label' => __('BIN'), 'max' => 40],
                ['name' => 'address', 'label' => __('Address'), 'max' => 255],
            ],
            'routes' => [$code, $name],
            'sales-reps' => [
                $code,
                $name,
                $phone,
                [
                    'name' => 'user_id',
                    'label' => __('Linked user'),
                    'type' => 'select',
                    'max' => 20,
                    'options' => User::query()->orderBy('name')->get()->mapWithKeys(fn (User $user) => [$user->id => $user->name.' ('.$user->username.')'])->all(),
                ],
            ],
            'vehicles' => [
                ['name' => 'type', 'label' => __('Vehicle type'), 'max' => 40],
                ['name' => 'registration', 'label' => __('Registration'), 'required' => true, 'max' => 40],
            ],
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, string $kind, ?Model $row = null): array
    {
        $unique = fn (string $table, string $column) => ['nullable', 'string', 'max:40', Rule::unique($table, $column)->ignore($row?->getKey())];

        $rules = match ($kind) {
            'suppliers' => [
                'code' => $unique('suppliers', 'code'),
                'name' => ['required', 'string', 'max:191'],
                'bin' => ['nullable', 'string', 'max:40'],
                'address' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:20'],
            ],
            'routes' => [
                'code' => $unique('routes', 'code'),
                'name' => ['required', 'string', 'max:120'],
            ],
            'sales-reps' => [
                'code' => $unique('sales_reps', 'code'),
                'name' => ['required', 'string', 'max:120'],
                'phone' => ['nullable', 'string', 'max:20'],
                'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            ],
            'vehicles' => [
                'type' => ['nullable', 'string', 'max:40'],
                'registration' => ['required', 'string', 'max:40', Rule::unique('vehicles', 'registration')->ignore($row?->getKey())],
            ],
            default => abort(404),
        };

        $data = $request->validate($rules);

        if (array_key_exists('code', $data)) {
            $data['code'] = $data['code'] !== null && $data['code'] !== '' ? $data['code'] : null;
        }

        return $data;
    }

    private function find(string $kind, int $id): Model
    {
        $model = $this->meta($kind)['model'];

        return $model::withTrashed()->findOrFail($id);
    }

    private function saved(string $kind, Model $row): string
    {
        $name = $this->label($row);

        return match ($kind) {
            'suppliers' => __('Supplier :name was saved.', ['name' => $name]),
            'routes' => __('Route :name was saved.', ['name' => $name]),
            'sales-reps' => __('Sales rep :name was saved.', ['name' => $name]),
            'vehicles' => __('Vehicle :name was saved.', ['name' => $name]),
            default => __('Saved.'),
        };
    }

    private function deactivated(string $kind, Model $row): string
    {
        $name = $this->label($row);

        return match ($kind) {
            'suppliers' => __('Supplier :name was deactivated.', ['name' => $name]),
            'routes' => __('Route :name was deactivated.', ['name' => $name]),
            'sales-reps' => __('Sales rep :name was deactivated.', ['name' => $name]),
            'vehicles' => __('Vehicle :name was deactivated.', ['name' => $name]),
            default => __('Saved.'),
        };
    }

    private function restored(string $kind, Model $row): string
    {
        $name = $this->label($row);

        return match ($kind) {
            'suppliers' => __('Supplier :name can be used again.', ['name' => $name]),
            'routes' => __('Route :name can be used again.', ['name' => $name]),
            'sales-reps' => __('Sales rep :name can be used again.', ['name' => $name]),
            'vehicles' => __('Vehicle :name can be used again.', ['name' => $name]),
            default => __('Saved.'),
        };
    }

    private function label(Model $row): string
    {
        return (string) ($row->name ?? $row->registration);
    }
}
