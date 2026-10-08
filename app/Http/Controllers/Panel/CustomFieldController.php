<?php

namespace App\Http\Controllers\Panel;

use App\Models\CustomField;
use App\Models\Page;
use App\Support\ActivityLogger;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Campos adicionais das ações de uma página. Alterações no esquema nunca apagam
 * valores já registrados: campos em uso são arquivados, e mudar o tipo de um
 * campo com valores é bloqueado.
 */
class CustomFieldController extends PanelController
{
    public function __construct(private ActivityLogger $log) {}

    public function index(Page $page)
    {
        $this->authorize('view', $page);
        $fields = $page->fields()->withCount(['values' => fn ($q) => $q->whereNotNull('value')->where('value', '!=', '')])->get();

        return view('panel.pages.fields', ['page' => $page, 'fields' => $fields, 'canManage' => request()->user()->can('manageStructure', $page)]);
    }

    public function store(Request $request, Page $page)
    {
        $this->authorize('manageStructure', $page);
        $data = $this->validated($request);
        $key = Slug::unique(str_replace('-', '_', Slug::make($data['label'], 'campo')), fn ($k) => $page->fields()->where('key', $k)->exists());
        $field = $page->fields()->create($data + ['key' => $key, 'position' => (int) $page->fields()->max('position') + 1]);
        $this->log->log($request->user(), 'field.created', $page, 'Criou o campo "'.$field->label.'" em "'.$page->title.'"');

        return redirect()->route('panel.pages.fields', $page)->with('status', 'Campo criado. Ele aparece no formulário das ações desta área e das subordinadas.');
    }

    public function update(Request $request, Page $page, CustomField $field)
    {
        $this->authorize('manageStructure', $page);
        abort_unless($field->page_id === $page->id, 404);
        $data = $this->validated($request);

        if ($data['type'] !== $field->type && $field->hasValues()) {
            throw ValidationException::withMessages(['type' => 'Este campo já tem valores registrados; mudar o tipo poderia corromper o histórico. Arquive-o e crie um novo campo.']);
        }
        if ($field->type === 'select' && $field->hasValues()) {
            $used = $field->values()->whereNotNull('value')->distinct()->pluck('value')->all();
            $missing = array_diff($used, $data['options'] ?? []);
            if ($missing) {
                throw ValidationException::withMessages(['options' => 'Estas opções já foram usadas e não podem ser removidas: '.implode(', ', $missing).'.']);
            }
        }
        $field->update($data);
        $this->log->log($request->user(), 'field.updated', $page, 'Editou o campo "'.$field->label.'"');

        return redirect()->route('panel.pages.fields', $page)->with('status', 'Campo atualizado.');
    }

    public function archive(Request $request, Page $page, CustomField $field)
    {
        $this->authorize('manageStructure', $page);
        abort_unless($field->page_id === $page->id, 404);
        $field->archived_at = now();
        $field->save();
        $this->log->log($request->user(), 'field.archived', $page, 'Arquivou o campo "'.$field->label.'"');

        return back()->with('status', 'Campo arquivado. Os valores já registrados foram preservados e podem voltar a aparecer se o campo for reativado.');
    }

    public function unarchive(Request $request, Page $page, CustomField $field)
    {
        $this->authorize('manageStructure', $page);
        abort_unless($field->page_id === $page->id, 404);
        $field->archived_at = null;
        $field->save();

        return back()->with('status', 'Campo reativado.');
    }

    public function destroy(Request $request, Page $page, CustomField $field)
    {
        $this->authorize('manageStructure', $page);
        abort_unless($field->page_id === $page->id, 404);
        if ($field->hasValues()) {
            return back()->withErrors(['field' => 'O campo "'.$field->label.'" tem valores registrados e não pode ser excluído. Use "Arquivar".']);
        }
        $field->values()->delete();
        $field->delete();
        $this->log->log($request->user(), 'field.deleted', $page, 'Excluiu o campo sem valores "'.$field->label.'"');

        return back()->with('status', 'Campo excluído.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'help' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(array_keys(CustomField::TYPES))],
            'options_text' => ['nullable', 'required_if:type,select', 'string', 'max:5000'],
            'is_required' => ['nullable', 'boolean'],
            'is_public' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'between:0,999'],
        ], ['options_text.required_if' => 'Informe as opções, uma por linha.'], ['label' => 'rótulo', 'type' => 'tipo']);

        $options = $data['type'] === 'select'
            ? array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['options_text'] ?? '')))))) : null;
        unset($data['options_text']);

        return $data + [
            'options' => $options,
            'is_required' => $request->boolean('is_required'),
            'is_public' => $request->boolean('is_public'),
            'position' => (int) ($data['position'] ?? 0),
        ];
    }
}
