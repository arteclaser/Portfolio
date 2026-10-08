<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** Regras de validação limitadas ao portfólio atual. */
class TenantRule
{
    public static function exists(string $table): Exists
    {
        $rule = Rule::exists($table, 'id');

        return Tenant::id() ? $rule->where('portfolio_id', Tenant::id()) : $rule;
    }
}
