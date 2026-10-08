<?php

return [
    // Incrementar ao alterar CSS/JS para invalidar o cache dos navegadores.
    'asset_version' => '2026.10.7',

    // Redireciona todo acesso para HTTPS (ative depois de instalar o certificado SSL).
    'force_https' => (bool) env('FORCE_HTTPS', false),

    // Nome da plataforma (página inicial de mostraqui.net e tela de acesso).
    'platform_name' => env('PLATFORM_NAME', 'Mostraqui'),
    'platform_tagline' => env('PLATFORM_TAGLINE', 'Portfólios institucionais de ações públicas'),

    /*
     * Como o portfólio é endereçado:
     *  - single:    um único portfólio direto no domínio: mostraqui.net        (padrão)
     *  - path:      vários portfólios por caminho: mostraqui.net/capinzal
     *  - subdomain: vários portfólios por subdomínio: capinzal.mostraqui.net
     *              (exige DNS curinga, subdomínio "*" e SSL curinga, uma única vez)
     * Ao mudar de "path" para "subdomain", os endereços antigos redirecionam (301) para os novos.
     */
    'routing' => env('PORTFOLIO_ROUTING', 'single'),

    // Modo "single": endereço interno (slug) do portfólio exibido. Vazio = o primeiro portfólio ativo.
    'single' => env('PORTFOLIO_SINGLE', ''),

    // Domínio principal, sem "www" (usado no modo subdomínio). Padrão: o host de APP_URL.
    'base_domain' => env('PORTFOLIO_BASE_DOMAIN') ?: (parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

    // Endereços que não podem ser usados por portfólios (rotas do sistema e serviços do cPanel).
    'reserved_slugs' => [
        'acoes', 'areas', 'equipe', 'painel', 'entrar', 'sair', 'esqueci-senha', 'redefinir-senha', 'convite', 'instalar', 'up', 'assets',
        'midia', 'storage', 'vendor', 'build', 'api', 'admin', 'administrador', 'plataforma', 'www', 'mail', 'email',
        'webmail', 'ftp', 'cpanel', 'whm', 'webdisk', 'cpcalendars', 'cpcontacts', 'autodiscover', 'autoconfig',
        'ns1', 'ns2', 'smtp', 'imap', 'pop', 'pop3', 'demo', 'teste', 'test', 'staging', 'dev', 'acessibilidade',
        'privacidade', 'ajuda', 'suporte', 'contato', 'sobre', 'login', 'logout', 'static', 'cdn', 'favicon-ico',
        'robots-txt', 'well-known',
    ],
];
