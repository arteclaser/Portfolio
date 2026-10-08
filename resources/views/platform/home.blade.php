<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('portfolio.platform_name') }} · {{ config('portfolio.platform_tagline') }}</title>
    <meta name="description" content="{{ config('portfolio.platform_tagline') }}">
    <link rel="icon" href="{{ asset('assets/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ config('portfolio.asset_version') }}">
    <script src="{{ asset('assets/js/theme-init.js') }}?v={{ config('portfolio.asset_version') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}?v={{ config('portfolio.asset_version') }}" defer></script>
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="{{ route('platform.home') }}">
            <span class="brand__name">{{ config('portfolio.platform_name') }}</span>
            <span class="brand__divider" aria-hidden="true"></span>
            <span class="brand__tagline"><strong>{{ config('portfolio.platform_tagline') }}</strong></span>
        </a>
        <nav class="site-nav" aria-label="Navegação principal">
            <button type="button" class="theme-toggle" data-theme-toggle hidden>
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <span class="visually-hidden" data-theme-label>Tema</span>
            </button>
            <a class="btn-header" href="{{ route('login') }}">Acesso da equipe</a>
        </nav>
    </div>
</header>
<main id="conteudo" tabindex="-1">
    <section class="hero container" aria-labelledby="titulo">
        <p class="kicker">Portfólios institucionais</p>
        <h1 class="hero__title" id="titulo">Ações públicas, <span class="highlight">em um só lugar.</span></h1>
        <p class="hero__lead">Cada órgão mantém o próprio portfólio de ações, com equipe, resultados documentados e publicações.</p>
    </section>
    <section class="container section section--tight" aria-labelledby="titulo-lista">
        <h2 class="section__title" id="titulo-lista" style="margin-bottom: 20px">Portfólios</h2>
        @if ($portfolios->isEmpty())
            <div class="state"><h3>Nenhum portfólio publicado</h3><p>Os portfólios aparecerão aqui quando forem criados.</p></div>
        @else
            <ul class="cards">
                @foreach ($portfolios as $p)
                    <li>
                        <article class="card">
                            <div class="card__body">
                                <div>
                                    @if ($p->short_name)<span class="tag">{{ $p->short_name }}</span>@endif
                                    <h3 class="card__title"><a href="{{ $p->publicUrl() }}">{{ $p->name }}</a></h3>
                                    <p class="card__meta">{{ preg_replace('#^https?://#', '', $p->publicUrl()) }}</p>
                                </div>
                                <span class="arrow-circle" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</main>
<footer class="site-footer">
    <div class="container site-footer__grid">
        <div><div class="site-footer__name">{{ config('portfolio.platform_name') }}</div><div>{{ config('portfolio.platform_tagline') }}</div></div>
        <div><p><a href="{{ route('login') }}">Acesso da equipe</a></p></div>
    </div>
</footer>
</body>
</html>
