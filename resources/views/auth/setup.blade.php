@extends('layouts.auth')
@section('title', 'Instalação')
@section('content')
<h1 class="auth-title">Instalação do portfólio</h1>
<p class="muted">Este formulário só funciona enquanto não houver usuários cadastrados e exige o código de instalação (<code>SETUP_TOKEN</code>) definido no arquivo <code>.env</code> do servidor.</p>
@include('partials.form-errors')
<form method="post" action="{{ route('setup.store') }}" class="form" novalidate>
    @csrf
    <x-field name="setup_token" label="Código de instalação" type="password" autocomplete="off" required />
    <fieldset class="fieldset">
        <legend>Portfólio</legend>
        <x-field name="portfolio_name" label="Nome do portfólio" :value="old('portfolio_name', 'Secretaria de Desenvolvimento Econômico, Inovação e Turismo de Capinzal')" required />
        <x-field name="short_name" label="Nome curto do cabeçalho" :value="old('short_name', 'Capinzal')" help="Aparece em destaque no topo, por exemplo: CAPINZAL." />
        @if (\App\Support\Routing::single())
            <x-field name="slug" label="Identificador do portfólio" :value="old('slug', 'capinzal')" help="Nome interno, usado nos backups e numa futura ampliação para vários portfólios. O site fica no próprio domínio. Letras minúsculas, números e hífens." />
        @else
            <x-field name="slug" label="Endereço do portfólio" :value="old('slug', 'capinzal')" help="Usado em mostraqui.net/capinzal (ou capinzal.mostraqui.net). Letras minúsculas, números e hífens." />
        @endif
        <div class="field field--check">
            <input type="checkbox" id="f-create_areas" name="create_areas" value="1" @checked(old('create_areas', true))>
            <label for="f-create_areas">Criar as áreas iniciais (Desenvolvimento Econômico, Inovação e Turismo). Podem ser alteradas depois.</label>
        </div>
    </fieldset>
    <fieldset class="fieldset">
        <legend>Administrador da plataforma</legend>
        <p class="fieldset__intro">@if (\App\Support\Routing::single())Administra o portfólio com todos os poderes de Master.@else Cria portfólios e atua como Master em qualquer um deles.@endif</p>
        <x-field name="name" label="Nome" autocomplete="name" required />
        <x-field name="email" label="E-mail" type="email" autocomplete="username" required />
        <x-field name="password" label="Senha" type="password" autocomplete="new-password" required help="Mínimo de 10 caracteres, com letras e números." />
        <x-field name="password_confirmation" label="Confirme a senha" type="password" autocomplete="new-password" required />
    </fieldset>
    <button type="submit" class="btn btn--block">Instalar</button>
</form>
@endsection
