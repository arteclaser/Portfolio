@extends('layouts.panel')
@section('title', 'Campos · '.$page->title)
@section('content')
@include('panel.pages._header')
@include('partials.form-errors')
<p class="muted">Campos adicionais aparecem no formulário das ações cuja área principal é esta página ou uma subordinada. Campos com valores registrados não podem ser excluídos nem mudar de tipo: arquive-os para preservar o histórico.</p>

@if ($fields->isEmpty())
    <div class="state"><h2>Nenhum campo adicional</h2></div>
@else
    @foreach ($fields as $field)
        <section class="box {{ $field->archived_at ? 'box--muted' : '' }}">
            <h2 style="margin-bottom: 4px">{{ $field->label }} @if ($field->archived_at)<span class="status status--archived">Arquivado</span>@endif</h2>
            <p class="muted">{{ $field->typeLabel() }} · {{ $field->values_count }} valor(es) registrado(s) · {{ $field->is_required ? 'obrigatório para publicar' : 'opcional' }} · {{ $field->is_public ? 'exibido no site' : 'uso interno' }}</p>
            @if ($canManage)
                <details>
                    <summary>Editar campo</summary>
                    <form method="post" action="{{ route('panel.pages.fields.update', [$page, $field]) }}" class="form" style="margin-top: 12px">
                        @csrf @method('PUT')
                        @include('panel.pages._field-form', ['f' => $field, 'prefix' => 'cf'.$field->id])
                        <div><button class="btn btn--small">Salvar campo</button></div>
                    </form>
                </details>
                <div class="actions-row" style="margin-top: 10px">
                    @if ($field->archived_at)
                        <form method="post" action="{{ route('panel.pages.fields.unarchive', [$page, $field]) }}">@csrf<button class="btn btn--secondary btn--small">Reativar</button></form>
                    @else
                        <form method="post" action="{{ route('panel.pages.fields.archive', [$page, $field]) }}">@csrf<button class="btn btn--secondary btn--small">Arquivar</button></form>
                    @endif
                    @if ($field->values_count === 0)
                        <form method="post" action="{{ route('panel.pages.fields.destroy', [$page, $field]) }}" data-confirm="Excluir este campo?">@csrf @method('DELETE')<button class="btn btn--ghost btn--small">Excluir</button></form>
                    @endif
                </div>
            @endif
        </section>
    @endforeach
@endif

@if ($canManage)
    <form method="post" action="{{ route('panel.pages.fields.store', $page) }}" class="fieldset form" style="margin-top: 20px" novalidate>
        @csrf
        <h2 style="margin:0">Novo campo</h2>
        @include('panel.pages._field-form', ['f' => new \App\Models\CustomField(['type' => 'text', 'is_public' => true]), 'prefix' => 'new'])
        <div><button class="btn" type="submit">Adicionar campo</button></div>
    </form>
@endif
@endsection
