@extends('layouts.panel')
@section('title', 'Registro de atividades')
@section('content')
<div class="page-title"><div><h1>Registro de atividades</h1><p>Quem fez o quê e quando. O registro não pode ser editado pelo painel.</p></div></div>
<form method="get" class="filters" style="margin-bottom: 16px">
    <div><label for="f-usuario">Usuário</label><select class="input" id="f-usuario" name="usuario"><option value="">Todos</option>@foreach ($users as $id => $name)<option value="{{ $id }}" @selected((int) ($filters['usuario'] ?? 0) === $id)>{{ $name }}</option>@endforeach</select></div>
    <div><label for="f-evento">Tipo</label><select class="input" id="f-evento" name="evento">
        @foreach (['' => 'Todos', 'action' => 'Ações', 'page' => 'Páginas', 'user' => 'Usuários', 'auth' => 'Acessos', 'team' => 'Equipe', 'media' => 'Mídias', 'settings' => 'Configurações'] as $v => $l)
            <option value="{{ $v }}" @selected(($filters['evento'] ?? '') === $v)>{{ $l }}</option>
        @endforeach
    </select></div>
    <div class="filters__actions"><button class="btn" type="submit">Filtrar</button></div>
</form>
<div class="table-wrap"><table class="table">
    <caption class="visually-hidden">Registro de atividades</caption>
    <thead><tr><th scope="col">Quando</th><th scope="col">Quem</th><th scope="col">O quê</th><th scope="col">IP</th></tr></thead>
    <tbody>
    @foreach ($logs as $log)
        <tr>
            <td data-label="Quando">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
            <td data-label="Quem">{{ $log->user?->name ?? 'Sistema' }}</td>
            <td data-label="O quê">{{ $log->description }} <span class="sub">{{ $log->event }}</span></td>
            <td data-label="IP">{{ $log->ip_address ?? '—' }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
{{ $logs->links() }}
@endsection
