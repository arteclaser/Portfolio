@extends('layouts.panel')
@section('title', 'Lixeira')
@section('content')
<div class="page-title"><div><h1>Lixeira</h1><p>Ações removidas podem ser restauradas. A exclusão permanente exige confirmação e é exclusiva do Administrador Master.</p></div>
<a class="btn btn--secondary" href="{{ route('panel.actions.index') }}">Voltar às ações</a></div>
@include('partials.form-errors')
@if ($actions->isEmpty())
    <div class="state"><h2>A lixeira está vazia</h2></div>
@else
    <div class="table-wrap"><table class="table">
        <caption class="visually-hidden">Ações na lixeira</caption>
        <thead><tr><th scope="col">Ação</th><th scope="col">Removida em</th><th scope="col">Opções</th></tr></thead>
        <tbody>
        @foreach ($actions as $action)
            @php $title = $action->currentVersion()?->displayTitle(); @endphp
            <tr>
                <td data-label="Ação"><strong>{{ $title }}</strong></td>
                <td data-label="Removida em">{{ $action->deleted_at?->format('d/m/Y H:i') }}</td>
                <td data-label="Opções">
                    <div class="actions-row">
                        @can('restore', $action)
                            <form method="post" action="{{ route('panel.actions.restore', $action->id) }}" class="inline-form">@csrf<button class="btn btn--secondary btn--small">Restaurar</button></form>
                        @endcan
                        @can('forceDelete', $action)
                            <form method="post" action="{{ route('panel.actions.force-delete', $action->id) }}" class="inline-form" data-confirm="Excluir permanentemente? Esta operação não pode ser desfeita.">
                                @csrf @method('DELETE')
                                <label for="confirm-{{ $action->id }}" class="visually-hidden">Digite o nome da ação para confirmar</label>
                                <input class="input" id="confirm-{{ $action->id }}" name="confirm_title" placeholder="Digite: {{ $title }}" required style="max-width: 260px; min-height: 36px;">
                                <button class="btn btn--danger btn--small">Excluir permanentemente</button>
                            </form>
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $actions->links() }}
@endif
@endsection
