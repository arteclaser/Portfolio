@extends('layouts.minimal')
@section('title', 'Página não encontrada')
@section('content')
<div class="container error-page">
    <p class="code">Erro 404</p>
    <h1 class="section__title">Página não encontrada</h1>
    <p class="lead">O endereço pode ter mudado, o conteúdo pode ter sido arquivado ou ainda não ter sido publicado.</p>
    <p><a class="btn" href="{{ url('/') }}">Ir para o início</a></p>
</div>
@endsection
