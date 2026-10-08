@php
    $nav = [
        ['home', 'Início', route('home')],
        ['areas', 'Áreas de atuação', route('areas.index')],
        ['actions', 'Ações', route('actions.index')],
        ['team', 'Equipe', route('team.index')],
    ];
@endphp
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand__name">{{ $portfolio->short_name ?: $portfolio->name }}</span>
            @if ($portfolio->tagline || $portfolio->subtitle)
                <span class="brand__divider" aria-hidden="true"></span>
                <span class="brand__tagline">
                    @if ($portfolio->tagline)<strong>{{ $portfolio->tagline }}</strong>@endif
                    {{ $portfolio->subtitle }}
                </span>
            @endif
        </a>
        <nav class="site-nav" aria-label="Navegação principal">
            <ul>
                @foreach ($nav as [$key, $label, $url])
                    <li><a class="nav-link" href="{{ $url }}" @if (($current ?? null) === $key) aria-current="page" @endif>{{ $label }}</a></li>
                @endforeach
            </ul>
            <button type="button" class="theme-toggle" data-theme-toggle hidden>
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <span class="visually-hidden" data-theme-label>Tema</span>
            </button>
            <a class="btn-header" href="{{ route('login') }}">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                Acesso da equipe
            </a>
        </nav>
    </div>
</header>
