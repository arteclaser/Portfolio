<section class="block" aria-labelledby="{{ $blockId }}">
    <div class="section__head">
        <h2 id="{{ $blockId }}" style="margin: 0">{{ $heading ?: 'Ações' }}</h2>
        <a href="{{ route('actions.index', ['area' => $page->slug]) }}">Buscar todas as ações desta página</a>
    </div>
    <ul class="cards">
        @foreach ($items as $item)
            <li>@include('partials.action-card', ['item' => $item])</li>
        @endforeach
    </ul>
</section>
