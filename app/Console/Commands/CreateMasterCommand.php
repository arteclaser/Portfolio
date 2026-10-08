<?php

namespace App\Console\Commands;

use App\Models\Portfolio;
use App\Services\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateMasterCommand extends Command
{
    protected $signature = 'mostraqui:criar-master {portfolio : Endereço do portfólio (ex.: capinzal)} {--nome= : Nome} {--email= : E-mail}';

    protected $description = 'Cria um Administrador Master de um portfólio e gera o link de convite.';

    public function handle(Installer $installer): int
    {
        $portfolio = Portfolio::where('slug', $this->argument('portfolio'))->first();
        if (! $portfolio) {
            $this->error('Portfólio não encontrado: '.$this->argument('portfolio'));

            return self::FAILURE;
        }
        $name = $this->option('nome') ?: $this->ask('Nome');
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail'))));
        $check = Validator::make(['name' => $name, 'email' => $email], ['name' => 'required|max:255', 'email' => 'required|email|max:255|unique:users,email']);
        if ($check->fails()) {
            foreach ($check->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $user = $installer->createMaster($portfolio, $name, $email);
        $this->info("Master de \"{$portfolio->name}\" criado: {$email}");
        $this->line('Link de convite (uso único) para definir a senha:');
        $this->line($installer->inviteUrl($user));

        return self::SUCCESS;
    }
}
