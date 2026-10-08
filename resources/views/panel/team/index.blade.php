@extends('layouts.panel')
@section('title', 'Equipe')
@section('content')
<div class="page-title">
    <div><h1>Equipe institucional</h1><p>Cadastro separado das contas de acesso: um integrante pode aparecer no portfólio sem ser usuário do sistema.</p></div>
    <div class="actions-row">
        <a class="btn btn--secondary" href="{{ route('panel.team.index', ['arquivados' => $showArchived ? 0 : 1]) }}">{{ $showArchived ? 'Ocultar arquivados' : 'Mostrar arquivados' }}</a>
        <a class="btn" href="{{ route('panel.team.create') }}">Novo integrante</a>
    </div>
</div>
@if ($members->isEmpty())
    <div class="state"><h2>Nenhum integrante cadastrado</h2></div>
@else
    <div class="table-wrap"><table class="table">
        <caption class="visually-hidden">Integrantes da equipe</caption>
        <thead><tr><th scope="col">Nome</th><th scope="col">Cargo e função</th><th scope="col">Área</th><th scope="col">Período</th><th scope="col">Site</th></tr></thead>
        <tbody>
        @foreach ($members as $m)
            <tr>
                <td data-label="Nome"><a class="row-title" href="{{ route('panel.team.edit', $m) }}">{{ $m->name }}</a>@if ($m->user)<span class="sub">Conta: {{ $m->user->email }}</span>@endif @if ($m->archived_at)<span class="status status--archived">Arquivado</span>@endif</td>
                <td data-label="Cargo e função">{{ collect([$m->role_title, $m->function])->filter()->implode(' · ') ?: '—' }}</td>
                <td data-label="Área">{{ $m->page?->title ?? '—' }}</td>
                <td data-label="Período">{{ $m->periodLabel() ?? '—' }} @unless ($m->isCurrent())<span class="sub">ex-integrante</span>@endunless</td>
                <td data-label="Site">{{ $m->is_public ? 'Visível' : 'Oculto' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
@endsection
