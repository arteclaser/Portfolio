@php $portfolio = rescue(fn () => \App\Models\Portfolio::current(), null, false); @endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ $portfolio?->name ?? config('app.name') }}</title>
    <link rel="icon" href="{{ asset('assets/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ config('portfolio.asset_version') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/panel.css') }}?v={{ config('portfolio.asset_version') }}">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ config('portfolio.asset_version') }}"></script>
</head>
<body class="auth-body">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="{{ \App\Support\Routing::startUrl() }}">
            <span class="brand__name">{{ $portfolio?->short_name ?: ($portfolio?->name ?? config('portfolio.platform_name')) }}</span>
            @if ($portfolio?->tagline)<span class="brand__divider" aria-hidden="true"></span><span class="brand__tagline"><strong>{{ $portfolio->tagline }}</strong>{{ $portfolio->subtitle }}</span>@endif
        </a>
    </div>
</header>
<main id="conteudo" tabindex="-1" class="auth-main">
    <div class="auth-card">
        @yield('content')
    </div>
</main>
</body>
</html>
