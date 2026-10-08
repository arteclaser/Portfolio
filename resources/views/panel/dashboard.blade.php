@extends('layouts.panel')
@section('title', 'Visão geral')
@section('content')
<div class="page-title">
    <div>
        <h1>Visão geral</h1>
        <p>Olá, {{ auth()->user()->name }}. Aqui estão as pendências das suas áreas.</p>
    </div>
    @can('create', \App\Models\Action::class)
        <a class="btn" href="{{ route('panel.actions.create') }}">Nova ação</a>
    @endcan
</div>

@foreach ($warnings as $warning)
    <div class="alert alert--warning" role="note">{{ $warning }}</div>
@endforeach

<div class="tiles">
    <a class="tile" href="{{ route('panel.actions.index', ['estado' => 'published']) }}" style="text-decoration:none"><div class="tile__value">{{ $counts['published'] }}</div><div class="tile__label">publicadas</div></a>
    <a class="tile" href="{{ route('panel.actions.index', ['estado' => 'in_review']) }}" style="text-decoration:none"><div class="tile__value">{{ $counts['in_review'] }}</div><div class="tile__label">em revisão</div></a>
    <a class="tile" href="{{ route('panel.actions.index', ['estado' => 'draft']) }}" style="text-decoration:none"><div class="tile__value">{{ $counts['drafts'] }}</div><div class="tile__label">rascunhos e devolvidas</div></a>
    <a class="tile" href="{{ route('panel.actions.index', ['estado' => 'archived']) }}" style="text-decoration:none"><div class="tile__value">{{ $counts['archived'] }}</div><div class="tile__label">arquivadas</div></a>
</div>

@if ($canReview)
    <section class="box" aria-labelledby="t-revisao">
        <h2 id="t-revisao">Aguardando revisão</h2>
        @if ($inReview->isEmpty())
            <p class="muted">Nada aguardando revisão nas suas áreas.</p>
        @else
            <ul class="plain-list">
                @foreach ($inReview as $action)
                    <li><a href="{{ route('panel.actions.review', $action) }}">{{ $action->workingVersion?->displayTitle() }}</a>
                        <span class="muted">· enviada por {{ $action->workingVersion?->author?->name ?? '—' }} em {{ $action->workingVersion?->submitted_at?->format('d/m/Y H:i') }}</span></li>
                @endforeach
            </ul>
        @endif
    </section>
@endif

<section class="box" aria-labelledby="t-meus">
    <h2 id="t-meus">Meus rascunhos e conteúdos devolvidos</h2>
    @if ($mine->isEmpty())
        <p class="muted">Você não tem rascunhos pendentes.</p>
    @else
        <ul class="plain-list">
            @foreach ($mine as $action)
                <li>
                    <a href="{{ route('panel.actions.edit', $action) }}">{{ $action->workingVersion?->displayTitle() }}</a>
                    <span class="status status--{{ $action->working_state }}">{{ \App\Models\Action::WORKING_STATES[$action->working_state] ?? '' }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section class="box" aria-labelledby="t-atividade">
    <h2 id="t-atividade">{{ auth()->user()->isMaster() ? 'Atividade recente' : 'Minha atividade recente' }}</h2>
    @if ($activity->isEmpty())
        <p class="muted">Sem registros.</p>
    @else
        <ul class="plain-list">
            @foreach ($activity as $log)
                <li><span class="muted">{{ $log->created_at?->format('d/m/Y H:i') }}</span> · {{ $log->user?->name ?? 'Sistema' }}: {{ $log->description }}</li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
