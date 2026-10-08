<?php

namespace App\Http\Controllers;

use App\Models\ActionVersion;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Portfolio;
use App\Models\TeamMember;
use App\Services\BlockRenderer;
use App\Services\IndicatorSummary;
use App\Services\PublicActions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PublicController extends Controller
{
    public function __construct(
        private PublicActions $actions,
        private IndicatorSummary $indicators,
    ) {}

    private function portfolio(): Portfolio
    {
        return Portfolio::current();
    }

    /** @return Collection<int, Page> */
    private function rootAreas(Portfolio $portfolio): Collection
    {
        return Page::publiclyVisible()->where('portfolio_id', $portfolio->id)
            ->whereNull('parent_id')->where('kind', 'area')
            ->orderBy('position')->orderBy('title')->get();
    }

    public function home(Request $request)
    {
        $portfolio = $this->portfolio();
        $areas = $this->rootAreas($portfolio);
        $activeArea = $areas->firstWhere('slug', (string) $request->query('area'));
        $totalPublished = $this->actions->query($portfolio)->count();

        $featured = $this->actions->featured($portfolio, 3);
        $gridQuery = $this->actions->withCardRelations($this->actions->filtered($portfolio, ['area' => $activeArea?->id]));
        if (! $activeArea && $totalPublished > 6) {
            $gridQuery->whereNotIn('action_versions.id', $featured->pluck('id')->all() ?: [0]);
        }
        $grid = $gridQuery->orderByDesc('action_versions.starts_on')->orderByDesc('action_versions.id')->limit(6)->get();

        return view('public.home', [
            'portfolio' => $portfolio,
            'areas' => $areas,
            'activeArea' => $activeArea,
            'featured' => $featured,
            'grid' => $grid,
            'totalPublished' => $totalPublished,
            'years' => $this->actions->years($portfolio),
            'summary' => $this->indicators->summarize($this->actions->query($portfolio)),
            'team' => TeamMember::publiclyVisible()->current()->where('portfolio_id', $portfolio->id)
                ->with(['photo', 'page'])->orderBy('position')->orderBy('name')->limit(8)->get(),
            'partners' => Partner::publiclyVisible()->where('portfolio_id', $portfolio->id)
                ->with('logo')->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function actions(Request $request)
    {
        $portfolio = $this->portfolio();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'area' => ['nullable', 'string', 'max:120'],
            'ano' => ['nullable', 'integer', 'between:1900,2200'],
            'tipo' => ['nullable', 'string', 'max:80'],
            'situacao' => ['nullable', 'in:'.implode(',', array_keys(ActionVersion::ACTIVITY_STATUSES))],
        ]);
        $pages = Page::publiclyVisible()->where('portfolio_id', $portfolio->id)->orderBy('position')->orderBy('title')->get();
        $area = ! empty($filters['area']) ? $pages->firstWhere('slug', $filters['area']) : null;
        $results = $this->actions->search($portfolio, ['area' => $area?->id] + $filters);
        $hasFilters = collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty();

        return view('public.actions', [
            'portfolio' => $portfolio,
            'results' => $results,
            'filters' => $filters,
            'hasFilters' => $hasFilters,
            'pages' => $pages,
            'years' => $this->actions->years($portfolio),
            'types' => $this->actions->types($portfolio),
            'totalPublished' => $hasFilters ? $this->actions->query($portfolio)->count() : $results->total(),
        ]);
    }

    public function action(string $slug)
    {
        $version = $this->actions->findBySlug($this->portfolio(), $slug) ?? abort(404);

        return view('public.action', self::actionViewData($version, $slug, false));
    }

    /** Dados da página da ação. A prévia do painel usa o mesmo filtro do público. */
    public static function actionViewData(ActionVersion $version, string $slug, bool $preview): array
    {
        $portfolio = Portfolio::current();
        $actions = app(PublicActions::class);
        $version->load([
            'primaryPage', 'programPage', 'media.media', 'links.image', 'partners.logo',
            'relatedPages' => fn ($q) => $preview ? $q->active() : $q->publiclyVisible(),
            'teamMembers' => fn ($q) => $q->publiclyVisible()->with('photo'),
            'indicators', 'fieldValues.field',
        ]);
        $version->setRelation('partners', $version->partners->filter(fn ($p) => $p->is_public && ! $p->archived_at)->values());

        $fields = $version->fieldValues
            ->filter(fn ($v) => $v->field && $v->field->is_public && ! $v->field->archived_at && filled($v->value))
            ->sortBy(fn ($v) => [$v->field->position, $v->field->id]);
        $related = $version->primary_page_id
            ? $actions->withCardRelations($actions->inPages($actions->query($portfolio), [$version->primary_page_id]))
                ->where('action_versions.action_id', '!=', $version->action_id)->orderByDesc('action_versions.starts_on')->limit(3)->get()
            : collect();

        return [
            'portfolio' => $portfolio,
            'version' => $version,
            'actionSlug' => $slug,
            'cover' => $version->cover(),
            'gallery' => $version->media->filter(fn ($m) => $m->media)->values(),
            'indicators' => $version->indicators->filter->isDocumented()->values(),
            'fields' => $fields,
            'related' => $related,
            'preview' => $preview,
        ];
    }

    public function areas()
    {
        $portfolio = $this->portfolio();
        $pages = Page::publiclyVisible()->where('portfolio_id', $portfolio->id)
            ->orderBy('position')->orderBy('title')->get();

        return view('public.areas', [
            'portfolio' => $portfolio,
            'roots' => $pages->whereNull('parent_id')->values(),
            'children' => $pages->whereNotNull('parent_id')->groupBy('parent_id'),
        ]);
    }

    public function page(string $slug, BlockRenderer $renderer)
    {
        $portfolio = $this->portfolio();
        $page = Page::publiclyVisible()->where('portfolio_id', $portfolio->id)->where('slug', $slug)->first() ?? abort(404);

        return view('public.page', $this->pageViewData($page, $page->published_snapshot ?? [], $portfolio, $renderer) + ['preview' => false]);
    }

    public static function pageViewData(Page $page, array $snapshot, Portfolio $portfolio, BlockRenderer $renderer): array
    {
        $cover = ! empty($snapshot['cover_media_id']) ? \App\Models\Media::find($snapshot['cover_media_id']) : null;
        $children = Page::publiclyVisible()->where('parent_id', $page->id)->orderBy('position')->orderBy('title')->get();
        $ancestors = array_values(array_filter($page->ancestors(), fn (Page $p) => $p->isPublic()));

        return [
            'portfolio' => $portfolio,
            'page' => $page,
            'description' => $snapshot['description'] ?? null,
            'cover' => $cover,
            'blocks' => $renderer->prepare($snapshot['blocks'] ?? [], $page, $portfolio),
            'children' => $children,
            'ancestors' => $ancestors,
        ];
    }

    public function team()
    {
        $portfolio = $this->portfolio();
        $members = TeamMember::publiclyVisible()->current()->where('portfolio_id', $portfolio->id)
            ->with(['photo', 'page'])->orderBy('position')->orderBy('name')->get();

        return view('public.team', ['portfolio' => $portfolio, 'members' => $members]);
    }
}
