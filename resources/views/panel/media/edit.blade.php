@extends('layouts.panel')
@section('title', 'Mídia')
@section('content')
<div class="page-title"><div><p class="muted" style="margin:0"><a href="{{ route('panel.media.index') }}">Mídias</a> ›</p><h1>{{ $media->original_name ?: 'Arquivo' }}</h1>
<p>{{ $media->isImage() ? $media->width.'×'.$media->height.' px · ' : '' }}{{ $media->sizeLabel() }} · enviado por {{ $media->uploader?->name ?? '—' }} em {{ $media->created_at?->format('d/m/Y H:i') }}</p></div></div>
@include('partials.form-errors')
<div class="grid-2" style="align-items: start" data-focal>
    <div>
        @if ($media->isImage())
            <div class="photo-thumb"><img data-focal-img src="{{ \App\Support\MediaUrl::url($media, 'md') }}" alt="" style="object-position: {{ $media->focal_x }}% {{ $media->focal_y }}%"></div>
        @else
            <p><a class="btn btn--secondary" href="{{ \App\Support\MediaUrl::url($media, 'file') }}">Abrir PDF</a></p>
        @endif
        <p class="muted">Uso: {{ $usage ? implode('; ', $usage) : 'não utilizado' }}.</p>
        @if ($media->source_url)<p class="muted">Origem: {{ $media->source_url }}</p>@endif
    </div>
    <form method="post" action="{{ route('panel.media.update', $media) }}" class="form">
        @csrf @method('PUT')
        <x-field name="alt" label="Descrição acessível (padrão)" :value="$media->alt" help="Usada quando a imagem aparece sem descrição própria." />
        <x-field name="caption" label="Legenda (padrão)" :value="$media->caption" />
        <x-field name="credit" label="Crédito (padrão)" :value="$media->credit" />
        @if ($media->isImage())
            <div class="grid-2">
                <div class="field"><label for="fx">Enquadramento horizontal</label><input id="fx" type="range" min="0" max="100" name="focal_x" value="{{ $media->focal_x }}" data-focal-x></div>
                <div class="field"><label for="fy">Enquadramento vertical</label><input id="fy" type="range" min="0" max="100" name="focal_y" value="{{ $media->focal_y }}" data-focal-y></div>
            </div>
        @endif
        <div class="actions-row"><button class="btn" type="submit">Salvar</button></div>
    </form>
</div>
@can('delete', $media)
    <form method="post" action="{{ route('panel.media.destroy', $media) }}" data-confirm="Excluir este arquivo permanentemente?" style="margin-top: 20px">
        @csrf @method('DELETE')
        <button class="btn btn--danger" @disabled(! empty($usage))>Excluir arquivo</button>
        @if ($usage)<p class="help">Arquivos em uso (inclusive em versões antigas) não podem ser excluídos.</p>@endif
    </form>
@endcan
@endsection
