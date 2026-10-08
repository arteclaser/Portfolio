<section class="block" aria-labelledby="{{ $blockId }}">
    <h2 id="{{ $blockId }}">{{ $heading ?: 'Documentos' }}</h2>
    <ul class="docs">
        @foreach ($items as $item)
            @php $href = $item['file'] ? \App\Support\MediaUrl::url($item['file'], 'file') : $item['url']; @endphp
            <li>
                <a href="{{ $href }}" @unless ($item['file']) rel="noopener noreferrer" @endunless>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg>
                    <span>
                        <strong>{{ $item['title'] }}</strong>
                        @if ($item['description']){{ $item['description'] }}<br>@endif
                        <span class="fineprint">{{ $item['file'] ? 'PDF · '.$item['file']->sizeLabel() : parse_url($item['url'], PHP_URL_HOST) }}</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ul>
</section>
