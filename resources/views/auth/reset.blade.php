@extends('layouts.auth')
@section('title', $invite ? 'Aceitar convite' : 'Definir nova senha')
@section('content')
<h1 class="auth-title">{{ $invite ? 'Bem-vindo(a) à equipe' : 'Definir nova senha' }}</h1>
<p class="muted">{{ $invite ? 'Defina sua senha para acessar o painel.' : 'Escolha uma nova senha.' }} Use pelo menos 10 caracteres, com letras e números.</p>
@include('partials.form-errors')
<form method="post" action="{{ $invite ? route('invite.store') : route('password.update') }}" class="form" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <x-field name="email" label="E-mail" type="email" autocomplete="username" :value="$email" required />
    <x-field name="password" label="Nova senha" type="password" autocomplete="new-password" required help="Mínimo de 10 caracteres, com letras e números." />
    <x-field name="password_confirmation" label="Confirme a senha" type="password" autocomplete="new-password" required />
    <button type="submit" class="btn btn--block">{{ $invite ? 'Criar senha e entrar' : 'Salvar nova senha' }}</button>
</form>
@endsection
