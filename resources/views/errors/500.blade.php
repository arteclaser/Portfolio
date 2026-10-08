@extends('layouts.minimal')
@section('title', 'Algo deu errado')
@section('content')
<div class="container error-page">
    <p class="code">Erro 500</p>
    <h1 class="section__title">Algo deu errado</h1>
    <p class="lead">Ocorreu um erro inesperado. Tente novamente em instantes. Se persistir, avise a equipe responsável pelo site.</p>
    <p><a class="btn" href="{{ url('/') }}">Ir para o início</a></p>
</div>
@endsection
