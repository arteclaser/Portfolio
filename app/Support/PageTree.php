<?php

namespace App\Support;

use App\Models\Page;
use Illuminate\Support\Collection;

/**
 * Árvore de páginas carregada uma vez por requisição. Usada para expandir
 * permissões e filtros às páginas subordinadas.
 */
class PageTree
{
    /** @var array<int, list<int>>|null */
    private ?array $children = null;

    /** @var array<int, int|null> */
    private array $parents = [];

    private function load(): void
    {
        if ($this->children !== null) {
            return;
        }
        $this->children = [];
        foreach (Page::query()->get(['id', 'parent_id']) as $page) {
            $this->parents[$page->id] = $page->parent_id;
            $this->children[$page->parent_id ?? 0][] = $page->id;
        }
    }

    public function flush(): void
    {
        $this->children = null;
        $this->parents = [];
    }

    /**
     * @param  iterable<int>  $ids
     * @return list<int> ids informados + todos os descendentes
     */
    public function withDescendants(iterable $ids): array
    {
        $this->load();
        $result = [];
        $stack = [];
        foreach ($ids as $id) {
            $stack[] = (int) $id;
        }
        while ($stack) {
            $id = array_pop($stack);
            if (isset($result[$id])) {
                continue;
            }
            $result[$id] = true;
            foreach ($this->children[$id] ?? [] as $child) {
                $stack[] = $child;
            }
        }

        return array_keys($result);
    }

    /** @return list<int> ancestrais do mais próximo ao mais distante */
    public function ancestorIds(int $id): array
    {
        $this->load();
        $out = [];
        $guard = 0;
        while (($parent = $this->parents[$id] ?? null) !== null && $guard++ < 50) {
            $out[] = $parent;
            $id = $parent;
        }

        return $out;
    }

    /**
     * Lista achatada em ordem de árvore, com profundidade, para seletores.
     *
     * @param  Collection<int, Page>  $pages
     * @return list<array{page: Page, depth: int}>
     */
    public static function flatten(Collection $pages): array
    {
        $byParent = $pages->groupBy(fn (Page $p) => $p->parent_id ?? 0);
        $out = [];
        $walk = function (int $parentId, int $depth) use (&$walk, &$out, $byParent) {
            foreach (($byParent[$parentId] ?? collect())->sortBy([['position', 'asc'], ['title', 'asc']]) as $page) {
                $out[] = ['page' => $page, 'depth' => $depth];
                if ($depth < 10) {
                    $walk($page->id, $depth + 1);
                }
            }
        };
        $walk(0, 0);
        // Páginas cujo pai não está na coleção (ex.: pai arquivado) entram no fim.
        $seen = array_map(fn ($row) => $row['page']->id, $out);
        foreach ($pages as $page) {
            if (! in_array($page->id, $seen, true)) {
                $out[] = ['page' => $page, 'depth' => 0];
            }
        }

        return $out;
    }
}
