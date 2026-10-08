@extends('layouts.panel')
@section('title', 'Páginas')
@section('content')
<div class="page-title">
    <div><h1>Páginas</h1><p>Áreas, programas e projetos do portfólio. Cada página tem blocos editáveis e só muda no site quando é publicada.</p></div>
    <div class="actions-row">
        <a class="btn btn--secondary" href="{{ route('panel.pages.index', ['arquivadas' => $showArchived ? 0 : 1]) }}">{{ $showArchived ? 'Ocultar arquivadas' : 'Mostrar arquivadas' }}</a>
        @can('create', \App\Models\Page::class)<a class="btn" href="{{ route('panel.pages.create') }}">Nova página</a>@endcan
    </div>
</div>
@if (empty($rows))
    <div class="state"><h2>Nenhuma página</h2><p>Crie a primeira área de atuação.</p></div>
@else
    <div class="table-wrap"><table class="table">
        <caption class="visually-hidden">Páginas do portfólio</caption>
        <thead><tr><th scope="col">Página</th><th scope="col">Tipo</th><th scope="col">Situação</th><th scope="col">Opções</th></tr></thead>
        <tbody>
        @foreach ($rows as $row)
            @php $p = $row['page']; @endphp
            <tr>
                <td data-label="Página"><span aria-hidden="true">{{ str_repeat('— ', $row['depth']) }}</span><a class="row-title" href="{{ route('panel.pages.blocks', $p) }}">{{ $p->title }}</a>
                    <span class="sub">/p/{{ $p->slug }} · {{ $p->blocks_count }} bloco(s)</span></td>
                <td data-label="Tipo">{{ $p->kindLabel() }}</td>
                <td data-label="Situação">
                    @if ($p->isArchived())<span class="status status--archived">Arquivada</span>
                    @elseif (! $p->published_at)<span class="status status--draft">Não publicada</span>
                    @elseif ($p->has_unpublished_changes)<span class="status status--in_review">Publicada · alterações pendentes</span>
                    @else<span class="status status--published">Publicada</span>@endif
                </td>
                <td data-label="Opções"><div class="actions-row">
                    <a class="btn btn--secondary btn--small" href="{{ route('panel.pages.blocks', $p) }}">Blocos</a>
                    <a class="btn btn--secondary btn--small" href="{{ route('panel.pages.edit', $p) }}">Configurações</a>
                    <a class="btn btn--ghost btn--small" href="{{ route('panel.pages.fields', $p) }}">Campos</a>
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
@endsection
