<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPortfolio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Partner extends Model
{
    use BelongsToPortfolio;

    protected $fillable = ['portfolio_id', 'name', 'url', 'logo_media_id', 'description', 'is_public', 'position'];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_public', true)->whereNull('archived_at');
    }
}
