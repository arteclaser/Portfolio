<ul class="partners">
    @foreach ($partners as $partner)
        <li class="partner">
            @if ($partner->logo)<x-picture :media="$partner->logo" alt="" variant="sm" sizes="140px" />@endif
            @if ($partner->url)
                <a href="{{ $partner->url }}" rel="noopener noreferrer">{{ $partner->name }}<span class="visually-hidden"> (site externo)</span></a>
            @else
                <span>{{ $partner->name }}</span>
            @endif
        </li>
    @endforeach
</ul>
