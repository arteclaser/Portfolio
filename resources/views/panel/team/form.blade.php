@extends('layouts.panel')
@section('title', $member->exists ? $member->name : 'Novo integrante')
@section('content')
<div class="page-title"><div><p class="muted" style="margin:0"><a href="{{ route('panel.team.index') }}">Equipe</a> ›</p><h1>{{ $member->exists ? $member->name : 'Novo integrante' }}</h1></div></div>
@include('partials.form-errors')
<form method="post" action="{{ $member->exists ? route('panel.team.update', $member) : route('panel.team.store') }}" enctype="multipart/form-data" class="form" data-dirty-check novalidate>
    @csrf
    @if ($member->exists) @method('PUT') @endif
    <fieldset class="fieldset"><legend>Identificação</legend>
        <x-field name="name" label="Nome" :value="$member->name" required />
        <div class="grid-2">
            <x-field name="role_title" label="Cargo" :value="$member->role_title" />
            <x-field name="function" label="Função" :value="$member->function" />
        </div>
        <div class="grid-2">
            <x-field name="page_id" label="Área" type="select" :options="$areaOptions" :value="$member->page_id" :required="! auth()->user()->isMaster()" />
            <x-field name="position" label="Ordem de exibição" type="number" min="0" :value="$member->position ?? 0" />
        </div>
        <x-field name="bio" label="Apresentação breve" type="textarea" rows="3" :value="$member->bio" counter="2000" />
        <div class="field">
            <label for="f-photo">Fotografia (opcional)</label>
            @if ($member->photo)
                <img src="{{ \App\Support\MediaUrl::url($member->photo, 'sm') }}" alt="" style="width: 96px; height: 96px; object-fit: cover; border-radius: 50%">
                <div class="field--check"><input type="checkbox" id="f-remove_photo" name="remove_photo" value="1"><label for="f-remove_photo">Remover foto</label></div>
            @endif
            <input id="f-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
            <p class="help">Use somente fotos com autorização de uso de imagem.</p>
            @error('photo')<p class="error">{{ $message }}</p>@enderror
        </div>
    </fieldset>
    <fieldset class="fieldset"><legend>Contato e período</legend>
        <div class="grid-2">
            <x-field name="contact_email" label="E-mail institucional (opcional)" type="email" :value="$member->contact_email" />
            <x-field name="contact_phone" label="Telefone institucional (opcional)" :value="$member->contact_phone" />
        </div>
        <div class="field--check"><input type="checkbox" id="f-contact_is_public" name="contact_is_public" value="1" @checked(old('contact_is_public', $member->contact_is_public))><label for="f-contact_is_public">Exibir contato no site (desmarcado = contato interno, nunca exibido)</label></div>
        <div class="grid-2">
            <x-field name="started_on" label="Início da atuação" type="date" :value="$member->started_on?->format('Y-m-d')" />
            <x-field name="ended_on" label="Fim da atuação" type="date" :value="$member->ended_on?->format('Y-m-d')" help="Ao sair da equipe, informe a data. O vínculo com ações anteriores é preservado." />
        </div>
        <div class="field--check"><input type="checkbox" id="f-is_public" name="is_public" value="1" @checked(old('is_public', $member->is_public))><label for="f-is_public">Visível no portfólio público</label></div>
        @if ($userOptions !== null)
            <x-field name="user_id" label="Conta de acesso vinculada (opcional)" type="select" :options="$userOptions" :value="$member->user_id" help="Apenas referência. Permissões são definidas em Usuários." />
        @endif
    </fieldset>
    <div class="form-footer"><span></span><button class="btn" type="submit">Salvar</button></div>
</form>
@if ($member->exists)
    <section class="box" style="margin-top: 20px">
        <h2>Arquivar cadastro</h2>
        <p class="muted">O cadastro some das listas, mas continua ligado às ações em que participou.</p>
        @if ($member->archived_at)
            <form method="post" action="{{ route('panel.team.unarchive', $member) }}">@csrf<button class="btn btn--secondary">Reativar</button></form>
        @else
            <form method="post" action="{{ route('panel.team.archive', $member) }}" data-confirm="Arquivar este cadastro?">@csrf<button class="btn btn--secondary">Arquivar</button></form>
        @endif
    </section>
@endif
@endsection
