@extends('layouts.auth')
@section('title', 'Acesso da equipe')
@section('content')
<h1 class="auth-title">Acesso da equipe</h1>
<p class="muted">Área restrita para quem mantém o portfólio.</p>
@include('partials.flash')
@include('partials.form-errors')
<form method="post" action="{{ route('login.attempt') }}" class="form" novalidate>
    @csrf
    <x-field name="email" label="E-mail" type="email" autocomplete="username" required autofocus />
    <x-field name="password" label="Senha" type="password" autocomplete="current-password" required />
    <div class="field field--check">
        <input type="checkbox" id="f-remember" name="remember" value="1" @checked(old('remember'))>
        <label for="f-remember">Manter conectado neste dispositivo</label>
    </div>
    <button type="submit" class="btn btn--block">Entrar</button>
</form>
<p class="auth-links"><a href="{{ route('password.request') }}">Esqueci minha senha</a> · <a href="{{ route('home') }}">Voltar ao portfólio</a></p>
@endsection
