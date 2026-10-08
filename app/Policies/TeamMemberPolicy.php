<?php

namespace App\Policies;

use App\Models\TeamMember;
use App\Models\User;
use App\Support\Access;

class TeamMemberPolicy
{
    public function __construct(private Access $access) {}

    private function manager(User $user): bool
    {
        return $user->is_active && ($user->isMaster() || ($user->isEditor() && $user->can_manage_team));
    }

    public function viewAny(User $user): bool
    {
        return $this->manager($user);
    }

    public function create(User $user): bool
    {
        return $this->manager($user);
    }

    public function update(User $user, TeamMember $member): bool
    {
        if (! $this->manager($user)) {
            return false;
        }

        // Editor administra apenas integrantes das próprias áreas.
        return $user->isMaster() || $this->access->canAccessPage($user, $member->page_id);
    }

    public function delete(User $user, TeamMember $member): bool
    {
        return $this->update($user, $member);
    }
}
