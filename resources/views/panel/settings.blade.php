@extends('layouts.panel')
@section('title', 'Configurações')
@section('content')
<div class="page-title"><div><h1>Configurações do portfólio</h1><p>Identidade, textos da página inicial e limites de mídia.</p></div></div>
@include('partials.form-errors')
<form method="post" action="{{ route('panel.settings.update') }}" class="form" data-dirty-check novalidate>
    @csrf @method('PUT')
    <fieldset class="fieldset"><legend>Identificação</legend>
        <x-field name="name" label="Nome do portfólio" :value="$portfolio->name" required />
        <div class="grid-3">
            <x-field name="short_name" label="Nome curto (cabeçalho)" :value="$portfolio->short_name" />
            <x-field name="tagline" label="Linha 1 ao lado do nome" :value="$portfolio->tagline" />
            <x-field name="subtitle" label="Linha 2 ao lado do nome" :value="$portfolio->subtitle" />
        </div>
        <p class="help">Não use brasões ou logotipos oficiais sem autorização: o cabeçalho é tipográfico.</p>
        <div class="grid-2">
            <x-field name="contact_email" label="E-mail de contato público" type="email" :value="$portfolio->contact_email" />
            <x-field name="footer_note" label="Nota do rodapé" :value="$portfolio->footer_note" />
        </div>
    </fieldset>
    <fieldset class="fieldset"><legend>Página inicial</legend>
        <div class="grid-2">
            <x-field name="hero_kicker" label="Chamada" :value="$portfolio->hero_kicker" />
            <x-field name="hero_description" label="Descrição" :value="$portfolio->hero_description" />
        </div>
        <div class="grid-2">
            <x-field name="hero_title" label="Título" :value="$portfolio->hero_title" />
            <x-field name="hero_highlight" label="Destaque do título (em azul)" :value="$portfolio->hero_highlight" />
        </div>
        <x-field name="about" label="Sobre o portfólio" type="textarea" rows="4" :value="$portfolio->about" markdown />
    </fieldset>
    <fieldset class="fieldset"><legend>Limites e listas</legend>
        <p class="fieldset__intro">Escolhas de produto, aplicadas também no servidor.</p>
        <div class="grid-2">
            <x-field name="max_images_per_action" label="Imagens por ação (incluindo a capa)" type="number" min="1" max="50" :value="$portfolio->maxImagesPerAction()" required />
            <x-field name="max_upload_mb" label="Tamanho máximo por imagem (MB, antes da otimização)" type="number" step="0.5" min="0.5" max="50" :value="$portfolio->setting('max_upload_mb')" required
                     :help="$serverLimitBytes ? 'Limite atual do PHP neste servidor: '.round($serverLimitBytes / 1048576, 1).' MB.' : null" />
        </div>
        <x-field name="activity_types" label="Tipos de atividade (um por linha)" type="textarea" rows="6" :value="implode(PHP_EOL, $portfolio->activityTypes())" />
    </fieldset>
    <fieldset class="fieldset"><legend>Ambiente</legend>
        <div class="field--check"><input type="checkbox" id="f-is_demo" name="is_demo" value="1" @checked(old('is_demo', $portfolio->is_demo))><label for="f-is_demo">Ambiente de demonstração (exibe aviso de conteúdo ilustrativo e pede aos buscadores que não indexem)</label></div>
    </fieldset>
    <div class="form-footer"><span></span><button class="btn" type="submit">Salvar configurações</button></div>
</form>
@endsection
