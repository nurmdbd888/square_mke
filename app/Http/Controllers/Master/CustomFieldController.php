<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomFieldController extends Controller
{
    public function index(): View
    {
        return view('master.custom-fields.index', [
            'fields' => CustomField::query()->orderBy('entity')->orderBy('sort')->orderBy('label')->paginate(50),
        ]);
    }

    public function create(): View
    {
        return view('master.custom-fields.form', [
            'field' => new CustomField(['entity' => 'product', 'type' => 'string', 'sort' => 0]),
        ]);
    }

    public function edit(CustomField $customField): View
    {
        return view('master.custom-fields.form', ['field' => $customField]);
    }

    public function store(Request $request): RedirectResponse
    {
        CustomField::query()->create($this->validated($request));

        return redirect()->route('masters.custom-fields.index')->with('status', __('Custom field saved.'));
    }

    public function update(Request $request, CustomField $customField): RedirectResponse
    {
        $customField->update($this->validated($request, $customField));

        return redirect()->route('masters.custom-fields.index')->with('status', __('Saved.'));
    }

    public function destroy(CustomField $customField): RedirectResponse
    {
        $customField->delete();

        return redirect()->route('masters.custom-fields.index')->with('status', __('Custom field removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CustomField $field = null): array
    {
        $data = $request->validate([
            'entity' => ['required', Rule::in(CustomField::ENTITIES)],
            'key' => [
                'required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_fields', 'key')->where(fn ($q) => $q->where('entity', $request->input('entity')))->ignore($field?->id),
            ],
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['string', 'number'])],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['sort'] = $data['sort'] ?? 0;

        return $data;
    }
}
