@extends('layouts.panel')
@section('title', 'Histórico · '.$version->displayTitle())
@section('content')
@include('panel.actions._header')
@include('partials.form-errors')

<section class="box" aria-labelledby="t-versoes">
    <h2 id="t-versoes">Versões</h2>
    @unless ($canRestore)
        <p class="muted">Para restaurar uma versão antiga, a ação não pode ter versão de trabalho pendente{{ auth()->user()->isCollaborator() ? ' e é preciso ser Editor ou Master' : '' }}.</p>
    @endunless
    <ol class="timeline" reversed>
        @foreach ($versions as $v)
            <li class="{{ $v->status === 'published' ? 'is-published' : '' }}">
                <h3>Versão {{ $v->number }} · <span class="status status--{{ in_array($v->status, ['published', 'in_review', 'returned', 'draft']) ? $v->status : 'archived' }}">{{ $v->statusLabel() }}</span>
                    @if ($action->published_version_id === $v->id)<span class="muted">(no site)</span>@endif
                    @if ($action->working_version_id === $v->id)<span class="muted">(em edição)</span>@endif</h3>
                <p class="muted" style="margin:0">
                    Criada por {{ $v->author?->name ?? '—' }} em {{ $v->created_at?->format('d/m/Y H:i') }}
                    @if ($v->reviewer) · revisada por {{ $v->reviewer->name }} em {{ $v->reviewed_at?->format('d/m/Y H:i') }}@endif
                    @if ($v->published_at) · publicada em {{ $v->published_at->format('d/m/Y H:i') }} por {{ $v->publisher?->name ?? '—' }}@endif
                    @if ($v->restored_from_version_id) · conteúdo restaurado de uma versão anterior @endif
                </p>
                @if ($v->change_note)<p style="margin:4px 0 0">Nota: {{ $v->change_note }}</p>@endif
                @if (! empty($diffs[$v->id]))<p style="margin:4px 0 0">Alterações em relação à versão anterior: {{ implode(', ', $diffs[$v->id]) }}.</p>@endif
                @if ($v->review_notes)<p style="margin:4px 0 0" class="muted">Observações da revisão: {{ \Illuminate\Support\Str::limit($v->review_notes, 300) }}</p>@endif
                @if ($canRestore && $action->published_version_id !== $v->id)
                    <form method="post" action="{{ route('panel.actions.versions.restore', [$action, $v]) }}" style="margin-top: 6px" data-confirm="Criar uma nova versão de trabalho com o conteúdo da versão {{ $v->number }}?">
                        @csrf<button class="btn btn--secondary btn--small">Restaurar este conteúdo para edição</button>
                    </form>
                @endif
            </li>
        @endforeach
    </ol>
</section>

<section class="box" aria-labelledby="t-registros">
    <h2 id="t-registros">Registro de alterações</h2>
    <div class="table-wrap"><table class="table">
        <caption class="visually-hidden">Registro de alterações da ação</caption>
        <thead><tr><th scope="col">Quando</th><th scope="col">Quem</th><th scope="col">O quê</th></tr></thead>
        <tbody>
        @foreach ($logs as $log)
            <tr>
                <td data-label="Quando">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                <td data-label="Quem">{{ $log->user?->name ?? 'Sistema' }}</td>
                <td data-label="O quê">{{ $log->description }}@if (! empty($log->properties['changed'])) <span class="sub">Campos: {{ implode(', ', $log->properties['changed']) }}</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</section>
@endsection
