<footer class="site-footer">
    <div class="container site-footer__grid">
        <div>
            <div class="site-footer__name">{{ $portfolio->short_name ?: $portfolio->name }}</div>
            <div>{{ $portfolio->name }}</div>
            @if ($portfolio->contact_email)
                <div>Contato: <a href="mailto:{{ $portfolio->contact_email }}">{{ $portfolio->contact_email }}</a></div>
            @endif
        </div>
        <div>
            @if ($portfolio->footer_note)<p>{{ $portfolio->footer_note }}</p>@endif
            @if ($portfolio->is_demo)<p>Proposta visual · imagens e conteúdos ilustrativos.</p>@endif
            <p class="footer-links">
                <a href="{{ route('accessibility') }}" class="a11y-link">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="6.5" r="1.3" fill="currentColor"/><path d="M7 9.5l5 1 5-1M12 10.5v3.5l-2.5 4.5M12 14l2.5 4.5"/></svg>
                    Acessibilidade
                </a>
                · <a href="{{ route('privacy') }}">Privacidade</a>
                · <a href="{{ route('login') }}">Acesso da equipe</a>
            </p>
        </div>
    </div>
</footer>
