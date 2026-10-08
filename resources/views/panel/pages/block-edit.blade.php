@extends('layouts.panel')
@section('title', 'Bloco · '.$page->title)
@section('content')
@include('panel.pages._header')
@include('partials.form-errors')
@php $s = $block->settings ?? []; @endphp
<h2>{{ $block->typeLabel() }}</h2>
<form method="post" action="{{ route('panel.pages.blocks.update', [$page, $block]) }}" enctype="multipart/form-data" class="form" data-dirty-check novalidate>
    @csrf @method('PUT')
    <x-field name="heading" label="Título do bloco (opcional)" :value="$s['heading'] ?? null" />

    @switch($block->type)
        @case('capa')
            <x-field name="title" label="Título da capa" :value="$s['title'] ?? null" help="Se vazio, usa o título da página." />
            <x-field name="subtitle" label="Subtítulo" :value="$s['subtitle'] ?? null" />
            <fieldset class="fieldset"><legend>Imagem</legend>
                <ul class="pick-grid">
                    <li><input type="radio" id="cm-0" name="media_id" value="" @checked(empty($s['media_id']))><label for="cm-0"><span class="thumb"></span><span class="cap">Usar a capa da página</span></label></li>
                    @foreach ($images as $img)
                        <li><input type="radio" id="cm-{{ $img->id }}" name="media_id" value="{{ $img->id }}" @checked(($s['media_id'] ?? null) == $img->id)>
                            <label for="cm-{{ $img->id }}"><span class="thumb"><img src="{{ \App\Support\MediaUrl::url($img, 'sm') }}" alt=""></span><span class="cap">{{ \Illuminate\Support\Str::limit($img->alt ?: $img->original_name, 40) }}</span></label></li>
                    @endforeach
                </ul>
                <div class="field"><label for="f-upload">Ou enviar nova imagem</label><input id="f-upload" type="file" name="upload" accept="image/jpeg,image/png,image/webp"></div>
            </fieldset>
            @break
        @case('apresentacao')
            <x-field name="kicker" label="Chamada (texto pequeno acima)" :value="$s['kicker'] ?? null" />
            <x-field name="text" label="Texto" type="textarea" rows="6" :value="$s['text'] ?? null" markdown />
            @break
        @case('texto')
            <x-field name="text" label="Texto" type="textarea" rows="10" :value="$s['text'] ?? null" markdown />
            @break
        @case('acoes_destaque')
            <x-field name="limit" label="Quantidade" type="number" min="1" max="3" :value="$s['limit'] ?? 3" help="Ações marcadas como destaque nesta página (e subordinadas). Se faltarem, entram as mais recentes." />
            @break
        @case('lista_acoes')
            <x-field name="limit" label="Quantidade" type="number" min="3" max="24" :value="$s['limit'] ?? 9" help="Ações publicadas desta página e das subordinadas, incluindo as associadas como área relacionada." />
            @break
        @case('equipe')
            <p class="muted">Mostra os responsáveis da página e os integrantes vinculados a esta área, quando visíveis ao público.</p>
            @break
        @case('indicadores')
            <p class="muted">Consolida os indicadores documentados (com fonte) das ações publicadas desta página. Valores só são somados entre o mesmo indicador e a mesma unidade, e cada ação conta uma vez.</p>
            @break
        @case('galeria')
            @php $sel = collect($s['items'] ?? [])->pluck('media_id')->map(fn ($v) => (int) $v)->all(); @endphp
            <fieldset class="fieldset"><legend>Imagens da galeria</legend>
                <p class="fieldset__intro">Legenda, crédito e descrição acessível vêm da biblioteca de mídias.</p>
                @if ($images->isEmpty())<p class="muted">A biblioteca está vazia.</p>@endif
                <ul class="pick-grid">
                    @foreach ($images as $img)
                        <li><input type="checkbox" id="gm-{{ $img->id }}" name="media_ids[]" value="{{ $img->id }}" @checked(in_array($img->id, $sel, true))>
                            <label for="gm-{{ $img->id }}"><span class="thumb"><img src="{{ \App\Support\MediaUrl::url($img, 'sm') }}" alt=""></span><span class="cap">{{ \Illuminate\Support\Str::limit($img->alt ?: $img->original_name, 40) }}</span></label></li>
                    @endforeach
                </ul>
                <div class="field"><label for="f-uploads">Enviar novas imagens</label><input id="f-uploads" type="file" name="uploads[]" multiple accept="image/jpeg,image/png,image/webp"></div>
            </fieldset>
            @break
        @case('videos')
        @case('reportagens')
            @php $items = array_values($s['items'] ?? []); $items = array_merge($items, array_fill(0, 2, [])); $extra = $block->type === 'reportagens'; @endphp
            <div class="repeater">
                @foreach ($items as $i => $item)
                    <div class="repeater__row" role="group" aria-label="Item {{ $i + 1 }}">
                        <div class="grid-2">
                            <x-field :name="'items['.$i.'][url]'" label="Endereço" type="url" :value="$item['url'] ?? null" placeholder="https://" />
                            <x-field :name="'items['.$i.'][title]'" label="Título" :value="$item['title'] ?? null" />
                        </div>
                        @if ($extra)
                            <div class="grid-2">
                                <x-field :name="'items['.$i.'][source]'" label="Fonte" :value="$item['source'] ?? null" />
                                <x-field :name="'items['.$i.'][media_id]'" label="Imagem (da biblioteca)" type="select" :options="['' => 'Sem imagem'] + $images->mapWithKeys(fn ($m) => [$m->id => \Illuminate\Support\Str::limit($m->alt ?: $m->original_name, 50)])->all()" :value="$item['media_id'] ?? null" />
                            </div>
                            <x-field :name="'items['.$i.'][description]'" label="Descrição breve" type="textarea" rows="2" :value="$item['description'] ?? null" />
                        @endif
                    </div>
                @endforeach
            </div>
            <p class="help">Para remover um item, apague o endereço. Salve para ganhar mais linhas.</p>
            @break
        @case('parceiros')
            @php $sel = array_map('intval', $s['partner_ids'] ?? []); @endphp
            <p class="muted">Sem seleção, mostra todos os parceiros públicos.</p>
            <ul class="checklist">
                @foreach ($partners as $p)
                    <li><input type="checkbox" id="pp-{{ $p->id }}" name="partner_ids[]" value="{{ $p->id }}" @checked(in_array($p->id, $sel, true))><label for="pp-{{ $p->id }}">{{ $p->name }}</label></li>
                @endforeach
            </ul>
            @break
        @case('documentos')
            @php $items = array_values($s['items'] ?? []); $items = array_merge($items, array_fill(0, 2, [])); @endphp
            <p class="muted">Use um link externo ou um PDF enviado em <a href="{{ route('panel.media.index') }}">Mídias</a>.</p>
            <div class="repeater">
                @foreach ($items as $i => $item)
                    <div class="repeater__row" role="group" aria-label="Documento {{ $i + 1 }}">
                        <div class="grid-2">
                            <x-field :name="'items['.$i.'][title]'" label="Título" :value="$item['title'] ?? null" />
                            <x-field :name="'items['.$i.'][url]'" label="Link externo" type="url" :value="$item['url'] ?? null" placeholder="https://" />
                        </div>
                        <div class="grid-2">
                            <x-field :name="'items['.$i.'][media_id]'" label="Ou PDF da biblioteca" type="select" :options="['' => 'Nenhum'] + $documents->mapWithKeys(fn ($m) => [$m->id => $m->original_name])->all()" :value="$item['media_id'] ?? null" />
                            <x-field :name="'items['.$i.'][description]'" label="Descrição" :value="$item['description'] ?? null" />
                        </div>
                    </div>
                @endforeach
            </div>
            @break
    @endswitch

    <div class="form-footer">
        <a href="{{ route('panel.pages.blocks', $page) }}">Voltar aos blocos</a>
        <button class="btn" type="submit">Salvar bloco</button>
    </div>
</form>
@endsection
