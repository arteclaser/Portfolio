<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    public const KINDS = [
        'area' => 'Área de atuação',
        'programa' => 'Programa',
        'projeto' => 'Projeto',
        'pagina' => 'Página institucional',
    ];

    protected $fillable = [
        'portfolio_id', 'parent_id', 'kind', 'title', 'slug', 'menu_title', 'description',
        'cover_media_id', 'accent_color', 'position', 'show_in_menu',
    ];

    protected function casts(): array
    {
        return [
            'published_snapshot' => 'array',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'has_unpublished_changes' => 'boolean',
            'show_in_menu' => 'boolean',
        ];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('position')->orderBy('title');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('position')->orderBy('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CustomField::class)->orderBy('position')->orderBy('id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function responsibles(): BelongsToMany
    {
        return $this->belongsToMany(TeamMember::class, 'page_team_member')
            ->withPivot('position')->orderByPivot('position');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** Páginas visíveis ao público: publicadas e não arquivadas. */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereNull('archived_at')->whereNotNull('published_at');
    }

    /** Capa da versão publicada (a capa em edição só aparece após publicar). */
    public function publicCover(): ?Media
    {
        $id = $this->published_snapshot['cover_media_id'] ?? null;

        return $id ? Media::find($id) : null;
    }

    public function isPublic(): bool
    {
        return $this->archived_at === null && $this->published_at !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function accent(): string
    {
        return $this->accent_color ?: ($this->portfolio?->accent_color ?: '#2155E8');
    }

    public function displayTitle(): string
    {
        return $this->menu_title ?: $this->title;
    }

    /** Cadeia de ancestrais, da raiz até o pai imediato. */
    public function ancestors(): array
    {
        $chain = [];
        $node = $this->parent;
        $guard = 0;
        while ($node && $guard++ < 20) {
            array_unshift($chain, $node);
            $node = $node->parent;
        }

        return $chain;
    }
}
