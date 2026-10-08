<?php

namespace App\Http\Controllers\Panel;

use App\Models\TeamMember;
use App\Models\User;
use App\Services\MediaException;
use App\Services\MediaStore;
use App\Support\Access;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Cadastro institucional da equipe, separado das contas de acesso. */
class TeamController extends PanelController
{
    public function __construct(private MediaStore $store, private ActivityLogger $log, private Access $access) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', TeamMember::class);
        $query = TeamMember::query()->with(['page', 'user', 'photo'])->orderBy('position')->orderBy('name');
        $allowed = $this->access->allowedPageIds($request->user());
        if ($allowed !== null) {
            $query->whereIn('page_id', $allowed ?: [0]);
        }
        if (! $request->boolean('arquivados')) {
            $query->whereNull('archived_at');
        }

        return view('panel.team.index', ['members' => $query->get(), 'showArchived' => $request->boolean('arquivados')]);
    }

    public function create()
    {
        $this->authorize('create', TeamMember::class);

        return view('panel.team.form', $this->formData(new TeamMember(['is_public' => true])));
    }

    public function store(Request $request)
    {
        $this->authorize('create', TeamMember::class);
        $member = new TeamMember(['portfolio_id' => $this->portfolio()->id]);
        $this->save($request, $member);

        return redirect()->route('panel.team.index')->with('status', 'Integrante cadastrado.');
    }

    public function edit(TeamMember $member)
    {
        $this->authorize('update', $member);

        return view('panel.team.form', $this->formData($member));
    }

    public function update(Request $request, TeamMember $member)
    {
        $this->authorize('update', $member);
        $this->save($request, $member);

        return redirect()->route('panel.team.edit', $member)->with('status', 'Cadastro atualizado.');
    }

    public function archive(Request $request, TeamMember $member)
    {
        $this->authorize('delete', $member);
        $member->archived_at = now();
        $member->save();
        $this->log->log($request->user(), 'team.archived', $member, 'Arquivou o cadastro de '.$member->name);

        return redirect()->route('panel.team.index')->with('status', 'Cadastro arquivado. O vínculo com ações anteriores foi preservado.');
    }

    public function unarchive(Request $request, TeamMember $member)
    {
        $this->authorize('delete', $member);
        $member->archived_at = null;
        $member->save();

        return back()->with('status', 'Cadastro reativado.');
    }

    private function formData(TeamMember $member): array
    {
        $user = request()->user();

        return [
            'member' => $member,
            'areaOptions' => $this->pageOptions($this->allowedPages(), $user->isMaster() ? 'Sem área específica' : null),
            'userOptions' => $user->isMaster()
                ? ['' => 'Sem conta de acesso'] + User::orderBy('name')->pluck('name', 'id')->all()
                : null,
        ];
    }

    private function save(Request $request, TeamMember $member): void
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role_title' => ['nullable', 'string', 'max:255'],
            'function' => ['nullable', 'string', 'max:255'],
            'page_id' => [$user->isMaster() ? 'nullable' : 'required', 'integer', Rule::exists('pages', 'id')],
            'bio' => ['nullable', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_is_public' => ['nullable', 'boolean'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'is_public' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'between:0,9999'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'photo' => ['nullable', 'file'],
            'remove_photo' => ['nullable', 'boolean'],
        ], ['ended_on.after_or_equal' => 'A saída deve ser igual ou posterior à entrada.'], ['name' => 'nome', 'page_id' => 'área']);

        if (! empty($data['page_id']) && ! $this->access->canAccessPage($user, (int) $data['page_id'])) {
            abort(403, 'Você não tem acesso a esta área.');
        }
        $member->fill([
            'name' => $data['name'],
            'role_title' => $data['role_title'] ?? null,
            'function' => $data['function'] ?? null,
            'page_id' => $data['page_id'] ?? null,
            'bio' => $data['bio'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_is_public' => $request->boolean('contact_is_public'),
            'started_on' => $data['started_on'] ?? null,
            'ended_on' => $data['ended_on'] ?? null,
            'is_public' => $request->boolean('is_public'),
            'position' => (int) ($data['position'] ?? 0),
        ]);
        if ($user->isMaster()) {
            $member->user_id = $data['user_id'] ?? null;
        }
        if ($request->boolean('remove_photo')) {
            $member->photo_media_id = null;
        }
        if ($request->hasFile('photo')) {
            try {
                $member->photo_media_id = $this->store->storeUploadedImage($request->file('photo'), $this->portfolio(), $user)->id;
            } catch (MediaException $e) {
                throw ValidationException::withMessages(['photo' => $e->getMessage()]);
            }
        }
        $member->save();
        $this->log->log($user, 'team.saved', $member, 'Salvou o cadastro de '.$member->name);
    }
}
