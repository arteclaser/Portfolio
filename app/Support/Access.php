<?php

namespace App\Support;

use App\Models\Action;
use App\Models\User;

/**
 * Regras de acesso por perfil e por área. Toda operação do servidor passa por
 * aqui (via policies); esconder botões na interface não substitui esta checagem.
 */
class Access
{
    /** @var array<int, list<int>> */
    private array $cache = [];

    public function __construct(private PageTree $tree) {}

    public function flush(): void
    {
        $this->cache = [];
        $this->tree->flush();
    }

    /** @return list<int>|null null significa todas as áreas (Master) */
    public function allowedPageIds(User $user): ?array
    {
        if ($user->isMaster()) {
            return null;
        }

        return $this->cache[$user->id] ??= $this->tree->withDescendants(
            $user->pages()->pluck('pages.id')->all()
        );
    }

    public function canAccessPage(User $user, ?int $pageId): bool
    {
        if (! $user->is_active) {
            return false;
        }
        if ($user->isMaster()) {
            return true;
        }
        if ($pageId === null) {
            return false;
        }

        return in_array($pageId, $this->allowedPageIds($user), true);
    }

    public function hasAnyArea(User $user): bool
    {
        return $user->isMaster() || count($this->allowedPageIds($user)) > 0;
    }

    /** Área que governa a autorização da ação: a da versão de trabalho ou, sem ela, a publicada. */
    public function actionAreaId(Action $action): ?int
    {
        $version = $action->currentVersion();

        return $version?->primary_page_id;
    }

    public function canAccessAction(User $user, Action $action): bool
    {
        if (! $user->is_active) {
            return false;
        }
        if ($user->isMaster()) {
            return true;
        }

        return $this->canAccessPage($user, $this->actionAreaId($action));
    }
}
