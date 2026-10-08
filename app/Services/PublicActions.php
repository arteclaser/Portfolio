<?php

namespace App\Services;

use App\Models\ActionVersion;
use App\Models\Portfolio;
use App\Support\PageTree;
use App\Support\Text;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Única porta de leitura do conteúdo de ações para o público: retorna apenas
 * versões aprovadas de ações não arquivadas e fora da lixeira.
 */
class PublicActions
{
    public function __construct(private PageTree $tree) {}

    public function query(Portfolio $portfolio): Builder
    {
        return ActionVersion::query()
            ->select('action_versions.*', 'actions.slug as public_slug')
            ->join('actions', 'actions.published_version_id', '=', 'action_versions.id')
            ->where('actions.portfolio_id', $portfolio->id)
            ->whereNull('actions.archived_at')
            ->whereNull('actions.deleted_at');
    }

    /** Restringe às ações de uma ou mais páginas (incluindo subordinadas), por área principal ou associação. */
    public function inPages(Builder $query, array $pageIds): Builder
    {
        $ids = $this->tree->withDescendants($pageIds);

        return $query->where(function (Builder $q) use ($ids) {
            $q->whereIn('action_versions.primary_page_id', $ids)
                ->orWhereExists(function ($sub) use ($ids) {
                    $sub->select(DB::raw(1))->from('action_version_page')
                        ->whereColumn('action_version_page.action_version_id', 'action_versions.id')
                        ->whereIn('action_version_page.page_id', $ids);
                });
        });
    }

    /**
     * @param  array{q?: ?string, area?: ?int, ano?: ?int, tipo?: ?string, situacao?: ?string}  $filters
     */
    public function filtered(Portfolio $portfolio, array $filters): Builder
    {
        $query = $this->query($portfolio);

        if (! empty($filters['area'])) {
            $this->inPages($query, [(int) $filters['area']]);
        }
        if (! empty($filters['ano'])) {
            $year = (int) $filters['ano'];
            $start = sprintf('%04d-01-01', $year);
            $end = sprintf('%04d-12-31', $year);
            // Ações cujo período toca o ano escolhido.
            $query->where('action_versions.starts_on', '<=', $end)
                ->where(fn ($q) => $q->where('action_versions.ends_on', '>=', $start)
                    ->orWhere(fn ($q2) => $q2->whereNull('action_versions.ends_on')->where('action_versions.starts_on', '>=', $start)));
        }
        if (! empty($filters['tipo'])) {
            $query->where('action_versions.activity_type', $filters['tipo']);
        }
        if (! empty($filters['situacao'])) {
            $query->where('action_versions.activity_status', $filters['situacao']);
        }
        if (! empty($filters['q'])) {
            foreach (array_slice(array_filter(explode(' ', Text::searchable($filters['q']))), 0, 8) as $term) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                $query->where('action_versions.search_text', 'like', $like);
            }
        }

        return $query;
    }

    public function search(Portfolio $portfolio, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        return $this->withCardRelations($this->filtered($portfolio, $filters))
            ->orderByDesc('action_versions.starts_on')->orderByDesc('action_versions.id')
            ->paginate($perPage)->withQueryString();
    }

    public function withCardRelations(Builder $query): Builder
    {
        return $query->with(['primaryPage', 'media.media']);
    }

    /** Destaques: as marcadas como destaque primeiro, completando com as mais recentes. */
    public function featured(Portfolio $portfolio, int $count = 3, ?array $pageIds = null)
    {
        $base = fn () => $pageIds ? $this->inPages($this->query($portfolio), $pageIds) : $this->query($portfolio);
        $featured = $this->withCardRelations($base()->where('action_versions.is_featured', true))
            ->orderByDesc('action_versions.starts_on')->limit($count)->get();
        if ($featured->count() < $count) {
            $more = $this->withCardRelations($base()->whereNotIn('action_versions.id', $featured->pluck('id')->all() ?: [0]))
                ->orderByDesc('action_versions.starts_on')->orderByDesc('action_versions.id')
                ->limit($count - $featured->count())->get();
            $featured = $featured->concat($more);
        }

        return $featured->values();
    }

    public function findBySlug(Portfolio $portfolio, string $slug): ?ActionVersion
    {
        return $this->query($portfolio)->where('actions.slug', $slug)->first();
    }

    /** @return list<int> anos com ações publicadas */
    public function years(Portfolio $portfolio): array
    {
        $years = [];
        foreach ($this->query($portfolio)->get(['action_versions.starts_on', 'action_versions.ends_on']) as $v) {
            if (! $v->starts_on) {
                continue;
            }
            $last = ($v->ends_on ?? $v->starts_on)->year;
            for ($y = $v->starts_on->year; $y <= $last && $y - $v->starts_on->year < 30; $y++) {
                $years[$y] = $y;
            }
        }
        krsort($years);

        return array_values($years);
    }

    /** @return list<string> */
    public function types(Portfolio $portfolio): array
    {
        return $this->query($portfolio)->whereNotNull('action_versions.activity_type')
            ->distinct()->orderBy('action_versions.activity_type')->pluck('action_versions.activity_type')->all();
    }
}
