<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function request()
    {
        return view('auth.forgot');
    }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        // Só contas ativas com senha recebem o link. A resposta é sempre a mesma.
        Password::broker('users')->sendResetLink(['email' => mb_strtolower(trim($data['email'])), 'is_active' => true]);

        return back()->with('status', 'Se o e-mail estiver cadastrado e ativo, você receberá um link em instantes. Verifique também a caixa de spam.');
    }

    public function reset(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => (string) $request->query('email'), 'invite' => false]);
    }

    public function invite(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => (string) $request->query('email'), 'invite' => true]);
    }

    public function update(Request $request, ActivityLogger $log)
    {
        $user = $this->resetWith('users', $request, $log, 'auth.password_reset');

        return redirect()->route('login')->with('status', 'Senha redefinida. Entre com a nova senha.');
    }

    public function acceptInvite(Request $request, ActivityLogger $log)
    {
        $user = $this->resetWith('invites', $request, $log, 'auth.invite_accepted');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('panel.dashboard')->with('status', 'Senha criada. Bem-vindo(a) ao painel!');
    }

    private function resetWith(string $broker, Request $request, ActivityLogger $log, string $event): User
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $resetUser = null;
        $status = Password::broker($broker)->reset(
            ['email' => mb_strtolower(trim($data['email'])), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token'], 'is_active' => true],
            function (User $user, string $password) use (&$resetUser) {
                $user->forceFill([
                    'password' => $password,
                    'password_set_at' => now(),
                    'remember_token' => Str::random(60),
                ])->save();
                $resetUser = $user;
            }
        );

        if ($status !== Password::PASSWORD_RESET || ! $resetUser) {
            throw ValidationException::withMessages(['email' => 'Link inválido ou expirado. Peça um novo link ao Administrador Master ou use "Esqueci minha senha".']);
        }
        $log->log($resetUser, $event, $resetUser, $event === 'auth.invite_accepted' ? 'Aceitou o convite e definiu a senha' : 'Redefiniu a senha');

        return $resetUser;
    }
}
