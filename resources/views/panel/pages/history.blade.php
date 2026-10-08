@extends('layouts.panel')
@section('title', 'Histórico · '.$page->title)
@section('content')
@include('panel.pages._header')
@include('partials.form-errors')
@if ($revisions->isEmpty())
    <div class="state"><h2>Nenhuma publicação ainda</h2></div>
@else
    <ol class="timeline" reversed>
        @foreach ($revisions as $rev)
            <li class="{{ $loop->first ? 'is-published' : '' }}">
                <h3>Publicação de {{ $rev->created_at?->format('d/m/Y H:i') }} @if ($loop->first)<span class="muted">(atual no site)</span>@endif</h3>
                <p class="muted" style="margin:0">Por {{ $rev->publisher?->name ?? 'Sistema' }} · {{ count($rev->snapshot['blocks'] ?? []) }} bloco(s){{ $rev->note ? ' · '.$rev->note : '' }}</p>
                @can('updateContent', $page)
                    <form method="post" action="{{ route('panel.pages.revisions.restore', [$page, $rev]) }}" data-confirm="Substituir os blocos de trabalho pelo conteúdo desta publicação? O site só muda quando você publicar." style="margin-top: 6px">
                        @csrf<button class="btn btn--secondary btn--small">Restaurar para edição</button>
                    </form>
                @endcan
            </li>
        @endforeach
    </ol>
@endif
@endsection
