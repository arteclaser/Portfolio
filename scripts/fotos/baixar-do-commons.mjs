// Baixa do Wikimedia Commons as fotos de uso livre listadas em scripts/fotos/lista.json,
// grava em src/assets/lugares/ e registra autoria e licença no arquivo de cada lugar.
//
// Uso: npm run fotos
// Para acrescentar uma foto: inclua em lista.json o título do arquivo no Commons ("File:..."), o nome
// local, o texto alternativo e, se quiser, uma legenda. Só entram fotos com licença livre.
//
// O Commons limita o número de pedidos por endereço de rede: o script vai devagar e, se for recusado
// (erro 429), para e avisa. Basta rodar de novo mais tarde: o que já foi baixado não é repetido.
import { existsSync, mkdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';

const raiz = new URL('../../', import.meta.url);
const lista = JSON.parse(readFileSync(new URL('scripts/fotos/lista.json', raiz), 'utf8'));
const AGENTE = 'mostraqui-capinzal/1.0 (montagem de site; download pontual de fotos livres)';
const LICENCAS_LIVRES = /^(CC0|Public domain|CC BY(-SA)? \d\.\d)/;
const NOMES = { CC0: 'CC0 1.0 (domínio público)', 'Public domain': 'Domínio público' };
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));
const limpar = (html) => (html ?? '').replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/\s+/g, ' ').trim();

async function pedir(url) {
  const resposta = await fetch(url, { headers: { 'User-Agent': AGENTE } });
  if (resposta.status === 429) {
    console.error(`\nO Wikimedia Commons recusou o pedido por excesso de acessos (espera sugerida: ${resposta.headers.get('retry-after') ?? '?'} s).`);
    console.error('Rode "npm run fotos" de novo mais tarde, de preferência de outra rede.');
    process.exit(2);
  }
  if (!resposta.ok) throw new Error(`HTTP ${resposta.status} em ${url}`);
  return resposta;
}

mkdirSync(new URL('src/assets/lugares/', raiz), { recursive: true });
const creditos = [];
let baixadas = 0;

for (const [lugar, fotos] of Object.entries(lista)) {
  const caminhoLugar = new URL(`src/data/lugares/${lugar}.json`, raiz);
  if (!existsSync(caminhoLugar)) throw new Error(`lista.json cita um lugar que não existe: ${lugar}`);
  const dados = JSON.parse(readFileSync(caminhoLugar, 'utf8'));
  const imagens = [];

  for (const foto of fotos) {
    const destino = new URL(`src/assets/lugares/${foto.arquivo}`, raiz);
    const jaRegistrada = (dados.imagens ?? []).find((i) => i.arquivo === foto.arquivo);
    if (jaRegistrada && existsSync(destino) && statSync(destino).size > 20000) {
      imagens.push({ ...jaRegistrada, alt: foto.alt, ...(foto.legenda ? { legenda: foto.legenda } : {}) });
      continue;
    }
    // Metadados: autoria, licença e endereço da miniatura de 1280 px.
    const api =
      'https://commons.wikimedia.org/w/api.php?action=query&format=json&prop=imageinfo&iiprop=url%7Csize%7Cextmetadata&iiurlwidth=1280&titles=' +
      encodeURIComponent(foto.titulo);
    const pagina = Object.values((await (await pedir(api)).json()).query.pages)[0];
    const info = pagina.imageinfo?.[0];
    if (!info) throw new Error(`Arquivo não encontrado no Commons: ${foto.titulo}`);
    const meta = info.extmetadata ?? {};
    const licenca = limpar(meta.LicenseShortName?.value);
    if (!LICENCAS_LIVRES.test(licenca)) throw new Error(`${foto.titulo}: licença "${licenca}" não é reconhecida como livre.`);
    await esperar(3000);
    const bytes = Buffer.from(await (await pedir(info.width > 1280 ? info.thumburl : info.url)).arrayBuffer());
    writeFileSync(destino, bytes);
    baixadas += 1;
    const ano = limpar(meta.DateTimeOriginal?.value).slice(0, 4);
    const autor = limpar(meta.Artist?.value);
    imagens.push({
      arquivo: foto.arquivo,
      alt: foto.alt,
      autor: autor === 'DAR7' ? 'DAR7 (Wikimedia Commons)' : autor,
      licenca: NOMES[licenca] ?? licenca,
      origem: info.descriptionurl,
      ...(/^\d{4}$/.test(ano) ? { ano } : {}),
      ...(foto.legenda ? { legenda: foto.legenda } : {}),
    });
    console.log(`baixada: ${foto.arquivo} (${Math.round(bytes.length / 1024)} kB) · ${autor} · ${licenca}`);
    await esperar(8000);
  }

  if (imagens.length) dados.imagens = imagens;
  // Mantém "imagens" antes de "fontes", como nos demais arquivos.
  const { fontes, conferidoEm, imagens: _, ...resto } = dados;
  writeFileSync(caminhoLugar, `${JSON.stringify({ ...resto, ...(imagens.length ? { imagens } : {}), fontes, conferidoEm }, null, 2)}\n`);
  for (const imagem of imagens) creditos.push({ lugar, ...imagem });
}

writeFileSync(new URL('src/data/creditos-imagens.json', raiz), `${JSON.stringify(creditos, null, 2)}\n`);
console.log(`\n${baixadas} foto(s) baixada(s) agora; ${creditos.length} no total, todas com autoria e licença registradas.`);
