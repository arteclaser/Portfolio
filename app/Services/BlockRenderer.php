<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\Partner;
use App\Models\Portfolio;
use App\Models\TeamMember;

/**
 * Prepara os blocos de uma página para exibição. O mesmo código atende a página
 * pública (instantâneo publicado) e a prévia do painel (blocos de trabalho).
 */
class BlockRenderer
{
    public function __construct(
        private PublicActions $actions,
        private IndicatorSummary $indicators,
        private LinkPreview $links,
    ) {}

    /**
     * @param  list<array{type: string, settings: array}>  $blocks
     * @return list<array{type: string, view: string, data: array}>
     */
    public function prepare(array $blocks, Page $page, Portfolio $portfolio): array
    {
        $mediaIds = [];
        foreach ($blocks as $block) {
            $mediaIds = array_merge($mediaIds, self::mediaIdsOf($block));
        }
        $media = Media::whereIn('id', array_unique($mediaIds) ?: [0])->get()->keyBy('id');

        $out = [];
        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            if (! array_key_exists($type, PageBlock::TYPES)) {
                continue;
            }
            $settings = $block['settings'] ?? [];
            $data = $this->dataFor($type, $settings, $page, $portfolio, $media);
            if ($data === null) {
                continue; // Bloco sem conteúdo: não aparece.
            }
            $out[] = ['type' => $type, 'view' => 'blocks.'.$type, 'data' => $data + ['heading' => $settings['heading'] ?? null]];
        }

        return $out;
    }

    private function dataFor(string $type, array $s, Page $page, Portfolio $portfolio, $media): ?array
    {
        switch ($type) {
            case 'capa':
                $image = $media[$s['media_id'] ?? 0] ?? $page->cover;

                return ['title' => $s['title'] ?? null ?: $page->title, 'subtitle' => $s['subtitle'] ?? null, 'image' => $image, 'kicker' => $page->kindLabel()];
            case 'apresentacao':
            case 'texto':
                return filled($s['text'] ?? null) ? ['text' => $s['text'], 'kicker' => $s['kicker'] ?? null] : null;
            case 'acoes_destaque':
                $items = $this->actions->featured($portfolio, max(1, min(3, (int) ($s['limit'] ?? 3))), [$page->id]);

                return $items->isNotEmpty() ? ['items' => $items] : null;
            case 'lista_acoes':
                $limit = max(3, min(24, (int) ($s['limit'] ?? 9)));
                $items = $this->actions->withCardRelations($this->actions->inPages($this->actions->query($portfolio), [$page->id]))
                    ->orderByDesc('action_versions.starts_on')->limit($limit)->get();

                return $items->isNotEmpty() ? ['items' => $items, 'page' => $page] : null;
            case 'equipe':
                $members = $page->responsibles()->publiclyVisible()->get();
                $more = TeamMember::publiclyVisible()->current()->where('page_id', $page->id)
                    ->whereNotIn('id', $members->pluck('id')->all() ?: [0])->orderBy('position')->orderBy('name')->get();
                $members = $members->concat($more)->load('photo');

                return $members->isNotEmpty() ? ['members' => $members] : null;
            case 'galeria':
                $items = [];
                foreach ((array) ($s['items'] ?? []) as $item) {
                    if ($m = $media[$item['media_id'] ?? 0] ?? null) {
                        $items[] = ['media' => $m, 'caption' => $item['caption'] ?? $m->caption, 'credit' => $item['credit'] ?? $m->credit, 'alt' => $item['alt'] ?? $m->alt];
                    }
                }

                return $items ? ['items' => $items] : null;
            case 'videos':
            case 'reportagens':
                $items = [];
                foreach ((array) ($s['items'] ?? []) as $item) {
                    if (blank($item['url'] ?? null)) {
                        continue;
                    }
                    $detected = $this->links->detect($item['url']);
                    $items[] = $detected + [
                        'url' => $item['url'],
                        'title' => $item['title'] ?? null,
                        'source' => $item['source'] ?? null,
                        'description' => $item['description'] ?? null,
                        'image' => $media[$item['media_id'] ?? 0] ?? null,
                    ];
                }

                return $items ? ['items' => $items] : null;
            case 'parceiros':
                $query = Partner::publiclyVisible()->where('portfolio_id', $portfolio->id)->with('logo')->orderBy('position')->orderBy('name');
                if (! empty($s['partner_ids'])) {
                    $query->whereIn('id', array_map('intval', (array) $s['partner_ids']));
                }
                $partners = $query->get();

                return $partners->isNotEmpty() ? ['partners' => $partners] : null;
            case 'indicadores':
                $summary = $this->indicators->summarize($this->actions->inPages($this->actions->query($portfolio), [$page->id]));

                return $summary['action_count'] > 0 ? ['summary' => $summary] : null;
            case 'documentos':
                $items = [];
                foreach ((array) ($s['items'] ?? []) as $item) {
                    $file = $media[$item['media_id'] ?? 0] ?? null;
                    $url = $item['url'] ?? null;
                    if (blank($item['title'] ?? null) || (! $file && blank($url))) {
                        continue;
                    }
                    $items[] = ['title' => $item['title'], 'description' => $item['description'] ?? null, 'file' => $file, 'url' => $url];
                }

                return $items ? ['items' => $items] : null;
        }

        return null;
    }

    /** @return list<int> */
    public static function mediaIdsOf(array $block): array
    {
        $s = $block['settings'] ?? [];
        $ids = [];
        if (! empty($s['media_id'])) {
            $ids[] = (int) $s['media_id'];
        }
        foreach ((array) ($s['items'] ?? []) as $item) {
            if (! empty($item['media_id'])) {
                $ids[] = (int) $item['media_id'];
            }
        }

        return $ids;
    }
}
