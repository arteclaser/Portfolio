<section class="block" aria-labelledby="{{ $blockId }}">
    <h2 id="{{ $blockId }}">{{ $heading ?: 'Galeria' }}</h2>
    <ul class="gallery" data-lightbox>
        @foreach ($items as $item)
            <li>
                <button type="button" class="gallery__btn" data-full="{{ \App\Support\MediaUrl::url($item['media'], 'xl') }}"
                        data-alt="{{ $item['alt'] }}" data-caption="{{ $item['caption'] }}" data-credit="{{ $item['credit'] }}"
                        aria-label="Ampliar imagem {{ $loop->iteration }} de {{ count($items) }}{{ $item['alt'] ? ': '.$item['alt'] : '' }}">
                    <x-picture :media="$item['media']" alt="" variant="sm" sizes="(max-width: 720px) 50vw, 240px" />
                </button>
            </li>
        @endforeach
    </ul>
</section>
@include('partials.lightbox')
