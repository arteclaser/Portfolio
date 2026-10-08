@php
    $tabs = [
        'panel.actions.edit' => 'Dados',
        'panel.actions.media' => 'Fotos',
        'panel.actions.links' => 'Vídeos e reportagens',
        'panel.actions.review' => 'Revisão e publicação',
        'panel.actions.history' => 'Histórico',
    ];
    $currentRoute = request()->route()->getName();
@endphp
<div class="page-title">
    <div>
        <p class="muted" style="margin:0"><a href="{{ route('panel.actions.index') }}">Ações</a> ›</p>
        <h1>{{ $version->displayTitle() }}</h1>
        <p>
            <span class="status status--{{ $action->editorialStatus() }}">{{ $action->editorialLabel() }}</span>
            @if ($version->activity_status)<span class="badge badge--{{ $version->activity_status }}">Ação {{ mb_strtolower($version->activityStatusLabel()) }}</span>@endif
            <span class="muted">· versão {{ $version->number }}{{ $action->workingVersion ? ' (de trabalho)' : ' (publicada)' }}</span>
        </p>
    </div>
    <div class="actions-row">
        <a class="btn btn--secondary" href="{{ route('panel.actions.preview', $action) }}">Prévia</a>
        @if ($action->isPubliclyVisible())
            <a class="btn btn--secondary" href="{{ route('actions.show', $action->slug) }}">Ver no site</a>
        @endif
    </div>
</div>
<nav aria-label="Seções da ação">
    <ul class="tabs">
        @foreach ($tabs as $name => $label)
            <li><a href="{{ route($name, $action) }}" @if ($currentRoute === $name) aria-current="page" @endif>{{ $label }}</a></li>
        @endforeach
    </ul>
</nav>
@if ($action->working_state === 'returned' && $action->workingVersion?->review_notes)
    <div class="alert alert--danger" role="note">
        <p><strong>Devolvido para ajustes</strong> por {{ $action->workingVersion->reviewer?->name ?? 'revisor' }} em {{ $action->workingVersion->reviewed_at?->format('d/m/Y H:i') }}:</p>
        <p style="white-space: pre-line">{{ $action->workingVersion->review_notes }}</p>
    </div>
@endif
@if ($action->hasPendingChanges())
    <div class="alert alert--info" role="note">O site continua mostrando a versão publicada. As alterações desta versão de trabalho só aparecem depois de aprovadas.</div>
@endif
@if ($action->isArchived())
    <div class="alert alert--warning" role="note">Esta ação está arquivada e não aparece no site.</div>
@endif
