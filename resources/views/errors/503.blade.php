@extends('layouts.minimal')
@section('title', 'Serviço indisponível')
@section('content')
<div class="container error-page">
    <p class="code">Erro 503</p>
    <h1 class="section__title">Serviço indisponível</h1>
    <p class="lead">O site está em manutenção ou temporariamente indisponível. Tente novamente em instantes.</p>
    <p><a class="btn" href="{{ url('/') }}">Ir para o início</a></p>
</div>
@endsection
