@php
    /** @var \App\Models\ActionVersion $version */
    $canonical = $preview ? null : route('actions.show', $actionSlug);
    $ogImage = $preview ? null : \App\Support\MediaUrl::publicAbsolute($cover?->media, 'lg');
@endphp
@extends('layouts.public', [
    'current' => 'actions',
    'title' => $version->displayTitle(),
    'metaDescription' => $version->summary,
    'canonical' => $canonical,
    'ogType' => 'article',
    'ogTitle' => $version->displayTitle(),
    'ogImage' => $ogImage,
    'ogImageAlt' => $cover?->altText(),
    'preview' => $preview,
])

@section('content')
<div class="container">
    <nav class="breadcrumb" aria-label="Você está em">
        <ol>
            <li><a href="{{ route('home') }}">Início</a></li>
            <li><a href="{{ route('actions.index') }}">Ações</a></li>
            @if ($version->primaryPage && $version->primaryPage->isPublic())
                <li><a href="{{ route('pages.show', $version->primaryPage->slug) }}">{{ $version->primaryPage->displayTitle() }}</a></li>
            @endif
            <li><span aria-current="page">{{ $version->displayTitle() }}</span></li>
        </ol>
    </nav>

    <header class="article-head">
        @if ($version->primaryPage)<span class="tag">{{ $version->primaryPage->displayTitle() }}</span>@endif
        <h1>{{ $version->displayTitle() }}</h1>
        @if ($version->summary)<p class="lead">{{ $version->summary }}</p>@endif
        <ul class="meta-row">
            @if ($version->periodLabel())<li>{{ $version->periodLabel() }}</li>@endif
            @if ($version->location)<li>{{ $version->location }}</li>@endif
            @if ($version->activity_status)<li><span class="badge badge--{{ $version->activity_status }}">{{ $version->activityStatusLabel() }}</span></li>@endif
        </ul>
    </header>

    @if ($cover)
        <figure class="cover">
            <div class="cover__frame">
                <x-picture :media="$cover->media" :alt="$cover->altText()" :focal-x="$cover->focal_x" :focal-y="$cover->focal_y" variant="lg" loading="eager" sizes="(max-width: 1240px) 100vw, 1200px" />
            </div>
            @if ($cover->caption || $cover->credit)
                <figcaption>{{ $cover->caption }}@if ($cover->caption && $cover->credit) · @endif @if ($cover->credit)Crédito: {{ $cover->credit }}@endif</figcaption>
            @endif
        </figure>
    @endif

    <div class="article">
        <div>
            @if ($version->description)
                <div class="prose">{!! \App\Support\Markdown::render($version->description) !!}</div>
            @endif

            @if ($version->objectives)
                <section class="content-section" aria-labelledby="sec-objetivos">
                    <h2 id="sec-objetivos">Objetivos</h2>
                    <div class="prose">{!! \App\Support\Markdown::render($version->objectives) !!}</div>
                </section>
            @endif

            @if ($version->results || $indicators->isNotEmpty())
                <section class="content-section" aria-labelledby="sec-resultados">
                    <h2 id="sec-resultados">Resultados</h2>
                    @if ($version->results)<div class="prose">{!! \App\Support\Markdown::render($version->results) !!}</div>@endif
                    @if ($indicators->isNotEmpty())
                        <ul class="stats" style="margin-top: 16px;">
                            @foreach ($indicators as $indicator)
                                <li class="stat">
                                    <div class="stat__value">{{ \App\Support\Text::number($indicator->value) }}</div>
                                    <div class="stat__label">{{ $indicator->label }}@if ($indicator->unit) ({{ $indicator->unit }})@endif</div>
                                    <div class="stat__note">
                                        @if ($indicator->is_participation)Contagem de participações; não representa pessoas únicas.<br>@endif
                                        @if ($indicator->period)Período: {{ $indicator->period }}.<br>@endif
                                        Fonte: {{ $indicator->source }}
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @if ($fields->isNotEmpty())
                <section class="content-section" aria-labelledby="sec-informacoes">
                    <h2 id="sec-informacoes">Informações adicionais</h2>
                    <dl class="facts">
                        @foreach ($fields as $value)
                            <div>
                                <dt>{{ $value->field->label }}</dt>
                                <dd>
                                    @switch($value->field->type)
                                        @case('url')
                                            @if (preg_match('#^https?://#i', $value->value))<a href="{{ $value->value }}" rel="noopener noreferrer nofollow">{{ $value->value }}</a>@else{{ $value->value }}@endif
                                            @break
                                        @case('date')
                                            {{ \Illuminate\Support\Carbon::parse($value->value)->translatedFormat('j \d\e F \d\e Y') }}
                                            @break
                                        @case('number')
                                            {{ \App\Support\Text::number($value->value) }}
                                            @break
                                        @default
                                            {{ $value->value }}
                                    @endswitch
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endif

            @if ($gallery->count() > 1 || ($gallery->count() === 1 && ! $cover))
                <section class="content-section" aria-labelledby="sec-galeria">
                    <h2 id="sec-galeria">Galeria</h2>
                    <ul class="gallery" data-lightbox>
                        @foreach ($gallery as $i => $item)
                            @continue(! $item->media)
                            <li>
                                <button type="button" class="gallery__btn"
                                        data-full="{{ \App\Support\MediaUrl::url($item->media, 'xl') }}"
                                        data-alt="{{ $item->altText() }}" data-caption="{{ $item->caption }}" data-credit="{{ $item->credit }}"
                                        aria-label="Ampliar imagem {{ $loop->iteration }} de {{ $gallery->count() }}{{ $item->altText() ? ': '.$item->altText() : '' }}">
                                    <x-picture :media="$item->media" alt="" :focal-x="$item->focal_x" :focal-y="$item->focal_y" variant="sm" sizes="(max-width: 720px) 50vw, 240px" />
                                </button>
                                <noscript><a href="{{ \App\Support\MediaUrl::url($item->media, 'xl') }}">Abrir imagem {{ $loop->iteration }}</a></noscript>
                            </li>
                        @endforeach
                    </ul>
                </section>
                @include('partials.lightbox')
            @endif

            @php
                $videos = $version->links->where('kind', 'video');
                $others = $version->links->where('kind', '!=', 'video');
            @endphp
            @if ($videos->isNotEmpty())
                <section class="content-section" aria-labelledby="sec-videos">
                    <h2 id="sec-videos">Vídeos</h2>
                    <ul class="media-list">
                        @foreach ($videos as $link)
                            <li>@include('partials.link-embed', ['link' => $link])</li>
                        @endforeach
                    </ul>
                </section>
            @endif
            @if ($others->isNotEmpty())
                <section class="content-section" aria-labelledby="sec-publicacoes">
                    <h2 id="sec-publicacoes">Reportagens e publicações</h2>
                    <ul class="media-list">
                        @foreach ($others as $link)
                            <li>@include('partials.link-embed', ['link' => $link])</li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside aria-label="Informações da ação">
            <div class="aside-box">
                <h2>Contexto</h2>
                <dl class="facts">
                    @if ($version->periodLabel())<div><dt>{{ $version->ends_on ? 'Período' : 'Data' }}</dt><dd>{{ $version->periodLabel() }}</dd></div>@endif
                    @if ($version->location)<div><dt>Local</dt><dd>{{ $version->location }}</dd></div>@endif
                    @if ($version->activity_type)<div><dt>Tipo de atividade</dt><dd>{{ $version->activity_type }}</dd></div>@endif
                    @if ($version->activity_status)<div><dt>Situação</dt><dd>{{ $version->activityStatusLabel() }}</dd></div>@endif
                    @if ($version->primaryPage)
                        <div><dt>Área principal</dt><dd>
                            @if ($version->primaryPage->isPublic() && ! $preview)<a href="{{ route('pages.show', $version->primaryPage->slug) }}">{{ $version->primaryPage->displayTitle() }}</a>@else{{ $version->primaryPage->displayTitle() }}@endif
                        </dd></div>
                    @endif
                    @if ($version->relatedPages->isNotEmpty())
                        <div><dt>Também em</dt><dd>
                            @foreach ($version->relatedPages as $rp)
                                @if ($rp->isPublic() && ! $preview)<a href="{{ route('pages.show', $rp->slug) }}">{{ $rp->displayTitle() }}</a>@else{{ $rp->displayTitle() }}@endif{{ $loop->last ? '' : ', ' }}
                            @endforeach
                        </dd></div>
                    @endif
                    @if ($version->programPage)<div><dt>Programa ou projeto</dt><dd>{{ $version->programPage->displayTitle() }}</dd></div>@endif
                </dl>
            </div>

            @if ($version->teamMembers->isNotEmpty())
                <div class="aside-box">
                    <h2>Equipe responsável</h2>
                    @include('partials.people', ['members' => $version->teamMembers])
                </div>
            @endif

            @if ($version->partners->isNotEmpty())
                <div class="aside-box">
                    <h2>Instituições parceiras</h2>
                    <ul class="plain-list">
                        @foreach ($version->partners as $partner)
                            <li>@if ($partner->url)<a href="{{ $partner->url }}" rel="noopener noreferrer">{{ $partner->name }}</a>@else{{ $partner->name }}@endif</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @unless ($preview)
                <div class="aside-box">
                    <h2>Compartilhar</h2>
                    <div class="share">
                        <button type="button" class="btn btn--outline" hidden data-share="{{ $canonical }}" data-title="{{ $version->displayTitle() }}" data-text="{{ $version->summary }}" data-share-status="#share-status">Compartilhar</button>
                        <a class="btn btn--outline" href="https://wa.me/?text={{ rawurlencode($version->displayTitle().' '.$canonical) }}" rel="noopener noreferrer">WhatsApp</a>
                    </div>
                    <p class="status-msg" id="share-status" role="status"></p>
                </div>
            @endunless
        </aside>
    </div>

    @if ($related->isNotEmpty())
        <section class="section" aria-labelledby="sec-relacionadas">
            <h2 class="section__title" id="sec-relacionadas" style="margin-bottom: 20px;">Outras ações da área</h2>
            <ul class="cards">
                @foreach ($related as $item)
                    <li>@include('partials.action-card', ['item' => $item])</li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
