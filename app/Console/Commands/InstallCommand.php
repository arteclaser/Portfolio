<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Installer;
use Illuminate\Console\Command;

/**
 * Primeira instalação: cria o administrador da plataforma (sem senha padrão no
 * código: digitada de forma oculta ou definida por convite) e, opcionalmente, o
 * primeiro portfólio.
 */
class InstallCommand extends Command
{
    protected $signature = 'mostraqui:instalar
        {--portfolio= : Nome do primeiro portfólio (vazio = não criar agora)}
        {--curto= : Nome curto do cabeçalho (ex.: Capinzal)}
        {--endereco= : Endereço do portfólio (ex.: capinzal)}
        {--nome= : Nome do administrador da plataforma}
        {--email= : E-mail do administrador da plataforma}
        {--convite : Gerar link de convite em vez de digitar a senha}
        {--sem-areas : Não criar as áreas iniciais}';

    protected $description = 'Cria o administrador da plataforma e, se informado, o primeiro portfólio.';

    public function handle(Installer $installer): int
    {
        if (User::query()->where('is_platform_admin', true)->exists()) {
            $this->error('A plataforma já está instalada. Use mostraqui:novo-portfolio ou mostraqui:admin-plataforma.');

            return self::FAILURE;
        }

        $name = $this->option('portfolio');
        if ($name === null && $this->input->isInteractive()) {
            $name = $this->ask('Nome do primeiro portfólio (deixe em branco para criar depois)', 'Secretaria de Desenvolvimento Econômico, Inovação e Turismo de Capinzal');
        }
        if ($name) {
            $short = $this->option('curto') ?: ($this->input->isInteractive() ? $this->ask('Nome curto do cabeçalho', 'Capinzal') : null);
            $slug = $this->option('endereco') ?: ($this->input->isInteractive() ? $this->ask('Endereço (ex.: capinzal)', \App\Support\PortfolioSlug::suggest($short ?: $name)) : null);
            try {
                $portfolio = $installer->ensurePortfolio($name, $short, ! $this->option('sem-areas'), false, $slug);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info('Portfólio: '.$portfolio->name.' → '.$portfolio->publicUrl());
        }

        return CreatePlatformAdminCommand::createInteractively($this, $installer);
    }
}
