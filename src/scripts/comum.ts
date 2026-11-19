// Comportamentos presentes em todas as páginas: tema claro/escuro e instalação do modo sem internet.

function temaAtual(): 'claro' | 'escuro' {
  const escolhido = document.documentElement.dataset.tema;
  if (escolhido === 'claro' || escolhido === 'escuro') return escolhido;
  return matchMedia('(prefers-color-scheme: dark)').matches ? 'escuro' : 'claro';
}

function atualizarBotoesDeTema() {
  const tema = temaAtual();
  for (const botao of document.querySelectorAll<HTMLElement>('[data-alternar-tema]')) {
    botao.setAttribute('aria-label', tema === 'escuro' ? 'Mudar para o tema claro' : 'Mudar para o tema escuro');
    for (const icone of botao.querySelectorAll<HTMLElement>('[data-tema-icone]')) {
      // Mostra o ícone do tema para o qual o botão leva.
      icone.hidden = icone.dataset.temaIcone === tema;
    }
  }
}

document.addEventListener('click', (evento) => {
  const botao = (evento.target as Element | null)?.closest('[data-alternar-tema]');
  if (!botao) return;
  const novo = temaAtual() === 'escuro' ? 'claro' : 'escuro';
  document.documentElement.dataset.tema = novo;
  try {
    localStorage.setItem('tema', novo);
  } catch {
    /* sem armazenamento: vale só para esta visita */
  }
  atualizarBotoesDeTema();
  document.dispatchEvent(new CustomEvent('tema', { detail: novo }));
});

matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
  atualizarBotoesDeTema();
  document.dispatchEvent(new CustomEvent('tema', { detail: temaAtual() }));
});
atualizarBotoesDeTema();

// Modo sem internet: o navegador guarda o site e o mapa para abrir mesmo sem sinal.
const enderecoLocal = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
const semModoOffline = new URLSearchParams(location.search).has('sem-sw');
if ('serviceWorker' in navigator && (location.protocol === 'https:' || enderecoLocal) && !semModoOffline) {
  addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {
      /* sem modo offline neste navegador: o site continua funcionando normalmente */
    });
  });
}


// Compartilhar: usa o painel do próprio aparelho quando existe; senão, copia o endereço da página.
document.addEventListener('click', async (evento) => {
  const botao = (evento.target as Element | null)?.closest<HTMLElement>('[data-compartilhar]');
  if (!botao) return;
  const dados = { title: botao.dataset.titulo ?? document.title, text: botao.dataset.texto ?? '', url: location.href.split('?')[0] };
  const rotulo = botao.querySelector<HTMLElement>('[data-rotulo]');
  const avisar = (texto: string) => {
    if (!rotulo) return;
    const original = rotulo.textContent;
    rotulo.textContent = texto;
    setTimeout(() => (rotulo.textContent = original), 2200);
  };
  try {
    if (navigator.share) await navigator.share(dados);
    else {
      await navigator.clipboard.writeText(dados.url);
      avisar('Link copiado');
    }
  } catch (erro) {
    if ((erro as DOMException)?.name !== 'AbortError') avisar('Não foi possível compartilhar');
  }
});

export { temaAtual };
