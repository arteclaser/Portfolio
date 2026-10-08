<?php

namespace App\Http\Middleware;

use App\Models\Portfolio;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortfolioInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $installed = Portfolio::current() !== null;
        } catch (\Illuminate\Database\QueryException) {
            $installed = false; // Tabelas ainda não criadas.
        }
        if (! $installed) {
            return response()->view('errors.not-installed', [], 503);
        }

        return $next($request);
    }
}
