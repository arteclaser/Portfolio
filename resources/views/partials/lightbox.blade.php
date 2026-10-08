@once
<dialog class="lightbox" data-lightbox-dialog aria-label="Galeria ampliada">
    <div class="lightbox__inner">
        <div class="lightbox__bar">
            <span data-lb-counter aria-live="polite"></span>
            <button type="button" data-lb-close>Fechar <span aria-hidden="true">✕</span></button>
        </div>
        <div class="lightbox__stage">
            <button type="button" class="lb-prev" data-lb-prev aria-label="Imagem anterior">‹</button>
            <img data-lb-img alt="">
            <button type="button" class="lb-next" data-lb-next aria-label="Próxima imagem">›</button>
        </div>
        <div>
            <div class="lightbox__nav-mobile">
                <button type="button" data-lb-prev>Anterior</button>
                <button type="button" data-lb-next>Próxima</button>
            </div>
            <p class="lightbox__caption" data-lb-caption></p>
        </div>
    </div>
</dialog>
@endonce
