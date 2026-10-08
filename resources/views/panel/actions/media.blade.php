@extends('layouts.panel')
@section('title', 'Fotos · '.$version->displayTitle())
@section('content')
@include('panel.actions._header')
@include('partials.form-errors')
@php
    $count = $version->media->count();
    $remaining = max(0, $maxImages - $count);
    $mb = rtrim(rtrim(number_format($maxBytes / 1048576, 1, ',', ''), '0'), ',');
@endphp

<p class="limits">{{ $count }} de {{ $maxImages }} imagens (incluindo a capa) · até {{ $mb }} MB por arquivo · JPEG, PNG ou WebP. As versões para cada tela são geradas automaticamente, sem deformar a imagem, e os metadados de localização (EXIF/GPS) são removidos.</p>
@if ($serverLimitBytes && $serverLimitBytes < $maxBytes)
    <div class="alert alert--warning" role="note">O servidor aceita no máximo {{ round($serverLimitBytes / 1048576, 1) }} MB por envio. Peça ao responsável técnico para ajustar o limite do PHP.</div>
@endif

@if ($canEdit)
    <form method="post" action="{{ route('panel.actions.media.store', $action) }}" enctype="multipart/form-data" class="upload-zone form" data-upload data-remaining="{{ $remaining }}" data-max-bytes="{{ $maxBytes }}" style="margin-bottom: 20px">
        @csrf
        <div class="field">
            <label for="f-photos">Enviar fotografias</label>
            <p class="help" id="f-photos-help">Você pode selecionar várias de uma vez. {{ $remaining > 0 ? "Restam {$remaining} envios nesta ação." : 'Limite atingido: remova uma imagem para enviar outra.' }}</p>
            <input id="f-photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple aria-describedby="f-photos-help" @disabled($remaining === 0)>
        </div>
        <progress class="progress" max="100" value="0" hidden aria-label="Progresso do envio"></progress>
        <p data-upload-status role="status" aria-live="polite" class="muted"></p>
        <div><button type="submit" class="btn" @disabled($remaining === 0)>Enviar</button></div>
    </form>
@endif

@if ($version->media->isEmpty())
    <div class="state"><h2>Nenhuma foto nesta versão</h2><p>Fotos são opcionais. Sem foto, o site usa um fundo neutro.</p></div>
@else
    <form method="post" action="{{ route('panel.actions.media.update', $action) }}" data-dirty-check>
        @csrf
        <fieldset style="border:0;padding:0;margin:0;min-width:0" @disabled(! $canEdit)>
        <ul class="photo-grid">
            @foreach ($version->media as $item)
                <li class="photo-item {{ $item->is_cover ? 'is-cover' : '' }}" data-focal>
                    <div>
                        <div class="photo-thumb"><img data-focal-img src="{{ \App\Support\MediaUrl::url($item->media, 'sm') }}" alt="" style="object-position: {{ $item->focal_x }}% {{ $item->focal_y }}%"></div>
                        <p class="muted" style="margin:6px 0 0; font-size:.8125rem">Cortes: destaque, cartão e quadrado</p>
                        <div class="crop-previews" aria-hidden="true">
                            <div class="crop-wide"><img data-focal-img src="{{ \App\Support\MediaUrl::url($item->media, 'sm') }}" alt="" style="object-position: {{ $item->focal_x }}% {{ $item->focal_y }}%"></div>
                            <div class="crop-card"><img data-focal-img src="{{ \App\Support\MediaUrl::url($item->media, 'sm') }}" alt="" style="object-position: {{ $item->focal_x }}% {{ $item->focal_y }}%"></div>
                            <div class="crop-square"><img data-focal-img src="{{ \App\Support\MediaUrl::url($item->media, 'sm') }}" alt="" style="object-position: {{ $item->focal_x }}% {{ $item->focal_y }}%"></div>
                        </div>
                        @if ($item->is_cover)<p><span class="status status--published">Capa</span></p>@endif
                    </div>
                    <div class="form" style="gap: 12px">
                        <p class="muted" style="margin:0">Imagem {{ $loop->iteration }} · {{ $item->media->width }}×{{ $item->media->height }} px</p>
                        <x-field :name="'items['.$item->id.'][alt]'" label="Descrição acessível" :value="$item->alt" help="Descreva o que a imagem mostra para quem usa leitor de tela." />
                        <div class="grid-2">
                            <x-field :name="'items['.$item->id.'][caption]'" label="Legenda" :value="$item->caption" />
                            <x-field :name="'items['.$item->id.'][credit]'" label="Crédito" :value="$item->credit" placeholder="Ex.: Foto: Nome do autor" />
                        </div>
                        <div class="grid-2">
                            <div class="field"><label for="fx-{{ $item->id }}">Enquadramento horizontal ({{ $item->focal_x }}%)</label>
                                <input id="fx-{{ $item->id }}" type="range" min="0" max="100" name="items[{{ $item->id }}][focal_x]" value="{{ $item->focal_x }}" data-focal-x></div>
                            <div class="field"><label for="fy-{{ $item->id }}">Enquadramento vertical ({{ $item->focal_y }}%)</label>
                                <input id="fy-{{ $item->id }}" type="range" min="0" max="100" name="items[{{ $item->id }}][focal_y]" value="{{ $item->focal_y }}" data-focal-y></div>
                        </div>
                        @if ($canEdit)
                            <div class="actions-row">
                                @unless ($item->is_cover)<button class="btn btn--secondary btn--small" name="op" value="cover:{{ $item->id }}">Usar como capa</button>@endunless
                                @unless ($loop->first)<button class="btn btn--secondary btn--small" name="op" value="up:{{ $item->id }}">Mover para cima</button>@endunless
                                @unless ($loop->last)<button class="btn btn--secondary btn--small" name="op" value="down:{{ $item->id }}">Mover para baixo</button>@endunless
                                <button class="btn btn--ghost btn--small" name="op" value="remove:{{ $item->id }}">Remover desta versão</button>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        </fieldset>
        @if ($canEdit)
            <div class="form-footer"><span class="save-state">As operações acima também salvam os textos digitados.</span><button class="btn" type="submit">Salvar informações das fotos</button></div>
        @endif
    </form>
@endif
@endsection
