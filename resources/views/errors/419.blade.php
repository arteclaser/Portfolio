@extends('layouts.minimal')
@section('title', 'Sessão expirada')
@section('content')
<div class="container error-page">
    <p class="code">Erro 419</p>
    <h1 class="section__title">Sessão expirada</h1>
    <p class="lead">Por segurança, o formulário expirou. Volte, recarregue a página e tente novamente. Se você estava editando, o painel tenta recuperar o texto não salvo neste navegador.</p>
    <p><a class="btn" href="{{ url('/') }}">Ir para o início</a></p>
</div>
@endsection
