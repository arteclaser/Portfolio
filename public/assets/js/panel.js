/* Painel — melhorias progressivas. Os formulários funcionam também sem JavaScript. */
(function () {
  'use strict';
  var root = document.documentElement;
  var csrf = (document.querySelector('input[name="_token"]') || {}).value;

  // Tema
  function labelFor(t) { return t === 'light' ? 'Tema: claro' : t === 'dark' ? 'Tema: escuro' : 'Tema: automático'; }
  document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
    btn.hidden = false;
    var text = btn.querySelector('[data-theme-label]');
    var cur = root.getAttribute('data-theme') || 'auto';
    if (text) text.textContent = labelFor(cur);
    btn.addEventListener('click', function () {
      var next = { auto: 'light', light: 'dark', dark: 'auto' }[root.getAttribute('data-theme') || 'auto'];
      if (next === 'auto') root.removeAttribute('data-theme'); else root.setAttribute('data-theme', next);
      try { if (next === 'auto') localStorage.removeItem('theme'); else localStorage.setItem('theme', next); } catch (e) {}
      if (text) text.textContent = labelFor(next);
    });
  });

  // Menu lateral no celular
  var panel = document.querySelector('[data-panel]');
  var toggle = document.querySelector('[data-menu-toggle]');
  if (panel && toggle) {
    toggle.addEventListener('click', function () {
      var open = panel.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Foco no resumo de erros para leitores de tela
  var errorSummary = document.querySelector('[data-autofocus]');
  if (errorSummary) errorSummary.focus();

  document.querySelectorAll('[data-select-on-focus]').forEach(function (el) {
    el.addEventListener('focus', function () { el.select(); });
  });

  // Confirmação antes de ações sensíveis
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Contador de caracteres
  document.querySelectorAll('[data-counter]').forEach(function (el) {
    var max = parseInt(el.getAttribute('maxlength'), 10);
    var out = document.createElement('p');
    out.className = 'counter';
    out.setAttribute('aria-live', 'polite');
    el.insertAdjacentElement('afterend', out);
    function update() { out.textContent = el.value.length + ' de ' + max + ' caracteres'; }
    el.addEventListener('input', update);
    update();
  });

  // Barra simples de formatação Markdown
  document.querySelectorAll('textarea[data-markdown]').forEach(function (ta) {
    var bar = document.createElement('div');
    bar.className = 'md-toolbar';
    bar.setAttribute('role', 'toolbar');
    bar.setAttribute('aria-label', 'Formatação do texto');
    [['Negrito', '**', '**', 'N'], ['Itálico', '*', '*', 'I'], ['Lista', '\n- ', '', '•'], ['Link', '[', '](https://)', 'Link']].forEach(function (b) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = b[3];
      btn.setAttribute('aria-label', b[0]);
      btn.addEventListener('click', function () {
        var s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e);
        ta.setRangeText(b[1] + sel + b[2], s, e, 'end');
        ta.focus();
        ta.dispatchEvent(new Event('input', { bubbles: true }));
      });
      bar.appendChild(btn);
    });
    ta.insertAdjacentElement('beforebegin', bar);
  });

  // Aviso de alterações não salvas + cópia de segurança local do formulário
  document.querySelectorAll('form[data-dirty-check]').forEach(function (form) {
    var dirty = false;
    var key = form.getAttribute('data-autosave-key');
    var saved = document.body.getAttribute('data-saved-form');
    var storageKey = key ? 'rascunho:' + key : null;
    function store() {
      if (!storageKey) return;
      var data = {};
      new FormData(form).forEach(function (v, k) { if (k !== '_token' && k !== '_method' && typeof v === 'string') { (data[k] = data[k] || []).push(v); } });
      try { localStorage.setItem(storageKey, JSON.stringify({ at: Date.now(), data: data })); } catch (e) {}
    }
    var timer;
    form.addEventListener('input', function () { dirty = true; clearTimeout(timer); timer = setTimeout(store, 800); });
    form.addEventListener('change', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; store(); });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    if (!storageKey) return;
    if (saved && saved === key) { try { localStorage.removeItem(storageKey); } catch (e) {} return; }
    var backup = null;
    try { backup = JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch (e) {}
    if (!backup || !backup.data) return;
    var differs = Object.keys(backup.data).some(function (k) {
      var el = form.elements.namedItem(k);
      return el && el.value !== undefined && el.type !== 'checkbox' && el.type !== 'radio' && el.value !== backup.data[k][0];
    });
    if (!differs) return;
    var box = document.createElement('div');
    box.className = 'alert alert--warning';
    box.setAttribute('role', 'status');
    var when = new Date(backup.at).toLocaleString('pt-BR');
    box.innerHTML = '<p><strong>Há um texto não salvo deste formulário</strong> (cópia local de ' + when + '). Deseja recuperá-lo?</p>';
    var restore = document.createElement('button');
    restore.type = 'button'; restore.className = 'btn btn--small'; restore.textContent = 'Recuperar texto';
    var drop = document.createElement('button');
    drop.type = 'button'; drop.className = 'btn btn--secondary btn--small'; drop.textContent = 'Descartar cópia';
    var row = document.createElement('div'); row.className = 'actions-row'; row.appendChild(restore); row.appendChild(drop); box.appendChild(row);
    form.insertAdjacentElement('beforebegin', box);
    restore.addEventListener('click', function () {
      Object.keys(backup.data).forEach(function (k) {
        var el = form.elements.namedItem(k);
        if (!el || el.type === 'checkbox' || el.type === 'radio' || el.type === 'file') return;
        if (el.value !== undefined) el.value = backup.data[k][0];
      });
      dirty = true;
      box.innerHTML = '<p>Texto recuperado. Revise e clique em salvar.</p>';
    });
    drop.addEventListener('click', function () { try { localStorage.removeItem(storageKey); } catch (e) {} box.remove(); });
  });

  // Campos adicionais conforme a área principal escolhida
  var primary = document.querySelector('[data-primary-area]');
  if (primary) {
    var groups = document.querySelectorAll('[data-field-pages]');
    var empty = document.querySelector('[data-fields-empty]');
    function syncFields() {
      var id = primary.value, any = false;
      groups.forEach(function (g) {
        var show = g.getAttribute('data-field-pages').split(',').indexOf(id) !== -1;
        g.hidden = !show;
        any = any || show;
      });
      if (empty) empty.hidden = any;
    }
    primary.addEventListener('change', syncFields);
    syncFields();
  }

  // Enquadramento: prévia ao vivo
  document.querySelectorAll('[data-focal]').forEach(function (wrap) {
    var x = wrap.querySelector('[data-focal-x]'), y = wrap.querySelector('[data-focal-y]');
    if (!x || !y) return;
    var imgs = wrap.querySelectorAll('[data-focal-img]');
    function apply() { imgs.forEach(function (img) { img.style.objectPosition = x.value + '% ' + y.value + '%'; }); }
    x.addEventListener('input', apply); y.addEventListener('input', apply);
  });

  // Envio de fotos com progresso (verificação prévia aqui; a definitiva é no servidor)
  document.querySelectorAll('form[data-upload]').forEach(function (form) {
    var input = form.querySelector('input[type="file"]');
    var bar = form.querySelector('progress');
    var status = form.querySelector('[data-upload-status]');
    var maxFiles = parseInt(form.getAttribute('data-remaining'), 10);
    var maxBytes = parseInt(form.getAttribute('data-max-bytes'), 10);
    form.addEventListener('submit', function (e) {
      if (!window.XMLHttpRequest || !window.FormData) return;
      e.preventDefault();
      var files = Array.prototype.slice.call(input.files || []);
      if (!files.length) { status.textContent = 'Escolha ao menos uma imagem.'; return; }
      if (!isNaN(maxFiles) && files.length > maxFiles) {
        status.textContent = 'Você escolheu ' + files.length + ' imagens, mas só pode enviar mais ' + maxFiles + ' nesta ação.';
        return;
      }
      var big = files.filter(function (f) { return f.size > maxBytes; });
      if (big.length) {
        status.textContent = 'Arquivo(s) acima do limite de ' + (maxBytes / 1048576).toFixed(1).replace('.', ',') + ' MB: ' + big.map(function (f) { return f.name; }).join(', ');
        return;
      }
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.action);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      bar.hidden = false; bar.value = 0;
      status.textContent = 'Enviando ' + files.length + ' imagem(ns)…';
      xhr.upload.addEventListener('progress', function (ev) {
        if (ev.lengthComputable) { bar.value = Math.round(ev.loaded / ev.total * 100); status.textContent = 'Enviando… ' + bar.value + '%'; }
      });
      xhr.addEventListener('load', function () {
        var res = {};
        try { res = JSON.parse(xhr.responseText); } catch (err) {}
        if (xhr.status === 200 && res.redirect) {
          status.textContent = (res.message || 'Envio concluído.') + (res.errors && res.errors.length ? ' ' + res.errors.join(' ') : '');
          if (!res.errors || !res.errors.length) { window.location = res.redirect; }
          else { setTimeout(function () { window.location = res.redirect; }, 4000); }
        } else if (xhr.status === 413) {
          status.textContent = 'O envio ultrapassou o limite aceito pelo servidor. Envie menos imagens por vez.';
        } else if (xhr.status === 419) {
          status.textContent = 'Sua sessão expirou. Recarregue a página e tente novamente.';
        } else {
          var msg = res.message || 'Não foi possível enviar.';
          if (res.errors) { msg += ' ' + (Array.isArray(res.errors) ? res.errors.join(' ') : Object.values(res.errors).flat().join(' ')); }
          status.textContent = msg;
        }
        bar.hidden = true;
      });
      xhr.addEventListener('error', function () { status.textContent = 'Falha de conexão durante o envio. Nada foi perdido: tente novamente.'; bar.hidden = true; });
      xhr.send(new FormData(form));
    });
  });

  // Prévia de link antes de adicionar
  document.querySelectorAll('[data-link-preview]').forEach(function (form) {
    var btn = form.querySelector('[data-preview-btn]');
    var status = form.querySelector('[data-preview-status]');
    var url = form.querySelector('input[name="url"]');
    btn.hidden = false;
    btn.addEventListener('click', function () {
      if (!url.value) { status.textContent = 'Cole um endereço primeiro.'; url.focus(); return; }
      status.textContent = 'Buscando prévia…';
      btn.disabled = true;
      fetch(btn.getAttribute('data-preview-btn'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ url: url.value })
      }).then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
        .then(function (res) {
          btn.disabled = false;
          if (!res.body.ok) { status.textContent = res.body.message || 'Prévia indisponível. Preencha os dados manualmente.'; return; }
          var d = res.body.data;
          ['title', 'description', 'source_name'].forEach(function (k) {
            var el = form.querySelector('[name="' + k + '"]');
            if (el && !el.value && d[k]) el.value = d[k];
          });
          var kind = form.querySelector('[name="kind"]');
          if (kind && d.kind) kind.value = d.kind;
          status.textContent = d.preview_message || ('Prévia encontrada (' + (d.source_name || d.provider) + '). Revise e clique em Adicionar.');
        })
        .catch(function () { btn.disabled = false; status.textContent = 'Não foi possível consultar a prévia agora. Você pode preencher manualmente.'; });
    });
  });
})();

// Prévia: alternar entre computador e celular
(function () {
  var frame = document.querySelector('[data-preview-frame]');
  if (!frame) return;
  document.querySelectorAll('[data-device-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      frame.setAttribute('data-device', btn.getAttribute('data-device-btn'));
      document.querySelectorAll('[data-device-btn]').forEach(function (b) { b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'); });
    });
  });
})();
