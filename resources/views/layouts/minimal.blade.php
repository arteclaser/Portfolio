<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('assets/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ config('portfolio.asset_version') }}">
    @hasSection('panel-css')<link rel="stylesheet" href="{{ asset('assets/css/panel.css') }}?v={{ config('portfolio.asset_version') }}">@endif
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ config('portfolio.asset_version') }}"></script>
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="{{ url('/') }}"><span class="brand__name">{{ config('app.name') }}</span></a>
    </div>
</header>
<main id="conteudo" tabindex="-1">
    @yield('content')
</main>
</body>
</html>
