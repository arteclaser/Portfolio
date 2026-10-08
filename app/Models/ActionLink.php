<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionLink extends Model
{
    public const KINDS = [
        'video' => 'Vídeo',
        'reportagem' => 'Reportagem',
        'publicacao' => 'Publicação em rede social',
        'link' => 'Link externo',
    ];

    public const PROVIDERS = [
        'youtube' => 'YouTube',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'site' => 'Site',
    ];

    protected $fillable = [
        'action_version_id', 'url', 'provider', 'kind', 'embed_id', 'title', 'description', 'source_name',
        'image_media_id', 'suggested_image_url', 'preview_status', 'preview_message', 'fetched_at', 'position',
    ];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ActionVersion::class, 'action_version_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function host(): string
    {
        return preg_replace('/^www\./', '', (string) parse_url($this->url, PHP_URL_HOST));
    }

    public function sourceLabel(): string
    {
        return $this->source_name ?: (self::PROVIDERS[$this->provider] ?? null) ?: $this->host();
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->host();
    }

    public function isYoutubeEmbed(): bool
    {
        return $this->provider === 'youtube' && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $this->embed_id) === 1;
    }

    public function isInstagramEmbed(): bool
    {
        return $this->provider === 'instagram' && preg_match('/^(p|reel|tv)\/[A-Za-z0-9_-]{5,40}$/', (string) $this->embed_id) === 1;
    }
}
