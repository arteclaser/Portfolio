<?php

namespace App\Providers;

use App\Models\Action;
use App\Models\User;
use App\Services\MediaVisibility;
use App\Support\Access;
use App\Support\PageTree;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Um estado por requisição (também correto em filas e testes).
        $this->app->scoped(PageTree::class);
        $this->app->scoped(Access::class);
        $this->app->scoped(MediaVisibility::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('pt_BR');
        Paginator::defaultView('partials.pagination');

        Route::bind('trashedAction', fn ($value) => Action::onlyTrashed()->findOrFail((int) $value));

        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        Gate::define('manage-users', fn (User $user) => $user->is_active && $user->isMaster());
        Gate::define('manage-settings', fn (User $user) => $user->is_active && $user->isMaster());
        Gate::define('view-activity', fn (User $user) => $user->is_active && $user->isMaster());

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
        RateLimiter::for('password-email', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('link-preview', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('setup', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
