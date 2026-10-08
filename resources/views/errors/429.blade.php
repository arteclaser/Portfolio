@extends('layouts.minimal')
@section('title', 'Muitas tentativas')
@section('content')
<div class="container error-page">
    <p class="code">Erro 429</p>
    <h1 class="section__title">Muitas tentativas</h1>
    <p class="lead">Aguarde alguns minutos antes de tentar novamente.</p>
    <p><a class="btn" href="{{ url('/') }}">Ir para o início</a></p>
</div>
@endsection
