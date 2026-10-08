@php
    /** @var \App\Models\ActionVersion $item */
    $cover = $item->cover();
    $slug = $item->public_slug ?? $item->slug;
    $href = ($previewLinks ?? false) ? '#' : route('actions.show', $slug);
@endphp
<article class="card">
    <div class="card__media">
        <x-picture :media="$cover?->media" alt="" :focal-x="$cover?->focal_x" :focal-y="$cover?->focal_y" sizes="(max-width: 720px) 100vw, 400px" />
    </div>
    <div class="card__body">
        <div>
            @if ($item->primaryPage)<span class="tag">{{ $item->primaryPage->displayTitle() }}</span>@endif
            <h3 class="card__title"><a href="{{ $href }}">{{ $item->displayTitle() }}</a></h3>
            @if ($item->periodLabel())<p class="card__meta">{{ $item->periodLabel() }}</p>@endif
        </div>
        <span class="arrow-circle" aria-hidden="true">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </span>
    </div>
</article>
