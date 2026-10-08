@extends('layouts.public', ['current' => 'team', 'title' => 'Equipe', 'canonical' => route('team.index')])

@section('content')
<div class="container section">
    <h1 class="section__title">Equipe</h1>
    <p class="lead">Pessoas que integram a equipe de {{ $portfolio->name }}.</p>
    @if ($members->isEmpty())
        <div class="state"><h2>Equipe ainda não publicada</h2><p>Os integrantes aparecerão aqui quando forem cadastrados como visíveis ao público.</p></div>
    @else
        @foreach ($members->groupBy(fn ($m) => $m->page?->displayTitle() ?? '') as $area => $group)
            <section class="block" aria-labelledby="eq-{{ $loop->index }}">
                <h2 id="eq-{{ $loop->index }}" style="font-size: 1.5rem;">{{ $area ?: 'Equipe geral' }}</h2>
                @include('partials.people', ['members' => $group, 'showBio' => true])
            </section>
        @endforeach
    @endif
</div>
@endsection
