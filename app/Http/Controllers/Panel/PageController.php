<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\PublicController;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\TeamMember;
use App\Services\BlockRenderer;
use App\Services\MediaException;
use App\Services\MediaStore;
use App\Services\PagePublisher;
use App\Support\Access;
use App\Support\ActivityLogger;
use App\Support\PageTree;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PageController extends PanelController
{
    public function __construct(
        private PagePublisher $publisher,
        private ActivityLogger $log,
        private Access $access,
        private MediaStore $store,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Page::class);
        $query = Page::query()->where('portfolio_id', $this->portfolio()->id)->withCount('blocks');
        $allowed = $this->access->allowedPageIds($request->user());
        if ($allowed !== null) {
            $query->whereIn('id', $allowed ?: [0]);
        }
        $showArchived = $request->boolean('arquivadas');
        if (! $showArchived) {
            $query->whereNull('archived_at');
        }

        return view('panel.pages.index', [
            'rows' => PageTree::flatten($query->orderBy('position')->orderBy('title')->get()),
            'showArchived' => $showArchived,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Page::class);

        return view('panel.pages.form', $this->formData(new Page(['kind' => 'area', 'show_in_menu' => true])));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Page::class);
        $data = $this->validateStructure($request, null);
        $page = new Page($data);
        $page->portfolio_id = $this->portfolio()->id;
        $page->created_by = $request->user()->id;
        $page->updated_by = $request->user()->id;
        $page->has_unpublished_changes = true;
        $page->save();
        $page->blocks()->create(['type' => 'lista_acoes', 'position' => 0, 'settings' => ['heading' => 'Ações', 'limit' => 9]]);
        $this->syncResponsibles($request, $page);
        $this->log->log($request->user(), 'page.created', $page, 'Criou a página "'.$page->title.'"');

        return redirect()->route('panel.pages.blocks', $page)->with('status', 'Página criada. Monte os blocos, confira a prévia e publique.');
    }

    public function edit(Request $request, Page $page)
    {
        $this->authorize('view', $page);

        return view('panel.pages.form', $this->formData($page) + [
            'canStructure' => $request->user()->can('manageStructure', $page),
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $this->authorize('updateContent', $page);
        $user = $request->user();
        $contentChanged = false;

        if ($user->can('manageStructure', $page)) {
            $data = $this->validateStructure($request, $page);
            $page->fill($data);
            $this->syncResponsibles($request, $page);
        }

        $content = $request->validate([
            'description' => ['nullable', 'string', 'max:20000'],
            'cover' => ['nullable', 'file'],
            'remove_cover' => ['nullable', 'boolean'],
        ]);
        if (($content['description'] ?? null) !== $page->description) {
            $page->description = $content['description'] ?? null;
            $contentChanged = true;
        }
        if ($request->boolean('remove_cover') && $page->cover_media_id) {
            $page->cover_media_id = null;
            $contentChanged = true;
        }
        if ($request->hasFile('cover')) {
            try {
                $page->cover_media_id = $this->store->storeUploadedImage($request->file('cover'), $this->portfolio(), $user)->id;
                $contentChanged = true;
            } catch (MediaException $e) {
                throw ValidationException::withMessages(['cover' => $e->getMessage()]);
            }
        }
        $page->updated_by = $user->id;
        if ($contentChanged) {
            $page->has_unpublished_changes = true;
        }
        $page->save();
        $this->log->log($user, 'page.updated', $page, 'Atualizou as configurações da página "'.$page->title.'"');

        return redirect()->route('panel.pages.edit', $page)->with('status', $contentChanged
            ? 'Configurações salvas. A descrição e a capa só mudam no site depois de publicar a página.'
            : 'Configurações salvas.');
    }

    public function archive(Request $request, Page $page)
    {
        $this->authorize('delete', $page);
        $page->archived_at = now();
        $page->save();
        $this->log->log($request->user(), 'page.archived', $page, 'Arquivou a página "'.$page->title.'"');

        return redirect()->route('panel.pages.index')->with('status', 'Página arquivada. Ela saiu do site, mas as ações e o histórico foram preservados.');
    }

    public function unarchive(Request $request, Page $page)
    {
        $this->authorize('delete', $page);
        $page->archived_at = null;
        $page->save();
        $this->log->log($request->user(), 'page.unarchived', $page, 'Reativou a página "'.$page->title.'"');

        return back()->with('status', 'Página reativada.');
    }

    public function publish(Request $request, Page $page)
    {
        $this->authorize('updateContent', $page);
        abort_if($page->isArchived(), 409);
        $this->publisher->publish($page, $request->user(), $request->input('note'));

        return back()->with('status', 'Página publicada.');
    }

    public function preview(Page $page)
    {
        $this->authorize('view', $page);

        return view('panel.pages.preview', ['page' => $page]);
    }

    public function previewContent(Page $page, BlockRenderer $renderer)
    {
        $this->authorize('view', $page);

        return view('public.page', PublicController::pageViewData($page, $this->publisher->workingSnapshot($page), $this->portfolio(), $renderer) + ['preview' => true]);
    }

    public function history(Page $page)
    {
        $this->authorize('view', $page);

        return view('panel.pages.history', ['page' => $page, 'revisions' => $page->revisions()->with('publisher')->limit(50)->get()]);
    }

    public function restoreRevision(Request $request, Page $page, PageRevision $revision)
    {
        $this->authorize('updateContent', $page);
        $this->publisher->restoreRevision($page, $revision, $request->user());

        return redirect()->route('panel.pages.blocks', $page)->with('status', 'Conteúdo da revisão trazido de volta para edição. Publique para que ele volte ao site.');
    }

    private function formData(Page $page): array
    {
        $pages = Page::query()->where('portfolio_id', $this->portfolio()->id)->active()
            ->when($page->exists, fn ($q) => $q->whereNotIn('id', app(PageTree::class)->withDescendants([$page->id])))
            ->orderBy('position')->orderBy('title')->get();

        return [
            'page' => $page,
            'parentOptions' => $this->pageOptions($pages, 'Nenhuma (página principal)'),
            'members' => TeamMember::query()->whereNull('archived_at')->orderBy('name')->get(),
            'responsibleIds' => $page->exists ? $page->responsibles()->pluck('team_members.id')->all() : [],
            'canStructure' => true,
        ];
    }

    private function validateStructure(Request $request, ?Page $page): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'menu_title' => ['nullable', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'kind' => ['required', Rule::in(array_keys(Page::KINDS))],
            'parent_id' => ['nullable', 'integer', Rule::exists('pages', 'id')],
            'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['nullable', 'integer', 'between:0,9999'],
            'show_in_menu' => ['nullable', 'boolean'],
        ], ['slug.regex' => 'Use letras minúsculas sem acento, números e hífens.', 'accent_color.regex' => 'Use uma cor no formato #RRGGBB.'],
            ['title' => 'título', 'slug' => 'endereço', 'kind' => 'tipo', 'parent_id' => 'página superior']);

        if ($page && ! empty($data['parent_id']) && in_array((int) $data['parent_id'], app(PageTree::class)->withDescendants([$page->id]), true)) {
            throw ValidationException::withMessages(['parent_id' => 'Uma página não pode ficar abaixo dela mesma ou de uma subordinada.']);
        }

        $base = Slug::make(filled($data['slug'] ?? null) ? $data['slug'] : $data['title'], 'pagina');
        $data['slug'] = Slug::unique($base, fn ($slug) => Page::where('portfolio_id', $this->portfolio()->id)
            ->where('slug', $slug)->when($page, fn ($q) => $q->where('id', '!=', $page->id))->exists());
        $data['position'] = (int) ($data['position'] ?? 0);
        $data['show_in_menu'] = $request->boolean('show_in_menu');
        $data['parent_id'] = $data['parent_id'] ?? null;
        $data['accent_color'] = $data['accent_color'] ?? null;

        return $data;
    }

    private function syncResponsibles(Request $request, Page $page): void
    {
        $ids = array_map('intval', (array) $request->input('responsible_ids', []));
        $sync = [];
        foreach (array_values(array_unique($ids)) as $i => $id) {
            $sync[$id] = ['position' => $i];
        }
        $page->responsibles()->sync($sync);
    }
}
