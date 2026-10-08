@extends('layouts.minimal')
@section('title', 'Acesso não permitido')
@section('content')
<div class="container error-page">
    <p class="code">Erro 403</p>
    <h1 class="section__title">Acesso não permitido</h1>
    <p class="lead">Você não tem permissão para acessar este conteúdo. Se acredita que deveria ter, procure o Administrador Master.</p>
    <p><a class="btn" href="{{ url('/') }}">Ir para o início</a></p>
</div>
@endsection
