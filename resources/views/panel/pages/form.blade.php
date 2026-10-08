@extends('layouts.panel')
@section('title', $page->exists ? 'Configurações · '.$page->title : 'Nova página')
@section('content')
@if ($page->exists)
    @include('panel.pages._header')
@else
    <div class="page-title"><div><p class="muted" style="margin:0"><a href="{{ route('panel.pages.index') }}">Páginas</a> ›</p><h1>Nova página</h1><p>Áreas, programas e projetos não ficam fixos no código: crie, renomeie, subordine e arquive quando precisar.</p></div></div>
@endif
@include('partials.form-errors')

<form method="post" action="{{ $page->exists ? route('panel.pages.update', $page) : route('panel.pages.store') }}" enctype="multipart/form-data" class="form" data-dirty-check novalidate>
    @csrf
    @if ($page->exists) @method('PUT') @endif

    <fieldset class="fieldset" @disabled(! $canStructure)>
        <legend>Estrutura</legend>
        @unless ($canStructure)<p class="fieldset__intro">Somente o Administrador Master altera a estrutura da página.</p>@endunless
        <div class="grid-2">
            <x-field name="title" label="Título" :value="$page->title" required />
            <x-field name="menu_title" label="Título curto (filtros e menus)" :value="$page->menu_title" help="Opcional. Ex.: Inovação" />
        </div>
        <div class="grid-3">
            <x-field name="kind" label="Tipo" type="select" :options="\App\Models\Page::KINDS" :value="$page->kind" required />
            <x-field name="parent_id" label="Página superior" type="select" :options="$parentOptions" :value="$page->parent_id" help="Permite páginas subordinadas." />
            <x-field name="position" label="Ordem de exibição" type="number" min="0" :value="$page->position ?? 0" />
        </div>
        <div class="grid-2">
            <x-field name="slug" label="Endereço" :value="$page->slug" prefix="/p/" help="Gerado a partir do título se ficar em branco." />
            <x-field name="accent_color" label="Cor de destaque" type="color" :value="$page->accent_color ?: '#2155E8'" help="Usada em detalhes da página. Verifique o contraste." />
        </div>
        <div class="field--check">
            <input type="checkbox" id="f-show_in_menu" name="show_in_menu" value="1" @checked(old('show_in_menu', $page->show_in_menu))>
            <label for="f-show_in_menu">Mostrar nos filtros e listas de áreas</label>
        </div>
        <div>
            <p class="field-label">Responsáveis pela página</p>
            @if ($members->isEmpty())
                <p class="muted">Nenhum integrante cadastrado.</p>
            @else
                @php $resp = array_map('intval', (array) old('responsible_ids', $responsibleIds)); @endphp
                <ul class="checklist">
                    @foreach ($members as $m)
                        <li><input type="checkbox" id="rs-{{ $m->id }}" name="responsible_ids[]" value="{{ $m->id }}" @checked(in_array($m->id, $resp, true))><label for="rs-{{ $m->id }}">{{ $m->name }}</label></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Conteúdo</legend>
        <p class="fieldset__intro">Descrição e capa só mudam no site depois de publicar a página.</p>
        <x-field name="description" label="Descrição" type="textarea" rows="5" :value="$page->description" markdown />
        <div class="field">
            <label for="f-cover">Imagem de capa</label>
            @if ($page->cover)
                <img src="{{ \App\Support\MediaUrl::url($page->cover, 'sm') }}" alt="" style="max-width: 280px; border-radius: 6px">
                <div class="field--check"><input type="checkbox" id="f-remove_cover" name="remove_cover" value="1"><label for="f-remove_cover">Remover capa</label></div>
            @endif
            <input id="f-cover" type="file" name="cover" accept="image/jpeg,image/png,image/webp">
            @error('cover')<p class="error">{{ $message }}</p>@enderror
        </div>
    </fieldset>

    <div class="form-footer">
        <span class="save-state">{{ $page->exists ? 'Última alteração: '.$page->updated_at?->format('d/m/Y H:i') : '' }}</span>
        <div class="actions-row"><button class="btn" type="submit">{{ $page->exists ? 'Salvar configurações' : 'Criar página' }}</button></div>
    </div>
</form>

@if ($page->exists && $canStructure)
    <section class="box" style="margin-top: 20px">
        <h2>Arquivar</h2>
        <p class="muted">Arquivar retira a página do site. As ações continuam cadastradas e o histórico é preservado.</p>
        @if ($page->isArchived())
            <form method="post" action="{{ route('panel.pages.unarchive', $page) }}">@csrf<button class="btn btn--secondary">Reativar página</button></form>
        @else
            <form method="post" action="{{ route('panel.pages.archive', $page) }}" data-confirm="Arquivar esta página?">@csrf<button class="btn btn--secondary">Arquivar página</button></form>
        @endif
    </section>
@endif
@endsection
