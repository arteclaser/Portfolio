@extends('layouts.panel')
@section('title', 'Nova ação')
@section('content')
<div class="page-title"><div><h1>Nova ação</h1><p>Comece pelo essencial. Você pode salvar o rascunho incompleto e continuar depois.</p></div></div>
@include('partials.form-errors')
<form method="post" action="{{ route('panel.actions.store') }}" class="form" style="max-width: 760px" data-dirty-check>
    @csrf
    <x-field name="title" label="Nome da ação" required-for-publish counter="255" />
    <x-field name="primary_page_id" label="Área principal" type="select" :options="$areaOptions" required help="Define quem pode editar e revisar a ação. Outras áreas podem ser associadas depois." />
    <x-field name="summary" label="Resumo" type="textarea" rows="3" counter="600" required-for-publish help="Uma ou duas frases que apresentem a ação." />
    <div class="actions-row"><button type="submit" class="btn">Criar rascunho</button><a href="{{ route('panel.actions.index') }}" class="btn btn--secondary">Cancelar</a></div>
</form>
@endsection
