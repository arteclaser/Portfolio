<section class="block" aria-labelledby="{{ $blockId }}">
    <h2 id="{{ $blockId }}">{{ $heading ?: 'Equipe' }}</h2>
    @include('partials.people', ['members' => $members, 'showBio' => true])
</section>
