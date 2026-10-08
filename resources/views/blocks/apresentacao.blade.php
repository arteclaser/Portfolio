<section class="block" @if ($heading) aria-labelledby="{{ $blockId }}" @endif>
    @if ($kicker)<p class="kicker">{{ $kicker }}</p>@endif
    @if ($heading)<h2 id="{{ $blockId }}" class="display" style="font-size: clamp(1.8rem, 4vw, 2.8rem);">{{ $heading }}</h2>@endif
    <div class="prose lead">{!! \App\Support\Markdown::render($text) !!}</div>
</section>
