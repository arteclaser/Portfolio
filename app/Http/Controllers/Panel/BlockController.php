<?php

namespace App\Http\Controllers\Panel;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Partner;
use App\Services\MediaException;
use App\Services\MediaStore;
use App\Services\PagePublisher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Editor de blocos: adicionar, configurar, ordenar, ocultar e remover. */
class BlockController extends PanelController
{
    public function __construct(private PagePublisher $publisher, private MediaStore $store) {}

    public function index(Page $page)
    {
        $this->authorize('view', $page);

        return view('panel.pages.blocks', ['page' => $page, 'blocks' => $page->blocks()->get()]);
    }

    public function store(Request $request, Page $page)
    {
        $this->authorize('updateContent', $page);
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(PageBlock::TYPES))]]);
        $block = $page->blocks()->create([
            'type' => $data['type'],
            'position' => (int) $page->blocks()->max('position') + 1,
            'settings' => [],
        ]);
        $this->publisher->markChanged($page, $request->user(), 'Adicionou o bloco '.$block->typeLabel());

        return redirect()->route('panel.pages.blocks.edit', [$page, $block])->with('status', 'Bloco adicionado. Configure o conteúdo abaixo.');
    }

    public function edit(Page $page, PageBlock $block)
    {
        $this->authorize('view', $page);
        abort_unless($block->page_id === $page->id, 404);

        return view('panel.pages.block-edit', [
            'page' => $page,
            'block' => $block,
            'images' => Media::where('kind', 'image')->latest('id')->limit(120)->get(),
            'documents' => Media::where('kind', 'document')->latest('id')->limit(120)->get(),
            'partners' => Partner::whereNull('archived_at')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Page $page, PageBlock $block)
    {
        $this->authorize('updateContent', $page);
        abort_unless($block->page_id === $page->id, 404);

        $settings = ['heading' => $request->validate(['heading' => ['nullable', 'string', 'max:255']])['heading'] ?? null];
        $settings += match ($block->type) {
            'capa' => $this->coverSettings($request),
            'apresentacao' => $request->validate(['kicker' => ['nullable', 'string', 'max:120'], 'text' => ['nullable', 'string', 'max:20000']]),
            'texto' => $request->validate(['text' => ['nullable', 'string', 'max:30000']]),
            'acoes_destaque' => $request->validate(['limit' => ['required', 'integer', 'between:1,3']]),
            'lista_acoes' => $request->validate(['limit' => ['required', 'integer', 'between:3,24']]),
            'galeria' => $this->gallerySettings($request),
            'videos' => ['items' => $this->linkItems($request, false)],
            'reportagens' => ['items' => $this->linkItems($request, true)],
            'parceiros' => ['partner_ids' => array_map('intval', $request->validate(['partner_ids' => ['nullable', 'array'], 'partner_ids.*' => ['integer']])['partner_ids'] ?? [])],
            'documentos' => ['items' => $this->documentItems($request)],
            default => [],
        };
        $block->settings = $settings;
        $block->save();
        $this->publisher->markChanged($page, $request->user(), 'Editou o bloco '.$block->typeLabel());

        return redirect()->route('panel.pages.blocks', $page)->with('status', 'Bloco salvo. Confira a prévia e publique para levar ao site.');
    }

    public function move(Request $request, Page $page, PageBlock $block)
    {
        $this->authorize('updateContent', $page);
        abort_unless($block->page_id === $page->id, 404);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $blocks = $page->blocks()->get()->values();
        $i = $blocks->search(fn ($b) => $b->id === $block->id);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if (isset($blocks[$j])) {
            [$blocks[$i], $blocks[$j]] = [$blocks[$j], $blocks[$i]];
        }
        foreach ($blocks->values() as $pos => $b) {
            $b->update(['position' => $pos]);
        }
        $this->publisher->markChanged($page, $request->user(), 'Reordenou os blocos');

        return redirect()->route('panel.pages.blocks', $page)->with('status', 'Ordem dos blocos atualizada.');
    }

    public function toggle(Request $request, Page $page, PageBlock $block)
    {
        $this->authorize('updateContent', $page);
        abort_unless($block->page_id === $page->id, 404);
        $block->update(['is_hidden' => ! $block->is_hidden]);
        $this->publisher->markChanged($page, $request->user(), ($block->is_hidden ? 'Ocultou' : 'Exibiu').' o bloco '.$block->typeLabel());

        return redirect()->route('panel.pages.blocks', $page)->with('status', $block->is_hidden ? 'Bloco ocultado.' : 'Bloco visível novamente.');
    }

    public function destroy(Request $request, Page $page, PageBlock $block)
    {
        $this->authorize('updateContent', $page);
        abort_unless($block->page_id === $page->id, 404);
        $block->delete();
        $this->publisher->markChanged($page, $request->user(), 'Removeu o bloco '.$block->typeLabel());

        return redirect()->route('panel.pages.blocks', $page)->with('status', 'Bloco removido. A versão publicada continua igual até você publicar.');
    }

    private function coverSettings(Request $request): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
            'upload' => ['nullable', 'file'],
        ]);
        if ($request->hasFile('upload')) {
            $data['media_id'] = $this->upload($request->file('upload'), $request, 'upload')->id;
        }
        unset($data['upload']);

        return $data;
    }

    private function gallerySettings(Request $request): array
    {
        $data = $request->validate([
            'media_ids' => ['nullable', 'array', 'max:60'],
            'media_ids.*' => ['integer', Rule::exists('media', 'id')],
            'uploads' => ['nullable', 'array', 'max:20'],
            'uploads.*' => ['file'],
        ]);
        $ids = array_map('intval', $data['media_ids'] ?? []);
        foreach ((array) $request->file('uploads', []) as $file) {
            $ids[] = $this->upload($file, $request, 'uploads')->id;
        }

        return ['items' => array_map(fn ($id) => ['media_id' => $id], array_values(array_unique($ids)))];
    }

    private function linkItems(Request $request, bool $withExtra): array
    {
        $rows = $request->validate([
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.url' => ['nullable', 'url:http,https', 'max:2048'],
            'items.*.title' => ['nullable', 'string', 'max:500'],
            'items.*.source' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
        ], [], ['items.*.url' => 'endereço'])['items'] ?? [];
        $items = [];
        foreach ($rows as $row) {
            if (blank($row['url'] ?? null)) {
                continue;
            }
            $item = ['url' => $row['url'], 'title' => $row['title'] ?? null];
            if ($withExtra) {
                $item += ['source' => $row['source'] ?? null, 'description' => $row['description'] ?? null, 'media_id' => isset($row['media_id']) ? (int) $row['media_id'] : null];
            }
            $items[] = $item;
        }

        return $items;
    }

    private function documentItems(Request $request): array
    {
        $rows = $request->validate([
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:500'],
            'items.*.url' => ['nullable', 'url:http,https', 'max:2048'],
            'items.*.media_id' => ['nullable', 'integer', Rule::exists('media', 'id')],
        ], [], ['items.*.url' => 'endereço', 'items.*.title' => 'título'])['items'] ?? [];
        $items = [];
        foreach ($rows as $i => $row) {
            if (blank($row['title'] ?? null) && blank($row['url'] ?? null) && blank($row['media_id'] ?? null)) {
                continue;
            }
            if (blank($row['title'] ?? null)) {
                throw ValidationException::withMessages(["items.{$i}.title" => 'Informe o título do documento.']);
            }
            $items[] = ['title' => $row['title'], 'description' => $row['description'] ?? null, 'url' => $row['url'] ?? null, 'media_id' => isset($row['media_id']) ? (int) $row['media_id'] : null];
        }

        return $items;
    }

    private function upload($file, Request $request, string $field): Media
    {
        try {
            return $this->store->storeUploadedImage($file, $this->portfolio(), $request->user());
        } catch (MediaException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }
}
