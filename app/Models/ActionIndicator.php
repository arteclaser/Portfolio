<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionIndicator extends Model
{
    protected $fillable = [
        'action_version_id', 'label', 'value', 'unit', 'period', 'source', 'is_participation', 'position',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_participation' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ActionVersion::class, 'action_version_id');
    }

    /** Só é exibido publicamente quando preenchido e documentado (com fonte). */
    public function isDocumented(): bool
    {
        return filled($this->label) && $this->value !== null && filled($this->source);
    }
}
