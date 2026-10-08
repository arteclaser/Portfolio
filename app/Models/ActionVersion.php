<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActionVersion extends Model
{
    public const ACTIVITY_STATUSES = [
        'planejada' => 'Planejada',
        'em_andamento' => 'Em andamento',
        'concluida' => 'Concluída',
        'cancelada' => 'Cancelada',
    ];

    public const STATUSES = [
        'draft' => 'Rascunho',
        'in_review' => 'Em revisão',
        'returned' => 'Devolvida',
        'published' => 'Publicada',
        'superseded' => 'Substituída',
        'discarded' => 'Descartada',
    ];

    /** Campos textuais copiados entre versões e comparados no histórico. */
    public const CONTENT_FIELDS = [
        'title' => 'Nome',
        'slug' => 'Endereço da página',
        'summary' => 'Resumo',
        'description' => 'Descrição',
        'primary_page_id' => 'Área principal',
        'program_page_id' => 'Programa ou projeto',
        'starts_on' => 'Data de início',
        'ends_on' => 'Data de término',
        'location' => 'Local',
        'activity_type' => 'Tipo de atividade',
        'activity_status' => 'Situação da ação',
        'objectives' => 'Objetivos',
        'results' => 'Resultados documentados',
        'is_featured' => 'Destaque',
    ];

    protected $fillable = [
        'action_id', 'number', 'status', 'author_id', 'last_editor_id', 'title', 'slug', 'summary',
        'description', 'primary_page_id', 'program_page_id', 'starts_on', 'ends_on', 'location',
        'activity_type', 'activity_status', 'objectives', 'results', 'is_featured', 'change_note',
        'restored_from_version_id', 'search_text',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_featured' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_editor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function primaryPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'primary_page_id');
    }

    public function programPage(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'program_page_id');
    }

    /** Áreas relacionadas (associação ao mesmo registro, sem duplicar a ação). */
    public function relatedPages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'action_version_page');
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(TeamMember::class, 'action_version_team_member')
            ->withPivot(['role_in_action', 'position'])->orderByPivot('position');
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class, 'action_version_partner');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(ActionIndicator::class)->orderBy('position')->orderBy('id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ActionMedia::class)->orderByDesc('is_cover')->orderBy('position')->orderBy('id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ActionLink::class)->orderBy('position')->orderBy('id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ActionFieldValue::class);
    }

    public function cover(): ?ActionMedia
    {
        return $this->media->firstWhere('is_cover', true) ?? $this->media->first();
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'in_review', 'returned'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function activityStatusLabel(): ?string
    {
        return $this->activity_status ? (self::ACTIVITY_STATUSES[$this->activity_status] ?? $this->activity_status) : null;
    }

    public function displayTitle(): string
    {
        return filled($this->title) ? $this->title : 'Ação sem nome';
    }

    /** Data ou período formatado em português. */
    public function periodLabel(): ?string
    {
        if (! $this->starts_on) {
            return null;
        }
        $start = $this->starts_on->translatedFormat('j \d\e F \d\e Y');
        if (! $this->ends_on || $this->ends_on->equalTo($this->starts_on)) {
            return $start;
        }

        return $start.' a '.$this->ends_on->translatedFormat('j \d\e F \d\e Y');
    }

    /** Todas as páginas às quais a ação pertence (principal + relacionadas), sem repetição. */
    public function allPageIds(): array
    {
        $ids = $this->relatedPages->pluck('id')->all();
        if ($this->primary_page_id) {
            array_unshift($ids, $this->primary_page_id);
        }

        return array_values(array_unique($ids));
    }
}
