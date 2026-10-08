<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Installer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Procedimento seguro do primeiro acesso: nenhum usuário ou senha padrão existe
 * no código. A senha é digitada de forma oculta ou definida pelo próprio Master
 * por meio de um link de convite de uso único.
 */
class InstallCommand extends Command
{
    protected $signature = 'mostraqui:instalar
        {--portfolio= : Nome do portfólio}
        {--curto= : Nome curto do cabeçalho (ex.: Capinzal)}
        {--nome= : Nome do primeiro Master}
        {--email= : E-mail do primeiro Master}
        {--convite : Gerar link de convite em vez de digitar a senha}
        {--sem-areas : Não criar as áreas iniciais}';

    protected $description = 'Cria o portfólio e o primeiro Administrador Master.';

    public function handle(Installer $installer): int
    {
        if (User::query()->where('role', User::MASTER)->exists()) {
            $this->error('Já existe um Administrador Master. Use mostraqui:criar-master para adicionar outro.');

            return self::FAILURE;
        }

        $portfolioName = $this->option('portfolio') ?: $this->ask('Nome do portfólio', 'Secretaria de Desenvolvimento Econômico, Inovação e Turismo de Capinzal');
        $short = $this->option('curto') ?: $this->ask('Nome curto do cabeçalho', 'Capinzal');
        $portfolio = $installer->ensurePortfolio($portfolioName, $short, ! $this->option('sem-areas'));
        $this->info('Portfólio: '.$portfolio->name);

        return CreateMasterCommand::createInteractively($this, $installer);
    }
}
