<?php

namespace App\Console\Commands;

use App\Services\Installer;
use Illuminate\Console\Command;
use InvalidArgumentException;

class NewPortfolioCommand extends Command
{
    protected $signature = 'mostraqui:novo-portfolio
        {nome : Nome do portfólio}
        {--curto= : Nome curto do cabeçalho}
        {--endereco= : Endereço (ex.: capinzal)}
        {--areas=* : Áreas iniciais (repita a opção). Padrão: nenhuma}
        {--master-nome= : Nome do Master do portfólio}
        {--master-email= : E-mail do Master do portfólio (gera convite)}';

    protected $description = 'Cria um portfólio (disponível na hora no seu endereço) e, opcionalmente, o convite do Master.';

    public function handle(Installer $installer): int
    {
        try {
            $portfolio = $installer->createPortfolio($this->argument('nome'), $this->option('curto'), $this->option('endereco'), $this->option('areas'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Portfólio criado: '.$portfolio->name);
        $this->line('Endereço público: '.$portfolio->publicUrl());

        if ($email = $this->option('master-email')) {
            $user = $installer->createMaster($portfolio, $this->option('master-nome') ?: $email, $email);
            $this->line('Convite do Master ('.$user->email.'): '.$installer->inviteUrl($user));
        }

        return self::SUCCESS;
    }
}
