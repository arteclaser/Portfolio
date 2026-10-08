<?php

namespace App\Http\Controllers\Panel;

use App\Models\Action;
use App\Models\ActionVersion;
use App\Models\CustomField;
use App\Models\Page;
use App\Models\Partner;
use App\Models\TeamMember;
use App\Services\ActionWorkflow;
use App\Support\Access;
use App\Support\PageTree;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActionController extends PanelController
{
    public function __construct(private ActionWorkflow $workflow, private Access $access) {}

    /**
     * Ações visíveis para o usuário, com a versão corrente (trabalho ou publicada)
     * unida como "cv" para filtrar por área.
     */
    public static function scopedQuery(?array $allowedPageIds, bool $trashed = false): Builder
    {
        $query = $trashed ? Action::onlyTrashed() : Action::query();
        $query->select('actions.*')
            ->leftJoin('action_versions as cv', 'cv.id', '=', DB::raw('COALESCE(actions.working_version_id, actions.published_version_id)'));
        if ($allowedPageIds !== null) {
            $query->whereIn('cv.primary_page_id', $allowedPageIds ?: [0]);
        }

        return $query;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Action::class);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'integer'],
            'estado' => ['nullable', 'in:draft,in_review,returned,published,pending,archived'],
            'situacao' => ['nullable', 'in:'.implode(',', array_keys(ActionVersion::ACTIVITY_STATUSES))],
            'minhas' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $query = self::scopedQuery($this->access->allowedPageIds($user))
            ->with(['workingVersion.primaryPage', 'workingVersion.author', 'publishedVersion.primaryPage', 'publishedVersion.author']);

        if (! empty($filters['q'])) {
            foreach (array_filter(explode(' ', Text::searchable($filters['q']))) as $term) {
                $query->where('cv.search_text', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%');
            }
        }
        if (! empty($filters['area'])) {
            $query->whereIn('cv.primary_page_id', app(PageTree::class)->withDescendants([(int) $filters['area']]));
        }
        if (! empty($filters['situacao'])) {
            $query->where('cv.activity_status', $filters['situacao']);
        }
        if (! empty($filters['minhas'])) {
            $query->where(fn ($q) => $q->where('actions.created_by', $user->id)->orWhere('cv.author_id', $user->id));
        }
        match ($filters['estado'] ?? null) {
            'draft', 'in_review', 'returned' => $query->where('actions.working_state', $filters['estado'])->whereNull('actions.archived_at'),
            'published' => $query->whereNotNull('actions.published_version_id')->whereNull('actions.archived_at'),
            'pending' => $query->whereNotNull('actions.published_version_id')->whereNotNull('actions.working_version_id'),
            'archived' => $query->whereNotNull('actions.archived_at'),
            default => $query->whereNull('actions.archived_at'),
        };

        return view('panel.actions.index', [
            'actions' => $query->orderByDesc('actions.updated_at')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'areaOptions' => $this->pageOptions($this->allowedPages(), 'Todas as áreas'),
        ]);
    }

    public function trash(Request $request)
    {
        $this->authorize('viewAny', Action::class);
        $actions = self::scopedQuery($this->access->allowedPageIds($request->user()), true)
            ->with(['workingVersion', 'publishedVersion'])->orderByDesc('actions.deleted_at')->paginate(20);

        return view('panel.actions.trash', ['actions' => $actions]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Action::class);
        $pages = $this->allowedPages();

        return view('panel.actions.create', ['areaOptions' => $this->pageOptions($pages, 'Escolha a área principal')]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Action::class);
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:600'],
            'primary_page_id' => ['required', 'integer'],
        ], [], ['primary_page_id' => 'área principal']);
        $this->assertAllowedArea($request, (int) $data['primary_page_id']);

        $action = $this->workflow->create($this->portfolio(), $request->user(), $data);

        return redirect()->route('panel.actions.edit', $action)->with('status', 'Rascunho criado. Complete as informações e salve quando quiser.');
    }

    public function edit(Request $request, Action $action)
    {
        $this->authorize('view', $action);
        $action->load(['workingVersion', 'publishedVersion']);
        $version = $action->currentVersion();
        $version->load(['relatedPages', 'teamMembers', 'partners', 'indicators', 'fieldValues']);
        $user = $request->user();

        $allPages = Page::query()->active()->where('portfolio_id', $this->portfolio()->id)->orderBy('position')->orderBy('title')->get();
        $allowed = $this->allowedPages();
        // A área atual permanece selecionável mesmo que fora das áreas do usuário (Master pode ter movido).
        if ($version->primary_page_id && ! $allowed->contains('id', $version->primary_page_id)) {
            $current = Page::find($version->primary_page_id);
            if ($current) {
                $allowed->push($current);
            }
        }

        $fields = CustomField::query()->active()->whereIn('page_id', $allPages->pluck('id'))->orderBy('position')->get();
        $tree = app(PageTree::class);
        // Para cada campo, as áreas em que ele se aplica (a página dona e as subordinadas).
        $fieldPages = $fields->mapWithKeys(fn (CustomField $f) => [$f->id => $tree->withDescendants([$f->page_id])]);

        return view('panel.actions.edit', [
            'action' => $action,
            'version' => $version,
            'canEdit' => $action->workingVersion !== null && $user->can('update', $action),
            'canStartWorking' => $action->workingVersion === null && $user->can('startWorkingCopy', $action),
            'areaOptions' => $this->pageOptions($allowed, 'Escolha a área principal'),
            'allPages' => $allPages,
            'programOptions' => $this->pageOptions($allPages->whereIn('kind', ['programa', 'projeto']), 'Nenhum'),
            'members' => TeamMember::query()->whereNull('archived_at')->orWhereIn('id', $version->teamMembers->pluck('id'))->orderBy('name')->get(),
            'partners' => Partner::query()->whereNull('archived_at')->orWhereIn('id', $version->partners->pluck('id'))->orderBy('name')->get(),
            'activityTypes' => $this->portfolio()->activityTypes(),
            'fields' => $fields,
            'fieldPages' => $fieldPages,
            'fieldValues' => $version->fieldValues->pluck('value', 'custom_field_id'),
        ]);
    }

    public function update(Request $request, Action $action)
    {
        $this->authorize('update', $action);
        $action->load('workingVersion');
        $version = $action->workingVersion ?? abort(409);

        // Bloqueio otimista: evita sobrescrever o que outra pessoa salvou.
        if ($request->filled('version_stamp') && $request->input('version_stamp') !== (string) $version->updated_at?->getTimestamp()) {
            return back()->withInput()->with('warning', 'Esta ação foi alterada por outra pessoa enquanto você editava. Seu texto foi mantido no formulário: revise e salve novamente para sobrescrever.')
                ->with('stale', true);
        }

        $data = $this->validated($request, $version);
        $this->assertAllowedArea($request, (int) $data['primary_page_id'], $version->primary_page_id);

        $this->workflow->save($action, $request->user(), $data);

        $message = 'Alterações salvas às '.now()->format('H:i').'.';
        if ($request->input('intent') === 'submit') {
            return redirect()->route('panel.actions.review', $action)->with('status', $message.' Confira a revisão antes de enviar.');
        }

        return redirect()->route('panel.actions.edit', $action)->with('status', $message)->with('saved_form', 'action-'.$action->id);
    }

    public function startWorkingCopy(Request $request, Action $action)
    {
        $this->authorize('startWorkingCopy', $action);
        $this->workflow->startWorkingCopy($action, $request->user());

        return redirect()->route('panel.actions.edit', $action)->with('status', 'Versão de trabalho criada. O site continua mostrando a versão publicada até a nova aprovação.');
    }

    public function archive(Request $request, Action $action)
    {
        $this->authorize('archive', $action);
        $this->workflow->archive($action, $request->user());

        return back()->with('status', 'Ação arquivada. Ela não aparece mais no site, mas seu histórico foi preservado.');
    }

    public function unarchive(Request $request, Action $action)
    {
        $this->authorize('archive', $action);
        $this->workflow->unarchive($action, $request->user());

        return back()->with('status', 'Ação retirada do arquivo.');
    }

    public function destroy(Request $request, Action $action)
    {
        $this->authorize('delete', $action);
        $this->workflow->trash($action, $request->user());

        return redirect()->route('panel.actions.index')->with('status', 'Ação movida para a lixeira. É possível restaurá-la.');
    }

    public function restore(Request $request, Action $trashedAction)
    {
        $this->authorize('restore', $trashedAction);
        $this->workflow->restoreFromTrash($trashedAction, $request->user());

        return redirect()->route('panel.actions.edit', $trashedAction)->with('status', 'Ação restaurada da lixeira.');
    }

    public function forceDelete(Request $request, Action $trashedAction)
    {
        $this->authorize('forceDelete', $trashedAction);
        $title = $trashedAction->currentVersion()?->displayTitle() ?? '';
        $request->validate(['confirm_title' => ['required', 'string']], [], ['confirm_title' => 'confirmação']);
        if (trim($request->input('confirm_title')) !== trim($title)) {
            throw ValidationException::withMessages(['confirm_title' => 'Digite o nome exato da ação para confirmar a exclusão permanente.']);
        }
        $this->workflow->forceDelete($trashedAction, $request->user());

        return redirect()->route('panel.actions.trash')->with('status', 'Ação excluída permanentemente.');
    }

    private function assertAllowedArea(Request $request, int $pageId, ?int $currentPageId = null): void
    {
        $page = Page::query()->active()->find($pageId);
        if (! $page) {
            throw ValidationException::withMessages(['primary_page_id' => 'Escolha uma área principal válida.']);
        }
        // Manter a área atual é permitido; mudar para outra exige acesso a ela.
        if ($pageId !== $currentPageId && ! $this->access->canAccessPage($request->user(), $pageId)) {
            abort(403, 'Você não tem acesso a esta área.');
        }
    }

    private function validated(Request $request, ActionVersion $version): array
    {
        $rules = [
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:600'],
            'description' => ['nullable', 'string', 'max:30000'],
            'primary_page_id' => ['required', 'integer'],
            'related_page_ids' => ['nullable', 'array', 'max:50'],
            'related_page_ids.*' => ['integer', \App\Support\TenantRule::exists('pages')],
            'program_page_id' => ['nullable', 'integer', \App\Support\TenantRule::exists('pages')],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'location' => ['nullable', 'string', 'max:255'],
            'activity_type' => ['nullable', 'string', 'max:80'],
            'activity_status' => ['nullable', Rule::in(array_keys(ActionVersion::ACTIVITY_STATUSES))],
            'team' => ['nullable', 'array', 'max:100'],
            'team.*.selected' => ['nullable', 'boolean'],
            'team.*.role' => ['nullable', 'string', 'max:120'],
            'partner_ids' => ['nullable', 'array', 'max:100'],
            'partner_ids.*' => ['integer', \App\Support\TenantRule::exists('partners')],
            'objectives' => ['nullable', 'string', 'max:10000'],
            'results' => ['nullable', 'string', 'max:20000'],
            'indicators' => ['nullable', 'array', 'max:30'],
            'indicators.*.label' => ['nullable', 'required_with:indicators.*.value', 'string', 'max:255'],
            'indicators.*.value' => ['nullable', 'numeric', 'between:-999999999999,999999999999'],
            'indicators.*.unit' => ['nullable', 'string', 'max:80'],
            'indicators.*.period' => ['nullable', 'string', 'max:120'],
            'indicators.*.source' => ['nullable', 'string', 'max:500'],
            'indicators.*.is_participation' => ['nullable', 'boolean'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'is_featured' => ['nullable', 'boolean'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'fields' => ['nullable', 'array'],
        ];
        $activeFields = CustomField::query()->active()->get();
        foreach ($activeFields as $field) {
            $key = 'fields.'.$field->id;
            $rules[$key] = match ($field->type) {
                'number' => ['nullable', 'numeric'],
                'date' => ['nullable', 'date'],
                'url' => ['nullable', 'url:http,https', 'max:2048'],
                'select' => ['nullable', Rule::in($field->optionList())],
                default => ['nullable', 'string', 'max:2000'],
            };
        }

        $data = $request->validate($rules, [
            'slug.regex' => 'Use apenas letras minúsculas sem acento, números e hífens (ex.: feira-de-inovacao-2026).',
            'ends_on.after_or_equal' => 'A data de término deve ser igual ou posterior à de início.',
            'indicators.*.label.required_with' => 'Informe o nome do indicador.',
        ], [
            'title' => 'nome', 'summary' => 'resumo', 'description' => 'descrição', 'primary_page_id' => 'área principal',
            'starts_on' => 'data de início', 'ends_on' => 'data de término', 'slug' => 'endereço da página',
        ]);

        // Equipe: só os integrantes marcados, e somente deste portfólio.
        $team = [];
        $validMembers = TeamMember::query()->pluck('id')->all();
        foreach ((array) $request->input('team', []) as $id => $row) {
            if (! empty($row['selected']) && in_array((int) $id, $validMembers, true)) {
                $team[] = ['id' => (int) $id, 'role' => $row['role'] ?? null];
            }
        }
        $data['team'] = $team;
        $data['related_page_ids'] = $data['related_page_ids'] ?? [];
        $data['partner_ids'] = $data['partner_ids'] ?? [];
        $data['indicators'] = $data['indicators'] ?? [];
        $data['is_featured'] = $request->boolean('is_featured');
        // Só campos ativos existentes; campos arquivados mantêm os valores já gravados.
        $data['fields'] = array_intersect_key((array) ($data['fields'] ?? []), array_flip($activeFields->pluck('id')->all()));

        // Destaque é decisão editorial: colaborador não altera.
        if ($request->user()->isCollaborator()) {
            $data['is_featured'] = $version->is_featured;
        }

        return $data;
    }
}
