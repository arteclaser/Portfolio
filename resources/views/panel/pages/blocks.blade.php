@extends('layouts.panel')
@section('title', 'Blocos · '.$page->title)
@section('content')
@include('panel.pages._header')
@include('partials.form-errors')
@php $canEdit = auth()->user()->can('updateContent', $page); @endphp

<p class="muted">Os blocos aparecem no site na ordem abaixo, com o mesmo sistema visual de todas as páginas. Blocos sem conteúdo não são exibidos. Alterações ficam pendentes até você publicar.</p>

@if ($blocks->isEmpty())
    <div class="state"><h2>Nenhum bloco</h2><p>Adicione o primeiro bloco abaixo.</p></div>
@else
    <ol class="block-list">
        @foreach ($blocks as $block)
            <li class="block-item {{ $block->is_hidden ? 'is-hidden' : '' }}">
                <div>
                    <div class="block-item__title">{{ $loop->iteration }}. {{ $block->typeLabel() }} @if ($block->is_hidden)<span class="status status--archived">Oculto</span>@endif</div>
                    <div class="block-item__desc">{{ $block->setting('heading') ?: \Illuminate\Support\Str::limit(strip_tags((string) ($block->setting('text') ?? $block->setting('title') ?? '')), 90) ?: 'Sem título' }}</div>
                </div>
                @if ($canEdit)
                    <div class="actions-row">
                        <a class="btn btn--secondary btn--small" href="{{ route('panel.pages.blocks.edit', [$page, $block]) }}">Editar<span class="visually-hidden"> bloco {{ $loop->iteration }}</span></a>
                        @unless ($loop->first)<form method="post" action="{{ route('panel.pages.blocks.move', [$page, $block]) }}" class="inline-form">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn--ghost btn--small">Subir<span class="visually-hidden"> bloco {{ $loop->iteration }}</span></button></form>@endunless
                        @unless ($loop->last)<form method="post" action="{{ route('panel.pages.blocks.move', [$page, $block]) }}" class="inline-form">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn--ghost btn--small">Descer<span class="visually-hidden"> bloco {{ $loop->iteration }}</span></button></form>@endunless
                        <form method="post" action="{{ route('panel.pages.blocks.toggle', [$page, $block]) }}" class="inline-form">@csrf<button class="btn btn--ghost btn--small">{{ $block->is_hidden ? 'Mostrar' : 'Ocultar' }}<span class="visually-hidden"> bloco {{ $loop->iteration }}</span></button></form>
                        <form method="post" action="{{ route('panel.pages.blocks.destroy', [$page, $block]) }}" class="inline-form" data-confirm="Remover este bloco?">@csrf @method('DELETE')<button class="btn btn--ghost btn--small">Remover<span class="visually-hidden"> bloco {{ $loop->iteration }}</span></button></form>
                    </div>
                @endif
            </li>
        @endforeach
    </ol>
@endif

@if ($canEdit)
    <form method="post" action="{{ route('panel.pages.blocks.store', $page) }}" class="fieldset form" style="margin-top: 20px; max-width: 560px">
        @csrf
        <h2 style="margin:0">Adicionar bloco</h2>
        <x-field name="type" label="Tipo de bloco" type="select" :options="\App\Models\PageBlock::TYPES" />
        <div><button class="btn" type="submit">Adicionar</button></div>
    </form>
@endif
@endsection
