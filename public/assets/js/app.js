/* Portfólio — comportamento público. Tudo funciona sem JavaScript; aqui só há melhorias. */
(function () {
  'use strict';

  // ---------- Tema (automático → claro → escuro) ----------
  var root = document.documentElement;
  function currentTheme() { return root.getAttribute('data-theme') || 'auto'; }
  function labelFor(theme) { return theme === 'light' ? 'Tema: claro' : theme === 'dark' ? 'Tema: escuro' : 'Tema: automático'; }
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.hidden = false;
    var text = btn.querySelector('[data-theme-label]');
    if (text) text.textContent = labelFor(currentTheme());
    btn.title = labelFor(currentTheme());
    btn.addEventListener('click', function () {
      var next = { auto: 'light', light: 'dark', dark: 'auto' }[currentTheme()];
      if (next === 'auto') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', next);
      try { if (next === 'auto') localStorage.removeItem('theme'); else localStorage.setItem('theme', next); } catch (e) {}
      if (text) text.textContent = labelFor(next);
      btn.title = labelFor(next);
    });
  });

  // ---------- Galeria com ampliação, fechamento e navegação por teclado ----------
  var dialog = document.querySelector('[data-lightbox-dialog]');
  if (dialog && typeof dialog.showModal === 'function') {
    var img = dialog.querySelector('[data-lb-img]');
    var caption = dialog.querySelector('[data-lb-caption]');
    var counter = dialog.querySelector('[data-lb-counter]');
    var items = [];
    var index = 0;
    var opener = null;

    function show(i) {
      index = (i + items.length) % items.length;
      var item = items[index];
      img.src = item.dataset.full;
      img.alt = item.dataset.alt || '';
      caption.textContent = [item.dataset.caption, item.dataset.credit ? 'Crédito: ' + item.dataset.credit : ''].filter(Boolean).join(' · ');
      counter.textContent = 'Imagem ' + (index + 1) + ' de ' + items.length;
    }

    document.querySelectorAll('[data-lightbox] [data-full]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        items = Array.prototype.slice.call(btn.closest('[data-lightbox]').querySelectorAll('[data-full]'));
        opener = btn;
        show(items.indexOf(btn));
        dialog.showModal();
        dialog.querySelector('[data-lb-close]').focus();
      });
    });
    dialog.querySelectorAll('[data-lb-prev]').forEach(function (b) { b.addEventListener('click', function () { show(index - 1); }); });
    dialog.querySelectorAll('[data-lb-next]').forEach(function (b) { b.addEventListener('click', function () { show(index + 1); }); });
    dialog.querySelector('[data-lb-close]').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { e.preventDefault(); show(index - 1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); show(index + 1); }
    });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    dialog.addEventListener('close', function () { img.removeAttribute('src'); if (opener) opener.focus(); });
  }

  // ---------- Vídeos do YouTube: o player oficial só carrega após o clique ----------
  document.querySelectorAll('[data-youtube]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-youtube');
      if (!/^[A-Za-z0-9_-]{11}$/.test(id)) return;
      var iframe = document.createElement('iframe');
      iframe.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
      iframe.title = btn.getAttribute('data-title') || 'Vídeo do YouTube';
      iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
      iframe.allowFullscreen = true;
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      btn.replaceWith(iframe);
      iframe.focus();
    });
  });

  // ---------- Instagram: incorporação oficial, carregada só por escolha do visitante ----------
  var instaLoading = null;
  function loadInstagram() {
    if (window.instgrm) return Promise.resolve();
    if (instaLoading) return instaLoading;
    instaLoading = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = 'https://www.instagram.com/embed.js';
      s.async = true;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
    return instaLoading;
  }
  document.querySelectorAll('[data-instagram]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var slot = btn.closest('[data-instagram-slot]');
      var status = slot.querySelector('[data-instagram-status]');
      var permalink = btn.getAttribute('data-instagram');
      if (!/^https:\/\/www\.instagram\.com\/(p|reel|tv)\/[A-Za-z0-9_-]+\/$/.test(permalink)) return;
      var quote = document.createElement('blockquote');
      quote.className = 'instagram-media';
      quote.setAttribute('data-instgrm-permalink', permalink);
      quote.setAttribute('data-instgrm-version', '14');
      var link = document.createElement('a');
      link.href = permalink;
      link.textContent = 'Ver esta publicação no Instagram';
      quote.appendChild(link);
      btn.replaceWith(quote);
      status.textContent = 'Carregando publicação do Instagram…';
      var timer = setTimeout(function () {
        status.textContent = 'A publicação não carregou. Ela pode ter sido removida ou estar restrita. Use o link para abrir no Instagram.';
      }, 10000);
      loadInstagram().then(function () {
        if (window.instgrm && window.instgrm.Embeds) window.instgrm.Embeds.process();
        setTimeout(function () {
          if (slot.querySelector('iframe')) { clearTimeout(timer); status.textContent = ''; }
        }, 4000);
      }).catch(function () {
        clearTimeout(timer);
        status.textContent = 'Não foi possível carregar o Instagram neste momento. Use o link para abrir a publicação.';
      });
    });
  });

  // ---------- Compartilhar ----------
  document.querySelectorAll('[data-share]').forEach(function (btn) {
    btn.hidden = false;
    var status = document.querySelector(btn.getAttribute('data-share-status'));
    btn.addEventListener('click', function () {
      var data = { title: btn.getAttribute('data-title'), text: btn.getAttribute('data-text'), url: btn.getAttribute('data-share') };
      if (navigator.share) {
        navigator.share(data).catch(function () {});
      } else if (navigator.clipboard) {
        navigator.clipboard.writeText(data.url).then(function () {
          if (status) status.textContent = 'Link copiado.';
        });
      }
    });
  });
})();
