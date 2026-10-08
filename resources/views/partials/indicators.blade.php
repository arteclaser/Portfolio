{{-- $summary vem de IndicatorSummary: só indicadores documentados, sem somar unidades diferentes. --}}
@php $groups = $summary['groups'] ?? []; @endphp
<ul class="stats">
    <li class="stat">
        <div class="stat__value">{{ \App\Support\Text::number($summary['action_count']) }}</div>
        <div class="stat__label">{{ $summary['action_count'] === 1 ? 'ação publicada' : 'ações publicadas' }}</div>
        <div class="stat__note">Cada ação conta uma vez, mesmo quando ligada a mais de uma área.</div>
    </li>
    @foreach ($groups as $group)
        <li class="stat">
            <div class="stat__value">{{ \App\Support\Text::number($group['total']) }}@if ($group['unit']) <span class="visually-hidden">{{ $group['unit'] }}</span>@endif</div>
            <div class="stat__label">{{ $group['label'] }}@if ($group['unit']) ({{ $group['unit'] }})@endif</div>
            <div class="stat__note">
                @if ($group['is_participation'])
                    Contagem de participações registradas; uma mesma pessoa pode ter participado mais de uma vez.
                @endif
                Soma de {{ $group['actions'] }} {{ $group['actions'] === 1 ? 'ação' : 'ações' }} com fonte informada.
            </div>
        </li>
    @endforeach
</ul>
@if ($groups)
    <p class="fineprint">Valores somados apenas entre registros com o mesmo indicador e a mesma unidade. Indicadores sem fonte não são exibidos.</p>
@endif
