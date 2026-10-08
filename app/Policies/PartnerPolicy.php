<?php

namespace App\Policies;

use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && ($user->isMaster() || $user->isEditor());
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Partner $partner): bool
    {
        return $this->viewAny($user) && $partner->belongsToCurrentPortfolio();
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->is_active && $user->isMaster();
    }
}
