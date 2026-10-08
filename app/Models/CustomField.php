<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomField extends Model
{
    public const TYPES = [
        'text' => 'Texto',
        'number' => 'Número',
        'date' => 'Data',
        'select' => 'Seleção',
        'url' => 'Endereço de site',
    ];

    protected $fillable = [
        'page_id', 'key', 'label', 'help', 'type', 'options', 'is_required', 'is_public', 'position',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_public' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Campos pertencem a páginas: só os das páginas do portfólio atual.
        static::addGlobalScope('portfolio', function (Builder $query) {
            if ($id = \App\Support\Tenant::id()) {
                $query->whereIn($query->getModel()->qualifyColumn('page_id'), fn ($q) => $q->select('id')->from('pages')->where('portfolio_id', $id));
            }
        });
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(ActionFieldValue::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** @return list<string> */
    public function optionList(): array
    {
        return array_values(array_filter(array_map('trim', (array) $this->options), fn ($o) => $o !== ''));
    }

    public function hasValues(): bool
    {
        return $this->values()->whereNotNull('value')->where('value', '!=', '')->exists();
    }
}
