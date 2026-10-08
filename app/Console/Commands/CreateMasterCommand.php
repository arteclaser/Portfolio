<?php

namespace App\Console\Commands;

use App\Models\Portfolio;
use App\Models\User;
use App\Services\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class CreateMasterCommand extends Command
{
    protected $signature = 'mostraqui:criar-master
        {--nome= : Nome}
        {--email= : E-mail}
        {--convite : Gerar link de convite em vez de digitar a senha}';

    protected $description = 'Cria um Administrador Master (senha digitada de forma oculta ou definida por convite).';

    public function handle(Installer $installer): int
    {
        if (! Portfolio::current()) {
            $this->error('Execute primeiro: php artisan mostraqui:instalar');

            return self::FAILURE;
        }

        return self::createInteractively($this, $installer);
    }

    public static function createInteractively(Command $cmd, Installer $installer): int
    {
        $name = $cmd->option('nome') ?: $cmd->ask('Nome do Administrador Master');
        $email = mb_strtolower(trim((string) ($cmd->option('email') ?: $cmd->ask('E-mail do Administrador Master'))));

        $check = Validator::make(['name' => $name, 'email' => $email], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);
        if ($check->fails()) {
            foreach ($check->errors()->all() as $error) {
                $cmd->error($error);
            }

            return self::FAILURE;
        }

        $useInvite = $cmd->option('convite') || ! $cmd->input->isInteractive();
        if (! $useInvite) {
            $password = (string) $cmd->secret('Senha (mínimo 10 caracteres, com letras e números; não aparece na tela)');
            $confirm = (string) $cmd->secret('Confirme a senha');
            $v = Validator::make(['password' => $password, 'password_confirmation' => $confirm], ['password' => ['required', 'confirmed', PasswordRule::defaults()]]);
            if ($v->fails()) {
                foreach ($v->errors()->all() as $error) {
                    $cmd->error($error);
                }

                return self::FAILURE;
            }
            $installer->createMaster($name, $email, $password);
            $cmd->info("Administrador Master criado: {$email}. Acesse /entrar.");

            return self::SUCCESS;
        }

        $user = new User;
        $user->forceFill(['name' => $name, 'email' => $email, 'role' => User::MASTER, 'is_active' => true, 'invited_at' => now()])->save();
        $token = Password::broker('invites')->createToken($user);
        $cmd->info("Administrador Master criado com convite pendente: {$email}");
        $cmd->line('Abra este link (uso único) para definir a senha:');
        $cmd->line(route('invite.accept', ['token' => $token, 'email' => $email]));
        $cmd->warn('Confira se APP_URL no .env corresponde ao endereço do site.');

        return self::SUCCESS;
    }
}
