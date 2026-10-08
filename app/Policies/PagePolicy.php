<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use App\Support\Access;

class PagePolicy
{
    public function __construct(private Access $access) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active && ($user->isMaster() || $user->isEditor());
    }

    public function view(User $user, Page $page): bool
    {
        return $this->viewAny($user) && $this->access->canAccessPage($user, $page->id);
    }

    /** Estrutura: criar, mover, endereço, campos, responsáveis, arquivar. */
    public function create(User $user): bool
    {
        return $user->is_active && $user->isMaster();
    }

    public function manageStructure(User $user, Page $page): bool
    {
        return $user->is_active && $user->isMaster();
    }

    /** Conteúdo: blocos, descrição, capa, prévia e publicação. */
    public function updateContent(User $user, Page $page): bool
    {
        return $this->view($user, $page);
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->manageStructure($user, $page);
    }
}
