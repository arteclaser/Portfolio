@php
    $user = auth()->user();
    $portfolio = \App\Models\Portfolio::current();
    $route = request()->route()?->getName() ?? '';
    $is = fn (string $prefix) => str_starts_with($route, $prefix) ? 'page' : null;
    $reviewCount = ($user->isMaster() || $user->isEditor())
        ? \App\Http\Controllers\Panel\ActionController::scopedQuery(app(\App\Support\Access::class)->allowedPageIds($user))->where('actions.working_state', 'in_review')->count()
        : 0;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · Painel · {{ $portfolio->name }}</title>
    <link rel="icon" href="{{ asset('assets/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ config('portfolio.asset_version') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/panel.css') }}?v={{ config('portfolio.asset_version') }}">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ config('portfolio.asset_version') }}"></script>
    <script src="{{ asset('assets/js/panel.js') }}?v={{ config('portfolio.asset_version') }}" defer></script>
</head>
<body data-saved-form="{{ session('saved_form') }}">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<div class="panel" data-panel>
    <aside class="panel-side" id="menu-painel" aria-label="Menu do painel">
        <a class="panel-side__brand" href="{{ route('panel.dashboard') }}">
            <strong>{{ $portfolio->short_name ?: 'Portfólio' }}</strong>
            <span>Painel da equipe</span>
        </a>
        <nav class="panel-nav" aria-label="Seções do painel">
            <ul>
                <li><a href="{{ route('panel.dashboard') }}" @if ($route === 'panel.dashboard') aria-current="page" @endif>Visão geral</a></li>
                <li><a href="{{ route('panel.actions.index') }}" aria-current="{{ $is('panel.actions') }}">Ações @if ($reviewCount)<span class="count" aria-label="{{ $reviewCount }} em revisão">{{ $reviewCount }}</span>@endif</a></li>
                @can('viewAny', \App\Models\Page::class)
                    <li><a href="{{ route('panel.pages.index') }}" aria-current="{{ $is('panel.pages') }}">Páginas</a></li>
                @endcan
                @can('viewAny', \App\Models\TeamMember::class)
                    <li><a href="{{ route('panel.team.index') }}" aria-current="{{ $is('panel.team') }}">Equipe</a></li>
                @endcan
                @can('viewAny', \App\Models\Partner::class)
                    <li><a href="{{ route('panel.partners.index') }}" aria-current="{{ $is('panel.partners') }}">Parceiros</a></li>
                @endcan
                <li><a href="{{ route('panel.media.index') }}" aria-current="{{ $is('panel.media') }}">Mídias</a></li>
            </ul>
            @can('manage-users')
                <p class="panel-nav__group">Administração</p>
                <ul>
                    <li><a href="{{ route('panel.users.index') }}" aria-current="{{ $is('panel.users') }}">Usuários e permissões</a></li>
                    <li><a href="{{ route('panel.settings.edit') }}" aria-current="{{ $is('panel.settings') }}">Configurações</a></li>
                    <li><a href="{{ route('panel.activity.index') }}" aria-current="{{ $is('panel.activity') }}">Registro de atividades</a></li>
                </ul>
            @endcan
        </nav>
    </aside>
    <div class="panel-main">
        <header class="panel-top">
            <button type="button" class="btn btn--secondary btn--small panel-menu-toggle" data-menu-toggle aria-expanded="false" aria-controls="menu-painel">Menu</button>
            <a href="{{ route('home') }}">Ver o portfólio público</a>
            <div class="panel-top__user">
                <span>{{ $user->name }} <span class="role">· {{ $user->roleLabel() }}</span></span>
                <button type="button" class="btn btn--ghost btn--small" data-theme-toggle hidden><span data-theme-label>Tema</span></button>
                <form method="post" action="{{ route('logout') }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn btn--secondary btn--small">Sair</button>
                </form>
            </div>
        </header>
        <main id="conteudo" class="panel-content" tabindex="-1">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
