<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsurePortfolioInstalled;
use App\Http\Middleware\PanelContext;
use App\Http\Middleware\PublicContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Portfólio público: sem sessão e sem cookies.
            Route::middleware('public')->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->group('public', [
            PublicContext::class,
            EnsurePortfolioInstalled::class,
            SubstituteBindings::class,
        ]);
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'panel' => PanelContext::class,
            'installed' => EnsurePortfolioInstalled::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel.dashboard'));
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ?: null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
