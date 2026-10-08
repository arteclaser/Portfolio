// Aplica o tema escolhido antes da pintura, para evitar piscar. Preferência local, opcional.
(function () {
  try {
    var t = localStorage.getItem('theme');
    if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
  } catch (e) { /* armazenamento indisponível: segue o sistema */ }
  document.documentElement.classList.add('js');
})();
