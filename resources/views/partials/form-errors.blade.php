@if ($errors->any())
    <div class="alert alert--danger" role="alert" tabindex="-1" data-autofocus>
        <p><strong>{{ $errors->count() === 1 ? 'Há 1 problema a corrigir:' : 'Há '.$errors->count().' problemas a corrigir:' }}</strong></p>
        <ul>
            @foreach ($errors->messages() as $field => $messages)
                <li><a href="#{{ 'f-'.str_replace(['.', '[', ']'], ['-', '-', ''], $field) }}">{{ $messages[0] }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
