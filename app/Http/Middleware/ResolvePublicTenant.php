<?php

namespace App\Http\Middleware;

use App\Models\Portfolio;
use App\Support\Routing;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica o portfólio antes de qualquer consulta: no modo único, o portfólio do
 * domínio; nos demais, pelo endereço (/capinzal ou capinzal.mostraqui.net).
 * Portfólios inexistentes ou desativados respondem 404.
 */
class ResolvePublicTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Routing::single()) {
            // Instalação ainda sem tabelas ou sem portfólio: página "instalação pendente".
            $portfolio = rescue(fn () => Portfolio::single(), null, false);
            if (! $portfolio) {
                Tenant::forget();

                return response()->view('errors.not-installed', [], 503);
            }
            Tenant::set($portfolio);
            URL::defaults(['portfolio' => $portfolio->slug]);

            return $next($request);
        }

        $route = $request->route();
        $slug = (string) $route->parameter('portfolio');

        if ($slug === 'www' && Routing::subdomain()) {
            return redirect()->away($request->getScheme().'://'.config('portfolio.base_domain').$request->getRequestUri(), 301);
        }

        try {
            $portfolio = Portfolio::query()->where('slug', $slug)->where('is_active', true)->first();
        } catch (\Illuminate\Database\QueryException) {
            $portfolio = null; // Instalação ainda sem tabelas.
        }
        if (! $portfolio) {
            Tenant::forget();
            abort(404);
        }

        Tenant::set($portfolio);
        URL::defaults(['portfolio' => $portfolio->slug]);
        // Os controladores recebem só os próprios parâmetros (ex.: o endereço da ação).
        $route->forgetParameter('portfolio');

        return $next($request);
    }
}
