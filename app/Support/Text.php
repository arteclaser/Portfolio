<?php

namespace App\Support;

use Illuminate\Support\Str;

class Text
{
    /** Normaliza para busca: minúsculas e sem acentos (resultado igual em MySQL e SQLite). */
    public static function searchable(?string ...$parts): string
    {
        $joined = implode(' ', array_filter(array_map(fn ($p) => (string) $p, $parts)));
        $joined = strip_tags($joined);

        return Str::of(Str::ascii($joined, 'pt_BR'))->lower()->squish()->limit(60000, '')->toString();
    }

    public static function number(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $value = (float) $value;
        $decimals = floor($value) == $value ? 0 : 2;

        return number_format($value, $decimals, ',', '.');
    }
}
