@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'help' => null,
    'required' => false,
    'requiredForPublish' => false,
    'options' => [],
    'placeholder' => null,
    'rows' => 4,
    'markdown' => false,
    'counter' => null,
    'prefix' => null,
    'id' => null,
    'useOld' => true,
])
@php
    // nome HTML (fields[12]) → chave de validação (fields.12) e id estável (f-fields-12)
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = $id ?: 'f-'.str_replace('.', '-', $key);
    $error = $errors->first($key);
    $current = $useOld ? old($key, $value) : $value;
    $describedBy = trim(($help ? $id.'-help ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="field @if ($error) field--error @endif">
    <label for="{{ $id }}">
        {{ $label }}
        @if ($required)<span class="req" aria-hidden="true">*</span><span class="visually-hidden">(obrigatório)</span>@endif
        @if ($requiredForPublish)<span class="req-pub">obrigatório para publicar</span>@endif
    </label>
    @if ($help)<p class="help" id="{{ $id }}-help">{{ $help }}</p>@endif
    @if ($error)<p class="error" id="{{ $id }}-error"><span aria-hidden="true">⚠</span> {{ $error }}</p>@endif

    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($markdown) data-markdown @endif
            @if ($counter) maxlength="{{ $counter }}" data-counter @endif
            {{ $attributes->class(['input']) }}>{{ $current }}</textarea>
        @if ($markdown)<p class="help">Formatação: **negrito**, *itálico*, listas com "- ", links como [texto](https://endereço). HTML não é aceito.</p>@endif
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}"
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            {{ $attributes->class(['input']) }}>
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $current === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @else
        @if ($prefix)<div class="input-prefix"><span>{{ $prefix }}</span>@endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
            @if ($type !== 'password') value="{{ $current }}" @endif
            @if ($required) required @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($counter) maxlength="{{ $counter }}" data-counter @endif
            {{ $attributes->class(['input']) }}>
        @if ($prefix)</div>@endif
    @endif
</div>
