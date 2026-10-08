<section class="page-cover block" aria-labelledby="{{ $blockId }}" @if ($isFirst) style="margin-top: 16px" @endif>
    @if ($image)<x-picture :media="$image" alt="" variant="lg" loading="eager" sizes="(max-width: 1240px) 100vw, 1200px" />@endif
    <div class="page-cover__body">
        <span class="tag">{{ $kicker }}</span>
        @if ($isFirst)
            <h1 id="{{ $blockId }}">{{ $title }}</h1>
        @else
            <h2 id="{{ $blockId }}">{{ $title }}</h2>
        @endif
        @if ($subtitle)<p>{{ $subtitle }}</p>@endif
    </div>
</section>
@if ($isFirst && $description)
    <div class="block prose lead">{!! \App\Support\Markdown::render($description) !!}</div>
@endif
