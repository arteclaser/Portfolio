@extends('layouts.panel')
@section('title', 'Mídias')
@section('content')
<div class="page-title"><div><h1>Biblioteca de mídias</h1><p>Imagens e documentos PDF usados em páginas, equipe e parceiros. Arquivos não publicados ficam privados.</p></div></div>
@include('partials.form-errors')
<form method="post" action="{{ route('panel.media.store') }}" enctype="multipart/form-data" class="upload-zone form" style="margin-bottom: 20px">
    @csrf
    <div class="field">
        <label for="f-files">Enviar arquivos</label>
        <p class="help" id="f-files-help">Imagens JPEG, PNG ou WebP até {{ rtrim(rtrim(number_format($maxBytes / 1048576, 1, ',', ''), '0'), ',') }} MB; PDFs até {{ rtrim(rtrim(number_format($maxBytes * 4 / 1048576, 1, ',', ''), '0'), ',') }} MB.</p>
        <input id="f-files" type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf" aria-describedby="f-files-help">
    </div>
    <div><button class="btn" type="submit">Enviar</button></div>
</form>
<nav aria-label="Filtrar mídias"><ul class="chips" style="margin-bottom: 16px">
    <li><a class="chip" href="{{ route('panel.media.index') }}" @if (! $kind) aria-current="true" @endif>Todos</a></li>
    <li><a class="chip" href="{{ route('panel.media.index', ['tipo' => 'image']) }}" @if ($kind === 'image') aria-current="true" @endif>Imagens</a></li>
    <li><a class="chip" href="{{ route('panel.media.index', ['tipo' => 'document']) }}" @if ($kind === 'document') aria-current="true" @endif>Documentos</a></li>
</ul></nav>
@if ($items->isEmpty())
    <div class="state"><h2>Nenhum arquivo</h2></div>
@else
    <ul class="media-library">
        @foreach ($items as $item)
            <li>
                <a href="{{ route('panel.media.edit', $item) }}" style="text-decoration:none; color: inherit">
                    <div class="thumb">@if ($item->isImage())<img src="{{ \App\Support\MediaUrl::url($item, 'sm') }}" alt="">@else<span>PDF</span>@endif</div>
                    <div class="info"><strong>{{ \Illuminate\Support\Str::limit($item->original_name ?: 'Arquivo', 40) }}</strong><br><span class="muted">{{ $item->sizeLabel() }} · {{ $item->created_at?->format('d/m/Y') }}</span>
                        @if ($item->isImage() && blank($item->alt))<br><span class="muted">Sem descrição acessível</span>@endif</div>
                </a>
            </li>
        @endforeach
    </ul>
    {{ $items->links() }}
@endif
@endsection
