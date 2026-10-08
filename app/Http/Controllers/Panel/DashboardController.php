<?php

namespace App\Http\Controllers\Panel;

use App\Models\Action;
use App\Models\ActivityLog;
use App\Support\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends PanelController
{
    public function __invoke(Request $request, Access $access)
    {
        $user = $request->user();
        $allowed = $access->allowedPageIds($user);
        $base = fn () => ActionController::scopedQuery($allowed);

        $mine = $base()->whereNotNull('actions.working_version_id')->where('cv.author_id', $user->id)
            ->whereIn('actions.working_state', ['draft', 'returned'])->with('workingVersion')->latest('actions.updated_at')->limit(8)->get();
        $inReview = $base()->where('actions.working_state', 'in_review')->with('workingVersion.author')->latest('actions.updated_at')->limit(10)->get();

        $counts = [
            'published' => $base()->whereNotNull('actions.published_version_id')->whereNull('actions.archived_at')->count(),
            'drafts' => $base()->whereIn('actions.working_state', ['draft', 'returned'])->count(),
            'in_review' => $base()->where('actions.working_state', 'in_review')->count(),
            'archived' => $base()->whereNotNull('actions.archived_at')->count(),
        ];

        $activity = ActivityLog::with('user')->latest('id')->limit(10);
        if (! $user->isMaster()) {
            $activity->where('user_id', $user->id);
        }

        $warnings = [];
        if ($user->isMaster()) {
            if (config('mail.default') === 'log') {
                $warnings[] = 'O envio de e-mails não está configurado (MAIL_MAILER=log). Convites e recuperações de senha não chegam por e-mail; copie o link de convite exibido ao criar o usuário.';
            }
            if (config('app.debug') && app()->environment('production')) {
                $warnings[] = 'APP_DEBUG está ativo em produção. Desative no arquivo .env.';
            }
            if (filled(config('services.setup.token'))) {
                $warnings[] = 'SETUP_TOKEN ainda está definido no .env. Remova-o após a instalação.';
            }
            if ($this->portfolio()->is_demo) {
                $warnings[] = 'Este é um ambiente de demonstração. O site exibe um aviso de conteúdo ilustrativo.';
            }
        }

        return view('panel.dashboard', [
            'counts' => $counts,
            'mine' => $mine,
            'inReview' => $inReview,
            'activity' => $activity->get(),
            'warnings' => $warnings,
            'canReview' => $user->isMaster() || $user->isEditor(),
        ]);
    }
}
