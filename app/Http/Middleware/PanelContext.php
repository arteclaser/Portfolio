<?php

namespace App\Http\Middleware;

use App\Support\MediaUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PanelContext
{
    public function handle(Request $request, Closure $next): Response
    {
        MediaUrl::usePanel(true);
        $response = $next($request);
        // Conteúdo do painel e prévias nunca devem ficar em cache compartilhado.
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
