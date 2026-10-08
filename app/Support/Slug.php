<?php

namespace App\Support;

use Illuminate\Support\Str;

class Slug
{
    /** Endereços reservados pelas rotas do sistema. */
    public const RESERVED = ['painel', 'entrar', 'sair', 'midia', 'acoes', 'areas', 'equipe', 'instalar', 'convite', 'redefinir-senha', 'esqueci-senha', 'assets', 'up', 'acessibilidade', 'privacidade'];

    public static function make(?string $text, string $fallback = 'item'): string
    {
        $slug = Str::slug((string) $text, '-', 'pt_BR');
        $slug = Str::limit($slug, 120, '');
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : $fallback;
    }

    /**
     * @param  callable(string): bool  $exists
     */
    public static function unique(string $base, callable $exists): string
    {
        $candidate = in_array($base, self::RESERVED, true) ? $base.'-1' : $base;
        $i = 2;
        while ($exists($candidate)) {
            $candidate = $base.'-'.$i++;
        }

        return $candidate;
    }
}
