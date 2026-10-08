<section class="block" aria-labelledby="{{ $blockId }}">
    <h2 id="{{ $blockId }}">{{ $heading ?: 'Vídeos' }}</h2>
    <ul class="media-list">
        @foreach ($items as $item)
            <li>@include('partials.link-embed', ['link' => $item])</li>
        @endforeach
    </ul>
</section>
