<?php

namespace App\Console\Commands;

use App\Models\Portfolio;
use App\Models\User;
use App\Services\DemoContent;
use Illuminate\Console\Command;

class DemoCommand extends Command
{
    protected $signature = 'mostraqui:demo
        {--portfolio= : Endereço do portfólio que receberá a demonstração (padrão: o primeiro)}
        {--forcar : Permite em APP_ENV=production (o portfólio ficará marcado como demonstração)}';

    protected $description = 'Cria conteúdo de demonstração e marca o ambiente como demonstração.';

    public function handle(DemoContent $demo): int
    {
        if (app()->environment('production') && ! $this->option('forcar')) {
            $this->error('Ambiente de produção: dados de demonstração não são criados. Use um ambiente separado (ex.: demo.mostraqui.net) ou --forcar.');

            return self::FAILURE;
        }
        $portfolio = $this->option('portfolio')
            ? Portfolio::where('slug', $this->option('portfolio'))->first()
            : Portfolio::query()->orderBy('id')->first();
        $master = User::where('is_active', true)->where(fn ($q) => $q->where('is_platform_admin', true)
            ->orWhere(fn ($q2) => $q2->where('role', User::MASTER)->where('portfolio_id', $portfolio?->id)))->orderByDesc('is_platform_admin')->orderBy('id')->first();
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
