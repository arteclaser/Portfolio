<?php

namespace App\Support;

use App\Models\Portfolio;

/**
 * Portfólio da requisição atual. Definido pelo middleware (site público: pelo
 * endereço; painel: pela conta do usuário) antes de qualquer consulta, para que
 * o escopo automático isole os dados de cada portfólio.
 */
class Tenant
{
    private static ?Portfolio $portfolio = null;

    public static function set(?Portfolio $portfolio): void
    {
        self::$portfolio = $portfolio;
    }

    public static function get(): ?Portfolio
    {
        return self::$portfolio;
    }

    public static function id(): ?int
    {
        return self::$portfolio?->id;
    }

    public static function forget(): void
    {
        self::$portfolio = null;
    }

    /** Executa algo no contexto de outro portfólio e restaura o anterior. */
    public static function run(Portfolio $portfolio, callable $callback): mixed
    {
        $previous = self::$portfolio;
        self::$portfolio = $portfolio;
        try {
            return $callback();
        } finally {
            self::$portfolio = $previous;
        }
    }
}
