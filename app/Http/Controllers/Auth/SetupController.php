<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Installer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Instalação pela web, para hospedagens sem acesso ao terminal. Só funciona com
 * SETUP_TOKEN definido no .env e enquanto não existir nenhum usuário.
 */
class SetupController extends Controller
{
    private function available(): bool
    {
        if (blank(config('services.setup.token'))) {
            return false;
        }
        // Sem tabelas ainda (primeira instalação sem terminal) também é permitido.
        return ! Schema::hasTable('users') || User::query()->count() === 0;
    }

    public function show()
    {
        abort_unless($this->available(), 404);

        return view('auth.setup');
    }

    public function store(Request $request, Installer $installer)
    {
        abort_unless($this->available(), 404);

        $data = $request->validate([
            'setup_token' => ['required', 'string'],
            'portfolio_name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        if (! hash_equals((string) config('services.setup.token'), (string) $data['setup_token'])) {
            throw ValidationException::withMessages(['setup_token' => 'Código de instalação incorreto.']);
        }

        // Cria ou atualiza as tabelas: permite instalar sem acesso ao terminal.
        Artisan::call('migrate', ['--force' => true]);

        $installer->ensurePortfolio($data['portfolio_name'], $data['short_name'] ?? null, $request->boolean('create_areas'));
        $user = $installer->createMaster($data['name'], $data['email'], $data['password']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('panel.dashboard')->with('status', 'Instalação concluída. Remova SETUP_TOKEN do arquivo .env por segurança.');
    }
}
