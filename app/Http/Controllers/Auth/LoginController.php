<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request, ActivityLogger $log)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
        ]);

        $ok = Auth::attempt(
            ['email' => mb_strtolower(trim($credentials['email'])), 'password' => $credentials['password'], 'is_active' => true],
            $request->boolean('remember'),
        );
        if (! $ok) {
            // Mensagem única: não revela se o e-mail existe.
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos, ou acesso desativado.']);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $log->log($user, 'auth.login', $user, 'Entrou no painel');

        return redirect()->intended(route('panel.dashboard'));
    }

    public function logout(Request $request, ActivityLogger $log)
    {
        $user = $request->user();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        if ($user) {
            $log->log($user, 'auth.logout', $user, 'Saiu do painel');
        }

        return redirect()->route('login')->with('status', 'Você saiu do painel.');
    }
}
