<?php

namespace App\Models;

use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class Portfolio extends Model
{
    /** Escolhas de produto ajustáveis pelo Master. */
    public const DEFAULT_SETTINGS = [
        'max_images_per_action' => 10,
        'max_upload_mb' => 5,
        'activity_types' => [
            'Capacitação', 'Oficina', 'Evento', 'Feira', 'Reunião técnica',
            'Visita técnica', 'Programa', 'Projeto', 'Campanha', 'Outro',
        ],
    ];

    protected $fillable = [
        'name', 'slug', 'short_name', 'tagline', 'subtitle', 'hero_kicker', 'hero_title',
        'hero_highlight', 'hero_description', 'about', 'accent_color', 'contact_email',
        'footer_note', 'settings', 'is_demo', 'is_active', 'is_listed', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_demo' => 'boolean',
            'is_active' => 'boolean',
            'is_listed' => 'boolean',
        ];
    }

    /**
     * Portfólio da requisição (definido pelo endereço no site público e pela conta no
     * painel). No terminal e nos testes, sem portfólio definido, usa o primeiro.
     */
    public static function current(): ?Portfolio
    {
        if ($tenant = Tenant::get()) {
            return $tenant;
        }

        return app()->runningInConsole() ? static::query()->orderBy('id')->first() : null;
    }

    public static function forgetCurrent(): void
    {
        Tenant::forget();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Endereço público do portfólio, conforme o modo configurado (caminho ou subdomínio). */
    public function publicUrl(): string
    {
        return route('home', ['portfolio' => $this->slug]);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->settings ?? [], $key, Arr::get(self::DEFAULT_SETTINGS, $key, $default));
    }

    public function maxImagesPerAction(): int
    {
        return max(1, (int) $this->setting('max_images_per_action'));
    }

    public function maxUploadBytes(): int
    {
        return (int) round(max(0.5, (float) $this->setting('max_upload_mb')) * 1024 * 1024);
    }

    /** @return list<string> */
    public function activityTypes(): array
    {
        return array_values(array_filter(array_map('trim', (array) $this->setting('activity_types'))));
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(Action::class);
    }
}
