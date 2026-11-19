// Aplica o tema escolhido antes de a página aparecer (evita o "clarão" ao abrir).
(function () {
  try {
    var tema = localStorage.getItem('tema');
    if (tema === 'claro' || tema === 'escuro') document.documentElement.dataset.tema = tema;
  } catch (e) { /* armazenamento indisponível: segue o tema do aparelho */ }
})();
