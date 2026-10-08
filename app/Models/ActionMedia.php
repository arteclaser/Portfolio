<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionMedia extends Model
{
    protected $table = 'action_media';

    protected $fillable = [
        'action_version_id', 'media_id', 'position', 'is_cover', 'caption', 'credit', 'alt', 'focal_x', 'focal_y',
    ];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ActionVersion::class, 'action_version_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function altText(): string
    {
        return (string) ($this->alt ?: $this->media?->alt ?: $this->caption ?: '');
    }
}
