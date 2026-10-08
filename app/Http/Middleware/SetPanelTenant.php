<?php

namespace App\Http\Middleware;

use App\Models\Portfolio;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o portfólio do painel: o da conta do usuário ou, para a administração da
 * plataforma, o escolhido em "Portfólios → Gerenciar". Roda antes da resolução dos
 * parâmetros das rotas, para que {ação}, {página} e {mídia} de outro portfólio não
 * sejam encontrados.
 */
class SetPanelTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if ($user->isPlatformAdmin()) {
            $id = $request->session()->get('panel_portfolio_id');
            $portfolio = ($id ? Portfolio::find($id) : null) ?? Portfolio::query()->orderBy('id')->first();
            if (! $portfolio) {
                Tenant::forget();
                // Sem nenhum portfólio ainda: só a gestão da plataforma faz sentido.
                if (! $request->routeIs('panel.platform.*')) {
                    return redirect()->route('panel.platform.create')->with('status', 'Crie o primeiro portfólio da plataforma.');
                }

                return $next($request);
            }
        } else {
            $portfolio = $user->portfolio;
            if (! $portfolio || ! $portfolio->is_active) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => 'O portfólio da sua conta está desativado. Procure a administração da plataforma.']);
            }
        }

        Tenant::set($portfolio);
        URL::defaults(['portfolio' => $portfolio->slug]);

        return $next($request);
    }
}
