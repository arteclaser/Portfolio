<?php

namespace App\Http\Controllers\Panel;

use App\Models\Action;
use App\Models\ActionLink;
use App\Services\FetchException;
use App\Services\LinkPreview;
use App\Services\MediaException;
use App\Services\MediaStore;
use App\Services\SafeFetcher;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActionLinkController extends PanelController
{
    public function __construct(
        private LinkPreview $preview,
        private SafeFetcher $fetcher,
        private MediaStore $store,
        private ActivityLogger $log,
    ) {}

    public function index(Request $request, Action $action)
    {
        $this->authorize('view', $action);
        $action->load(['workingVersion', 'publishedVersion']);
        $version = $action->currentVersion();
        $version->load('links.image');

        return view('panel.actions.links', [
            'action' => $action,
            'version' => $version,
            'canEdit' => $action->workingVersion !== null && $request->user()->can('update', $action),
            'previewEnabled' => (bool) config('services.link_preview.enabled'),
        ]);
    }

    /** Consulta de prévia (usada pelo formulário via JavaScript). */
    public function preview(Request $request, Action $action)
    {
        $this->authorize('update', $action);
        $data = $request->validate(['url' => ['required', 'string', 'max:2048']]);
        try {
            return response()->json(['ok' => true, 'data' => $this->analyze($data['url'])]);
        } catch (FetchException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function store(Request $request, Action $action)
    {
        $this->authorize('update', $action);
        $version = $action->workingVersion ?? abort(409);
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'kind' => ['nullable', Rule::in(array_keys(ActionLink::KINDS))],
            'title' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'source_name' => ['nullable', 'string', 'max:255'],
        ], [], ['url' => 'endereço']);

        try {
            $analysis = $this->analyze($data['url']);
        } catch (FetchException $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }
        // O que a equipe digitou prevalece sobre o que veio do site.
        foreach (['title', 'description', 'source_name', 'kind'] as $field) {
            if (filled($data[$field] ?? null)) {
                $analysis[$field] = $data[$field];
            }
        }

        $link = $version->links()->create($analysis + [
            'position' => (int) $version->links()->max('position') + 1,
            'fetched_at' => $analysis['preview_status'] === 'manual' ? null : now(),
        ]);
        $this->log->log($request->user(), 'action.link_added', $action, 'Adicionou o link '.$link->url);

        $message = match ($link->preview_status) {
            'ok' => 'Link adicionado com a prévia do site. Revise os dados se quiser.',
            'partial' => 'Link adicionado com prévia parcial. Complete os dados que faltam.',
            default => 'Link adicionado. A prévia automática não está disponível: preencha título e imagem manualmente. O link continuará abrindo a origem.',
        };

        return redirect()->route('panel.actions.links', $action)->with('status', $message);
    }

    public function update(Request $request, Action $action, ActionLink $link)
    {
        $this->authorize('update', $action);
        $this->assertBelongs($action, $link);
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ActionLink::KINDS))],
            'title' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
            'source_name' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'file'],
            'remove_image' => ['nullable', 'boolean'],
        ], [], ['title' => 'título', 'image' => 'imagem']);

        $link->fill([
            'kind' => $data['kind'],
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'source_name' => $data['source_name'] ?? null,
        ]);
        if ($request->boolean('remove_image')) {
            $link->image_media_id = null;
        }
        if ($request->hasFile('image')) {
            try {
                $media = $this->store->storeUploadedImage($request->file('image'), $this->portfolio(), $request->user());
                $link->image_media_id = $media->id;
            } catch (MediaException $e) {
                throw ValidationException::withMessages(['links.'.$link->id.'.image' => $e->getMessage()]);
            }
        }
        if (! $link->fetched_at && $link->preview_status !== 'ok') {
            $link->preview_status = 'manual';
        }
        $link->save();
        $this->log->log($request->user(), 'action.link_updated', $action, 'Editou o link '.$link->url);

        return redirect()->route('panel.actions.links', $action)->with('status', 'Link atualizado.');
    }

    /** Baixa a imagem sugerida pelo site, somente quando a equipe a escolhe. */
    public function image(Request $request, Action $action, ActionLink $link)
    {
        $this->authorize('update', $action);
        $this->assertBelongs($action, $link);
        if (blank($link->suggested_image_url)) {
            return back()->withErrors(['links.'.$link->id.'.image' => 'Este link não tem imagem sugerida.']);
        }
        $portfolio = $this->portfolio();
        $tmp = tempnam(sys_get_temp_dir(), 'lnk');
        try {
            $response = $this->fetcher->get($link->suggested_image_url, ['image/jpeg', 'image/png', 'image/webp'], $portfolio->maxUploadBytes(), 'image/webp,image/jpeg,image/png;q=0.9');
            file_put_contents($tmp, $response['body']);
            $media = $this->store->storeImageFromPath($tmp, $portfolio, $request->user(), basename((string) parse_url($response['url'], PHP_URL_PATH)), $link->suggested_image_url);
            $link->image_media_id = $media->id;
            $link->save();
        } catch (FetchException|MediaException $e) {
            return back()->withErrors(['links.'.$link->id.'.image' => 'Não foi possível usar a imagem sugerida: '.$e->getMessage().' Envie uma imagem manualmente.']);
        } finally {
            @unlink($tmp);
        }
        $this->log->log($request->user(), 'action.link_image', $action, 'Usou a imagem sugerida do link '.$link->url);

        return redirect()->route('panel.actions.links', $action)->with('status', 'Imagem do link salva. Confirme que o uso está autorizado pela fonte.');
    }

    public function move(Request $request, Action $action, ActionLink $link)
    {
        $this->authorize('update', $action);
        $this->assertBelongs($action, $link);
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $links = $action->workingVersion->links()->get()->values();
        $i = $links->search(fn ($l) => $l->id === $link->id);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($links[$j])) {
            [$links[$i], $links[$j]] = [$links[$j], $links[$i]];
        }
        foreach ($links->values() as $pos => $l) {
            $l->update(['position' => $pos]);
        }

        return redirect()->route('panel.actions.links', $action)->with('status', 'Ordem atualizada.');
    }

    public function destroy(Request $request, Action $action, ActionLink $link)
    {
        $this->authorize('update', $action);
        $this->assertBelongs($action, $link);
        $link->delete();
        $this->log->log($request->user(), 'action.link_removed', $action, 'Removeu o link '.$link->url);

        return redirect()->route('panel.actions.links', $action)->with('status', 'Link removido desta versão.');
    }

    private function analyze(string $url): array
    {
        if (! config('services.link_preview.enabled')) {
            $this->fetcher->validateUrl($url);

            return $this->preview->detect($url) + [
                'url' => $url, 'title' => null, 'description' => null, 'source_name' => null, 'suggested_image_url' => null,
                'preview_status' => 'manual', 'preview_message' => 'A consulta automática de prévias está desativada nas configurações do servidor.',
            ];
        }

        return $this->preview->analyze($url);
    }

    private function assertBelongs(Action $action, ActionLink $link): void
    {
        abort_unless($action->working_version_id && $link->action_version_id === $action->working_version_id, 404);
    }
}
