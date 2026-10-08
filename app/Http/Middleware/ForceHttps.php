<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redireciona para HTTPS quando FORCE_HTTPS=true no .env. Fica na aplicação (e não
 * no .htaccess) para que a implantação automática possa substituir os arquivos
 * públicos sem desfazer a configuração.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('portfolio.force_https') && ! $request->isSecure() && ! app()->runningUnitTests()) {
            return redirect()->to('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
