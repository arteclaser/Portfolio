@extends('layouts.panel')
@section('title', 'Portfólios da plataforma')
@section('content')
<div class="page-title">
    <div><h1>Portfólios da plataforma</h1><p>Cada portfólio fica disponível na hora em que é criado, no próprio endereço. Não é preciso configurar nada no cPanel.</p></div>
    <a class="btn" href="{{ route('panel.platform.create') }}">Novo portfólio</a>
</div>
@if ($portfolios->isEmpty())
    <div class="state"><h2>Nenhum portfólio ainda</h2><p><a class="btn" href="{{ route('panel.platform.create') }}">Criar o primeiro portfólio</a></p></div>
@else
    <div class="table-wrap"><table class="table">
        <caption class="visually-hidden">Portfólios</caption>
        <thead><tr><th scope="col">Portfólio</th><th scope="col">Endereço público</th><th scope="col">Situação</th><th scope="col">Ações publicadas</th><th scope="col">Opções</th></tr></thead>
        <tbody>
        @foreach ($portfolios as $p)
            <tr>
                <td data-label="Portfólio"><a class="row-title" href="{{ route('panel.platform.edit', $p->id) }}">{{ $p->name }}</a>@if ($currentId === $p->id)<span class="sub">Em gestão agora</span>@endif</td>
                <td data-label="Endereço público"><a href="{{ $p->publicUrl() }}">{{ preg_replace('#^https?://#', '', $p->publicUrl()) }}</a></td>
                <td data-label="Situação">
                    @if (! $p->is_active)<span class="status status--archived">Desativado</span>@elseif (! $p->is_listed)<span class="status status--draft">Ativo, não listado</span>@else<span class="status status--published">Ativo</span>@endif
                    @if ($p->is_demo)<span class="sub">demonstração</span>@endif
                </td>
                <td data-label="Ações publicadas">{{ $stats[$p->id]['published'] }} <span class="sub">{{ $stats[$p->id]['users'] }} usuário(s)</span></td>
                <td data-label="Opções">
                    <form method="post" action="{{ route('panel.platform.manage', $p->id) }}" class="inline-form">@csrf<button class="btn btn--secondary btn--small" @disabled($currentId === $p->id)>Gerenciar<span class="visually-hidden"> {{ $p->name }}</span></button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
@endsection
