// Tela de mapa (explorar lugares e página de roteiro): liga a lista de cartões ao mapa.
// A lista em HTML é a fonte dos dados e também a alternativa acessível ao mapa.
import Supercluster from 'supercluster';
import { distanciaM, formatarDistancia, normalizar } from '../../lib/geo';
import { criarMapa, desenharRota, maplibregl, temWebGL, type Mapa } from './nucleo';

interface Item {
  id: string;
  el: HTMLElement;
  cartao: HTMLElement;
  lon: number;
  lat: number;
  categoria: string;
  icone: string;
  destaque: number;
  nome: string;
  curto: string;
  tipo: string;
  resumo: string;
  onde: string;
  precisao: string;
  url: string;
  comoChegar: string;
  foto?: string;
  busca: string;
  numero?: number;
  visivel: boolean;
}

interface RotaNoMapa {
  id: string;
  nome: string;
  cor: string;
  corEscuro: string;
  geometria: [number, number][];
  url: string;
}

const app = document.querySelector<HTMLElement>('[data-app]');
if (app) iniciar(app);

function iniciar(app: HTMLElement) {
  const modo = (app.dataset.modo ?? 'explorar') as 'explorar' | 'rota';
  const caixaDoMapa = app.querySelector<HTMLElement>('.mapa')!;
  const tela = app.querySelector<HTMLElement>('.mapa__tela')!;
  const lista = app.querySelector<HTMLElement>('[data-lista]')!;
  const contagem = app.querySelector<HTMLElement>('[data-contagem]');
  const vazio = app.querySelector<HTMLElement>('[data-vazio]');
  const campoBusca = app.querySelector<HTMLInputElement>('input[data-busca]');
  const botaoLimpar = app.querySelector<HTMLButtonElement>('[data-limpar]');
  const botaoOrdenar = app.querySelector<HTMLButtonElement>('[data-ordenar]');
  const botaoVisao = app.querySelector<HTMLButtonElement>('[data-alternar-visao]');
  const filtros = [...app.querySelectorAll<HTMLButtonElement>('[data-filtro]')];
  const celular = matchMedia('(max-width: 899px)');
  const semMovimento = matchMedia('(prefers-reduced-motion: reduce)');

  const itens: Item[] = [...lista.querySelectorAll<HTMLElement>('[data-lugar]')].map((el, i) => ({
    id: el.dataset.lugar!,
    el,
    cartao: el.querySelector<HTMLElement>('.cartao')!,
    lon: Number(el.dataset.lon),
    lat: Number(el.dataset.lat),
    categoria: el.dataset.categoria!,
    icone: el.dataset.icone!,
    destaque: Number(el.dataset.destaque ?? 1),
    nome: el.dataset.nome!,
    curto: el.dataset.curto ?? el.dataset.nome!,
    tipo: el.dataset.tipo ?? '',
    resumo: el.dataset.resumo ?? '',
    onde: el.dataset.onde ?? '',
    precisao: el.dataset.precisao ?? 'exata',
    url: el.dataset.url!,
    comoChegar: el.dataset.comoChegar!,
    foto: el.dataset.foto || undefined,
    busca: normalizar(el.dataset.busca ?? el.dataset.nome!),
    numero: modo === 'rota' ? i + 1 : undefined,
    visivel: true,
  }));
  const porId = new Map(itens.map((item) => [item.id, item]));

  let selecionado: string | null = null;
  let ignorarRolagemAte = 0;
  let ctx: Mapa | null = null;
  let mapa!: maplibregl.Map;
  let posicao: [number, number] | null = null;
  let indice = new Supercluster<{ id: string }>({ radius: 46, maxZoom: 15, minPoints: 2 });
  const marcadores = new Map<string, maplibregl.Marker>();
  let balao: maplibregl.Popup | null = null;

  // ------------------------------------------------------------------ filtros e busca
  const ativos = new Set<string>();

  function aplicarFiltros() {
    const termo = normalizar(campoBusca?.value ?? '');
    let total = 0;
    for (const item of itens) {
      item.visivel = (ativos.size === 0 || ativos.has(item.categoria)) && (!termo || item.busca.includes(termo));
      item.el.hidden = !item.visivel;
      if (item.visivel) total += 1;
    }
    if (contagem && modo === 'explorar') {
      contagem.textContent =
        total === 0 ? 'Nenhum lugar encontrado' : total === 1 ? '1 lugar' : `${total} lugares${total < itens.length ? ` de ${itens.length}` : ''}`;
    }
    if (vazio) vazio.hidden = total > 0;
    if (botaoLimpar) botaoLimpar.hidden = !termo;
    if (selecionado && !porId.get(selecionado)?.visivel) selecionar(null);
    reindexar();
    guardarEstado();
  }

  for (const botao of filtros) {
    botao.addEventListener('click', () => {
      const id = botao.dataset.filtro!;
      const ligado = botao.getAttribute('aria-pressed') !== 'true';
      botao.setAttribute('aria-pressed', String(ligado));
      if (ligado) ativos.add(id);
      else ativos.delete(id);
      aplicarFiltros();
      enquadrarVisiveis();
    });
  }
  campoBusca?.addEventListener('input', () => aplicarFiltros());
  campoBusca?.addEventListener('keydown', (evento) => {
    if (evento.key === 'Enter') {
      evento.preventDefault();
      enquadrarVisiveis();
      const primeiro = itens.find((item) => item.visivel);
      if (primeiro && itens.filter((item) => item.visivel).length === 1) selecionar(primeiro.id, { voar: true, rolar: true });
    }
  });
  botaoLimpar?.addEventListener('click', () => {
    if (!campoBusca) return;
    campoBusca.value = '';
    aplicarFiltros();
    campoBusca.focus();
  });

  // ------------------------------------------------------------------ lista ⇄ mapa (celular)
  botaoVisao?.addEventListener('click', () => {
    const emLista = app.dataset.visao !== 'lista';
    app.dataset.visao = emLista ? 'lista' : 'mapa';
    botaoVisao.setAttribute('aria-pressed', String(emLista));
    const rotulo = botaoVisao.querySelector('[data-rotulo]');
    if (rotulo) rotulo.textContent = emLista ? 'Ver o mapa' : 'Ver em lista';
    for (const icone of botaoVisao.querySelectorAll<HTMLElement>('[data-icone]')) icone.hidden = (icone.dataset.icone === 'lista') === emLista;
    if (!emLista) {
      ctx?.mapa.resize();
      if (selecionado) rolarAte(selecionado, false);
    } else {
      lista.scrollTo({ top: 0 });
    }
  });

  function rolarAte(id: string, suave = true) {
    const item = porId.get(id);
    if (!item) return;
    ignorarRolagemAte = performance.now() + 700;
    item.el.scrollIntoView({ behavior: suave && !semMovimento.matches ? 'smooth' : 'auto', block: 'nearest', inline: 'center' });
  }

  // No carrossel do celular, o cartão que para no centro vira o lugar selecionado.
  // Só vale depois que a pessoa mexe no carrossel: ao abrir, o mapa mostra o município inteiro.
  let carrosselUsado = false;
  for (const tipo of ['pointerdown', 'touchstart', 'wheel', 'keydown'] as const) {
    lista.addEventListener(tipo, () => (carrosselUsado = true), { passive: true });
  }
  const observador = new IntersectionObserver(
    (entradas) => {
      if (!carrosselUsado || !celular.matches || app.dataset.visao === 'lista' || performance.now() < ignorarRolagemAte) return;
      const centro = entradas.filter((e) => e.isIntersecting && e.intersectionRatio >= 0.75).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      const id = (centro?.target as HTMLElement | undefined)?.dataset.lugar;
      if (id && id !== selecionado) selecionar(id, { voar: true, rolar: false });
    },
    { root: lista, threshold: [0.75, 0.95] },
  );
  for (const item of itens) observador.observe(item.el);

  // No computador, passar o mouse (ou o foco) por um cartão destaca o lugar no mapa.
  let esperaDestaque = 0;
  for (const item of itens) {
    const destacar = () => {
      if (celular.matches) return;
      clearTimeout(esperaDestaque);
      esperaDestaque = window.setTimeout(() => selecionar(item.id, { voar: true, rolar: false, balao: false }), 260);
    };
    item.cartao.addEventListener('mouseenter', destacar);
    item.cartao.addEventListener('focusin', destacar);
    item.cartao.addEventListener('mouseleave', () => clearTimeout(esperaDestaque));
  }

  // ------------------------------------------------------------------ estado (voltar ao mapa onde parou)
  const chaveEstado = `mapa:${location.pathname}`;
  function guardarEstado() {
    try {
      const camera = ctx
        ? { centro: ctx.mapa.getCenter().toArray(), zoom: ctx.mapa.getZoom(), giro: ctx.mapa.getBearing(), inclinacao: ctx.mapa.getPitch() }
        : null;
      sessionStorage.setItem(chaveEstado, JSON.stringify({ camera, selecionado, filtros: [...ativos], busca: campoBusca?.value ?? '' }));
    } catch {
      /* sem armazenamento */
    }
  }
  function lerEstado(): { camera?: { centro: [number, number]; zoom: number; giro: number; inclinacao: number } | null; selecionado?: string | null; filtros?: string[]; busca?: string } {
    try {
      return JSON.parse(sessionStorage.getItem(chaveEstado) ?? '{}');
    } catch {
      return {};
    }
  }

  const parametros = new URLSearchParams(location.search);
  const pedido = parametros.get('lugar');
  const estado = pedido ? {} : lerEstado();
  for (const id of parametros.get('categoria')?.split(',') ?? estado.filtros ?? []) {
    const botao = filtros.find((b) => b.dataset.filtro === id);
    if (botao) {
      ativos.add(id);
      botao.setAttribute('aria-pressed', 'true');
    }
  }
  if (campoBusca && estado.busca) campoBusca.value = estado.busca;

  // ------------------------------------------------------------------ mapa
  function semMapa() {
    ctx = null;
    caixaDoMapa.dataset.estado = 'erro';
    const aviso = caixaDoMapa.querySelector('.mapa__aviso p');
    if (aviso) aviso.textContent = 'Este aparelho não conseguiu abrir o mapa interativo. A lista de lugares continua disponível, com endereço e link para rotas.';
    if (celular.matches && app.dataset.visao !== 'lista') botaoVisao?.click();
    aplicarFiltros();
  }
  if (!temWebGL()) {
    semMapa();
    return;
  }

  const margem = (): maplibregl.PaddingOptions =>
    celular.matches ? { top: modo === 'rota' ? 180 : 130, bottom: 180, left: 28, right: 28 } : { top: 84, bottom: 56, left: 60, right: 76 };

  function limitesDe(pontos: [number, number][]): [[number, number], [number, number]] {
    const lons = pontos.map((p) => p[0]);
    const lats = pontos.map((p) => p[1]);
    return [[Math.min(...lons), Math.min(...lats)], [Math.max(...lons), Math.max(...lats)]];
  }

  const dadosDaRota = modo === 'rota' ? (JSON.parse(document.getElementById('dados-rota')?.textContent ?? 'null') as RotaNoMapa | null) : null;
  const limitesIniciais = dadosDaRota ? limitesDe(dadosDaRota.geometria) : limitesDe(itens.map((item) => [item.lon, item.lat]));

  try {
    ctx = criarMapa(tela, { limites: limitesIniciais, margem: margem() });
  } catch (erro) {
    console.warn('Mapa:', erro);
    semMapa();
    return;
  }
  mapa = ctx.mapa;
  if (estado.camera) {
    mapa.jumpTo({ center: estado.camera.centro, zoom: estado.camera.zoom, bearing: estado.camera.giro, pitch: 0 });
  }

  mapa.once('load', () => {
    caixaDoMapa.dataset.estado = 'pronto';
  });
  mapa.on('error', (evento) => {
    // Falhas pontuais de blocos não derrubam o mapa; só registramos.
    console.warn('Mapa:', evento.error?.message ?? evento);
  });

  // ---- roteiros desenhados
  const corDaRota = (rota: RotaNoMapa) => (document.documentElement.dataset.tema === 'escuro' || (!document.documentElement.dataset.tema && matchMedia('(prefers-color-scheme: dark)').matches) ? rota.corEscuro : rota.cor);
  if (dadosDaRota) {
    ctx.aoEstilo(() => desenharRota(mapa, dadosDaRota.id, dadosDaRota.geometria, corDaRota(dadosDaRota), { destaque: true }));
  } else {
    fetch('/dados/rotas.json')
      .then((resposta) => (resposta.ok ? (resposta.json() as Promise<RotaNoMapa[]>) : []))
      .then((rotas) => {
        if (!rotas.length) return;
        ctx!.aoEstilo(() => {
          for (const rota of rotas) desenharRota(mapa, rota.id, rota.geometria, corDaRota(rota));
        });
        for (const rota of rotas) {
          mapa.on('click', `rota-${rota.id}-contorno`, (evento) => {
            if ((evento.originalEvent.target as Element | null)?.closest('.pino, .grupo')) return;
            abrirBalaoDeRota(rota, evento.lngLat);
          });
          mapa.on('mouseenter', `rota-${rota.id}-contorno`, () => (mapa.getCanvas().style.cursor = 'pointer'));
          mapa.on('mouseleave', `rota-${rota.id}-contorno`, () => (mapa.getCanvas().style.cursor = ''));
        }
      })
      .catch(() => {
        /* sem roteiros: o mapa de lugares segue normal */
      });
  }

  // ---- marcadores
  const moldes = document.getElementById('icones-do-mapa') as HTMLTemplateElement | null;
  function icone(nome: string): Node | null {
    return moldes?.content.querySelector(`[data-icone="${nome}"] svg`)?.cloneNode(true) ?? null;
  }

  function criarPino(item: Item): HTMLButtonElement {
    const botao = document.createElement('button');
    botao.type = 'button';
    botao.tabIndex = -1;
    botao.className = item.numero ? 'pino pino--numero' : `pino pino--${item.destaque} cat-${item.categoria}`;
    botao.setAttribute('aria-label', item.numero ? `Parada ${item.numero}: ${item.nome}` : `${item.nome} (${item.tipo})`);
    if (item.numero) botao.textContent = String(item.numero);
    else {
      const svg = icone(item.icone);
      if (svg) botao.append(svg);
    }
    const nome = document.createElement('span');
    nome.className = 'pino__nome';
    nome.textContent = item.curto;
    nome.setAttribute('aria-hidden', 'true');
    botao.append(nome);
    botao.addEventListener('click', (evento) => {
      evento.stopPropagation();
      selecionar(item.id, { voar: true, rolar: true });
    });
    return botao;
  }

  function reindexar() {
    indice = new Supercluster<{ id: string }>({ radius: 46, maxZoom: 15, minPoints: 2 });
    // O lugar selecionado nunca entra em um grupo: fica sempre visível.
    indice.load(
      itens
        .filter((item) => item.visivel && item.id !== selecionado)
        .map((item) => ({ type: 'Feature' as const, properties: { id: item.id }, geometry: { type: 'Point' as const, coordinates: [item.lon, item.lat] } })),
    );
    atualizarMarcadores();
  }

  function atualizarMarcadores() {
    if (!ctx) return;
    const zoom = mapa.getZoom();
    caixaDoMapa.dataset.rotulos = modo === 'rota' || zoom >= 15.6 ? 'todos' : zoom >= 13.2 ? 'sim' : 'nao';
    const vistos = new Set<string>();

    const colocar = (chave: string, lngLat: [number, number], criar: () => HTMLElement) => {
      vistos.add(chave);
      if (marcadores.has(chave)) return;
      marcadores.set(chave, new maplibregl.Marker({ element: criar(), anchor: 'center' }).setLngLat(lngLat).addTo(mapa));
    };

    if (modo === 'rota') {
      for (const item of itens) colocar(`p:${item.id}`, [item.lon, item.lat], () => criarPino(item));
    } else {
      for (const ponto of indice.getClusters([-180, -85, 180, 85], Math.floor(zoom))) {
        const [lon, lat] = ponto.geometry.coordinates as [number, number];
        const props = ponto.properties as { cluster?: boolean; cluster_id?: number; point_count?: number; id?: string };
        if (props.cluster) {
          colocar(`g:${props.cluster_id}:${props.point_count}`, [lon, lat], () => {
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.tabIndex = -1;
            botao.className = 'grupo';
            botao.textContent = String(props.point_count);
            botao.setAttribute('aria-label', `${props.point_count} lugares nesta área: aproximar`);
            botao.addEventListener('click', (evento) => {
              evento.stopPropagation();
              mapa.easeTo({ center: [lon, lat], zoom: Math.min(indice.getClusterExpansionZoom(props.cluster_id!) + 0.4, 17), duration: 600 });
            });
            return botao;
          });
        } else {
          const item = porId.get(props.id!)!;
          colocar(`p:${item.id}`, [lon, lat], () => criarPino(item));
        }
      }
      const atual = selecionado ? porId.get(selecionado) : null;
      if (atual?.visivel) colocar(`p:${atual.id}`, [atual.lon, atual.lat], () => criarPino(atual));
    }

    for (const [chave, marcador] of marcadores) {
      if (!vistos.has(chave)) {
        marcador.remove();
        marcadores.delete(chave);
      }
    }
    for (const [chave, marcador] of marcadores) {
      if (!chave.startsWith('p:')) continue;
      const atual = chave === `p:${selecionado}`;
      const el = marcador.getElement();
      if (atual) el.setAttribute('aria-current', 'true');
      else el.removeAttribute('aria-current');
      el.style.zIndex = atual ? '3' : '';
    }
  }

  let quadro = 0;
  mapa.on('move', () => {
    cancelAnimationFrame(quadro);
    quadro = requestAnimationFrame(atualizarMarcadores);
  });
  mapa.on('moveend', guardarEstado);

  // ---- balão (computador)
  function fecharBalao() {
    balao?.remove();
    balao = null;
  }

  function elemento<K extends keyof HTMLElementTagNameMap>(tag: K, classe?: string, texto?: string): HTMLElementTagNameMap[K] {
    const el = document.createElement(tag);
    if (classe) el.className = classe;
    if (texto) el.textContent = texto;
    return el;
  }

  function abrirBalao(item: Item) {
    fecharBalao();
    const caixa = elemento('div', `cat-${item.categoria}`);
    if (item.foto) {
      const foto = elemento('img', 'balao__foto');
      foto.src = item.foto;
      foto.alt = '';
      foto.width = 640;
      foto.height = 360;
      caixa.append(foto);
    }
    const corpo = elemento('div', 'balao__corpo');
    const tipo = elemento('span', 'balao__tipo');
    const svg = icone(item.icone);
    if (svg) {
      (svg as SVGElement).setAttribute('width', '14');
      (svg as SVGElement).setAttribute('height', '14');
      tipo.append(svg);
    }
    tipo.append(`${item.tipo} · ${item.onde}`);
    const acoes = elemento('div', 'balao__acoes');
    const detalhes = elemento('a', 'botao botao--p', 'Ver detalhes');
    detalhes.href = item.url;
    const chegar = elemento('a', 'botao botao--p botao--claro', 'Como chegar');
    chegar.href = item.comoChegar;
    chegar.target = '_blank';
    chegar.rel = 'noopener';
    acoes.append(detalhes, chegar);
    corpo.append(tipo, elemento('h2', 'balao__nome', item.nome), elemento('p', 'balao__resumo', item.resumo), acoes);
    caixa.append(corpo);

    balao = new maplibregl.Popup({ className: 'balao', offset: 26, closeButton: true, focusAfterOpen: false, maxWidth: 'none' })
      .setLngLat([item.lon, item.lat])
      .setDOMContent(caixa)
      .addTo(mapa);
    balao.on('close', () => {
      balao = null;
    });
  }

  function abrirBalaoDeRota(rota: RotaNoMapa, lngLat: maplibregl.LngLat) {
    fecharBalao();
    const caixa = elemento('div');
    const corpo = elemento('div', 'balao__corpo');
    const acoes = elemento('div', 'balao__acoes');
    const ver = elemento('a', 'botao botao--p', 'Ver o roteiro');
    ver.href = rota.url;
    acoes.append(ver);
    corpo.append(elemento('span', 'balao__tipo', 'Roteiro'), elemento('h2', 'balao__nome', rota.nome), acoes);
    caixa.append(corpo);
    balao = new maplibregl.Popup({ className: 'balao', offset: 12, focusAfterOpen: false, maxWidth: 'none' }).setLngLat(lngLat).setDOMContent(caixa).addTo(mapa);
  }

  // ---- seleção
  function selecionar(id: string | null, opcoes: { voar?: boolean; rolar?: boolean; balao?: boolean } = {}) {
    const anterior = selecionado;
    selecionado = id;
    for (const item of itens) {
      if (item.id === id) item.cartao.setAttribute('aria-current', 'true');
      else item.cartao.removeAttribute('aria-current');
    }
    if (anterior !== id) reindexar();
    else atualizarMarcadores();
    fecharBalao();
    const item = id ? porId.get(id) : null;
    if (!item) {
      guardarEstado();
      return;
    }
    if (opcoes.rolar) rolarAte(item.id);
    if (!ctx) return;
    if (opcoes.voar) {
      const dentro = mapa.getBounds().contains([item.lon, item.lat]);
      const zoom = Math.max(mapa.getZoom(), modo === 'rota' ? 14.5 : 15.2);
      const destino = { center: [item.lon, item.lat] as [number, number], zoom, padding: margem() };
      if (semMovimento.matches) mapa.jumpTo(destino);
      else if (dentro && Math.abs(zoom - mapa.getZoom()) < 0.6) mapa.easeTo({ ...destino, duration: 500 });
      else mapa.flyTo({ ...destino, duration: 1100, essential: false });
    }
    if (!celular.matches && opcoes.balao !== false) abrirBalao(item);
    guardarEstado();
  }

  mapa.on('click', (evento) => {
    if ((evento.originalEvent.target as Element | null)?.closest('.pino, .grupo, .maplibregl-popup')) return;
    if (selecionado && !balao) return;
    selecionar(null);
  });

  function enquadrarVisiveis() {
    const visiveis = itens.filter((item) => item.visivel);
    if (!ctx || !visiveis.length) return;
    if (visiveis.length === 1) {
      mapa.easeTo({ center: [visiveis[0].lon, visiveis[0].lat], zoom: Math.max(mapa.getZoom(), 15), padding: margem(), duration: 700 });
      return;
    }
    mapa.fitBounds(limitesDe(visiveis.map((item) => [item.lon, item.lat])), { padding: margem(), maxZoom: 15.5, duration: semMovimento.matches ? 0 : 800 });
  }

  // ---- distância até cada lugar ("onde estou")
  function mostrarDistancias(coords: GeolocationCoordinates) {
    posicao = [coords.longitude, coords.latitude];
    for (const item of itens) {
      const alvo = item.el.querySelector<HTMLElement>('[data-distancia]');
      if (!alvo) continue;
      const metros = distanciaM(posicao, [item.lon, item.lat]);
      alvo.textContent = `a ${formatarDistancia(metros)} de você`;
      alvo.hidden = false;
      item.el.dataset.metros = String(Math.round(metros));
    }
    if (botaoOrdenar) botaoOrdenar.hidden = modo === 'rota';
  }
  ctx.localizador?.on('geolocate', (evento) => mostrarDistancias((evento as unknown as GeolocationPosition).coords));
  ctx.localizador?.on('outofmaxbounds', (evento) => mostrarDistancias((evento as unknown as GeolocationPosition).coords));
  botaoOrdenar?.addEventListener('click', () => {
    if (!posicao) return;
    const ordenados = [...itens].sort((a, b) => Number(a.el.dataset.metros) - Number(b.el.dataset.metros));
    for (const item of ordenados) lista.append(item.el);
    botaoOrdenar.hidden = true;
    lista.scrollTo({ top: 0, left: 0 });
  });

  celular.addEventListener('change', () => {
    app.dataset.visao = 'mapa';
    fecharBalao();
    mapa.resize();
  });

  // ---- início
  aplicarFiltros();
  const inicial = pedido && porId.has(pedido) ? pedido : estado.selecionado && porId.has(estado.selecionado) ? estado.selecionado : null;
  if (inicial) {
    mapa.once('load', () => selecionar(inicial, { voar: Boolean(pedido), rolar: true }));
    if (pedido) mapa.jumpTo({ center: [porId.get(inicial)!.lon, porId.get(inicial)!.lat], zoom: 15.6 });
  }
}
