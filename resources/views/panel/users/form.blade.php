@extends('layouts.panel')
@section('title', $user->exists ? $user->name : 'Convidar usuário')
@section('content')
<div class="page-title"><div><p class="muted" style="margin:0"><a href="{{ route('panel.users.index') }}">Usuários</a> ›</p><h1>{{ $user->exists ? $user->name : 'Convidar usuário' }}</h1>
@if ($user->exists)<p>{{ $user->email }} · {{ $user->is_active ? ($user->hasPendingInvite() ? 'convite pendente' : 'ativo') : 'desativado' }}</p>@endif</div></div>
@include('partials.form-errors')
<form method="post" action="{{ $user->exists ? route('panel.users.update', $user) : route('panel.users.store') }}" class="form" style="max-width: 860px" novalidate>
    @csrf
    @if ($user->exists) @method('PUT') @endif
    <div class="grid-2">
        <x-field name="name" label="Nome" :value="$user->name" required />
        <x-field name="email" label="E-mail" type="email" :value="$user->email" required />
    </div>
    @php $role = old('role', $user->role); @endphp
    <fieldset class="fieldset"><legend>Perfil</legend>
        @foreach (\App\Models\User::ROLES as $key => $label)
            <div class="field--check">
                <input type="radio" id="role-{{ $key }}" name="role" value="{{ $key }}" @checked($role === $key) aria-describedby="role-{{ $key }}-desc">
                <label for="role-{{ $key }}"><strong>{{ $label }}</strong><br><span class="muted" id="role-{{ $key }}-desc">{{ [
                    'master' => 'Administra usuários, permissões, páginas, configurações, equipe, ações e publicações de todo o portfólio.',
                    'editor' => 'Cadastra, revisa, edita e publica conteúdos das áreas autorizadas.',
                    'collaborator' => 'Cria ações nas áreas autorizadas, edita os próprios rascunhos e conteúdos devolvidos e encaminha para revisão. Não publica.',
                ][$key] }}</span></label>
            </div>
        @endforeach
        <div class="fieldset" style="background: var(--surface)">
            <p class="field-label" style="margin:0">Autorizações extras do Editor</p>
            <div class="field--check"><input type="checkbox" id="f-can_manage_team" name="can_manage_team" value="1" @checked(old('can_manage_team', $user->can_manage_team))><label for="f-can_manage_team">Pode administrar a equipe das suas áreas</label></div>
            <div class="field--check"><input type="checkbox" id="f-can_archive" name="can_archive" value="1" @checked(old('can_archive', $user->can_archive))><label for="f-can_archive">Pode arquivar ações e mover para a lixeira</label></div>
        </div>
    </fieldset>
    <fieldset class="fieldset" id="f-page_ids"><legend>Áreas autorizadas</legend>
        <p class="fieldset__intro">Para Editores e Colaboradores. O acesso a uma página inclui as subordinadas. O Master acessa todas.</p>
        @error('page_ids')<p class="error">{{ $message }}</p>@enderror
        @php $sel = array_map('intval', (array) old('page_ids', $selectedPages)); @endphp
        <ul class="checklist">
            @foreach (\App\Support\PageTree::flatten($pages) as $row)
                <li><input type="checkbox" id="pg-{{ $row['page']->id }}" name="page_ids[]" value="{{ $row['page']->id }}" @checked(in_array($row['page']->id, $sel, true))><label for="pg-{{ $row['page']->id }}">{{ str_repeat('— ', $row['depth']) }}{{ $row['page']->title }}</label></li>
            @endforeach
        </ul>
    </fieldset>
    <div class="actions-row"><button class="btn" type="submit">{{ $user->exists ? 'Salvar permissões' : 'Criar e gerar convite' }}</button></div>
</form>

@if ($user->exists)
    <section class="box" style="margin-top: 20px">
        <h2>Acesso</h2>
        <div class="actions-row">
            @if ($user->is_active)
                <form method="post" action="{{ route('panel.users.invite', $user) }}">@csrf<button class="btn btn--secondary">{{ $user->hasPendingInvite() ? 'Gerar novo convite' : 'Gerar link de redefinição de senha' }}</button></form>
                @unless ($user->is(auth()->user()))
                    <form method="post" action="{{ route('panel.users.deactivate', $user) }}" data-confirm="Desativar o acesso deste usuário? A autoria e o histórico serão preservados.">@csrf<button class="btn btn--danger">Desativar acesso</button></form>
                @endunless
            @else
                <form method="post" action="{{ route('panel.users.reactivate', $user) }}">@csrf<button class="btn btn--secondary">Reativar acesso</button></form>
            @endif
        </div>
    </section>
@endif
@endsection
