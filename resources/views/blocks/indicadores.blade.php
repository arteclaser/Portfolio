<section class="block" aria-labelledby="{{ $blockId }}">
    <h2 id="{{ $blockId }}">{{ $heading ?: 'Indicadores' }}</h2>
    @include('partials.indicators', ['summary' => $summary])
</section>
