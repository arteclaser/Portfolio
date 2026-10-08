<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPortfolio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    use BelongsToPortfolio;

    protected $table = 'media';

    /** Larguras máximas geradas para telas diferentes (sem ampliar nem deformar). */
    public const VARIANTS = ['sm' => 480, 'md' => 960, 'lg' => 1600, 'xl' => 2400];

    protected $fillable = [
        'uuid', 'portfolio_id', 'uploaded_by', 'kind', 'disk', 'directory', 'mime', 'size', 'width',
        'height', 'original_name', 'variants', 'alt', 'caption', 'credit', 'focal_x', 'focal_y', 'source_url',
    ];

    protected function casts(): array
    {
        return ['variants' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function actionUses(): HasMany
    {
        return $this->hasMany(ActionMedia::class);
    }

    public function isImage(): bool
    {
        return $this->kind === 'image';
    }

    public function isDocument(): bool
    {
        return $this->kind === 'document';
    }

    /** Caminho relativo ao disco para uma variante existente. */
    public function variantPath(string $variant): ?string
    {
        $variants = $this->variants ?? [];
        if ($this->isDocument()) {
            return $variants['file']['path'] ?? null;
        }
        if (isset($variants[$variant]['path'])) {
            return $variants[$variant]['path'];
        }
        foreach (['md', 'lg', 'sm', 'xl'] as $fallback) {
            if (isset($variants[$fallback]['path'])) {
                return $variants[$fallback]['path'];
            }
        }

        return null;
    }

    /** @return array<string, int> nome => largura real */
    public function variantWidths(): array
    {
        $out = [];
        foreach (array_keys(self::VARIANTS) as $name) {
            if (isset($this->variants[$name]['w'])) {
                $out[$name] = (int) $this->variants[$name]['w'];
            }
        }

        return $out;
    }

    public function sizeLabel(): string
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1, ',', '.').' MB' : number_format($kb, 0, ',', '.').' KB';
    }
}
