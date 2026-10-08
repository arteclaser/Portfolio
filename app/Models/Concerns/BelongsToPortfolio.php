<?php

namespace App\Models\Concerns;

use App\Models\Portfolio;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isola os registros por portfólio: com um portfólio ativo na requisição, toda
 * consulta (inclusive a resolução de {parâmetros} nas rotas) só enxerga os dele,
 * e novos registros recebem o portfolio_id automaticamente.
 */
trait BelongsToPortfolio
{
    public static function bootBelongsToPortfolio(): void
    {
        static::addGlobalScope('portfolio', function (Builder $query) {
            if ($id = Tenant::id()) {
                $query->where($query->getModel()->qualifyColumn('portfolio_id'), $id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->portfolio_id) && ($id = Tenant::id())) {
                $model->portfolio_id = $id;
            }
        });
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function belongsToCurrentPortfolio(): bool
    {
        return Tenant::id() === null || (int) $this->portfolio_id === Tenant::id();
    }
}
