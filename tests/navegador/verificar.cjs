// Verificação no navegador (Playwright + axe-core): mapa, lista, filtros, teclado, celular, tema escuro,
// acessibilidade (WCAG 2.x A e AA), política de segurança (CSP) e modo sem internet.
//
// Uso: npm run build && node tests/navegador/verificar.cjs
// O script sobe um servidor local sobre dist/ com os mesmos cabeçalhos de segurança de public/.htaccess.
// Exige os pacotes "playwright" (com o Chromium instalado) e "axe-core".
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { chromium, devices } = require('playwright');

const RAIZ = path.resolve(__dirname, '../..');
const DIST = path.join(RAIZ, 'dist');
const AXE = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const CAPTURAS = path.join(__dirname, 'capturas');

// ---------------------------------------------------------------- servidor local com os cabeçalhos do .htaccess
const htaccess = fs.readFileSync(path.join(RAIZ, 'public/.htaccess'), 'utf8');
const cabecalhos = {};
for (const m of htaccess.matchAll(/^\s*Header always set (\S+) "(.+)"\s*$/gm)) cabecalhos[m[1]] = m[2];
if (!cabecalhos['Content-Security-Policy']) throw new Error('Não encontrei a Content-Security-Policy em public/.htaccess.');
delete cabecalhos['Strict-Transport-Security'];

const TIPOS = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8', '.geojson': 'application/geo+json; charset=utf-8', '.webmanifest': 'application/manifest+json; charset=utf-8',
  '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.avif': 'image/avif', '.woff2': 'font/woff2',
  '.pbf': 'application/x-protobuf', '.pmtiles': 'application/octet-stream', '.xml': 'application/xml', '.txt': 'text/plain; charset=utf-8',
};

function servidor() {
  return http.createServer((req, res) => {
    let caminho = decodeURIComponent(new URL(req.url, 'http://x').pathname);
    if (caminho.split('/').some((parte) => parte.startsWith('.') && parte !== '.well-known')) {
      res.writeHead(403, cabecalhos).end('Proibido');
      return;
    }
    let arquivo = path.join(DIST, caminho);
    if (!arquivo.startsWith(DIST)) return void res.writeHead(403).end();
    if (fs.existsSync(arquivo) && fs.statSync(arquivo).isDirectory()) {
      if (!caminho.endsWith('/')) return void res.writeHead(301, { Location: `${caminho}/` }).end();
      arquivo = path.join(arquivo, 'index.html');
    }
    let status = 200;
    if (!fs.existsSync(arquivo)) {
      arquivo = path.join(DIST, '404.html');
      status = 404;
    }
    const tamanho = fs.statSync(arquivo).size;
    const base = { ...cabecalhos, 'Content-Type': TIPOS[path.extname(arquivo)] ?? 'application/octet-stream', 'Cache-Control': 'no-cache', 'Accept-Ranges': 'bytes' };
    const faixa = /^bytes=(\d+)-(\d*)$/.exec(req.headers.range ?? '');
    if (faixa && status === 200) {
      const inicio = Number(faixa[1]);
      const fim = Math.min(faixa[2] ? Number(faixa[2]) : tamanho - 1, tamanho - 1);
      res.writeHead(206, { ...base, 'Content-Range': `bytes ${inicio}-${fim}/${tamanho}`, 'Content-Length': fim - inicio + 1 });
      fs.createReadStream(arquivo, { start: inicio, end: fim }).pipe(res);
      return;
    }
    res.writeHead(status, { ...base, 'Content-Length': tamanho });
    fs.createReadStream(arquivo).pipe(res);
  });
}

// ---------------------------------------------------------------- utilidades de teste
const resultados = [];
function registrar(ok, nome, detalhe = '') {
  resultados.push({ ok, nome, detalhe });
  console.log(`${ok ? '  ok  ' : ' FALHA'} · ${nome}${detalhe ? ` — ${detalhe}` : ''}`);
}
async function conferir(nome, fn) {
  try {
    const detalhe = await fn();
    registrar(true, nome, typeof detalhe === 'string' ? detalhe : '');
  } catch (erro) {
    registrar(false, nome, String(erro.message ?? erro).split('\n')[0].slice(0, 260));
  }
}
function exigir(condicao, mensagem) {
  if (!condicao) throw new Error(mensagem);
}

(async () => {
  if (!fs.existsSync(path.join(DIST, 'sw.js'))) throw new Error('Monte o site antes: npm run build');
  fs.mkdirSync(CAPTURAS, { recursive: true });
  const http_ = servidor();
  await new Promise((r) => http_.listen(0, '127.0.0.1', r));
  const base = `http://127.0.0.1:${http_.address().port}`;
  const browser = await chromium.launch({ args: ['--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'] });

  const problemasDeConsole = [];
  const pedidosExternos = new Set();
  function vigiar(page, rotulo) {
    page.on('console', (m) => {
      const texto = m.text();
      if (/GPU stall|GL Driver Message/.test(texto)) return;
      // A página de erro 404 é testada de propósito: o aviso do navegador sobre ela não conta.
      if (/status of 404/.test(texto) && m.location().url.includes('/pagina-que-nao-existe/')) return;
      if (m.type() === 'error' || /Content Security Policy|Refused to/i.test(texto)) problemasDeConsole.push(`${rotulo}: ${texto.slice(0, 160)} [${m.location().url.slice(-60)}]`);
    });
    page.on('pageerror', (e) => problemasDeConsole.push(`${rotulo}: ${String(e).slice(0, 200)}`));
    page.on('request', (r) => {
      const url = r.url();
      if (!url.startsWith(base) && !url.startsWith('data:') && !url.startsWith('blob:')) pedidosExternos.add(new URL(url).origin);
    });
  }
  const mapaPronto = (page) => page.waitForSelector('.mapa[data-estado="pronto"]', { timeout: 45000 });
  async function axe(page, rotulo) {
    await page.evaluate(AXE);
    const r = await page.evaluate(() => window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } }));
    const resumo = r.violations.map((v) => `${v.id} (${v.nodes.length}): ${v.nodes[0].target.join(' ')}`);
    exigir(resumo.length === 0, `${rotulo}: ${resumo.join(' | ')}`);
    return `${r.passes.length} regras aprovadas`;
  }

  const dados = { lugares: fs.readdirSync(path.join(RAIZ, 'src/data/lugares')).length, rotas: fs.readdirSync(path.join(RAIZ, 'src/data/rotas')).length };

  // ================================================================ computador
  console.log('\nComputador (1366×768)');
  const pc = await browser.newContext({ viewport: { width: 1366, height: 768 }, serviceWorkers: 'block' });
  const p = await pc.newPage();
  vigiar(p, 'computador');

  await p.goto(`${base}/`);
  await conferir('a página inicial abre o mapa', async () => {
    await mapaPronto(p);
    exigir(await p.locator('.maplibregl-canvas').isVisible(), 'o desenho do mapa não apareceu');
  });
  await conferir('a lista mostra todos os lugares', async () => {
    const n = await p.locator('[data-lista] > li:not([hidden])').count();
    exigir(n === dados.lugares, `esperados ${dados.lugares}, visíveis ${n}`);
    return `${n} cartões`;
  });
  await conferir('o mapa mostra marcadores e grupos', async () => {
    await p.waitForSelector('.pino, .grupo', { timeout: 15000 });
    const n = await p.locator('.pino, .grupo').count();
    exigir(n >= 3, `só ${n} marcadores`);
    return `${n} marcadores na vista inicial`;
  });
  await conferir('os roteiros aparecem na legenda do mapa', async () => {
    const n = await p.locator('.legenda-rotas a').count();
    exigir(n === dados.rotas, `esperados ${dados.rotas}, encontrados ${n}`);
  });
  await conferir('o filtro por categoria reduz a lista e avisa a contagem', async () => {
    await p.click('[data-filtro="fe"]');
    const n = await p.locator('[data-lista] > li:not([hidden])').count();
    exigir(n > 0 && n < dados.lugares, `visíveis depois do filtro: ${n}`);
    const texto = await p.locator('[data-contagem]').innerText();
    exigir(texto.includes(String(n)), `contagem anunciada: "${texto}"`);
    await p.click('[data-filtro="fe"]');
    return texto;
  });
  await conferir('a busca ignora acentos ("pensil" encontra a Ponte Pênsil)', async () => {
    await p.fill('[data-busca]', 'pensil');
    await p.waitForTimeout(250);
    const nomes = await p.locator('[data-lista] > li:not([hidden]) .cartao__nome').allInnerTexts();
    exigir(nomes.length >= 1 && nomes.some((n) => /Pênsil/.test(n)), `resultado: ${nomes.join(', ')}`);
    await p.fill('[data-busca]', 'zzzzqq');
    await p.waitForTimeout(250);
    exigir(await p.locator('[data-vazio]').isVisible(), 'o aviso de "nenhum lugar" não apareceu');
    await p.click('[data-limpar]');
  });
  await conferir('clicar em um marcador abre a ficha com foto, resumo e links', async () => {
    await p.goto(`${base}/?lugar=igreja-matriz`);
    await mapaPronto(p);
    await p.waitForSelector('.balao .balao__nome', { timeout: 15000 });
    exigir((await p.locator('.balao__nome').innerText()).includes('Igreja Matriz'), 'ficha de outro lugar');
    exigir((await p.locator('.balao a[href="/lugares/igreja-matriz/"]').count()) === 1, 'sem link para a página do lugar');
    exigir((await p.locator('.balao a[href*="google.com/maps"]').count()) === 1, 'sem link "Como chegar"');
    await p.screenshot({ path: path.join(CAPTURAS, 'inicio-computador.png') });
  });
  await conferir('acessibilidade (axe) na página inicial', () => axe(p, 'início'));

  await conferir('teclado: o primeiro Tab leva ao link "Pular para o conteúdo"', async () => {
    await p.goto(`${base}/lugares/`);
    await p.keyboard.press('Tab');
    const foco = await p.evaluate(() => ({ texto: document.activeElement.textContent.trim(), visivel: document.activeElement.getBoundingClientRect().top >= 0 }));
    exigir(foco.texto === 'Pular para o conteúdo' && foco.visivel, `foco em "${foco.texto}"`);
    await p.keyboard.press('Enter');
    exigir(await p.evaluate(() => location.hash === '#conteudo'), 'o link não levou ao conteúdo');
  });
  await conferir('lista de lugares: todas as categorias e todos os cartões', async () => {
    const n = await p.locator('.grade-cartoes > li').count();
    exigir(n === dados.lugares, `esperados ${dados.lugares}, encontrados ${n}`);
    return axe(p, 'lugares');
  });

  await conferir('página de lugar: título, endereço, fontes e "como chegar"', async () => {
    await p.goto(`${base}/lugares/ponte-pensil/`);
    exigir((await p.locator('h1').innerText()).includes('Ponte Pênsil'), 'título errado');
    exigir((await p.locator('.fontes ol li').count()) >= 3, 'faltam fontes');
    exigir((await p.locator('.lugar__acoes a[href*="google.com/maps/dir"]').count()) === 1, 'sem "Como chegar"');
    exigir((await p.locator('script[type="application/ld+json"]').count()) === 1, 'sem dados estruturados');
    const ld = JSON.parse(await p.locator('script[type="application/ld+json"]').innerText());
    exigir(ld.name.includes('Ponte Pênsil') && ld.geo, 'dados estruturados incompletos');
    await p.locator('[data-mini-mapa]').scrollIntoViewIfNeeded();
    await p.waitForSelector('[data-mini-mapa] .maplibregl-canvas', { timeout: 30000 });
    return axe(p, 'lugar');
  });
  await conferir('estabelecimento "a confirmar" mostra o aviso', async () => {
    await p.goto(`${base}/lugares/corujao-lanches/`);
    exigir(await p.getByText('Funcionamento a confirmar').first().isVisible(), 'aviso ausente');
    exigir(await p.getByText('Aproximada').first().isVisible(), 'posição aproximada não informada');
  });

  await conferir('página de roteiro: linha no mapa, paradas numeradas e perfil de altitude', async () => {
    await p.goto(`${base}/rotas/centro-a-pe/`);
    await mapaPronto(p);
    const paradas = await p.locator('[data-lista] > li').count();
    await p.waitForFunction((n) => document.querySelectorAll('.pino--numero').length === n, paradas, { timeout: 15000 });
    exigir(await p.locator('.perfil svg').isVisible(), 'perfil de altitude ausente');
    exigir((await p.locator('.rota-acoes a[href*="google.com/maps/dir"]').count()) === 1, 'sem link para abrir o roteiro');
    const camadas = await p.evaluate(() => !!document.querySelector('.maplibregl-canvas'));
    exigir(camadas, 'mapa do roteiro não desenhou');
    await p.screenshot({ path: path.join(CAPTURAS, 'roteiro-computador.png') });
    return axe(p, 'roteiro');
  });
  for (const [caminho, rotulo] of [['/rotas/', 'roteiros'], ['/capinzal/', 'cidade'], ['/sobre/', 'sobre'], ['/pagina-que-nao-existe/', 'erro 404']]) {
    await conferir(`acessibilidade (axe): ${rotulo}`, async () => {
      const resposta = await p.goto(`${base}${caminho}`);
      if (rotulo === 'erro 404') exigir(resposta.status() === 404, `status ${resposta.status()}`);
      exigir((await p.locator('h1').count()) === 1, 'a página precisa de exatamente um h1');
      return axe(p, rotulo);
    });
  }
  await conferir('arquivos ocultos não são servidos', async () => {
    const r = await p.request.get(`${base}/.htaccess`);
    exigir(r.status() === 403, `status ${r.status()}`);
  });
  await pc.close();

  // ================================================================ tema escuro
  console.log('\nTema escuro');
  const escuro = await browser.newContext({ viewport: { width: 1366, height: 768 }, colorScheme: 'dark', serviceWorkers: 'block' });
  const pe = await escuro.newPage();
  vigiar(pe, 'escuro');
  await conferir('o mapa usa o estilo escuro e a página passa no axe', async () => {
    await pe.goto(`${base}/`);
    await mapaPronto(pe);
    const fundo = await pe.evaluate(() => getComputedStyle(document.body).backgroundColor);
    exigir(fundo === 'rgb(14, 21, 19)', `fundo: ${fundo}`);
    await pe.screenshot({ path: path.join(CAPTURAS, 'inicio-escuro.png') });
    return axe(pe, 'início escuro');
  });
  await conferir('o botão de tema troca para o claro e guarda a escolha', async () => {
    await pe.click('[data-alternar-tema]');
    exigir((await pe.evaluate(() => document.documentElement.dataset.tema)) === 'claro', 'tema não mudou');
    await pe.goto(`${base}/lugares/igreja-matriz/`);
    exigir((await pe.evaluate(() => document.documentElement.dataset.tema)) === 'claro', 'escolha não foi lembrada');
    await pe.click('[data-alternar-tema]');
    await pe.waitForTimeout(400); // espera a transição de cores terminar antes de medir o contraste
    return axe(pe, 'lugar escuro');
  });
  await escuro.close();

  // ================================================================ celular
  console.log('\nCelular (Pixel 7)');
  const cel = await browser.newContext({ ...devices['Pixel 7'], serviceWorkers: 'block' });
  const pm = await cel.newPage();
  vigiar(pm, 'celular');
  await conferir('mapa em tela cheia, com carrossel de cartões e barra de abas', async () => {
    await pm.goto(`${base}/`);
    await mapaPronto(pm);
    exigir(await pm.locator('.abas').isVisible(), 'barra de abas ausente');
    const caixa = await pm.locator('[data-lista] > li').first().boundingBox();
    const tela = pm.viewportSize();
    exigir(caixa && caixa.y > tela.height * 0.55, 'o carrossel deveria ficar na parte de baixo');
    const largura = await pm.evaluate(() => document.documentElement.scrollWidth);
    exigir(largura <= tela.width, `a página rola para o lado (${largura}px)`);
    await pm.screenshot({ path: path.join(CAPTURAS, 'inicio-celular.png') });
  });
  await conferir('"Ver em lista" mostra todos os lugares e volta ao mapa', async () => {
    await pm.click('[data-alternar-visao]');
    exigir((await pm.locator('[data-app]').getAttribute('data-visao')) === 'lista', 'não entrou na lista');
    exigir(await pm.locator('[data-contagem]').isVisible(), 'contagem ausente');
    await pm.screenshot({ path: path.join(CAPTURAS, 'lista-celular.png') });
    const resultado = await axe(pm, 'lista no celular');
    await pm.click('[data-alternar-visao]');
    exigir((await pm.locator('[data-app]').getAttribute('data-visao')) === 'mapa', 'não voltou ao mapa');
    return resultado;
  });
  await conferir('alvos de toque com pelo menos 24×24 px (WCAG 2.5.8)', async () => {
    const pequenos = await pm.evaluate(() =>
      [...document.querySelectorAll('a, button, input')]
        .filter((el) => el.offsetParent !== null && !el.closest('.maplibregl-ctrl-attrib'))
        // Cartões: o link do título cobre o cartão inteiro por meio de ::after, então a área real é a do cartão.
        .filter((el) => getComputedStyle(el, '::after').position !== 'absolute')
        // Links dentro de frases são exceção prevista no critério 2.5.8 (alvo "em linha").
        .filter((el) => !(el.tagName === 'A' && el.closest('p, figcaption, .fontes, .prosa li')))
        .map((el) => ({ el, r: el.getBoundingClientRect() }))
        .filter(({ r }) => r.width > 0 && (r.width < 24 || r.height < 24))
        .map(({ el, r }) => `${el.tagName.toLowerCase()}.${el.className} ${Math.round(r.width)}×${Math.round(r.height)}`),
    );
    exigir(pequenos.length === 0, pequenos.slice(0, 4).join(' | '));
  });
  await conferir('página de lugar e de roteiro no celular, sem rolagem lateral', async () => {
    for (const caminho of ['/lugares/igreja-matriz/', '/capinzal/', '/sobre/']) {
      await pm.goto(`${base}${caminho}`);
      const largura = await pm.evaluate(() => document.documentElement.scrollWidth);
      exigir(largura <= pm.viewportSize().width, `${caminho} rola para o lado (${largura}px)`);
    }
    await pm.goto(`${base}/rotas/duas-pontes/`);
    await mapaPronto(pm);
    await pm.screenshot({ path: path.join(CAPTURAS, 'roteiro-celular.png') });
    return axe(pm, 'roteiro no celular');
  });
  await cel.close();

  // ================================================================ modo sem internet
  console.log('\nModo sem internet');
  const off = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const po = await off.newPage();
  vigiar(po, 'offline');
  await conferir('o service worker guarda o site e os dois arquivos de mapa', async () => {
    await po.goto(`${base}/`);
    const estado = await po.evaluate(async () => {
      await navigator.serviceWorker.ready;
      for (let i = 0; i < 120; i++) {
        const nomes = (await caches.keys()).filter((n) => n.startsWith('mapa-'));
        let prontos = 0;
        for (const n of nomes) prontos += (await (await caches.open(n)).keys()).length;
        if (prontos === 2) break;
        await new Promise((r) => setTimeout(r, 500));
      }
      const saida = {};
      for (const n of await caches.keys()) saida[n.replace(/-[0-9a-f]{12}$/, '')] = (saida[n.replace(/-[0-9a-f]{12}$/, '')] ?? 0) + (await (await caches.open(n)).keys()).length;
      return saida;
    });
    exigir(estado.app >= 40 && estado.mapa === 2, JSON.stringify(estado));
    return JSON.stringify(estado);
  });
  await conferir('sem internet: páginas, mapa e roteiro abrem; página nunca vista mostra o aviso', async () => {
    await off.setOffline(true);
    await po.goto(`${base}/`);
    await mapaPronto(po);
    await po.goto(`${base}/lugares/museu-vitor-almeida/`);
    exigir((await po.locator('h1').innerText()).includes('Museu'), 'a página do lugar não abriu');
    await po.goto(`${base}/rotas/interior/`);
    await mapaPronto(po);
    await po.goto(`${base}/caminho-nunca-visitado/`);
    exigir((await po.title()).startsWith('Sem internet'), `título: ${await po.title()}`);
    await off.setOffline(false);
  });
  await off.close();

  // ================================================================ segurança e privacidade
  console.log('\nSegurança e privacidade');
  registrar(problemasDeConsole.length === 0, 'nenhum erro de console nem bloqueio da política de segurança (CSP)', [...new Set(problemasDeConsole)].slice(0, 3).join(' | '));
  registrar(pedidosExternos.size === 0, 'nenhum pedido a servidores de terceiros', [...pedidosExternos].join(', '));

  await browser.close();
  http_.close();
  const falhas = resultados.filter((r) => !r.ok);
  console.log(`\n${resultados.length - falhas.length} de ${resultados.length} verificações aprovadas.`);
  process.exit(falhas.length ? 1 : 0);
})().catch((erro) => {
  console.error(erro);
  process.exit(1);
});
