<?php

namespace App\Support;

use App\Models\Portfolio;

/** Endereço do portfólio: vale como caminho (/capinzal) e como subdomínio (capinzal.). */
class PortfolioSlug
{
    public const PATTERN = '[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])';

    /** @return string|null mensagem de erro, ou null se válido */
    public static function problem(string $slug, ?int $ignoreId = null): ?string
    {
        if (! preg_match('/^'.self::PATTERN.'$/', $slug) || str_contains($slug, '--')) {
            return 'Use de 3 a 40 caracteres: letras minúsculas sem acento, números e hífens (sem hífen no início, no fim ou repetido).';
        }
        if (in_array($slug, config('portfolio.reserved_slugs'), true)) {
            return 'Este endereço é reservado pelo sistema. Escolha outro.';
        }
        if (Portfolio::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            return 'Já existe um portfólio com este endereço.';
        }

        return null;
    }

    public static function suggest(string $text): string
    {
        $base = Slug::make($text, 'portfolio');
        $base = trim(substr($base, 0, 40), '-');
        if (strlen($base) < 3) {
            $base = str_pad($base, 3, '0');
        }
        $candidate = $base;
        $i = 2;
        while (self::problem($candidate) !== null) {
            $suffix = '-'.$i++;
            $candidate = trim(substr($base, 0, 40 - strlen($suffix)), '-').$suffix;
            if ($i > 500) {
                break;
            }
        }

        return $candidate;
    }
}
