<?php

namespace App\Models;

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
        'footer_note', 'settings', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_demo' => 'boolean',
        ];
    }

    private static ?Portfolio $current = null;

    /**
     * A primeira versão atende um portfólio por instalação; o modelo de dados
     * já separa tudo por portfolio_id para permitir outros portfólios depois.
     */
    public static function current(): ?Portfolio
    {
        if (static::$current === null || ! static::$current->exists) {
            static::$current = static::query()->orderBy('id')->first();
        }

        return static::$current;
    }

    public static function forgetCurrent(): void
    {
        static::$current = null;
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
