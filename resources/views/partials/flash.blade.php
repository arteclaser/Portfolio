@if (session('status'))
    <div class="alert alert--success" role="status">{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert--warning" role="status">{{ session('warning') }}</div>
@endif
@if (session('invite_link'))
    <div class="alert alert--info" role="status">
        <p><strong>Link de convite gerado.</strong> Envie com segurança à pessoa convidada (vale por {{ intdiv(config('auth.passwords.invites.expire'), 60) }} horas e só pode ser usado uma vez):</p>
        <p><input type="text" readonly value="{{ session('invite_link') }}" class="input" aria-label="Link de convite" data-select-on-focus></p>
    </div>
@endif
