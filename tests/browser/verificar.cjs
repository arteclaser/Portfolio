/*
 * Verificação no navegador (critério de aceite 9): celular, teclado e acessibilidade.
 *
 * Requisitos: Node 18+, playwright e axe-core instalados (npm i -D playwright axe-core).
 * Uso:
 *   BASE_URL=http://127.0.0.1:8000 PAINEL_EMAIL=... PAINEL_SENHA=... node tests/browser/verificar.cjs
 *   No modo portfólio único (padrão), o portfólio é o próprio BASE_URL. Com vários portfólios,
 *   SITE_URL=http://127.0.0.1:8000/capinzal define o portfólio; sem ele, usa o primeiro listado na plataforma.
 * Requer conteúdo publicado com galeria (ex.: php artisan mostraqui:demo em ambiente de teste).
 */
const { chromium } = require('playwright');
const fs = require('fs');

const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const AXE = fs.readFileSync(process.env.AXE_PATH || require.resolve('axe-core/axe.min.js'), 'utf8');
let failures = 0;
const ok = (cond, msg) => { console.log((cond ? '  ok   ' : '  FALHA ') + msg); if (!cond) failures++; };

async function axe(page, label) {
  await page.addScriptTag({ content: AXE });
  const result = await page.evaluate(async () => {
    const r = await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
    return r.violations.map(v => `${v.id} (${v.impact}): ${v.nodes.length} elemento(s) · ${v.nodes.slice(0, 2).map(n => n.target.join(' ')).join(' | ')}`);
  });
  ok(result.length === 0, `axe WCAG A/AA sem violações em ${label}` + (result.length ? '\n         ' + result.join('\n         ') : ''));
}

(async () => {
  const browser = await chromium.launch();
  // A raiz mostra a lista de portfólios (vários portfólios) ou o próprio portfólio (modo único).
  const rootHtml = await (await fetch(BASE + '/')).text();
  const listed = (rootHtml.match(/class="card__title"><a href="([^"]+)"/) || [])[1];
  const multi = (await fetch(BASE + '/acoes')).status === 404;
  let SITE = process.env.SITE_URL;
  if (!SITE) {
    if (multi && !listed) { console.error('Nenhum portfólio listado em ' + BASE); process.exit(1); }
    SITE = multi ? (listed.startsWith('http') ? listed : BASE + listed) : BASE;
  }
  console.log('Portfólio verificado: ' + SITE + (multi ? ' (vários portfólios)' : ' (portfólio único)'));

  console.log('Celular (390 × 844) e tema escuro');
  for (const scheme of ['light', 'dark']) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, colorScheme: scheme, isMobile: true, hasTouch: true, bypassCSP: true });
    const page = await ctx.newPage();
    const html = await (await page.goto(SITE + '/acoes')).text();
    const slug = (html.match(/\/acoes\/([a-z0-9-]+)"/) || [])[1];
    if (multi) {
      await page.goto(BASE + '/', { waitUntil: 'networkidle' });
      await axe(page, `página da plataforma (${scheme}, celular)`);
    }
    for (const path of ['', '/acoes', '/areas', '/equipe', '/acessibilidade', slug ? '/acoes/' + slug : null].filter(s => s !== null)) {
      await page.goto(SITE + path, { waitUntil: 'networkidle' });
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      ok(overflow <= 0, `${scheme}: sem rolagem horizontal em ${path || '/'}`);
      // Corpo de texto: o tamanho base e os parágrafos de leitura (rótulos e legendas podem ser menores).
      const sizes = await page.evaluate(() => [document.body, ...document.querySelectorAll('.prose p, .lead, .hero__lead')].map(el => parseFloat(getComputedStyle(el).fontSize)));
      ok(Math.min(...sizes) >= 16, `${scheme}: corpo de texto ≥ 16 px em ${path} (mín. ${Math.min(...sizes)}px)`);
      await axe(page, `${path} (${scheme}, celular)`);
    }
    await ctx.close();
  }

  console.log('Teclado');
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, bypassCSP: true });
  const page = await ctx.newPage();
  await page.goto(SITE, { waitUntil: 'networkidle' });
  await page.keyboard.press('Tab');
  ok(await page.evaluate(() => document.activeElement.classList.contains('skip-link')), 'primeiro Tab leva ao link "Pular para o conteúdo"');
  const outline = await page.evaluate(() => getComputedStyle(document.activeElement).outlineStyle);
  ok(outline !== 'none', 'foco visível no elemento focado');

  // Busca usando apenas o teclado
  await page.focus('#busca-inicio');
  await page.keyboard.type('a');
  await Promise.all([page.waitForURL(/\/acoes\?/), page.keyboard.press('Enter')]);
  ok(page.url().includes('/acoes?q=a'), 'busca enviada com Enter');

  // Galeria: abrir, navegar com setas, fechar com Esc e devolver o foco
  const actionsHtml = await (await page.goto(SITE + '/acoes')).text();
  const slugs = [...actionsHtml.matchAll(/\/acoes\/([a-z0-9-]+)"/g)].map(m => m[1]);
  let tested = false;
  for (const s of slugs) {
    await page.goto(SITE + '/acoes/' + s, { waitUntil: 'networkidle' });
    if (await page.locator('.gallery__btn').count() < 2) continue;
    await axe(page, '/acoes/' + s + ' (computador)');
    const first = page.locator('.gallery__btn').first();
    await first.focus();
    await page.keyboard.press('Enter');
    ok(await page.locator('dialog[open]').count() === 1, 'Enter abre a galeria ampliada');
    const c1 = await page.locator('[data-lb-counter]').textContent();
    await page.keyboard.press('ArrowRight');
    const c2 = await page.locator('[data-lb-counter]').textContent();
    ok(c1 !== c2 && /Imagem 2 de/.test(c2), `seta direita avança (${c1} → ${c2})`);
    await page.keyboard.press('ArrowLeft');
    ok(/Imagem 1 de/.test(await page.locator('[data-lb-counter]').textContent()), 'seta esquerda volta');
    await page.keyboard.press('Escape');
    ok(await page.locator('dialog[open]').count() === 0, 'Esc fecha a galeria');
    ok(await page.evaluate(() => document.activeElement.classList.contains('gallery__btn')), 'foco volta para a miniatura que abriu a galeria');
    tested = true;
    break;
  }
  ok(tested, 'encontrada ação com galeria para testar');

  // Painel no celular e por teclado (opcional, exige credenciais)
  if (process.env.PAINEL_EMAIL && process.env.PAINEL_SENHA) {
    console.log('Painel');
    const m = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, bypassCSP: true });
    const p = await m.newPage();
    await p.goto(BASE + '/entrar');
    await p.fill('#f-email', process.env.PAINEL_EMAIL);
    await p.fill('#f-password', process.env.PAINEL_SENHA);
    await Promise.all([p.waitForURL(/\/painel$/), p.keyboard.press('Enter')]);
    ok(p.url().endsWith('/painel'), 'login pelo teclado (Enter)');
    const panelPaths = ['/painel', '/painel/acoes', '/painel/acoes/1', '/painel/acoes/1/fotos', '/painel/acoes/1/revisao', '/painel/paginas', '/painel/usuarios'];
    if (multi) panelPaths.push('/painel/plataforma/portfolios', '/painel/plataforma/portfolios/novo');
    for (const path of panelPaths) {
      await p.goto(BASE + path, { waitUntil: 'networkidle' });
      const overflow = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      ok(overflow <= 0, `painel sem rolagem horizontal em ${path}`);
      await axe(p, path + ' (celular)');
    }
    const toggle = p.locator('[data-menu-toggle]');
    await toggle.focus();
    await p.keyboard.press('Enter');
    ok(await toggle.getAttribute('aria-expanded') === 'true', 'menu do painel abre pelo teclado');
    await m.close();
  }

  await browser.close();
  console.log(failures ? `\n${failures} verificação(ões) falharam.` : '\nTodas as verificações passaram.');
  process.exit(failures ? 1 : 0);
})();
