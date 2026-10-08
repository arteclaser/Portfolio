@extends('layouts.public', ['current' => 'actions', 'title' => 'Ações', 'canonical' => route('actions.index')])

@section('content')
<div class="container section">
    <h1 class="section__title">Ações</h1>
    <p class="lead">Busque por nome, área, período, tipo de atividade ou situação.</p>

    <form class="filters" method="get" action="{{ route('actions.index') }}" role="search" aria-label="Filtrar ações">
        <div>
            <label for="f-q">Buscar</label>
            <input id="f-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Palavras da ação" maxlength="120">
        </div>
        <div>
            <label for="f-area">Área</label>
            <select id="f-area" name="area" class="select">
                <option value="">Todas</option>
                @foreach (\App\Support\PageTree::flatten($pages) as $row)
                    <option value="{{ $row['page']->slug }}" @selected(($filters['area'] ?? '') === $row['page']->slug)>{{ str_repeat('— ', $row['depth']) }}{{ $row['page']->displayTitle() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-ano">Período</label>
            <select id="f-ano" name="ano" class="select">
                <option value="">Todos os anos</option>
                @foreach ($years as $year)
                    <option value="{{ $year }}" @selected((int) ($filters['ano'] ?? 0) === $year)>{{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-tipo">Tipo</label>
            <select id="f-tipo" name="tipo" class="select">
                <option value="">Todos</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(($filters['tipo'] ?? '') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="f-situacao">Situação</label>
            <select id="f-situacao" name="situacao" class="select">
                <option value="">Todas</option>
                @foreach (\App\Models\ActionVersion::ACTIVITY_STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected(($filters['situacao'] ?? '') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filters__actions">
            <button type="submit" class="btn">Aplicar filtros</button>
            @if ($hasFilters)<a class="btn btn--outline" href="{{ route('actions.index') }}">Limpar</a>@endif
        </div>
    </form>

    @if ($totalPublished === 0)
        <div class="state" style="margin-top: 24px;">
            <h2>Ainda não há ações publicadas</h2>
            <p>Volte em breve: as ações aparecem aqui depois de revisadas pela equipe.</p>
        </div>
    @elseif ($results->isEmpty())
        <div class="state" style="margin-top: 24px;" role="status">
            <h2>Nenhuma ação encontrada</h2>
            <p>Nenhum resultado para os filtros escolhidos. Tente outras palavras ou remova algum filtro.</p>
            <p><a class="btn btn--outline" href="{{ route('actions.index') }}">Limpar filtros</a></p>
        </div>
    @else
        <p class="result-count" role="status">{{ $results->total() }} {{ $results->total() === 1 ? 'ação encontrada' : 'ações encontradas' }}</p>
        <ul class="cards">
            @foreach ($results as $item)
                <li>@include('partials.action-card', ['item' => $item])</li>
            @endforeach
        </ul>
        {{ $results->links() }}
    @endif
</div>
@endsection
