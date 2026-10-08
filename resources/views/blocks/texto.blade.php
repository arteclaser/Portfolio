<section class="block" @if ($heading) aria-labelledby="{{ $blockId }}" @endif>
    @if ($heading)<h2 id="{{ $blockId }}">{{ $heading }}</h2>@endif
    <div class="prose">{!! \App\Support\Markdown::render($text) !!}</div>
</section>
