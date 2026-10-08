<?php

namespace App\Http\Controllers\Panel;

use App\Models\Media;
use App\Services\MediaException;
use App\Services\MediaStore;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MediaLibraryController extends PanelController
{
    public function __construct(private MediaStore $store, private ActivityLogger $log) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Media::class);
        $user = $request->user();
        $query = Media::query()->with('uploader')->latest('id');
        if ($user->isCollaborator()) {
            $query->where('uploaded_by', $user->id);
        }
        $kind = $request->query('tipo');
        if (in_array($kind, ['image', 'document'], true)) {
            $query->where('kind', $kind);
        }

        return view('panel.media.index', [
            'items' => $query->paginate(36)->withQueryString(),
            'kind' => $kind,
            'maxBytes' => $this->portfolio()->maxUploadBytes(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Media::class);
        $request->validate(['files' => ['required', 'array', 'max:20'], 'files.*' => ['file']], ['files.required' => 'Escolha ao menos um arquivo.']);
        $saved = 0;
        $errors = [];
        foreach ($request->file('files') as $file) {
            try {
                $isPdf = strtolower($file->getClientOriginalExtension()) === 'pdf' || $file->getMimeType() === 'application/pdf';
                $isPdf
                    ? $this->store->storeUploadedDocument($file, $this->portfolio(), $request->user())
                    : $this->store->storeUploadedImage($file, $this->portfolio(), $request->user());
                $saved++;
            } catch (MediaException $e) {
                $errors[] = $file->getClientOriginalName().': '.$e->getMessage();
            }
        }
        if ($saved) {
            $this->log->log($request->user(), 'media.uploaded', null, "Enviou {$saved} arquivo(s) para a biblioteca");
        }
        $redirect = redirect()->route('panel.media.index');
        if ($saved) {
            $redirect->with('status', $saved === 1 ? '1 arquivo enviado.' : "{$saved} arquivos enviados.");
        }

        return $errors ? $redirect->withErrors(['files' => $errors]) : $redirect;
    }

    public function edit(Media $media)
    {
        $this->authorize('view', $media);

        return view('panel.media.edit', ['media' => $media, 'usage' => $this->usage($media)]);
    }

    public function update(Request $request, Media $media)
    {
        $this->authorize('update', $media);
        $data = $request->validate([
            'alt' => ['nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:500'],
            'credit' => ['nullable', 'string', 'max:255'],
            'focal_x' => ['nullable', 'integer', 'between:0,100'],
            'focal_y' => ['nullable', 'integer', 'between:0,100'],
        ]);
        $media->fill($data + ['focal_x' => 50, 'focal_y' => 50])->save();

        return redirect()->route('panel.media.edit', $media)->with('status', 'Informações salvas.');
    }

    public function destroy(Request $request, Media $media)
    {
        $this->authorize('delete', $media);
        $usage = $this->usage($media);
        if ($usage) {
            return back()->withErrors(['media' => 'Este arquivo está em uso e não pode ser excluído: '.implode('; ', $usage).'.']);
        }
        $name = $media->original_name;
        $this->store->delete($media);
        $this->log->log($request->user(), 'media.deleted', null, 'Excluiu o arquivo '.$name);

        return redirect()->route('panel.media.index')->with('status', 'Arquivo excluído.');
    }

    /** @return list<string> descrições de onde o arquivo é usado (inclui versões antigas e páginas). */
    private function usage(Media $media): array
    {
        $out = [];
        $versions = DB::table('action_media')->where('media_id', $media->id)->count()
            + DB::table('action_links')->where('image_media_id', $media->id)->count();
        if ($versions) {
            $out[] = "{$versions} uso(s) em versões de ações";
        }
        if ($n = DB::table('team_members')->where('photo_media_id', $media->id)->count()) {
            $out[] = "foto de {$n} integrante(s)";
        }
        if ($n = DB::table('partners')->where('logo_media_id', $media->id)->count()) {
            $out[] = "logotipo de {$n} parceiro(s)";
        }
        if ($n = DB::table('pages')->where('cover_media_id', $media->id)->count()) {
            $out[] = "capa de {$n} página(s)";
        }
        $inBlocks = DB::table('page_blocks')->pluck('settings')->filter(fn ($s) => $this->jsonUses($s, $media->id))->count();
        $inSnapshots = DB::table('pages')->pluck('published_snapshot')->filter(fn ($s) => $this->jsonUses($s, $media->id))->count()
            + DB::table('page_revisions')->pluck('snapshot')->filter(fn ($s) => $this->jsonUses($s, $media->id))->count();
        if ($inBlocks || $inSnapshots) {
            $out[] = 'blocos ou histórico de páginas';
        }

        return $out;
    }

    private function jsonUses(?string $json, int $id): bool
    {
        $data = json_decode((string) $json, true);
        if (! is_array($data)) {
            return false;
        }
        $found = false;
        array_walk_recursive($data, function ($value, $key) use ($id, &$found) {
            if (in_array($key, ['media_id', 'cover_media_id'], true) && (int) $value === $id) {
                $found = true;
            }
        });

        return $found || in_array($id, array_map('intval', $data['media_ids'] ?? []), true);
    }
}
