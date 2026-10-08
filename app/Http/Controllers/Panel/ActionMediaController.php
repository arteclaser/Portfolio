<?php

namespace App\Http\Controllers\Panel;

use App\Models\Action;
use App\Models\ActionMedia;
use App\Services\MediaException;
use App\Services\MediaStore;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActionMediaController extends PanelController
{
    public function __construct(private MediaStore $store, private ActivityLogger $log) {}

    public function index(Request $request, Action $action)
    {
        $this->authorize('view', $action);
        $action->load(['workingVersion', 'publishedVersion']);
        $version = $action->currentVersion();
        $version->load('media.media');
        $portfolio = $this->portfolio();

        return view('panel.actions.media', [
            'action' => $action,
            'version' => $version,
            'canEdit' => $action->workingVersion !== null && $request->user()->can('update', $action),
            'maxImages' => $portfolio->maxImagesPerAction(),
            'maxBytes' => $portfolio->maxUploadBytes(),
            'serverLimitBytes' => self::serverUploadLimit(),
        ]);
    }

    /** Envio múltiplo. Os limites valem aqui, no servidor, independentemente da interface. */
    public function store(Request $request, Action $action)
    {
        $this->authorize('update', $action);
        $version = $action->workingVersion ?? abort(409);
        $portfolio = $this->portfolio();
        $max = $portfolio->maxImagesPerAction();

        $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['file'],
        ], ['photos.required' => 'Escolha ao menos uma imagem.'], ['photos' => 'imagens']);

        $files = $request->file('photos');
        $current = $version->media()->count();
        if ($current + count($files) > $max) {
            $free = max(0, $max - $current);
            $message = "Limite de {$max} imagens por ação (incluindo a capa). Esta ação já tem {$current}; ".
                ($free > 0 ? "você pode enviar mais {$free}." : 'remova alguma imagem antes de enviar outra.');

            return $this->respond($request, $action, false, $message, [], 422);
        }

        $saved = 0;
        $errors = [];
        $position = (int) $version->media()->max('position');
        $hasCover = $version->media()->where('is_cover', true)->exists();
        foreach ($files as $file) {
            try {
                $media = $this->store->storeUploadedImage($file, $portfolio, $request->user());
                $version->media()->create([
                    'media_id' => $media->id,
                    'position' => ++$position,
                    'is_cover' => ! $hasCover && $saved === 0,
                ]);
                $saved++;
            } catch (MediaException $e) {
                $errors[] = $file->getClientOriginalName().': '.$e->getMessage();
            }
        }
        if ($saved) {
            $this->log->log($request->user(), 'action.media_uploaded', $action, "Enviou {$saved} imagem(ns) para a versão {$version->number}");
        }

        $message = $saved ? ($saved === 1 ? '1 imagem enviada.' : "{$saved} imagens enviadas.") : 'Nenhuma imagem foi enviada.';

        return $this->respond($request, $action, $saved > 0, $message, $errors, $saved ? 200 : 422);
    }

    /** Salva legendas, créditos, descrições e enquadramento; e aplica a operação pedida. */
    public function update(Request $request, Action $action)
    {
        $this->authorize('update', $action);
        $version = $action->workingVersion ?? abort(409);
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.caption' => ['nullable', 'string', 'max:500'],
            'items.*.credit' => ['nullable', 'string', 'max:255'],
            'items.*.alt' => ['nullable', 'string', 'max:500'],
            'items.*.focal_x' => ['nullable', 'integer', 'between:0,100'],
            'items.*.focal_y' => ['nullable', 'integer', 'between:0,100'],
            'op' => ['nullable', 'string', 'regex:/^(cover|up|down|remove):\d+$/'],
        ]);

        DB::transaction(function () use ($version, $data) {
            $items = $version->media()->get()->keyBy('id');
            foreach ((array) ($data['items'] ?? []) as $id => $fields) {
                if ($item = $items->get((int) $id)) {
                    $item->fill([
                        'caption' => $fields['caption'] ?? null,
                        'credit' => $fields['credit'] ?? null,
                        'alt' => $fields['alt'] ?? null,
                        'focal_x' => (int) ($fields['focal_x'] ?? 50),
                        'focal_y' => (int) ($fields['focal_y'] ?? 50),
                    ])->save();
                }
            }
            if (! empty($data['op'])) {
                [$op, $id] = explode(':', $data['op']);
                $this->applyOperation($version->media()->get(), $op, (int) $id);
            }
        });
        $this->log->log($request->user(), 'action.media_updated', $action, 'Atualizou as fotos da versão '.$version->number);

        $status = match (explode(':', (string) ($data['op'] ?? ''))[0]) {
            'cover' => 'Capa definida.',
            'remove' => 'Imagem removida desta versão.',
            'up', 'down' => 'Ordem atualizada.',
            default => 'Informações das fotos salvas.',
        };

        return redirect()->route('panel.actions.media', $action)->with('status', $status);
    }

    private function applyOperation($items, string $op, int $id): void
    {
        $ordered = $items->sortBy([['is_cover', 'desc'], ['position', 'asc'], ['id', 'asc']])->values();
        $index = $ordered->search(fn (ActionMedia $m) => $m->id === $id);
        if ($index === false) {
            return;
        }
        if ($op === 'remove') {
            $wasCover = $ordered[$index]->is_cover;
            $ordered[$index]->delete();
            $ordered->forget($index);
            $ordered = $ordered->values();
            if ($wasCover && $ordered->isNotEmpty()) {
                $ordered[0]->is_cover = true;
            }
        } elseif ($op === 'cover') {
            foreach ($ordered as $m) {
                $m->is_cover = $m->id === $id;
            }
            $cover = $ordered->pull($index);
            $ordered = collect([$cover])->concat($ordered)->values();
        } elseif ($op === 'up' && $index > 0) {
            [$ordered[$index - 1], $ordered[$index]] = [$ordered[$index], $ordered[$index - 1]];
        } elseif ($op === 'down' && $index < $ordered->count() - 1) {
            [$ordered[$index + 1], $ordered[$index]] = [$ordered[$index], $ordered[$index + 1]];
        }
        // A capa é sempre a primeira imagem.
        $ordered = $ordered->values();
        foreach ($ordered as $i => $m) {
            $m->position = $i;
            $m->is_cover = $i === 0;
            $m->save();
        }
    }

    private function respond(Request $request, Action $action, bool $ok, string $message, array $errors, int $status)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message, 'errors' => $errors, 'redirect' => route('panel.actions.media', $action)], $status);
        }
        $redirect = redirect()->route('panel.actions.media', $action);
        if ($ok) {
            $redirect->with('status', $message);
        }
        if (! $ok || $errors) {
            $redirect->withErrors(['photos' => array_merge($ok ? [] : [$message], $errors)]);
        }

        return $redirect;
    }

    public static function serverUploadLimit(): int
    {
        $toBytes = function (string $v): int {
            $v = trim($v);
            $n = (float) $v;

            return (int) match (strtolower(substr($v, -1))) {
                'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n,
            };
        };
        $limits = array_filter([$toBytes((string) ini_get('upload_max_filesize')), $toBytes((string) ini_get('post_max_size'))]);

        return $limits ? min($limits) : 0;
    }
}
