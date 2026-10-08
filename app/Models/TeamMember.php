<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPortfolio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    use BelongsToPortfolio;

    protected $fillable = [
        'portfolio_id', 'user_id', 'page_id', 'name', 'role_title', 'function', 'photo_media_id',
        'bio', 'contact_email', 'contact_phone', 'contact_is_public', 'started_on', 'ended_on',
        'is_public', 'position',
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
            'contact_is_public' => 'boolean',
            'is_public' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_public', true)->whereNull('archived_at');
    }

    /** Integrante atual: sem data de saída ou com saída futura. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', now()->toDateString()));
    }

    public function isCurrent(): bool
    {
        return $this->ended_on === null || $this->ended_on->isFuture() || $this->ended_on->isToday();
    }

    public function isPubliclyVisible(): bool
    {
        return $this->is_public && $this->archived_at === null;
    }

    public function periodLabel(): ?string
    {
        if (! $this->started_on && ! $this->ended_on) {
            return null;
        }
        $start = $this->started_on?->format('m/Y');
        $end = $this->ended_on?->format('m/Y');
        if ($start && $end) {
            return "{$start} a {$end}";
        }

        return $start ? "Desde {$start}" : "Até {$end}";
    }
}
