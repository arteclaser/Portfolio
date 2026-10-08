<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\DB;

/**
 * Um arquivo só é público quando está em uso por conteúdo publicado. Rascunhos,
 * versões de trabalho e fotos de pessoas ocultas continuam privados.
 */
class MediaVisibility
{
    /** @var array<int, bool> */
    private array $cache = [];

    public function isPublic(Media $media): bool
    {
        return $this->cache[$media->id] ??= $this->compute($media);
    }

    private function compute(Media $media): bool
    {
        $id = $media->id;

        $inPublishedAction = DB::table('action_media')
            ->join('actions', 'actions.published_version_id', '=', 'action_media.action_version_id')
            ->where('action_media.media_id', $id)
            ->whereNull('actions.archived_at')->whereNull('actions.deleted_at')
            ->exists();
        if ($inPublishedAction) {
            return true;
        }

        $inPublishedLink = DB::table('action_links')
            ->join('actions', 'actions.published_version_id', '=', 'action_links.action_version_id')
            ->where('action_links.image_media_id', $id)
            ->whereNull('actions.archived_at')->whereNull('actions.deleted_at')
            ->exists();
        if ($inPublishedLink) {
            return true;
        }

        if (DB::table('team_members')->where('photo_media_id', $id)->where('is_public', true)->whereNull('archived_at')->exists()) {
            return true;
        }
        if (DB::table('partners')->where('logo_media_id', $id)->where('is_public', true)->whereNull('archived_at')->exists()) {
            return true;
        }

        // Páginas publicadas: a lista de mídias usadas é gravada no instantâneo publicado.
        $snapshots = DB::table('pages')->whereNull('archived_at')->whereNotNull('published_at')->pluck('published_snapshot');
        foreach ($snapshots as $json) {
            $snapshot = json_decode((string) $json, true);
            if (in_array($id, array_map('intval', $snapshot['media_ids'] ?? []), true)) {
                return true;
            }
        }

        return false;
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
