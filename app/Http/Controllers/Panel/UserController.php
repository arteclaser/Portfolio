<?php

namespace App\Http\Controllers\Panel;

use App\Models\Page;
use App\Models\User;
use App\Notifications\InviteNotification;
use App\Support\Access;
use App\Support\ActivityLogger;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Usuários e permissões (somente Administrador Master). */
class UserController extends PanelController
{
    public function __construct(private ActivityLogger $log, private Access $access) {}

    public function index(Request $request)
    {
        Gate::authorize('manage-users');

        return view('panel.users.index', ['users' => User::with('pages')->where('portfolio_id', Tenant::id())
            ->orderByDesc('is_active')->orderBy('name')->get()]);
    }

    public function create()
    {
        Gate::authorize('manage-users');

        return view('panel.users.form', $this->formData(new User(['role' => User::COLLABORATOR, 'is_active' => true])));
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-users');
        $data = $this->validated($request, null);
        $user = new User;
        $user->forceFill([
            'portfolio_id' => Tenant::id(),
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => true,
            'can_manage_team' => $data['role'] === User::EDITOR && $request->boolean('can_manage_team'),
            'can_archive' => $data['role'] === User::EDITOR && $request->boolean('can_archive'),
            'invited_at' => now(),
        ])->save();
        $user->pages()->sync($data['role'] === User::MASTER ? [] : ($data['page_ids'] ?? []));
        $this->log->log($request->user(), 'user.created', $user, 'Criou o usuário '.$user->email.' ('.$user->roleLabel().')');

        return $this->sendInvite($request, $user, 'Usuário criado.');
    }

    public function edit(User $user)
    {
        Gate::authorize('manage-users');

        return view('panel.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user)
    {
        Gate::authorize('manage-users');
        $data = $this->validated($request, $user);
        if ($user->isMaster() && $data['role'] !== User::MASTER && $this->activeMasters() <= 1) {
            throw ValidationException::withMessages(['role' => 'É preciso manter pelo menos um Administrador Master ativo.']);
        }
        $before = ['role' => $user->role, 'pages' => $user->pages()->pluck('pages.id')->sort()->values()->all()];
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'can_manage_team' => $data['role'] === User::EDITOR && $request->boolean('can_manage_team'),
            'can_archive' => $data['role'] === User::EDITOR && $request->boolean('can_archive'),
        ])->save();
        $user->pages()->sync($data['role'] === User::MASTER ? [] : ($data['page_ids'] ?? []));
        $this->access->flush();
        $this->log->log($request->user(), 'user.updated', $user, 'Atualizou perfil e áreas de '.$user->email, [
            'antes' => $before,
            'depois' => ['role' => $user->role, 'pages' => $user->pages()->pluck('pages.id')->sort()->values()->all()],
        ]);

        return redirect()->route('panel.users.edit', $user)->with('status', 'Permissões atualizadas.');
    }

    public function invite(Request $request, User $user)
    {
        Gate::authorize('manage-users');
        abort_unless($user->is_active, 409);

        return $this->sendInvite($request, $user, $user->hasPendingInvite() ? 'Novo convite gerado.' : 'Link para redefinir a senha gerado.');
    }

    public function deactivate(Request $request, User $user)
    {
        Gate::authorize('manage-users');
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Você não pode desativar o próprio acesso.']);
        }
        if ($user->isMaster() && $this->activeMasters() <= 1) {
            return back()->withErrors(['user' => 'É preciso manter pelo menos um Administrador Master ativo.']);
        }
        $user->forceFill(['is_active' => false, 'deactivated_at' => now(), 'remember_token' => null])->save();
        $this->log->log($request->user(), 'user.deactivated', $user, 'Desativou o acesso de '.$user->email);

        return back()->with('status', 'Acesso desativado. A autoria de conteúdos e o histórico foram preservados.');
    }

    public function reactivate(Request $request, User $user)
    {
        Gate::authorize('manage-users');
        $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();
        $this->log->log($request->user(), 'user.reactivated', $user, 'Reativou o acesso de '.$user->email);

        return back()->with('status', 'Acesso reativado.');
    }

    private function sendInvite(Request $request, User $user, string $prefix)
    {
        $token = Password::broker('invites')->createToken($user);
        $url = route('invite.accept', ['token' => $token, 'email' => $user->email]);
        $mailed = false;
        if (config('mail.default') !== 'log') {
            try {
                $user->notify(new InviteNotification($url, $this->portfolio()->name));
                $mailed = true;
            } catch (Throwable $e) {
                report($e);
            }
        }
        $this->log->log($request->user(), 'user.invited', $user, 'Gerou convite para '.$user->email.($mailed ? ' (enviado por e-mail)' : ''));

        $redirect = redirect()->route('panel.users.edit', $user)->with('status', $prefix.($mailed
            ? ' O convite foi enviado por e-mail.'
            : ' O e-mail não está configurado ou falhou: copie o link abaixo e envie por um canal seguro.'));

        return $mailed ? $redirect : $redirect->with('invite_link', $url);
    }

    private function validated(Request $request, ?User $user): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'page_ids' => ['nullable', 'array'],
            'page_ids.*' => ['integer', \App\Support\TenantRule::exists('pages')],
            'can_manage_team' => ['nullable', 'boolean'],
            'can_archive' => ['nullable', 'boolean'],
        ], [], ['name' => 'nome', 'email' => 'e-mail', 'role' => 'perfil', 'page_ids' => 'áreas']);
        $data['email'] = mb_strtolower(trim($data['email']));
        if ($data['role'] !== User::MASTER && empty($data['page_ids'])) {
            throw ValidationException::withMessages(['page_ids' => 'Escolha ao menos uma área para Editores e Colaboradores.']);
        }

        return $data;
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'pages' => Page::query()->active()->orderBy('position')->orderBy('title')->get(),
            'selectedPages' => $user->exists ? $user->pages()->pluck('pages.id')->all() : [],
        ];
    }

    private function activeMasters(): int
    {
        return User::where('portfolio_id', Tenant::id())->where('role', User::MASTER)->where('is_active', true)->count();
    }
}
