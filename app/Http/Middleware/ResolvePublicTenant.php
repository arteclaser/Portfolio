<?php

namespace App\Http\Middleware;

use App\Models\Portfolio;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica o portfólio pelo endereço (/capinzal ou capinzal.mostraqui.net) antes
 * de qualquer consulta. Portfólios inexistentes ou desativados respondem 404.
 */
class ResolvePublicTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $slug = (string) $route->parameter('portfolio');

        if ($slug === 'www' && config('portfolio.routing') === 'subdomain') {
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
