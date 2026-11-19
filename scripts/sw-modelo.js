// Mostraqui Capinzal — modo sem internet.
// Este arquivo é um modelo: scripts/pos-build.mjs preenche a versão e as listas e grava dist/sw.js.
//
// Estratégia:
//  - páginas: tenta a rede (para ter o conteúdo mais novo) e, sem sinal, usa a cópia guardada;
//  - arquivos com nome versionado (/assets/): cópia guardada primeiro, nunca mudam;
//  - mapa (.pmtiles): o arquivo inteiro fica guardado e os pedidos de "fatias" (Range) são atendidos
//    a partir dele, porque o navegador não guarda respostas parciais.
const VERSAO = '__VERSAO__';
const ESSENCIAIS = __ESSENCIAIS__;
const MAPAS = __MAPAS__; // [{ url, versao }]

const CACHE_APP = `app-${VERSAO}`;
const CACHE_VISITADOS = 'visitados-v1';
const cacheDoMapa = (mapa) => `mapa-${mapa.versao}`;
const CACHES_ATUAIS = new Set([CACHE_APP, CACHE_VISITADOS, ...MAPAS.map(cacheDoMapa)]);

self.addEventListener('install', (evento) => {
  evento.waitUntil(
    caches
      .open(CACHE_APP)
      // "reload" ignora o cache HTTP: a instalação sempre guarda os arquivos desta versão.
      .then((cache) => cache.addAll(ESSENCIAIS.map((url) => new Request(url, { cache: 'reload' }))))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (evento) => {
  evento.waitUntil(
    (async () => {
      for (const nome of await caches.keys()) {
        if (!CACHES_ATUAIS.has(nome)) await caches.delete(nome);
      }
      await self.clients.claim();
      // O mapa é baixado depois, sem atrasar a abertura do site.
      await guardarMapas();
    })(),
  );
});

let baixandoMapas = null;
/** Guarda os arquivos inteiros do mapa (uma tentativa por vez). */
function guardarMapas() {
  baixandoMapas ??= (async () => {
    for (const mapa of MAPAS) {
      try {
        const cache = await caches.open(cacheDoMapa(mapa));
        if (await cache.match(mapa.url)) continue;
        const resposta = await fetch(mapa.url, { cache: 'no-store' });
        if (resposta.ok && resposta.status === 200) await cache.put(mapa.url, resposta);
      } catch {
        // Sem conexão agora: tenta de novo na próxima visita.
      }
    }
  })().finally(() => {
    baixandoMapas = null;
  });
  return baixandoMapas;
}

/** Atende um pedido de fatia (Range) do mapa a partir do arquivo inteiro guardado. */
async function fatiaDoMapa(evento, mapa) {
  const pedido = evento.request;
  const cache = await caches.open(cacheDoMapa(mapa));
  const inteiro = await cache.match(mapa.url);
  const faixa = /^bytes=(\d+)-(\d*)$/.exec(pedido.headers.get('range') ?? '');
  if (!inteiro) {
    // Ainda não guardado: atende pela rede e guarda o arquivo inteiro em segundo plano.
    evento.waitUntil(guardarMapas());
    return fetch(pedido);
  }
  if (!faixa) return inteiro;
  const dados = await inteiro.blob();
  const inicio = Number(faixa[1]);
  const fim = Math.min(faixa[2] ? Number(faixa[2]) : dados.size - 1, dados.size - 1);
  if (inicio >= dados.size) return new Response(null, { status: 416, headers: { 'Content-Range': `bytes */${dados.size}` } });
  return new Response(dados.slice(inicio, fim + 1), {
    status: 206,
    headers: {
      'Content-Type': 'application/octet-stream',
      'Content-Range': `bytes ${inicio}-${fim}/${dados.size}`,
      'Content-Length': String(fim - inicio + 1),
      'Accept-Ranges': 'bytes',
    },
  });
}

/** Páginas: rede primeiro; se a rede falhar ou demorar mais de 3,5 s, usa a cópia guardada. */
async function paginaDaRedeOuGuardada(pedido) {
  const semBusca = new URL(pedido.url);
  semBusca.search = '';
  const guardada = async () => (await caches.match(semBusca.href)) ?? (await caches.match(pedido, { ignoreSearch: true }));
  const daRede = fetch(pedido).then(async (resposta) => {
    if (resposta.ok) (await caches.open(CACHE_VISITADOS)).put(semBusca.href, resposta.clone());
    return resposta;
  });
  const prazo = new Promise((resolver) => setTimeout(resolver, 3500, null));
  try {
    return (await Promise.race([daRede, prazo])) ?? (await guardada()) ?? (await daRede);
  } catch {
    return (
      (await guardada()) ??
      (await caches.match('/offline/')) ??
      new Response('Sem conexão.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } })
    );
  }
}

async function guardadoOuRede(pedido, nomeDoCache) {
  const guardado = await caches.match(pedido);
  if (guardado) return guardado;
  try {
    const resposta = await fetch(pedido);
    if (resposta.ok && resposta.status === 200) {
      const cache = await caches.open(nomeDoCache);
      cache.put(pedido, resposta.clone());
    }
    return resposta;
  } catch (erro) {
    // Sem internet e sem esta versão da foto: usa outra versão da mesma foto que já esteja guardada.
    if (pedido.destination === 'image') {
      const substituta = await outraVersaoDaFoto(new URL(pedido.url).pathname);
      if (substituta) return substituta;
    }
    throw erro;
  }
}

/** As versões de uma foto têm o mesmo começo no nome: "ponte.AbC123_" + variante + extensão. */
async function outraVersaoDaFoto(caminho) {
  const prefixo = caminho.slice(0, caminho.lastIndexOf('_') + 1);
  if (!prefixo.startsWith('/assets/') || prefixo === '/assets/') return null;
  let melhor = null;
  for (const nome of [CACHE_VISITADOS, CACHE_APP]) {
    const cache = await caches.open(nome);
    for (const chave of await cache.keys()) {
      if (!new URL(chave.url).pathname.startsWith(prefixo)) continue;
      const dados = await (await cache.match(chave)).blob();
      if (!melhor || dados.size > melhor.size) melhor = dados;
    }
  }
  return melhor ? new Response(melhor, { headers: { 'Content-Type': melhor.type || 'image/webp' } }) : null;
}

async function guardadoEAtualiza(pedido) {
  const cache = await caches.open(CACHE_VISITADOS);
  const guardado = await caches.match(pedido);
  const daRede = fetch(pedido)
    .then((resposta) => {
      if (resposta.ok && resposta.status === 200) cache.put(pedido, resposta.clone());
      return resposta;
    })
    .catch(() => null);
  return guardado ?? (await daRede) ?? new Response(null, { status: 504 });
}

self.addEventListener('fetch', (evento) => {
  const pedido = evento.request;
  if (pedido.method !== 'GET') return;
  const url = new URL(pedido.url);
  if (url.origin !== self.location.origin) return;

  const mapa = MAPAS.find((m) => m.url === url.pathname);
  if (mapa) {
    evento.respondWith(fatiaDoMapa(evento, mapa));
    return;
  }
  if (pedido.mode === 'navigate') {
    evento.respondWith(paginaDaRedeOuGuardada(pedido));
    return;
  }
  if (url.pathname.startsWith('/assets/')) {
    evento.respondWith(guardadoOuRede(pedido, CACHE_VISITADOS));
    return;
  }
  evento.respondWith(guardadoEAtualiza(pedido));
});
