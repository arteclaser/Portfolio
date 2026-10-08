<?php

namespace App\Policies;

use App\Models\ActionMedia;
use App\Models\Media;
use App\Models\User;
use App\Support\Access;
use Illuminate\Support\Facades\DB;

class MediaPolicy
{
    public function __construct(private Access $access) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /** Ver um arquivo privado no painel. */
    public function view(User $user, Media $media): bool
    {
        if (! $user->is_active) {
            return false;
        }
        if ($user->isMaster() || $user->isEditor() || (int) $media->uploaded_by === $user->id) {
            return true;
        }
        // Colaborador: arquivos usados em ações das suas áreas.
        $allowed = $this->access->allowedPageIds($user) ?? [];
        $versionIds = ActionMedia::where('media_id', $media->id)->pluck('action_version_id')
            ->merge(DB::table('action_links')->where('image_media_id', $media->id)->pluck('action_version_id'));

        return DB::table('action_versions')->whereIn('id', $versionIds)->whereIn('primary_page_id', $allowed ?: [0])->exists();
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Media $media): bool
    {
        return $user->is_active && ($user->isMaster() || $user->isEditor() || (int) $media->uploaded_by === $user->id);
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->update($user, $media);
    }
}
