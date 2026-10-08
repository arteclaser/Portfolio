@extends('layouts.public', [
    'current' => 'areas',
    'title' => $page->title,
    'metaDescription' => \App\Support\Markdown::plain($description, 280),
    'canonical' => $preview ? null : route('pages.show', $page->slug),
    'ogImage' => $preview ? null : \App\Support\MediaUrl::publicAbsolute($cover, 'lg'),
    'preview' => $preview,
])

@php $hasCoverBlock = collect($blocks)->contains(fn ($b) => $b['type'] === 'capa'); @endphp

@section('content')
<div class="container" style="--accent-page: {{ $page->accent() }};">
    <nav class="breadcrumb" aria-label="Você está em">
        <ol>
            <li><a href="{{ route('home') }}">Início</a></li>
            <li><a href="{{ route('areas.index') }}">Áreas de atuação</a></li>
            @foreach ($ancestors as $ancestor)
                <li><a href="{{ route('pages.show', $ancestor->slug) }}">{{ $ancestor->displayTitle() }}</a></li>
            @endforeach
            <li><span aria-current="page">{{ $page->displayTitle() }}</span></li>
        </ol>
    </nav>

    @if (! $hasCoverBlock)
        <header class="article-head">
            <span class="tag">{{ $page->kindLabel() }}</span>
            <h1>{{ $page->title }}</h1>
            @if ($description)<div class="lead prose">{!! \App\Support\Markdown::render($description) !!}</div>@endif
        </header>
    @endif

    @foreach ($blocks as $index => $block)
        @include($block['view'], $block['data'] + ['page' => $page, 'isFirst' => $index === 0, 'description' => $description, 'blockId' => 'bloco-'.$index])
    @endforeach

    @if (empty($blocks) && ! $description)
        <div class="state block"><h2>Conteúdo em preparação</h2><p>Esta página ainda não tem conteúdo publicado.</p></div>
    @endif

    @if ($children->isNotEmpty())
        <section class="block section" aria-labelledby="sec-subpaginas">
            <h2 id="sec-subpaginas">Programas e projetos</h2>
            <ul class="subpages">
                @foreach ($children as $child)
                    <li>
                        <article class="card">
                            <div class="card__media"><x-picture :media="$child->publicCover()" alt="" sizes="(max-width: 720px) 100vw, 360px" /></div>
                            <div class="card__body">
                                <div>
                                    <span class="tag">{{ $child->kindLabel() }}</span>
                                    <h3 class="card__title"><a href="{{ route('pages.show', $child->slug) }}">{{ $child->displayTitle() }}</a></h3>
                                </div>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
    <div style="height: 32px"></div>
</div>
@endsection
