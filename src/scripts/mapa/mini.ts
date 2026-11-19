// Mapa pequeno das páginas de lugar. Só é carregado quando chega perto da tela.
const caixas = document.querySelectorAll<HTMLElement>('[data-mini-mapa]');

async function montar(caixa: HTMLElement) {
  const { criarMapa, maplibregl, temWebGL } = await import('./nucleo');
  const tela = caixa.querySelector<HTMLElement>('.mapa__tela');
  if (!tela || !temWebGL()) {
    caixa.hidden = true;
    return;
  }
  const centro: [number, number] = [Number(caixa.dataset.lon), Number(caixa.dataset.lat)];
  try {
    const { mapa } = criarMapa(tela, { centro, zoom: 15.4, embutido: true, localizacao: false });
    const pino = document.createElement('div');
    pino.className = `pino pino--3 cat-${caixa.dataset.categoria}`;
    pino.setAttribute('aria-hidden', 'true');
    const icone = caixa.querySelector('template')?.content.querySelector('svg')?.cloneNode(true);
    if (icone) pino.append(icone);
    new maplibregl.Marker({ element: pino, anchor: 'center' }).setLngLat(centro).addTo(mapa);
  } catch {
    caixa.hidden = true;
  }
}

if (caixas.length) {
  const observador = new IntersectionObserver(
    (entradas) => {
      for (const entrada of entradas) {
        if (!entrada.isIntersecting) continue;
        observador.unobserve(entrada.target);
        void montar(entrada.target as HTMLElement);
      }
    },
    { rootMargin: '300px' },
  );
  for (const caixa of caixas) observador.observe(caixa);
}
