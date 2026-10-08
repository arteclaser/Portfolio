<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Portfolio;
use App\Support\Access;
use App\Support\PageTree;
use Illuminate\Support\Collection;

abstract class PanelController extends Controller
{
    protected function portfolio(): Portfolio
    {
        return Portfolio::current();
    }

    /** Páginas ativas que o usuário pode usar como área principal. */
    protected function allowedPages(): Collection
    {
        $allowed = app(Access::class)->allowedPageIds(request()->user());
        $query = Page::query()->active()->where('portfolio_id', $this->portfolio()->id);
        if ($allowed !== null) {
            $query->whereIn('id', $allowed ?: [0]);
        }

        return $query->orderBy('position')->orderBy('title')->get();
    }

    /** @return array<int|string, string> opções com recuo por nível */
    protected function pageOptions(Collection $pages, ?string $empty = null): array
    {
        $options = $empty !== null ? ['' => $empty] : [];
        foreach (PageTree::flatten($pages) as $row) {
            $options[$row['page']->id] = str_repeat('— ', $row['depth']).$row['page']->title.($row['page']->kind !== 'area' ? ' ('.$row['page']->kindLabel().')' : '');
        }

        return $options;
    }
}
