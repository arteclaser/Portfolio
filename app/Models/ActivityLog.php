<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPortfolio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use BelongsToPortfolio;

    public const UPDATED_AT = null;

    protected $fillable = ['portfolio_id', 'user_id', 'event', 'subject_type', 'subject_id', 'description', 'properties', 'ip_address'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
