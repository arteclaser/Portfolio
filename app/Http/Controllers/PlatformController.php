<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Support\Tenant;

/** Página inicial de mostraqui.net: lista os portfólios publicados. */
class PlatformController extends Controller
{
    public function home()
    {
        Tenant::forget();
        try {
            $portfolios = Portfolio::query()->active()->where('is_listed', true)->orderBy('name')->get();
        } catch (\Illuminate\Database\QueryException) {
            return response()->view('errors.not-installed', [], 503);
        }
        if ($portfolios->isEmpty() && ! Portfolio::query()->exists()) {
            return response()->view('errors.not-installed', [], 503);
        }

        return view('platform.home', ['portfolios' => $portfolios]);
    }

    /** Modo subdomínio: endereços antigos /capinzal/... seguem para capinzal.mostraqui.net/... */
    public function redirectToSubdomain(string $portfolio, ?string $rest = null)
    {
        $found = Portfolio::query()->active()->where('slug', $portfolio)->first() ?? abort(404);
        $query = request()->getQueryString();
        $url = request()->getScheme().'://'.$found->slug.'.'.config('portfolio.base_domain').'/'.ltrim((string) $rest, '/').($query ? '?'.$query : '');

        return redirect()->away($url, 301);
    }
}
