@extends('layouts.panel')
@section('title', 'Ações')
@section('content')
<div class="page-title">
    <div><h1>Ações</h1><p>Atividades e resultados das áreas que você pode acessar.</p></div>
    <div class="actions-row">
        <a class="btn btn--secondary" href="{{ route('panel.actions.trash') }}">Lixeira</a>
        @can('create', \App\Models\Action::class)<a class="btn" href="{{ route('panel.actions.create') }}">Nova ação</a>@endcan
    </div>
</div>

<form method="get" class="filters" role="search" aria-label="Filtrar ações" style="margin-bottom: 18px;">
    <div><label for="f-q">Buscar</label><input class="input" id="f-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"></div>
    <div><label for="f-area">Área</label>
        <select class="input" id="f-area" name="area">
            @foreach ($areaOptions as $v => $l)<option value="{{ $v }}" @selected((string) ($filters['area'] ?? '') === (string) $v)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div><label for="f-estado">Situação editorial</label>
        <select class="input" id="f-estado" name="estado">
            @foreach (['' => 'Ativas (não arquivadas)', 'draft' => 'Rascunho', 'returned' => 'Devolvidas', 'in_review' => 'Em revisão', 'published' => 'Publicadas', 'pending' => 'Publicadas com alterações pendentes', 'archived' => 'Arquivadas'] as $v => $l)
                <option value="{{ $v }}" @selected(($filters['estado'] ?? '') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div><label for="f-sit">Situação da ação</label>
        <select class="input" id="f-sit" name="situacao">
            <option value="">Todas</option>
            @foreach (\App\Models\ActionVersion::ACTIVITY_STATUSES as $v => $l)<option value="{{ $v }}" @selected(($filters['situacao'] ?? '') === $v)>{{ $l }}</option>@endforeach
        </select>
    </div>
    <div class="field--check" style="align-self:center"><input type="checkbox" id="f-minhas" name="minhas" value="1" @checked(! empty($filters['minhas']))><label for="f-minhas">Somente minhas</label></div>
    <div class="filters__actions"><button class="btn" type="submit">Filtrar</button></div>
</form>

@if ($actions->isEmpty())
    <div class="state">
        <h2>Nenhuma ação encontrada</h2>
        <p>{{ collect($filters)->filter()->isNotEmpty() ? 'Nenhuma ação corresponde aos filtros.' : 'Ainda não há ações nas suas áreas.' }}</p>
        @can('create', \App\Models\Action::class)<p><a class="btn" href="{{ route('panel.actions.create') }}">Cadastrar a primeira ação</a></p>@endcan
    </div>
@else
    <div class="table-wrap">
        <table class="table">
            <caption class="visually-hidden">Lista de ações</caption>
            <thead><tr><th scope="col">Ação</th><th scope="col">Área</th><th scope="col">Situação editorial</th><th scope="col">Situação da ação</th><th scope="col">Atualizada</th></tr></thead>
            <tbody>
                @foreach ($actions as $action)
                    @php $v = $action->currentVersion(); @endphp
                    <tr>
                        <td data-label="Ação"><a class="row-title" href="{{ route('panel.actions.edit', $action) }}">{{ $v?->displayTitle() }}</a>
                            <span class="sub">por {{ $v?->author?->name ?? '—' }} · versão {{ $v?->number }}</span></td>
                        <td data-label="Área">{{ $v?->primaryPage?->title ?? '—' }}</td>
                        <td data-label="Situação editorial"><span class="status status--{{ $action->editorialStatus() }}">{{ $action->editorialLabel() }}</span></td>
                        <td data-label="Situação da ação">{{ $v?->activityStatusLabel() ?? '—' }}</td>
                        <td data-label="Atualizada">{{ $action->updated_at?->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $actions->links() }}
@endif
@endsection
