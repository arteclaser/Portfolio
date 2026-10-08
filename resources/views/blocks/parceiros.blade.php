<section class="block" aria-labelledby="{{ $blockId }}">
    <h2 id="{{ $blockId }}">{{ $heading ?: 'Parceiros' }}</h2>
    @include('partials.partners', ['partners' => $partners])
</section>
