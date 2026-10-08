@extends('layouts.panel')
@section('title', 'Prévia')
@section('content')
<div class="page-title">
    <div><p class="muted" style="margin:0"><a href="{{ route('panel.actions.edit', $action) }}">Voltar à ação</a></p><h1>Prévia</h1>
    <p>Visualização da versão {{ $which === 'publicada' ? 'publicada' : 'de trabalho' }}, exatamente como o público a veria. Nada aqui fica visível no site.</p></div>
</div>
<div class="preview-bar" role="group" aria-label="Tamanho da prévia">
    <button type="button" class="btn btn--secondary btn--small" data-device-btn="desktop" aria-pressed="true">Computador</button>
    <button type="button" class="btn btn--secondary btn--small" data-device-btn="mobile" aria-pressed="false">Celular</button>
    @if ($action->published_version_id && $action->working_version_id)
        <a class="btn btn--ghost btn--small" href="{{ route('panel.actions.preview', [$action, 'versao' => $which === 'publicada' ? 'trabalho' : 'publicada']) }}">Ver versão {{ $which === 'publicada' ? 'de trabalho' : 'publicada' }}</a>
    @endif
    <a class="btn btn--ghost btn--small" href="{{ route('panel.actions.preview.content', [$action, 'versao' => $which]) }}">Abrir em tela cheia</a>
</div>
<div class="preview-frame-wrap">
    <iframe class="preview-frame" data-preview-frame data-device="desktop" src="{{ route('panel.actions.preview.content', [$action, 'versao' => $which]) }}" title="Prévia da ação"></iframe>
</div>
@endsection
