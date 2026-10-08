<?php

namespace App\Console\Commands;

use App\Services\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class CreatePlatformAdminCommand extends Command
{
    protected $signature = 'mostraqui:admin-plataforma
        {--nome= : Nome}
        {--email= : E-mail}
        {--convite : Gerar link de convite em vez de digitar a senha}';

    protected $description = 'Cria um administrador da plataforma (gerencia todos os portfólios).';

    public function handle(Installer $installer): int
    {
        return self::createInteractively($this, $installer);
    }

    public static function createInteractively(Command $cmd, Installer $installer): int
    {
        $name = $cmd->option('nome') ?: $cmd->ask('Nome do administrador da plataforma');
        $email = mb_strtolower(trim((string) ($cmd->option('email') ?: $cmd->ask('E-mail do administrador da plataforma'))));
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

        if (! $cmd->option('convite') && $cmd->input->isInteractive()) {
            $password = (string) $cmd->secret('Senha (mínimo 10 caracteres, com letras e números; não aparece na tela)');
            $confirm = (string) $cmd->secret('Confirme a senha');
            $v = Validator::make(['password' => $password, 'password_confirmation' => $confirm], ['password' => ['required', 'confirmed', PasswordRule::defaults()]]);
            if ($v->fails()) {
                foreach ($v->errors()->all() as $error) {
                    $cmd->error($error);
                }

                return self::FAILURE;
            }
            $installer->createPlatformAdmin($name, $email, $password);
            $cmd->info("Administrador da plataforma criado: {$email}. Acesse /entrar.");

            return self::SUCCESS;
        }

        $user = $installer->createPlatformAdmin($name, $email, null);
        $cmd->info("Administrador da plataforma criado com convite pendente: {$email}");
        $cmd->line('Abra este link (uso único) para definir a senha:');
        $cmd->line($installer->inviteUrl($user));
        $cmd->warn('Confira se APP_URL no .env corresponde ao endereço do site.');

        return self::SUCCESS;
    }
}
