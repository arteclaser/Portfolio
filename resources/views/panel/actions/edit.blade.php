@extends('layouts.panel')
@section('title', $version->displayTitle())
@section('content')
@include('panel.actions._header')

@if (! $action->workingVersion)
    <div class="box box--muted">
        <h2>Conteúdo publicado</h2>
        @if ($canStartWorking)
            <p>Para alterar esta ação, crie uma versão de trabalho. O site continuará mostrando a versão publicada até a nova aprovação.</p>
            <form method="post" action="{{ route('panel.actions.working', $action) }}">@csrf<button class="btn">Editar conteúdo (criar versão de trabalho)</button></form>
        @else
            <p>Somente Editores e o Administrador Master podem alterar ações já publicadas. Fale com o editor da área se algo precisar ser corrigido.</p>
        @endif
    </div>
@elseif (! $canEdit)
    <div class="alert alert--info" role="note">
        @if ($action->working_state === 'in_review')
            Este conteúdo está em revisão. Aguarde o retorno do editor para voltar a editar.
        @else
            Você pode visualizar esta ação, mas não editá-la.
        @endif
    </div>
@endif

@include('partials.form-errors')

<form method="post" action="{{ route('panel.actions.update', $action) }}" class="form" novalidate
      @if ($canEdit) data-dirty-check data-autosave-key="action-{{ $action->id }}" @endif>
    @csrf @method('PUT')
    <input type="hidden" name="version_stamp" value="{{ $version->updated_at?->getTimestamp() }}">
    <fieldset class="form" style="border:0;padding:0;margin:0;min-width:0" @disabled(! $canEdit)>

    <fieldset class="fieldset">
        <legend>Identificação</legend>
        <x-field name="title" label="Nome da ação" :value="$version->title" required-for-publish counter="255" />
        <x-field name="summary" label="Resumo" type="textarea" rows="3" :value="$version->summary" required-for-publish counter="600" help="Aparece nos cartões, na busca e no compartilhamento." />
        <x-field name="description" label="Descrição" type="textarea" rows="10" :value="$version->description" required-for-publish markdown />
        <div class="grid-2">
            <x-field name="primary_page_id" label="Área principal" type="select" :options="$areaOptions" :value="$version->primary_page_id" required data-primary-area help="Define as permissões de edição e revisão." />
            <x-field name="program_page_id" label="Programa ou projeto (opcional)" type="select" :options="$programOptions" :value="$version->program_page_id" />
        </div>
        <fieldset class="fieldset" style="background: var(--surface)">
            <legend style="font-size:1.05rem">Áreas relacionadas</legend>
            <p class="fieldset__intro">A ação também aparecerá nessas páginas, sem duplicar o cadastro nem a contagem geral.</p>
            @php $related = old('related_page_ids', $version->relatedPages->pluck('id')->all()); @endphp
            <ul class="checklist">
                @foreach (\App\Support\PageTree::flatten($allPages) as $row)
                    <li><input type="checkbox" id="rp-{{ $row['page']->id }}" name="related_page_ids[]" value="{{ $row['page']->id }}" @checked(in_array($row['page']->id, array_map('intval', (array) $related), true))>
                        <label for="rp-{{ $row['page']->id }}">{{ str_repeat('— ', $row['depth']) }}{{ $row['page']->title }}</label></li>
                @endforeach
            </ul>
        </fieldset>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Contexto</legend>
        <div class="grid-2">
            <x-field name="starts_on" label="Data ou início do período" type="date" :value="$version->starts_on?->format('Y-m-d')" required-for-publish />
            <x-field name="ends_on" label="Término do período (opcional)" type="date" :value="$version->ends_on?->format('Y-m-d')" help="Deixe em branco para ações de um único dia." />
        </div>
        <div class="grid-2">
            <x-field name="location" label="Local" :value="$version->location" />
            @php $types = collect($activityTypes)->mapWithKeys(fn ($t) => [$t => $t]); if ($version->activity_type && ! $types->has($version->activity_type)) { $types->put($version->activity_type, $version->activity_type); } @endphp
            <x-field name="activity_type" label="Tipo de atividade" type="select" :options="['' => 'Selecione'] + $types->all()" :value="$version->activity_type" />
        </div>
        @php $status = old('activity_status', $version->activity_status); @endphp
        <fieldset class="field" style="border:0;padding:0;margin:0">
            <legend class="field-label">Situação da ação</legend>
            <p class="help">Situação da atividade em si. Não confunda com a aprovação do conteúdo, que fica em “Revisão e publicação”.</p>
            <div class="radio-row">
                <label><input type="radio" name="activity_status" value="" @checked(! $status)> Não informada</label>
                @foreach (\App\Models\ActionVersion::ACTIVITY_STATUSES as $key => $label)
                    <label><input type="radio" name="activity_status" value="{{ $key }}" @checked($status === $key)> {{ $label }}</label>
                @endforeach
            </div>
        </fieldset>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Responsáveis</legend>
        <div>
            <p class="field-label">Integrantes da equipe</p>
            <p class="help">Marque quem participou e, se quiser, descreva o papel nesta ação. Ex-integrantes continuam disponíveis para preservar o histórico.</p>
            @php $selectedTeam = $version->teamMembers->keyBy('id'); @endphp
            @forelse ($members as $member)
                @php
                    $sel = old('team.'.$member->id.'.selected', $selectedTeam->has($member->id));
                    $role = old('team.'.$member->id.'.role', $selectedTeam->get($member->id)?->pivot->role_in_action);
                @endphp
                <div class="member-row">
                    <div class="field--check">
                        <input type="checkbox" id="tm-{{ $member->id }}" name="team[{{ $member->id }}][selected]" value="1" @checked($sel)>
                        <label for="tm-{{ $member->id }}">{{ $member->name }}@if ($member->role_title) <span class="muted">· {{ $member->role_title }}</span>@endif @if (! $member->isCurrent())<span class="muted">(ex-integrante)</span>@endif @if (! $member->is_public)<span class="muted">(não exibido no site)</span>@endif</label>
                    </div>
                    <div>
                        <label for="tr-{{ $member->id }}" class="visually-hidden">Papel de {{ $member->name }} nesta ação</label>
                        <input class="input" id="tr-{{ $member->id }}" name="team[{{ $member->id }}][role]" value="{{ $role }}" placeholder="Papel nesta ação (opcional)" maxlength="120">
                    </div>
                </div>
            @empty
                <p class="muted">Nenhum integrante cadastrado. @can('create', \App\Models\TeamMember::class)<a href="{{ route('panel.team.create') }}">Cadastrar equipe</a>@endcan</p>
            @endforelse
        </div>
        <div>
            <p class="field-label">Instituições parceiras</p>
            @php $selPartners = array_map('intval', (array) old('partner_ids', $version->partners->pluck('id')->all())); @endphp
            @if ($partners->isEmpty())
                <p class="muted">Nenhum parceiro cadastrado. @can('create', \App\Models\Partner::class)<a href="{{ route('panel.partners.create') }}">Cadastrar parceiro</a>@endcan</p>
            @else
                <ul class="checklist">
                    @foreach ($partners as $partner)
                        <li><input type="checkbox" id="pt-{{ $partner->id }}" name="partner_ids[]" value="{{ $partner->id }}" @checked(in_array($partner->id, $selPartners, true))><label for="pt-{{ $partner->id }}">{{ $partner->name }}</label></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Resultados</legend>
        <x-field name="objectives" label="Objetivos" type="textarea" rows="4" :value="$version->objectives" markdown />
        <x-field name="results" label="Resultados documentados" type="textarea" rows="6" :value="$version->results" markdown help="Descreva apenas resultados que possam ser comprovados." />
        <div>
            <p class="field-label">Indicadores (opcional)</p>
            <p class="help">Informe valor, unidade, período e fonte. <strong>Indicadores sem fonte não aparecem no site.</strong> Marque “participações” quando a contagem puder incluir a mesma pessoa mais de uma vez.</p>
            @php
                $rows = old('indicators', $version->indicators->map(fn ($i) => ['label' => $i->label, 'value' => $i->value !== null ? rtrim(rtrim((string) $i->value, '0'), '.') : '', 'unit' => $i->unit, 'period' => $i->period, 'source' => $i->source, 'is_participation' => $i->is_participation])->all());
                $rows = array_values((array) $rows);
                $rows = array_merge($rows, array_fill(0, max(2, 3 - count($rows)), ['label' => '', 'value' => '', 'unit' => '', 'period' => '', 'source' => '', 'is_participation' => false]));
            @endphp
            <div class="repeater">
                @foreach ($rows as $i => $row)
                    <div class="repeater__row" role="group" aria-label="Indicador {{ $i + 1 }}">
                        <div class="grid-3">
                            <x-field :name="'indicators['.$i.'][label]'" label="Indicador" :value="$row['label'] ?? ''" placeholder="Ex.: Participações em oficinas" />
                            <x-field :name="'indicators['.$i.'][value]'" label="Valor" type="number" step="any" :value="$row['value'] ?? ''" />
                            <x-field :name="'indicators['.$i.'][unit]'" label="Unidade" :value="$row['unit'] ?? ''" placeholder="Ex.: participações, empresas, R$" />
                        </div>
                        <div class="grid-2">
                            <x-field :name="'indicators['.$i.'][period]'" label="Período" :value="$row['period'] ?? ''" placeholder="Ex.: março a junho de 2026" />
                            <x-field :name="'indicators['.$i.'][source]'" label="Fonte" :value="$row['source'] ?? ''" placeholder="Ex.: Listas de presença da Secretaria" />
                        </div>
                        <div class="field--check">
                            <input type="hidden" name="indicators[{{ $i }}][is_participation]" value="0">
                            <input type="checkbox" id="ip-{{ $i }}" name="indicators[{{ $i }}][is_participation]" value="1" @checked(! empty($row['is_participation']))>
                            <label for="ip-{{ $i }}">É contagem de participações (não de pessoas únicas)</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="help">Precisa de mais linhas? Salve e novas linhas vazias aparecerão.</p>
        </div>
    </fieldset>

    <fieldset class="fieldset">
        <legend>Campos adicionais</legend>
        <p class="fieldset__intro" data-fields-empty @if ($fields->isNotEmpty()) hidden @endif>A área escolhida não tem campos adicionais.</p>
        @foreach ($fields as $field)
            <div data-field-pages="{{ implode(',', $fieldPages[$field->id] ?? []) }}">
                @php $opts = $field->type === 'select' ? ['' => 'Selecione'] + array_combine($field->optionList(), $field->optionList()) : []; @endphp
                <x-field :name="'fields['.$field->id.']'" :label="$field->label" :type="match ($field->type) { 'number' => 'number', 'date' => 'date', 'url' => 'url', 'select' => 'select', default => 'text' }"
                         :options="$opts" :value="$fieldValues[$field->id] ?? null" :help="$field->help" :required-for-publish="$field->is_required" />
            </div>
        @endforeach
    </fieldset>

    <fieldset class="fieldset">
        <legend>Publicação</legend>
        <x-field name="slug" label="Endereço da página" :value="$version->slug" prefix="{{ parse_url(config('app.url'), PHP_URL_HOST) }}/acoes/" help="Gerado a partir do nome. Use letras minúsculas, números e hífens." />
        @if (auth()->user()->isMaster() || auth()->user()->isEditor())
            <div class="field--check">
                <input type="checkbox" id="f-is_featured" name="is_featured" value="1" @checked(old('is_featured', $version->is_featured))>
                <label for="f-is_featured">Destacar na página inicial</label>
            </div>
        @endif
        <x-field name="change_note" label="Nota sobre esta versão (opcional)" :value="$version->change_note" help="Ex.: correção da data; inclusão de resultados. Fica no histórico." />
    </fieldset>
    </fieldset>

    @if ($canEdit)
        <div class="form-footer">
            <span class="save-state">Última gravação: {{ $version->updated_at?->format('d/m/Y H:i') }} por {{ $version->lastEditor?->name ?? '—' }}</span>
            <div class="actions-row">
                <button type="submit" class="btn btn--secondary" name="intent" value="save">Salvar rascunho</button>
                <button type="submit" class="btn" name="intent" value="submit">Salvar e ir para revisão</button>
            </div>
        </div>
    @endif
</form>
@endsection
