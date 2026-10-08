@extends('layouts.panel')
@section('title', 'Usuários e permissões')
@section('content')
<div class="page-title">
    <div><h1>Usuários e permissões</h1><p>Perfis por função e acesso por área. Usuários convidados definem a própria senha.</p></div>
    <a class="btn" href="{{ route('panel.users.create') }}">Convidar usuário</a>
</div>
<div class="table-wrap"><table class="table">
    <caption class="visually-hidden">Usuários</caption>
    <thead><tr><th scope="col">Nome</th><th scope="col">Perfil</th><th scope="col">Áreas</th><th scope="col">Situação</th><th scope="col">Último acesso</th></tr></thead>
    <tbody>
    @foreach ($users as $u)
        <tr>
            <td data-label="Nome"><a class="row-title" href="{{ route('panel.users.edit', $u) }}">{{ $u->name }}</a><span class="sub">{{ $u->email }}</span></td>
            <td data-label="Perfil">{{ $u->roleLabel() }}@if ($u->isEditor() && ($u->can_manage_team || $u->can_archive))<span class="sub">{{ collect([$u->can_manage_team ? 'administra equipe' : null, $u->can_archive ? 'arquiva ações' : null])->filter()->implode(' · ') }}</span>@endif</td>
            <td data-label="Áreas">{{ $u->isMaster() ? 'Todas' : ($u->pages->pluck('title')->implode(', ') ?: '—') }}</td>
            <td data-label="Situação">
                @if (! $u->is_active)<span class="status status--archived">Desativado</span>
                @elseif ($u->hasPendingInvite())<span class="status status--in_review">Convite pendente</span>
                @else<span class="status status--published">Ativo</span>@endif
            </td>
            <td data-label="Último acesso">{{ $u->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endsection
