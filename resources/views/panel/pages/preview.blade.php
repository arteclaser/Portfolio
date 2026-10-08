@extends('layouts.panel')
@section('title', 'Prévia · '.$page->title)
@section('content')
@include('panel.pages._header')
<p class="muted">Prévia dos blocos de trabalho (inclui alterações ainda não publicadas). Blocos ocultos não aparecem.</p>
<div class="preview-bar" role="group" aria-label="Tamanho da prévia">
    <button type="button" class="btn btn--secondary btn--small" data-device-btn="desktop" aria-pressed="true">Computador</button>
    <button type="button" class="btn btn--secondary btn--small" data-device-btn="mobile" aria-pressed="false">Celular</button>
    <a class="btn btn--ghost btn--small" href="{{ route('panel.pages.preview.content', $page) }}">Abrir em tela cheia</a>
</div>
<div class="preview-frame-wrap">
    <iframe class="preview-frame" data-preview-frame data-device="desktop" src="{{ route('panel.pages.preview.content', $page) }}" title="Prévia da página"></iframe>
</div>
@endsection
