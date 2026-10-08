@extends('layouts.panel')
@section('title', $portfolio->exists ? $portfolio->name : 'Novo portfólio')
@section('content')
@php
    $mode = config('portfolio.routing');
    $base = config('portfolio.base_domain');
    $example = $mode === 'subdomain' ? '<strong>endereço</strong>.'.$base : $base.'/<strong>endereço</strong>';
@endphp
<div class="page-title"><div><p class="muted" style="margin:0"><a href="{{ route('panel.platform.index') }}">Portfólios</a> ›</p>
    <h1>{{ $portfolio->exists ? $portfolio->name : 'Novo portfólio' }}</h1>
    @if ($portfolio->exists)<p><a href="{{ $portfolio->publicUrl() }}">{{ preg_replace('#^https?://#', '', $portfolio->publicUrl()) }}</a></p>@endif
</div></div>
@include('partials.form-errors')
<form method="post" action="{{ $portfolio->exists ? route('panel.platform.update', $portfolio->id) : route('panel.platform.store') }}" class="form" style="max-width: 860px" novalidate>
    @csrf
    @if ($portfolio->exists) @method('PUT') @endif
    <fieldset class="fieldset"><legend>Identificação</legend>
        <x-field name="name" label="Nome do portfólio" :value="$portfolio->name" required placeholder="Ex.: Secretaria de Turismo de Exemplo" />
        <div class="grid-2">
            <x-field name="short_name" label="Nome curto do cabeçalho" :value="$portfolio->short_name" placeholder="Ex.: Capinzal" />
            <x-field name="slug" label="Endereço" :value="$portfolio->slug" :required="$portfolio->exists" placeholder="Ex.: capinzal"
                     help="3 a 40 caracteres: letras minúsculas sem acento, números e hífens. Se ficar em branco, é gerado a partir do nome curto." />
        </div>
        <p class="help">O portfólio ficará em {!! $example !!}, disponível assim que você salvar.</p>
    </fieldset>

    @unless ($portfolio->exists)
        <fieldset class="fieldset"><legend>Áreas iniciais</legend>
            <x-field name="areas" label="Uma por linha (opcional)" type="textarea" rows="4" :value="old('areas', implode(PHP_EOL, array_keys(\App\Services\Installer::INITIAL_AREAS)))" help="Podem ser renomeadas, reorganizadas ou arquivadas depois, em Páginas." />
        </fieldset>
    @else
        <fieldset class="fieldset"><legend>Situação</legend>
            <div class="field--check"><input type="checkbox" id="f-is_active" name="is_active" value="1" @checked(old('is_active', $portfolio->is_active))><label for="f-is_active">Ativo (desmarcado: o site sai do ar e a equipe do portfólio não acessa o painel)</label></div>
            <div class="field--check"><input type="checkbox" id="f-is_listed" name="is_listed" value="1" @checked(old('is_listed', $portfolio->is_listed))><label for="f-is_listed">Listado na página inicial da plataforma</label></div>
        </fieldset>
    @endunless

    <fieldset class="fieldset"><legend>{{ $portfolio->exists ? 'Adicionar outro Master' : 'Administrador Master do portfólio (opcional)' }}</legend>
        @if ($portfolio->exists && isset($masters) && $masters->isNotEmpty())
            <p class="fieldset__intro">Masters atuais: {{ $masters->map(fn ($m) => $m->name.' ('.$m->email.')')->implode(', ') }}</p>
        @endif
        <p class="fieldset__intro">A pessoa recebe um convite para definir a própria senha e passa a administrar só este portfólio.</p>
        <div class="grid-2">
            <x-field name="master_name" label="Nome" />
            <x-field name="master_email" label="E-mail" type="email" />
        </div>
    </fieldset>
    <div class="actions-row"><button class="btn" type="submit">{{ $portfolio->exists ? 'Salvar' : 'Criar portfólio' }}</button></div>
</form>
@if ($portfolio->exists)
    <form method="post" action="{{ route('panel.platform.manage', $portfolio->id) }}" style="margin-top: 20px">@csrf<button class="btn btn--secondary">Gerenciar este portfólio no painel</button></form>
@endif
@endsection
