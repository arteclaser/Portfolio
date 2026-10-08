<?php

namespace App\Console\Commands;

use App\Models\Portfolio;
use App\Models\User;
use App\Services\DemoContent;
use Illuminate\Console\Command;

class DemoCommand extends Command
{
    protected $signature = 'mostraqui:demo {--forcar : Permite em APP_ENV=production (o site ficará marcado como demonstração)}';

    protected $description = 'Cria conteúdo de demonstração e marca o ambiente como demonstração.';

    public function handle(DemoContent $demo): int
    {
        if (app()->environment('production') && ! $this->option('forcar')) {
            $this->error('Ambiente de produção: dados de demonstração não são criados. Use um ambiente separado (ex.: demo.mostraqui.net) ou --forcar.');

            return self::FAILURE;
        }
        $portfolio = Portfolio::current();
        $master = User::where('role', User::MASTER)->where('is_active', true)->orderBy('id')->first();
        if (! $portfolio || ! $master) {
            $this->error('Execute primeiro: php artisan mostraqui:instalar');

            return self::FAILURE;
        }
        $credentials = $demo->seed($portfolio, $master);
        $this->info('Conteúdo de demonstração criado. O site exibe o aviso "Ambiente de demonstração".');
        foreach ($credentials as $email => $password) {
            $this->line("Usuário de demonstração: {$email} · senha gerada: {$password}");
        }
        if ($credentials) {
            $this->warn('Guarde essas senhas agora: elas não são exibidas novamente.');
        }

        return self::SUCCESS;
    }
}
