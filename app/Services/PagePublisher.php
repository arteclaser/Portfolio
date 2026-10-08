<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Páginas também têm conteúdo de trabalho e conteúdo publicado: os blocos são
 * editados livremente e só chegam ao público quando alguém autorizado publica.
 */
class PagePublisher
{
    public function __construct(private ActivityLogger $log) {}

    /** Instantâneo dos blocos visíveis de trabalho. */
    public function workingSnapshot(Page $page): array
    {
        $page->loadMissing('blocks');
        $blocks = [];
        $mediaIds = $page->cover_media_id ? [(int) $page->cover_media_id] : [];
        foreach ($page->blocks as $block) {
            if ($block->is_hidden) {
                continue;
            }
            $entry = ['type' => $block->type, 'settings' => $block->settings ?? []];
            $mediaIds = array_merge($mediaIds, BlockRenderer::mediaIdsOf($entry));
            $blocks[] = $entry;
        }

        return [
            'description' => $page->description,
            'cover_media_id' => $page->cover_media_id,
            'blocks' => $blocks,
            'media_ids' => array_values(array_unique($mediaIds)),
        ];
    }

    public function publish(Page $page, ?User $user, ?string $note = null): void
    {
        DB::transaction(function () use ($page, $user, $note) {
            $snapshot = $this->workingSnapshot($page);
            $page->published_snapshot = $snapshot;
            $page->published_at = now();
            $page->published_by = $user?->id;
            $page->has_unpublished_changes = false;
            $page->save();
            PageRevision::create(['page_id' => $page->id, 'snapshot' => $snapshot, 'published_by' => $user?->id, 'note' => $note]);
        });
        app(MediaVisibility::class)->flush();
        $this->log->log($user, 'page.published', $page, 'Publicou a página "'.$page->title.'"');
    }

    /** Traz o conteúdo de uma revisão de volta para os blocos de trabalho (sem publicar). */
    public function restoreRevision(Page $page, PageRevision $revision, User $user): void
    {
        abort_unless($revision->page_id === $page->id, 404);
        DB::transaction(function () use ($page, $revision) {
            $snapshot = $revision->snapshot ?? [];
            $page->blocks()->delete();
            foreach (array_values($snapshot['blocks'] ?? []) as $i => $block) {
                $page->blocks()->create(['type' => $block['type'], 'settings' => $block['settings'] ?? [], 'position' => $i]);
            }
            $page->description = $snapshot['description'] ?? null;
            $page->cover_media_id = $snapshot['cover_media_id'] ?? null;
            $page->has_unpublished_changes = true;
            $page->save();
        });
        $this->log->log($user, 'page.revision_restored', $page, 'Restaurou para edição a revisão de '.$revision->created_at?->format('d/m/Y H:i'));
    }

    public function markChanged(Page $page, User $user, string $what): void
    {
        $page->has_unpublished_changes = true;
        $page->updated_by = $user->id;
        $page->save();
        $this->log->log($user, 'page.changed', $page, $what.' em "'.$page->title.'"');
    }
}
