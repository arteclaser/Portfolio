<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionFieldValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['action_version_id', 'custom_field_id', 'value'];

    public function field(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }
}
