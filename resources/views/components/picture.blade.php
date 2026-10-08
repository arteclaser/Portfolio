@props(['media' => null, 'alt' => '', 'sizes' => '100vw', 'focalX' => null, 'focalY' => null, 'loading' => 'lazy', 'variant' => 'md'])
@if ($media && $media->isImage())
    @php
        $fx = $focalX ?? $media->focal_x ?? 50;
        $fy = $focalY ?? $media->focal_y ?? 50;
        $w = $media->variants[$variant]['w'] ?? $media->width;
        $h = $media->variants[$variant]['h'] ?? $media->height;
    @endphp
    <img src="{{ \App\Support\MediaUrl::url($media, $variant) }}"
         srcset="{{ \App\Support\MediaUrl::srcset($media) }}"
         sizes="{{ $sizes }}"
         alt="{{ $alt }}"
         @if ($w && $h) width="{{ $w }}" height="{{ $h }}" @endif
         loading="{{ $loading }}" decoding="async"
         style="object-position: {{ (int) $fx }}% {{ (int) $fy }}%"
         {{ $attributes }}>
@else
    <div class="placeholder-media" aria-hidden="true">Sem imagem</div>
@endif
