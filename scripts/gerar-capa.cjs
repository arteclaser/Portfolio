// Gera a imagem de compartilhamento (1200×630) usada quando o link do site é enviado por mensagem
// ou rede social. Usa uma captura real do mapa (tests/navegador/capturas/) e as fontes do site.
// Uso: npm run build && npm run test:navegador && node scripts/gerar-capa.cjs   (exige Playwright)
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const raiz = path.resolve(__dirname, '..');
const dados = (arquivo, tipo) => `data:${tipo};base64,${fs.readFileSync(path.join(raiz, arquivo)).toString('base64')}`;
const mapa = dados('tests/navegador/capturas/roteiro-computador.png', 'image/png');
const figtree = dados('node_modules/@fontsource-variable/figtree/files/figtree-latin-wght-normal.woff2', 'font/woff2');
const bricolage = dados('node_modules/@fontsource-variable/bricolage-grotesque/files/bricolage-grotesque-latin-wght-normal.woff2', 'font/woff2');

const html = `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><style>
  @font-face { font-family: "Figtree"; font-weight: 300 900; src: url(${figtree}) format("woff2"); }
  @font-face { font-family: "Bricolage Grotesque"; font-weight: 200 800; src: url(${bricolage}) format("woff2"); }
  * { box-sizing: border-box; margin: 0; }
  body { width: 1200px; height: 630px; display: grid; grid-template-columns: 520px 1fr; background: linear-gradient(120deg, #0F766E 0%, #15803D 60%, #0E7490 100%); color: #FFFBF5; font-family: "Figtree", sans-serif; overflow: hidden; }
  .texto { padding: 64px 0 64px 64px; display: flex; flex-direction: column; justify-content: space-between; }
  .marca { display: flex; align-items: center; gap: 16px; font-family: "Bricolage Grotesque"; font-size: 34px; font-weight: 700; letter-spacing: -.02em; }
  .marca svg { width: 64px; height: 64px; }
  h1 { font-family: "Bricolage Grotesque"; font-size: 68px; font-weight: 700; line-height: 1.02; letter-spacing: -.025em; }
  p { margin-top: 22px; font-size: 27px; line-height: 1.35; color: #F0FDF8; max-width: 420px; }
  .rodape { font-size: 22px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #FDE68A; }
  .mapa { position: relative; margin: 44px -40px 44px 44px; border-radius: 28px; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,.35); background: #EDE7D9; }
  .mapa img { position: absolute; left: -430px; top: -120px; width: 1366px; }
</style></head><body>
  <div class="texto">
    <div class="marca">
      <svg viewBox="0 0 48 48"><rect width="48" height="48" rx="13" fill="#FFFBF5"/><path d="M24 7.500c-5.800 0-10.500 4.600-10.500 10.300 0 6.900 7.400 13.900 9.600 15.800a1.400 1.400 0 0 0 1.800 0c2.200-1.900 9.600-8.900 9.600-15.800C34.500 12.100 29.800 7.500 24 7.500z" fill="#0F766E"/><circle cx="24" cy="17.800" r="4.400" fill="#F0A630"/><path d="M8.500 40.500c3.900-2.700 7.300-2.700 10.300 0 3 2.700 6.400 2.700 10.400 0 3.900-2.700 7.300-2.700 10.300 0" fill="none" stroke="#5FA9BB" stroke-width="2.800" stroke-linecap="round"/></svg>
      Mostraqui
    </div>
    <div>
      <h1>O mapa turístico de Capinzal</h1>
      <p>Lugares, roteiros pelas ruas e mapa que funciona sem internet.</p>
    </div>
    <div class="rodape">mostraqui.net · Capinzal, SC</div>
  </div>
  <div class="mapa"><img src="${mapa}" alt=""></div>
</body></html>`;

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 1200, height: 630 }, deviceScaleFactor: 1 });
  await page.setContent(html, { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  const destino = path.join(raiz, 'public/icones/compartilhar.png');
  await page.screenshot({ path: destino });
  await browser.close();
  console.log('Capa gravada em public/icones/compartilhar.png');
})();
