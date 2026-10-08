<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function log(?User $user, string $event, ?Model $subject = null, ?string $description = null, array $properties = []): ActivityLog
    {
        return ActivityLog::create([
            'portfolio_id' => Tenant::id() ?? ($subject?->portfolio_id ?? null),
            'user_id' => $user?->id,
            'event' => $event,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description ? mb_substr($description, 0, 500) : null,
            'properties' => $properties ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
