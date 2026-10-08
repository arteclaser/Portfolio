<?php

namespace App\Support;

/**
 * Formato dos endereços públicos (PORTFOLIO_ROUTING):
 *  - single:    um único portfólio direto no domínio (mostraqui.net)   — padrão
 *  - path:      vários portfólios por caminho (mostraqui.net/capinzal)
 *  - subdomain: vários portfólios por subdomínio (capinzal.mostraqui.net)
 */
final class Routing
{
    public const MODES = ['single', 'path', 'subdomain'];

    public static function mode(): string
    {
        $mode = (string) config('portfolio.routing');

        return in_array($mode, self::MODES, true) ? $mode : 'single';
    }

    public static function single(): bool
    {
        return self::mode() === 'single';
    }

    public static function subdomain(): bool
    {
        return self::mode() === 'subdomain';
    }

    /** Página inicial do domínio: o portfólio (modo único) ou a lista de portfólios. */
    public static function startUrl(): string
    {
        return self::single() ? route('home') : route('platform.home');
    }
}
