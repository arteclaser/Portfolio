<?php

namespace App\Policies;

use App\Models\Action;
use App\Models\User;
use App\Support\Access;

class ActionPolicy
{
    public function __construct(private Access $access) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Action $action): bool
    {
        return $this->access->canAccessAction($user, $action);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $this->access->hasAnyArea($user);
    }

    /** Editar a versão de trabalho. */
    public function update(User $user, Action $action): bool
    {
        if (! $this->access->canAccessAction($user, $action) || $action->trashed()) {
            return false;
        }
        if ($user->isMaster() || $user->isEditor()) {
            return true;
        }
        // Colaborador: somente os próprios rascunhos e conteúdos devolvidos.
        $working = $action->workingVersion;

        return $working !== null
            && (int) $working->author_id === $user->id
            && in_array($action->working_state, ['draft', 'returned'], true);
    }

    /** Criar versão de trabalho a partir do conteúdo publicado. */
    public function startWorkingCopy(User $user, Action $action): bool
    {
        return ($user->isMaster() || $user->isEditor())
            && $this->access->canAccessAction($user, $action)
            && ! $action->trashed();
    }

    public function submit(User $user, Action $action): bool
    {
        return $this->update($user, $action)
            && in_array($action->working_state, ['draft', 'returned'], true);
    }

    /** Aprovar, devolver e publicar. */
    public function review(User $user, Action $action): bool
    {
        return ($user->isMaster() || $user->isEditor())
            && $this->access->canAccessAction($user, $action)
            && ! $action->trashed();
    }

    public function discardWorking(User $user, Action $action): bool
    {
        return $action->isPublished() && $action->working_version_id !== null
            && ($this->review($user, $action) || $this->update($user, $action));
    }

    public function restoreVersion(User $user, Action $action): bool
    {
        return $this->review($user, $action);
    }

    public function archive(User $user, Action $action): bool
    {
        if (! $this->access->canAccessAction($user, $action)) {
            return false;
        }

        return $user->isMaster() || ($user->isEditor() && $user->can_archive);
    }

    public function delete(User $user, Action $action): bool
    {
        if ($this->archive($user, $action)) {
            return true;
        }

        // Colaborador pode mandar para a lixeira o próprio rascunho nunca publicado.
        return $user->isCollaborator() && ! $action->isPublished() && $this->update($user, $action);
    }

    public function restore(User $user, Action $action): bool
    {
        return $this->archive($user, $action);
    }

    public function forceDelete(User $user, Action $action): bool
    {
        return $user->is_active && $user->isMaster();
    }
}
