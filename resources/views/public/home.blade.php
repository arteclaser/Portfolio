@extends('layouts.public', ['current' => 'home', 'canonical' => route('home')])

@section('content')
<section class="hero container" aria-labelledby="titulo-portfolio">
    <div class="hero__grid">
        <div class="hero__kicker">@if ($portfolio->hero_kicker)<p class="kicker">{{ $portfolio->hero_kicker }}</p>@endif</div>
        <form class="search-box" action="{{ route('actions.index') }}" method="get" role="search" aria-label="Buscar ações">
            <div class="search-field">
                <label for="busca-inicio" class="visually-hidden">Buscar uma ação</label>
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="busca-inicio" type="search" name="q" placeholder="Buscar uma ação" autocomplete="off">
            </div>
            @if ($years)
                <label for="ano-inicio" class="visually-hidden">Ano</label>
                <select id="ano-inicio" name="ano" class="select">
                    <option value="">Todos os anos</option>
                    @foreach ($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="btn btn-search">Buscar</button>
        </form>
        <div class="hero__text">
            <h1 class="hero__title" id="titulo-portfolio">
                {{ $portfolio->hero_title ?: $portfolio->name }}
                @if ($portfolio->hero_highlight)<span class="highlight">{{ $portfolio->hero_highlight }}</span>@endif
            </h1>
            @if ($portfolio->hero_description)<p class="hero__lead">{{ $portfolio->hero_description }}</p>@endif
        </div>
    </div>
</section>

@if ($totalPublished === 0)
    <section class="container section section--tight">
        <div class="state">
            <h2>Ainda não há ações publicadas</h2>
            <p>As ações aparecerão aqui assim que forem revisadas e publicadas pela equipe.</p>
        </div>
    </section>
@else
    <section class="container" aria-label="Ações em destaque">
        <div class="features">
            @php $main = $featured->first(); $sides = $featured->slice(1, 2); @endphp
            @if ($main)
                @php $cover = $main->cover(); @endphp
                <article class="feature-main">
                    <div class="feature-main__media">
                        <x-picture :media="$cover?->media" alt="" :focal-x="$cover?->focal_x" :focal-y="$cover?->focal_y" variant="lg" loading="eager" sizes="(max-width: 960px) 100vw, 66vw" />
                    </div>
                    <div class="feature-main__panel on-dark">
                        <div>
                            @if ($main->primaryPage)<span class="tag">{{ $main->primaryPage->displayTitle() }}</span>@endif
                            <h2 class="feature-main__title"><a href="{{ route('actions.show', $main->public_slug) }}">{{ $main->displayTitle() }}</a></h2>
                            @if ($main->summary)<p class="feature-main__summary">{{ \Illuminate\Support\Str::limit($main->summary, 160) }}</p>@endif
                        </div>
                        <a class="btn btn--on-dark" href="{{ route('actions.show', $main->public_slug) }}" tabindex="-1" aria-hidden="true">Conhecer a ação
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                </article>
            @endif
            @if ($sides->isNotEmpty())
                <div class="features__side">
                    @foreach ($sides as $side)
                        @php $cover = $side->cover(); @endphp
                        <article class="feature-side">
                            <div class="feature-side__media">
                                <x-picture :media="$cover?->media" alt="" :focal-x="$cover?->focal_x" :focal-y="$cover?->focal_y" sizes="(max-width: 960px) 100vw, 33vw" />
                            </div>
                            <div class="feature-side__body">
                                <div>
                                    @if ($side->primaryPage)<span class="tag">{{ $side->primaryPage->displayTitle() }}</span>@endif
                                    <h2 class="feature-side__title"><a href="{{ route('actions.show', $side->public_slug) }}">{{ $side->displayTitle() }}</a></h2>
                                </div>
                                <span class="arrow-circle" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="container section" id="explorar" aria-labelledby="titulo-explorar">
        <div class="section__head">
            <h2 class="section__title" id="titulo-explorar">Explore nossas ações</h2>
            @if ($areas->count() > 1)
                <nav aria-label="Filtrar por área">
                    <ul class="chips">
                        <li><a class="chip" href="{{ route('home') }}#explorar" @if (! $activeArea) aria-current="true" @endif>Todas</a></li>
                        @foreach ($areas as $area)
                            <li><a class="chip" href="{{ route('home', ['area' => $area->slug]) }}#explorar" @if ($activeArea?->is($area)) aria-current="true" @endif>{{ $area->displayTitle() }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
        @if ($grid->isEmpty())
            <div class="state">
                <h3>Nenhuma ação nesta área por enquanto</h3>
                <p><a href="{{ route('home') }}#explorar">Ver todas as áreas</a></p>
            </div>
        @else
            <ul class="cards">
                @foreach ($grid as $item)
                    <li>@include('partials.action-card', ['item' => $item])</li>
                @endforeach
            </ul>
        @endif
        <p style="margin-top: 24px;"><a class="btn btn--outline" href="{{ route('actions.index', array_filter(['area' => $activeArea?->slug])) }}">Ver todas as ações</a></p>
    </section>

    @if ($summary['groups'])
        <section class="section section--surface" aria-labelledby="titulo-resultados">
            <div class="container">
                <h2 class="section__title" id="titulo-resultados" style="margin-bottom: 20px;">Resultados documentados</h2>
                @include('partials.indicators', ['summary' => $summary])
            </div>
        </section>
    @endif
@endif

@if ($team->isNotEmpty())
    <section class="container section" aria-labelledby="titulo-equipe">
        <div class="section__head">
            <h2 class="section__title" id="titulo-equipe">Equipe</h2>
            <a href="{{ route('team.index') }}">Conhecer toda a equipe</a>
        </div>
        @include('partials.people', ['members' => $team])
    </section>
@endif

@if ($partners->isNotEmpty())
    <section class="container section" aria-labelledby="titulo-parceiros">
        <h2 class="section__title" id="titulo-parceiros" style="margin-bottom: 20px;">Parceiros</h2>
        @include('partials.partners', ['partners' => $partners])
    </section>
@endif
@endsection
