@php
    $tabs = [
        'panel.pages.blocks' => 'Blocos',
        'panel.pages.edit' => 'Configurações',
        'panel.pages.fields' => 'Campos adicionais',
        'panel.pages.history' => 'Histórico',
    ];
    $currentRoute = request()->route()->getName();
@endphp
<div class="page-title">
    <div>
        <p class="muted" style="margin:0"><a href="{{ route('panel.pages.index') }}">Páginas</a> ›</p>
        <h1>{{ $page->title }}</h1>
        <p>
            @if ($page->isArchived())<span class="status status--archived">Arquivada</span>
            @elseif (! $page->published_at)<span class="status status--draft">Não publicada</span>
            @elseif ($page->has_unpublished_changes)<span class="status status--in_review">Alterações ainda não publicadas</span>
            @else<span class="status status--published">Publicada em {{ $page->published_at->format('d/m/Y H:i') }}</span>@endif
            <span class="muted">· {{ $page->kindLabel() }}</span>
        </p>
    </div>
    <div class="actions-row">
        <a class="btn btn--secondary" href="{{ route('panel.pages.preview', $page) }}">Prévia</a>
        @if ($page->isPublic())<a class="btn btn--secondary" href="{{ route('pages.show', $page->slug) }}">Ver no site</a>@endif
        @can('updateContent', $page)
            @unless ($page->isArchived())
                <form method="post" action="{{ route('panel.pages.publish', $page) }}" class="inline-form">@csrf<button class="btn" type="submit">Publicar página</button></form>
            @endunless
        @endcan
    </div>
</div>
<nav aria-label="Seções da página"><ul class="tabs">
    @foreach ($tabs as $name => $label)
        <li><a href="{{ route($name, $page) }}" @if ($currentRoute === $name) aria-current="page" @endif>{{ $label }}</a></li>
    @endforeach
</ul></nav>
