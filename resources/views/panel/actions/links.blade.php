@extends('layouts.panel')
@section('title', 'Vídeos e reportagens · '.$version->displayTitle())
@section('content')
@include('panel.actions._header')
@include('partials.form-errors')

<div class="box box--muted">
    <p style="margin:0">Cole o endereço de um vídeo do YouTube, de uma publicação do Instagram ou de uma reportagem. O sistema tenta obter título, descrição e imagem; quando o site não fornece esses dados, preencha manualmente. <strong>O endereço original fica sempre visível e acessível.</strong> Nada é baixado nem copiado do conteúdo externo, e vídeos só tocam após o clique do visitante.</p>
</div>

@if ($canEdit)
    <form method="post" action="{{ route('panel.actions.links.store', $action) }}" class="fieldset form" data-link-preview novalidate style="margin-bottom: 24px">
        @csrf
        <h2 style="margin:0">Adicionar link</h2>
        <x-field name="url" label="Endereço (URL)" type="url" placeholder="https://" required />
        <div class="actions-row">
            <button type="button" class="btn btn--secondary btn--small" data-preview-btn="{{ route('panel.actions.links.preview', $action) }}" hidden>Buscar prévia</button>
            <span data-preview-status role="status" aria-live="polite" class="muted"></span>
        </div>
        <div class="grid-2">
            <x-field name="kind" label="Tipo" type="select" :options="['' => 'Detectar automaticamente'] + \App\Models\ActionLink::KINDS" />
            <x-field name="source_name" label="Fonte (opcional)" placeholder="Ex.: Jornal da cidade" />
        </div>
        <x-field name="title" label="Título (opcional)" help="Se vazio, usamos o título fornecido pelo site, quando houver." />
        <x-field name="description" label="Descrição breve (opcional)" type="textarea" rows="2" />
        <div><button type="submit" class="btn">Adicionar</button></div>
    </form>
@endif

@if ($version->links->isEmpty())
    <div class="state"><h2>Nenhum link nesta versão</h2><p>Vídeos e reportagens são opcionais.</p></div>
@else
    <ul class="plain-list">
        @foreach ($version->links as $link)
            <li class="box">
                <div class="actions-row" style="justify-content: space-between">
                    <div>
                        <span class="tag">{{ \App\Models\ActionLink::KINDS[$link->kind] ?? $link->kind }} · {{ \App\Models\ActionLink::PROVIDERS[$link->provider] ?? $link->provider }}</span>
                        <h2 style="margin:4px 0">{{ $link->displayTitle() }}</h2>
                        <p style="margin:0"><a href="{{ $link->url }}" rel="noopener noreferrer">{{ $link->url }}</a></p>
                    </div>
                    @php
                        $statusLabel = ['ok' => 'Prévia obtida', 'partial' => 'Prévia parcial', 'failed' => 'Prévia indisponível', 'manual' => 'Dados manuais'][$link->preview_status] ?? $link->preview_status;
                    @endphp
                    <span class="status status--{{ $link->preview_status === 'ok' ? 'published' : ($link->preview_status === 'failed' ? 'returned' : 'draft') }}">{{ $statusLabel }}</span>
                </div>
                @if ($link->preview_message)<p class="muted">{{ $link->preview_message }}</p>@endif

                @if ($canEdit)
                    <form method="post" action="{{ route('panel.actions.links.update', [$action, $link]) }}" enctype="multipart/form-data" class="form" style="margin-top: 12px">
                        @csrf @method('PUT')
                        <div class="grid-2">
                            <x-field name="kind" :id="'l'.$link->id.'-kind'" :use-old="false" label="Tipo" type="select" :options="\App\Models\ActionLink::KINDS" :value="$link->kind" />
                            <x-field name="source_name" :id="'l'.$link->id.'-source'" :use-old="false" label="Fonte" :value="$link->source_name" />
                        </div>
                        <x-field name="title" :id="'l'.$link->id.'-title'" :use-old="false" label="Título" :value="$link->title" />
                        <x-field name="description" :id="'l'.$link->id.'-desc'" :use-old="false" label="Descrição breve" type="textarea" rows="2" :value="$link->description" />
                        <div class="grid-2">
                            <div class="field">
                                <p class="field-label">Imagem do cartão</p>
                                @if ($link->image)
                                    <img src="{{ \App\Support\MediaUrl::url($link->image, 'sm') }}" alt="" style="max-width: 220px; border-radius: 6px">
                                    <div class="field--check"><input type="checkbox" id="ri-{{ $link->id }}" name="remove_image" value="1"><label for="ri-{{ $link->id }}">Remover imagem</label></div>
                                @else
                                    <p class="muted">Sem imagem.</p>
                                @endif
                            </div>
                            <div class="field">
                                <label for="li-{{ $link->id }}">Enviar imagem autorizada</label>
                                <input id="li-{{ $link->id }}" type="file" name="image" accept="image/jpeg,image/png,image/webp">
                                @error('links.'.$link->id.'.image')<p class="error">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="actions-row"><button class="btn btn--small" type="submit">Salvar link</button></div>
                    </form>
                    <div class="actions-row" style="margin-top: 10px">
                        @if ($link->suggested_image_url && ! $link->image_media_id)
                            <form method="post" action="{{ route('panel.actions.links.image', [$action, $link]) }}" class="inline-form">@csrf
                                <button class="btn btn--secondary btn--small" type="submit">Usar a imagem sugerida pelo site</button>
                            </form>
                            <span class="muted" style="font-size:.875rem">Use apenas se a fonte autorizar.</span>
                        @endif
                        @unless ($loop->first)<form method="post" action="{{ route('panel.actions.links.move', [$action, $link]) }}" class="inline-form">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn--secondary btn--small">Mover para cima</button></form>@endunless
                        @unless ($loop->last)<form method="post" action="{{ route('panel.actions.links.move', [$action, $link]) }}" class="inline-form">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn--secondary btn--small">Mover para baixo</button></form>@endunless
                        <form method="post" action="{{ route('panel.actions.links.destroy', [$action, $link]) }}" class="inline-form" data-confirm="Remover este link desta versão?">@csrf @method('DELETE')<button class="btn btn--ghost btn--small">Remover</button></form>
                    </div>
                @endif
            </li>
        @endforeach
    </ul>
@endif
@endsection
