<section class="block" aria-labelledby="{{ $blockId }}">
    <h2 id="{{ $blockId }}">{{ $heading ?: 'Ações em destaque' }}</h2>
    <ul class="cards">
        @foreach ($items as $item)
            <li>@include('partials.action-card', ['item' => $item])</li>
        @endforeach
    </ul>
</section>
