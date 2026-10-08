<ul class="people">
    @foreach ($members as $member)
        <li class="person">
            <div class="person__photo">
                @if ($member->photo)
                    <x-picture :media="$member->photo" alt="" variant="sm" sizes="72px" />
                @else
                    <span aria-hidden="true">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                @endif
            </div>
            <div>
                <p class="person__name">{{ $member->name }}</p>
                @php $role = collect([$member->pivot->role_in_action ?? null, $member->role_title, $member->function])->filter()->unique()->implode(' · '); @endphp
                @if ($role)<p class="person__role">{{ $role }}</p>@endif
                @if (! $member->isCurrent())<p class="person__role">Integrou a equipe {{ $member->periodLabel() ? '('.$member->periodLabel().')' : '' }}</p>@endif
                @if (($showBio ?? false) && $member->bio)<p class="person__bio">{{ $member->bio }}</p>@endif
                @if ($member->contact_is_public && ($member->contact_email || $member->contact_phone))
                    <p class="person__bio">
                        @if ($member->contact_email)<a href="mailto:{{ $member->contact_email }}">{{ $member->contact_email }}</a>@endif
                        @if ($member->contact_phone)<br>{{ $member->contact_phone }}@endif
                    </p>
                @endif
            </div>
        </li>
    @endforeach
</ul>
