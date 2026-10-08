@php
    /** @var \App\Models\ActionLink|array $link */
    $isModel = $link instanceof \App\Models\ActionLink;
    $url = $isModel ? $link->url : $link['url'];
    $provider = $isModel ? $link->provider : $link['provider'];
    $embedId = $isModel ? $link->embed_id : $link['embed_id'];
    $title = $isModel ? $link->displayTitle() : ($link['title'] ?: parse_url($url, PHP_URL_HOST));
    $source = $isModel ? $link->sourceLabel() : ($link['source'] ?: (\App\Models\ActionLink::PROVIDERS[$provider] ?? parse_url($url, PHP_URL_HOST)));
    $description = $isModel ? $link->description : ($link['description'] ?? null);
    $image = $isModel ? $link->image : ($link['image'] ?? null);
    $youtube = $provider === 'youtube' && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $embedId);
    $instagram = $provider === 'instagram' && preg_match('#^(p|reel|tv)/[A-Za-z0-9_-]{5,40}$#', (string) $embedId);
    $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
@endphp
@if ($youtube)
    <div class="embed">
        <div class="embed__frame">
            <button type="button" class="embed__play" data-youtube="{{ $embedId }}" data-title="{{ $title }}">
                @if ($image)<x-picture :media="$image" alt="" variant="md" sizes="(max-width: 960px) 100vw, 800px" />@endif
                <span><svg class="icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg> Reproduzir vídeo<span class="visually-hidden">: {{ $title }}</span></span>
            </button>
        </div>
        <div class="embed__body">
            <span class="tag">{{ $source }}</span>
            <h3>{{ $title }}</h3>
            @if ($description)<p>{{ $description }}</p>@endif
            <a href="{{ $url }}" rel="noopener noreferrer">Abrir no YouTube <span class="visually-hidden">(site externo)</span></a>
            <p class="embed__note">O player do YouTube só é carregado quando você clica em reproduzir. Se a incorporação estiver desativada pelo autor, use o link acima.</p>
        </div>
    </div>
@elseif ($instagram)
    <div class="embed" data-instagram-slot>
        <div class="embed__body">
            <span class="tag">{{ $source }}</span>
            <h3>{{ $title }}</h3>
            @if ($description)<p>{{ $description }}</p>@endif
        </div>
        @if ($image)<div style="aspect-ratio: 16/9;"><x-picture :media="$image" alt="" variant="md" sizes="(max-width: 960px) 100vw, 800px" style="width:100%;height:100%;object-fit:cover;" /></div>@endif
        <div class="insta-slot">
            <button type="button" class="btn btn--outline" data-instagram="https://www.instagram.com/{{ $embedId }}/">Carregar publicação do Instagram</button>
            <p class="embed__note">Ao carregar, o Instagram (Meta) recebe dados da sua visita. <a href="{{ $url }}" rel="noopener noreferrer">Abrir no Instagram</a></p>
            <p class="embed__note" data-instagram-status role="status"></p>
        </div>
    </div>
@else
    <article class="link-card">
        <div class="link-card__media">
            @if ($image)<x-picture :media="$image" alt="" variant="sm" sizes="180px" />@else<div class="placeholder-media" aria-hidden="true">{{ $host }}</div>@endif
        </div>
        <div class="link-card__body">
            <span class="tag">{{ $source }}</span>
            <h3><a href="{{ $url }}" rel="noopener noreferrer">{{ $title }}<span class="visually-hidden"> (abre {{ $host }})</span></a></h3>
            @if ($description)<p>{{ \Illuminate\Support\Str::limit($description, 240) }}</p>@endif
            <p class="link-card__url">{{ $url }}</p>
        </div>
    </article>
@endif
