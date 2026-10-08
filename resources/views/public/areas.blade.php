@extends('layouts.public', ['current' => 'areas', 'title' => 'Áreas de atuação', 'canonical' => route('areas.index')])

@section('content')
<div class="container section">
    <h1 class="section__title">Áreas de atuação</h1>
    <p class="lead">Áreas, programas e projetos que organizam as ações do portfólio.</p>
    @if ($roots->isEmpty())
        <div class="state"><h2>Nenhuma área publicada</h2><p>As áreas aparecerão aqui quando forem publicadas.</p></div>
    @else
        <ul class="cards" style="margin-top: 24px;">
            @foreach ($roots as $page)
                <li>
                    <article class="card">
                        <div class="card__media"><x-picture :media="$page->publicCover()" alt="" sizes="(max-width: 720px) 100vw, 400px" /></div>
                        <div class="card__body">
                            <div>
                                <span class="tag">{{ $page->kindLabel() }}</span>
                                <h2 class="card__title"><a href="{{ route('pages.show', $page->slug) }}">{{ $page->displayTitle() }}</a></h2>
                                @if (! empty($children[$page->id]))
                                    <p class="card__meta">{{ $children[$page->id]->pluck('title')->implode(' · ') }}</p>
                                @endif
                            </div>
                            <span class="arrow-circle" aria-hidden="true"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
