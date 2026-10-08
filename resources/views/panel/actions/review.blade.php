@extends('layouts.panel')
@section('title', 'Revisão · '.$version->displayTitle())
@section('content')
@include('panel.actions._header')
@include('partials.form-errors')

<div class="grid-2" style="align-items: start">
    <section class="box" aria-labelledby="t-checklist">
        <h2 id="t-checklist">Requisitos para publicar</h2>
        @if (! $working)
            <p>Esta ação não tem alterações pendentes. A versão {{ $version->number }} foi publicada em {{ $version->published_at?->format('d/m/Y H:i') }} por {{ $version->publisher?->name ?? '—' }}.</p>
            @if ($can['startWorking'])
                <form method="post" action="{{ route('panel.actions.working', $action) }}">@csrf<button class="btn">Editar conteúdo (criar versão de trabalho)</button></form>
            @endif
        @else
            @php
                $checks = [
                    'title' => 'Nome', 'summary' => 'Resumo', 'description' => 'Descrição',
                    'primary_page_id' => 'Área principal', 'starts_on' => 'Data ou período',
                ];
            @endphp
            <ul class="plain-list">
                @foreach ($checks as $field => $label)
                    <li>{!! isset($errors_publish[$field]) ? '<span aria-hidden="true">✗</span> <strong>'.e($label).'</strong>: '.e($errors_publish[$field]) : '<span aria-hidden="true">✓</span> '.e($label) !!}</li>
                @endforeach
                @foreach ($errors_publish as $field => $message)
                    @continue(isset($checks[$field]))
                    <li><span aria-hidden="true">✗</span> {{ $message }}</li>
                @endforeach
            </ul>
            @if ($missingAlt)
                <p class="alert alert--warning" style="margin-top: 12px">{{ $missingAlt }} {{ $missingAlt === 1 ? 'foto está' : 'fotos estão' }} sem descrição acessível. Não impede a publicação, mas prejudica quem usa leitor de tela. <a href="{{ route('panel.actions.media', $action) }}">Completar nas fotos</a></p>
            @endif
            @if (empty($errors_publish))
                <p class="alert alert--success" style="margin-top: 12px">Tudo pronto para {{ $can['review'] ? 'publicar' : 'enviar para revisão' }}.</p>
            @else
                <p><a href="{{ route('panel.actions.edit', $action) }}">Completar os dados</a></p>
            @endif
        @endif
    </section>

    <section class="box" aria-labelledby="t-fluxo">
        <h2 id="t-fluxo">Fluxo editorial</h2>
        @if ($working)
            <dl class="facts">
                <div><dt>Situação</dt><dd>{{ \App\Models\Action::WORKING_STATES[$action->working_state] ?? '—' }}</dd></div>
                <div><dt>Autor da versão</dt><dd>{{ $working->author?->name ?? '—' }}</dd></div>
                @if ($working->submitted_at)<div><dt>Enviada para revisão</dt><dd>{{ $working->submitted_at->format('d/m/Y H:i') }}</dd></div>@endif
                @if ($working->reviewer)<div><dt>Última revisão</dt><dd>{{ $working->reviewer->name }} em {{ $working->reviewed_at?->format('d/m/Y H:i') }}</dd></div>@endif
            </dl>
            <div class="preview-bar" style="margin-top: 14px">
                <a class="btn btn--secondary btn--small" href="{{ route('panel.actions.preview', $action) }}">Prévia (computador e celular)</a>
            </div>

            @if ($can['submit'])
                <form method="post" action="{{ route('panel.actions.submit', $action) }}" style="margin-top: 14px">
                    @csrf
                    <button class="btn" type="submit" @disabled(! empty($errors_publish))>Encaminhar para revisão</button>
                    @if (! empty($errors_publish))<p class="help">Complete os requisitos para encaminhar.</p>@endif
                </form>
            @endif

            @if ($can['review'])
                <form method="post" action="{{ route('panel.actions.publish', $action) }}" class="form" style="margin-top: 18px">
                    @csrf
                    <x-field name="note" label="Nota da publicação (opcional)" help="Fica registrada no histórico." />
                    <button class="btn" type="submit" @disabled(! empty($errors_publish))>{{ $action->isPublished() ? 'Aprovar e publicar alterações' : 'Aprovar e publicar' }}</button>
                </form>
                @if ($action->working_state === 'in_review')
                    <form method="post" action="{{ route('panel.actions.return', $action) }}" class="form" style="margin-top: 18px">
                        @csrf
                        <x-field name="review_notes" label="Devolver com observações" type="textarea" rows="4" required help="Explique o que precisa ser ajustado. O autor verá esta mensagem." />
                        <button class="btn btn--secondary" type="submit">Devolver para ajustes</button>
                    </form>
                @endif
            @endif

            @if ($can['discard'])
                <form method="post" action="{{ route('panel.actions.discard', $action) }}" style="margin-top: 18px" data-confirm="Descartar todas as alterações desta versão de trabalho? A versão publicada não muda.">
                    @csrf
                    <button class="btn btn--ghost" type="submit">Descartar alterações e manter a versão publicada</button>
                </form>
            @endif
        @else
            <p class="muted">Sem versão de trabalho.</p>
        @endif
    </section>
</div>

@if ($can['archive'] || $can['delete'])
    <section class="box" aria-labelledby="t-zona">
        <h2 id="t-zona">Arquivo e lixeira</h2>
        <p class="muted">Arquivar retira a ação do site e preserva tudo. A lixeira permite restaurar depois.</p>
        <div class="actions-row">
            @if ($can['archive'])
                @if ($action->isArchived())
                    <form method="post" action="{{ route('panel.actions.unarchive', $action) }}">@csrf<button class="btn btn--secondary">Retirar do arquivo</button></form>
                @else
                    <form method="post" action="{{ route('panel.actions.archive', $action) }}" data-confirm="Arquivar esta ação? Ela deixará de aparecer no site.">@csrf<button class="btn btn--secondary">Arquivar</button></form>
                @endif
            @endif
            @if ($can['delete'])
                <form method="post" action="{{ route('panel.actions.destroy', $action) }}" data-confirm="Mover esta ação para a lixeira?">@csrf<button class="btn btn--danger">Mover para a lixeira</button></form>
            @endif
        </div>
    </section>
@endif
@endsection
