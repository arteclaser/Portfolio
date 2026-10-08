<?php

namespace App\Http\Middleware;

use App\Support\MediaUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicContext
{
    public function handle(Request $request, Closure $next): Response
    {
        MediaUrl::usePanel(false);

        return $next($request);
    }
}
