@extends('layouts.panel')
@section('title', 'Parceiros')
@section('content')
<div class="page-title">
    <div><h1>Instituições parceiras</h1></div>
    <div class="actions-row">
        <a class="btn btn--secondary" href="{{ route('panel.partners.index', ['arquivados' => $showArchived ? 0 : 1]) }}">{{ $showArchived ? 'Ocultar arquivados' : 'Mostrar arquivados' }}</a>
        <a class="btn" href="{{ route('panel.partners.create') }}">Novo parceiro</a>
    </div>
</div>
@if ($partners->isEmpty())
    <div class="state"><h2>Nenhum parceiro cadastrado</h2></div>
@else
    <div class="table-wrap"><table class="table">
        <caption class="visually-hidden">Parceiros</caption>
        <thead><tr><th scope="col">Nome</th><th scope="col">Site</th><th scope="col">Exibição</th></tr></thead>
        <tbody>
        @foreach ($partners as $p)
            <tr>
                <td data-label="Nome"><a class="row-title" href="{{ route('panel.partners.edit', $p) }}">{{ $p->name }}</a> @if ($p->archived_at)<span class="status status--archived">Arquivado</span>@endif</td>
                <td data-label="Site">{{ $p->url ?: '—' }}</td>
                <td data-label="Exibição">{{ $p->is_public ? 'Visível' : 'Oculto' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
@endsection
