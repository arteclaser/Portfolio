@extends('layouts.auth')
@section('title', 'Recuperar acesso')
@section('content')
<h1 class="auth-title">Recuperar acesso</h1>
<p class="muted">Informe seu e-mail. Se ele estiver cadastrado e ativo, enviaremos um link para definir uma nova senha.</p>
@include('partials.flash')
@include('partials.form-errors')
<form method="post" action="{{ route('password.email') }}" class="form" novalidate>
    @csrf
    <x-field name="email" label="E-mail" type="email" autocomplete="email" required autofocus />
    <button type="submit" class="btn btn--block">Enviar link</button>
</form>
<p class="auth-links"><a href="{{ route('login') }}">Voltar para o acesso</a></p>
@endsection
