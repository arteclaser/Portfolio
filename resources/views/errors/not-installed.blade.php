@extends('layouts.minimal')
@section('title', 'Instalação pendente')
@section('content')
<div class="container error-page">
    <p class="code">Instalação pendente</p>
    <h1 class="section__title">O portfólio ainda não foi configurado</h1>
    <p class="lead">A pessoa responsável pela instalação deve executar <code>php artisan mostraqui:instalar</code> no servidor ou acessar <code>/instalar</code> com o código de instalação definido no arquivo de configuração.</p>
</div>
@endsection
