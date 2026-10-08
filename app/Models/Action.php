<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPortfolio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Identidade estável de uma ação. O conteúdo vive em versões: a versão publicada
 * é a única que o público vê; a versão de trabalho recebe as edições da equipe.
 */
class Action extends Model
{
    use BelongsToPortfolio, SoftDeletes;

    public const WORKING_STATES = [
        'draft' => 'Rascunho',
        'in_review' => 'Em revisão',
        'returned' => 'Devolvido para ajustes',
    ];

    protected $fillable = ['portfolio_id', 'slug', 'created_by'];

    protected function casts(): array
    {
        return [
            'first_published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }


    public function versions(): HasMany
    {
        return $this->hasMany(ActionVersion::class)->orderByDesc('number');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ActionVersion::class, 'published_version_id');
    }

    public function workingVersion(): BelongsTo
    {
        return $this->belongsTo(ActionVersion::class, 'working_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Versão usada para exibir e autorizar no painel: a de trabalho, se houver. */
    public function currentVersion(): ?ActionVersion
    {
        return $this->workingVersion ?? $this->publishedVersion;
    }

    public function isPublished(): bool
    {
        return $this->published_version_id !== null;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->isPublished() && ! $this->isArchived() && ! $this->trashed();
    }

    public function hasPendingChanges(): bool
    {
        return $this->isPublished() && $this->working_version_id !== null;
    }

    /** Situação editorial: rascunho, em revisão, publicado ou arquivado. */
    public function editorialStatus(): string
    {
        if ($this->trashed()) {
            return 'trashed';
        }
        if ($this->isArchived()) {
            return 'archived';
        }
        if ($this->isPublished()) {
            return 'published';
        }

        return $this->working_state ?? 'draft';
    }

    public function editorialLabel(): string
    {
        $label = match ($this->editorialStatus()) {
            'trashed' => 'Na lixeira',
            'archived' => 'Arquivado',
            'published' => 'Publicado',
            'in_review' => 'Em revisão',
            'returned' => 'Rascunho devolvido',
            default => 'Rascunho',
        };
        if ($this->editorialStatus() === 'published' && $this->hasPendingChanges()) {
            $label .= ' · alterações '.($this->working_state === 'in_review' ? 'em revisão' : 'em rascunho');
        }

        return $label;
    }
}
