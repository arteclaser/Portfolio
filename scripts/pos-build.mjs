// Depois de o Astro montar o site em dist/: gera o service worker (modo sem internet), o sitemap e um resumo.
// Uso: node scripts/pos-build.mjs (chamado por "npm run build")
import { createHash } from 'node:crypto';
import { readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';

const dist = new URL('../dist/', import.meta.url).pathname;
const site = 'https://mostraqui.net';

function arquivos(pasta) {
  return readdirSync(pasta, { withFileTypes: true }).flatMap((item) => {
    const caminho = join(pasta, item.name);
    return item.isDirectory() ? arquivos(caminho) : [caminho];
  });
}
const todos = arquivos(dist).map((caminho) => '/' + relative(dist, caminho).split('\\').join('/'));
const sha = (caminho) => createHash('sha256').update(readFileSync(join(dist, caminho))).digest('hex');

// ---------------------------------------------------------------- páginas
const paginas = todos
  .filter((arquivo) => arquivo.endsWith('/index.html'))
  .map((arquivo) => arquivo.slice(0, -'index.html'.length))
  .sort();

// ---------------------------------------------------------------- o que fica guardado na instalação
const inicio = readFileSync(join(dist, 'index.html'), 'utf8');
const miniaturasDaCapa = [...inicio.matchAll(/(?:src|data-foto)="(\/assets\/[^"]+\.(?:webp|avif|jpg|jpeg|png))"/g)].map((m) => m[1]);
const essenciais = [
  ...new Set([
    ...paginas,
    ...todos.filter((a) => a.startsWith('/assets/') && /\.(css|js|mjs|woff2)$/.test(a)),
    ...todos.filter((a) => /^\/mapa\/(estilo-(claro|escuro)\.json|limite\.geojson)$/.test(a)),
    ...todos.filter((a) => /^\/mapa\/fontes\/[^/]+\/0-255\.pbf$/.test(a)),
    ...todos.filter((a) => ['/dados/rotas.json', '/app.webmanifest', '/favicon.svg', '/js/tema.js', '/icones/icone-192.png'].includes(a)),
    ...miniaturasDaCapa,
  ]),
].sort();
for (const url of essenciais) {
  const arquivo = url.endsWith('/') ? `${url}index.html` : url;
  if (!todos.includes(arquivo)) throw new Error(`Arquivo essencial ausente em dist/: ${arquivo}`);
}

const mapas = todos
  .filter((a) => /^\/mapa\/[^/]+\.pmtiles$/.test(a))
  .sort()
  .map((url) => ({ url, versao: sha(url).slice(0, 12) }));

const versao = createHash('sha256')
  .update(essenciais.map((url) => `${url}:${sha(url.endsWith('/') ? `${url}index.html` : url)}`).join('\n'))
  .digest('hex')
  .slice(0, 12);

const modelo = readFileSync(new URL('./sw-modelo.js', import.meta.url), 'utf8');
writeFileSync(
  join(dist, 'sw.js'),
  modelo
    .replace("'__VERSAO__'", JSON.stringify(versao))
    .replace('__ESSENCIAIS__', JSON.stringify(essenciais))
    .replace('__MAPAS__', JSON.stringify(mapas)),
);

// ---------------------------------------------------------------- sitemap
const foraDoSitemap = ['/offline/'];
const hoje = new Date().toISOString().slice(0, 10);
writeFileSync(
  join(dist, 'sitemap.xml'),
  `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n` +
    paginas
      .filter((p) => !foraDoSitemap.includes(p))
      .map((p) => `  <url><loc>${site}${p}</loc><lastmod>${hoje}</lastmod></url>`)
      .join('\n') +
    `\n</urlset>\n`,
);

// ---------------------------------------------------------------- resumo (também usado para conferir a implantação)
const bytes = (lista) => lista.reduce((soma, a) => soma + statSync(join(dist, a.endsWith('/') ? `${a}index.html` : a)).size, 0);
const resumo = {
  versao,
  geradoEm: new Date().toISOString(),
  paginas: paginas.length,
  arquivos: todos.length + 2,
  instalacaoKB: Math.round(bytes(essenciais) / 1024),
  mapasKB: Math.round(bytes(mapas.map((m) => m.url)) / 1024),
};
writeFileSync(join(dist, 'versao.json'), `${JSON.stringify(resumo, null, 2)}\n`);
console.log(
  `Pós-montagem: ${resumo.paginas} páginas · versão ${versao} · modo sem internet guarda ${resumo.instalacaoKB} kB na instalação ` +
    `(${essenciais.length} arquivos) e ${resumo.mapasKB} kB de mapa em seguida.`,
);
