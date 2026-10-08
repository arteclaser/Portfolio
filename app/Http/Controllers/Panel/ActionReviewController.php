<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\PublicController;
use App\Models\Action;
use App\Models\ActionVersion;
use App\Models\ActivityLog;
use App\Services\ActionWorkflow;
use App\Support\VersionDiff;
use Illuminate\Http\Request;

class ActionReviewController extends PanelController
{
    public function __construct(private ActionWorkflow $workflow) {}

    public function show(Request $request, Action $action)
    {
        $this->authorize('view', $action);
        $action->load(['workingVersion.author', 'workingVersion.reviewer', 'workingVersion.media', 'publishedVersion.publisher']);
        $working = $action->workingVersion;
        $user = $request->user();

        return view('panel.actions.review', [
            'action' => $action,
            'version' => $action->currentVersion(),
            'working' => $working,
            'errors_publish' => $working ? $this->workflow->publicationErrors($action, $working) : [],
            'missingAlt' => $working ? $working->media->filter(fn ($m) => blank($m->altText()))->count() : 0,
            'can' => [
                'submit' => $working && $user->can('submit', $action),
                'review' => $working && $user->can('review', $action),
                'discard' => $user->can('discardWorking', $action),
                'archive' => $user->can('archive', $action),
                'delete' => $user->can('delete', $action),
                'startWorking' => ! $working && $user->can('startWorkingCopy', $action),
            ],
        ]);
    }

    public function submit(Request $request, Action $action)
    {
        $this->authorize('submit', $action);
        $this->workflow->submit($action, $request->user());

        return redirect()->route('panel.actions.review', $action)->with('status', 'Enviado para revisão. Um editor da área poderá aprovar ou devolver com observações.');
    }

    public function publish(Request $request, Action $action)
    {
        $this->authorize('review', $action);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $this->workflow->publish($action, $request->user(), $data['note'] ?? null);

        return redirect()->route('panel.actions.review', $action)->with('status', 'Publicado. A versão aprovada já está no site.');
    }

    public function returnForChanges(Request $request, Action $action)
    {
        $this->authorize('review', $action);
        $data = $request->validate(['review_notes' => ['required', 'string', 'max:5000']], [
            'review_notes.required' => 'Escreva as observações para quem vai ajustar o conteúdo.',
        ]);
        $this->workflow->returnForChanges($action, $request->user(), $data['review_notes']);

        return redirect()->route('panel.actions.review', $action)->with('status', 'Conteúdo devolvido com observações.');
    }

    public function discard(Request $request, Action $action)
    {
        $this->authorize('discardWorking', $action);
        $this->workflow->discardWorking($action, $request->user());

        return redirect()->route('panel.actions.review', $action)->with('status', 'Alterações descartadas. A versão publicada permanece como estava.');
    }

    public function preview(Request $request, Action $action)
    {
        $this->authorize('view', $action);
        $which = $request->query('versao') === 'publicada' && $action->published_version_id ? 'publicada' : 'trabalho';

        return view('panel.actions.preview', ['action' => $action, 'which' => $which]);
    }

    public function previewContent(Request $request, Action $action)
    {
        $this->authorize('view', $action);
        $action->load(['workingVersion', 'publishedVersion']);
        $version = $request->query('versao') === 'publicada' ? $action->publishedVersion : $action->currentVersion();
        abort_unless($version, 404);

        return view('public.action', PublicController::actionViewData($version, $action->slug ?? $version->slug ?? 'previa', true));
    }

    public function history(Request $request, Action $action)
    {
        $this->authorize('view', $action);
        $versions = $action->versions()->with(['author', 'reviewer', 'publisher'])->get();
        $diffs = [];
        $list = $versions->sortBy('number')->values();
        foreach ($list as $i => $v) {
            $diffs[$v->id] = $i === 0 ? [] : VersionDiff::between($list[$i - 1], $v);
        }
        $logs = ActivityLog::with('user')->where('subject_type', 'Action')->where('subject_id', $action->id)->latest('id')->limit(100)->get();

        return view('panel.actions.history', [
            'action' => $action,
            'version' => $action->currentVersion(),
            'versions' => $versions,
            'diffs' => $diffs,
            'logs' => $logs,
            'canRestore' => $request->user()->can('restoreVersion', $action) && ! $action->working_version_id,
        ]);
    }

    public function restoreVersion(Request $request, Action $action, ActionVersion $version)
    {
        $this->authorize('restoreVersion', $action);
        $this->workflow->restoreVersion($action, $version, $request->user());

        return redirect()->route('panel.actions.edit', $action)->with('status', 'Conteúdo da versão '.$version->number.' restaurado como versão de trabalho. Revise e publique quando estiver pronto.');
    }
}
