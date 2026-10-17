<?php

namespace App\Http\Controllers\Import;

use App\Domain\Import\EntityImportRegistry;
use App\Domain\Import\EntityImportService;
use App\Http\Controllers\Controller;
use App\Models\EntityImportBatch;
use App\Models\ImportMappingProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EntityImportController extends Controller
{
    public function __construct(private readonly EntityImportService $imports) {}

    public function index(string $entity): View
    {
        $this->authorizeEntity($entity);

        return view('import.entity.index', [
            'entity' => $entity,
            'def' => EntityImportRegistry::get($entity),
            'batches' => EntityImportBatch::query()->where('entity', $entity)->orderByDesc('id')->limit(15)->get(),
            'profiles' => ImportMappingProfile::query()->where('entity', $entity)->orderBy('name')->get(),
        ]);
    }

    public function template(string $entity, Request $request): StreamedResponse
    {
        $this->authorizeEntity($entity);

        return $this->imports->templateDownload($entity, $request->string('format', 'csv')->toString());
    }

    public function export(string $entity): StreamedResponse
    {
        $this->authorizeEntity($entity);

        return $this->imports->exportList($entity);
    }

    public function store(string $entity, Request $request): RedirectResponse
    {
        $this->authorizeEntity($entity);
        $request->validate([
            'file' => ['required', 'file', 'max:51200', 'mimes:csv,xls,xlsx,txt'],
            'mapping_profile_id' => ['nullable', 'integer', 'exists:import_mapping_profiles,id'],
        ]);

        $result = $this->imports->preview(
            $entity,
            $request->file('file'),
            $request->user(),
            $request->integer('mapping_profile_id') ?: null,
        );

        $message = $result['queued']
            ? __('Import is parsing in the background. Refresh this page in a moment.')
            : __('Import preview ready.');

        return redirect()->route('imports.entity.show', [$entity, $result['batch']])->with('status', $message);
    }

    public function show(string $entity, EntityImportBatch $entityImportBatch): View
    {
        $this->authorizeEntity($entity);
        abort_if($entityImportBatch->entity !== $entity, 404);
        $entityImportBatch->load(['rows' => fn ($q) => $q->orderBy('row_no')->limit(250)]);

        return view('import.entity.show', [
            'entity' => $entity,
            'def' => EntityImportRegistry::get($entity),
            'batch' => $entityImportBatch,
        ]);
    }

    public function confirm(string $entity, EntityImportBatch $entityImportBatch, Request $request): RedirectResponse
    {
        $this->authorizeEntity($entity);
        abort_if($entityImportBatch->entity !== $entity, 404);
        $request->validate(['duplicate_mode' => ['required', 'in:update,skip']]);
        $this->imports->confirm($entityImportBatch, $request->string('duplicate_mode')->toString(), $request->user());

        return redirect()->route('imports.entity.show', [$entity, $entityImportBatch])->with('status', __('Import batch posted.'));
    }

    public function undo(string $entity, EntityImportBatch $entityImportBatch): RedirectResponse
    {
        $this->authorizeEntity($entity);
        abort_if($entityImportBatch->entity !== $entity, 404);
        $this->imports->undo($entityImportBatch, request()->user());

        return redirect()->route('imports.entity.index', $entity)->with('status', __('Import batch undone.'));
    }

    public function storeProfile(string $entity, Request $request): RedirectResponse
    {
        $this->authorizeEntity($entity);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'mapping' => ['nullable', 'array'],
        ]);

        ImportMappingProfile::query()->create([
            'entity' => $entity,
            'name' => $data['name'],
            'mapping' => $data['mapping'] ?? [],
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('imports.entity.index', $entity)->with('status', __('Profile saved.'));
    }

    private function authorizeEntity(string $entity): void
    {
        if (! EntityImportRegistry::has($entity)) {
            abort(404);
        }
        $def = EntityImportRegistry::get($entity);
        abort_unless(request()->user()?->can($def['permission']), 403);
    }
}
