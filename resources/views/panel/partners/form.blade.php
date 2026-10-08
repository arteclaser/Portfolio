@extends('layouts.panel')
@section('title', $partner->exists ? $partner->name : 'Novo parceiro')
@section('content')
<div class="page-title"><div><p class="muted" style="margin:0"><a href="{{ route('panel.partners.index') }}">Parceiros</a> ›</p><h1>{{ $partner->exists ? $partner->name : 'Novo parceiro' }}</h1></div></div>
@include('partials.form-errors')
<form method="post" action="{{ $partner->exists ? route('panel.partners.update', $partner) : route('panel.partners.store') }}" enctype="multipart/form-data" class="form" style="max-width: 760px" data-dirty-check novalidate>
    @csrf
    @if ($partner->exists) @method('PUT') @endif
    <x-field name="name" label="Nome" :value="$partner->name" required />
    <x-field name="url" label="Site" type="url" :value="$partner->url" placeholder="https://" />
    <x-field name="description" label="Descrição" type="textarea" rows="3" :value="$partner->description" />
    <x-field name="position" label="Ordem" type="number" min="0" :value="$partner->position ?? 0" />
    <div class="field">
        <label for="f-logo">Logotipo (opcional)</label>
        @if ($partner->logo)
            <img src="{{ \App\Support\MediaUrl::url($partner->logo, 'sm') }}" alt="" style="max-width: 160px">
            <div class="field--check"><input type="checkbox" id="f-remove_logo" name="remove_logo" value="1"><label for="f-remove_logo">Remover logotipo</label></div>
        @endif
        <input id="f-logo" type="file" name="logo" accept="image/jpeg,image/png,image/webp">
        <p class="help">Use somente logotipos fornecidos ou autorizados pela instituição.</p>
        @error('logo')<p class="error">{{ $message }}</p>@enderror
    </div>
    <div class="field--check"><input type="checkbox" id="f-is_public" name="is_public" value="1" @checked(old('is_public', $partner->is_public))><label for="f-is_public">Visível no portfólio público</label></div>
    <div class="actions-row"><button class="btn" type="submit">Salvar</button></div>
</form>
@if ($partner->exists)
    <div style="margin-top: 20px">
        @if ($partner->archived_at)
            <form method="post" action="{{ route('panel.partners.unarchive', $partner) }}">@csrf<button class="btn btn--secondary">Reativar</button></form>
        @else
            <form method="post" action="{{ route('panel.partners.archive', $partner) }}" data-confirm="Arquivar este parceiro?">@csrf<button class="btn btn--secondary">Arquivar</button></form>
        @endif
    </div>
@endif
@endsection
