// Confere a integridade dos dados do guia antes de montar o site.
// Regras principais: todo lugar tem fonte; a posição cai dentro da área do mapa (e dentro de Capinzal,
// salvo os lugares declarados em Ouro); toda foto tem autoria e licença; todo roteiro passa pelas paradas.
// Uso: node scripts/conferir-dados.mjs
import { existsSync, readdirSync, readFileSync } from 'node:fs';

const raiz = new URL('../', import.meta.url);
const ler = (caminho) => JSON.parse(readFileSync(new URL(caminho, raiz), 'utf8'));
const listar = (pasta) => readdirSync(new URL(pasta, raiz)).filter((f) => f.endsWith('.json'));

const CATEGORIAS = ['historia', 'fe', 'natureza', 'sabores', 'hospedagem', 'apoio'];
const TIPOS_DE_FONTE = ['oficial', 'imprensa', 'enciclopedia', 'mapa', 'proprio', 'plataforma', 'base', 'acervo', 'outro'];
const AREA_DETALHADA = [-51.8, -27.57, -51.46, -27.27]; // onde o mapa tem o detalhe das ruas
const DATA = /^\d{4}-\d{2}-\d{2}$/;

const erros = [];
const avisos = [];
const erro = (onde, texto) => erros.push(`${onde}: ${texto}`);

// ---------------------------------------------------------------- limite do município
const limite = ler('src/data/geo/limite-capinzal-osm.json').features[0].geometry.coordinates[0];
function emCapinzal([lon, lat]) {
  let dentro = false;
  for (let i = 0; i < limite.length - 1; i++) {
    const [x1, y1] = limite[i];
    const [x2, y2] = limite[i + 1];
    if (y1 > lat !== y2 > lat && lon < ((x2 - x1) * (lat - y1)) / (y2 - y1) + x1) dentro = !dentro;
  }
  return dentro;
}
/** Menor distância (m) de um ponto a uma linha, medindo até os trechos e não só até os vértices. */
function distanciaAteLinha(ponto, linha) {
  const kx = Math.cos((ponto[1] * Math.PI) / 180) * 111320;
  const ky = 110540;
  let menor = Infinity;
  for (let i = 0; i < linha.length - 1; i++) {
    const ax = (linha[i][0] - ponto[0]) * kx;
    const ay = (linha[i][1] - ponto[1]) * ky;
    const bx = (linha[i + 1][0] - ponto[0]) * kx;
    const by = (linha[i + 1][1] - ponto[1]) * ky;
    const dx = bx - ax;
    const dy = by - ay;
    const t = Math.max(0, Math.min(1, -(ax * dx + ay * dy) / (dx * dx + dy * dy || 1e-12)));
    menor = Math.min(menor, Math.hypot(ax + t * dx, ay + t * dy));
  }
  return menor;
}

// ---------------------------------------------------------------- lugares
const lugares = new Map();
for (const arquivo of listar('src/data/lugares/')) {
  const onde = `lugares/${arquivo}`;
  const l = ler(`src/data/lugares/${arquivo}`);
  if (`${l.id}.json` !== arquivo) erro(onde, `o campo "id" (${l.id}) precisa ser igual ao nome do arquivo`);
  if (!/^[a-z0-9]+(-[a-z0-9]+)*$/.test(l.id ?? '')) erro(onde, 'id só pode ter letras minúsculas, números e hífens');
  if (lugares.has(l.id)) erro(onde, 'id repetido');
  lugares.set(l.id, l);

  for (const campo of ['nome', 'tipo', 'resumo', 'municipio', 'origemCoordenadas', 'conferidoEm']) {
    if (typeof l[campo] !== 'string' || !l[campo].trim()) erro(onde, `falta o campo "${campo}"`);
  }
  if (!CATEGORIAS.includes(l.categoria)) erro(onde, `categoria inválida: ${l.categoria}`);
  if (![1, 2, 3].includes(l.destaque)) erro(onde, 'destaque deve ser 1, 2 ou 3');
  if (!Array.isArray(l.descricao) || !l.descricao.length || l.descricao.some((p) => typeof p !== 'string' || p.trim().length < 20)) erro(onde, 'descricao precisa de pelo menos um parágrafo');
  if ((l.resumo ?? '').length > 200) erro(onde, `resumo com ${l.resumo.length} caracteres (máximo: 200)`);
  if (!DATA.test(l.conferidoEm ?? '')) erro(onde, 'conferidoEm deve estar no formato AAAA-MM-DD');
  if (!['exata', 'aproximada'].includes(l.precisao)) erro(onde, 'precisao deve ser "exata" ou "aproximada"');
  if (l.precisao === 'exata' && !l.osm) avisos.push(`${onde}: posição "exata" sem referência do OpenStreetMap`);

  const c = l.coordenadas;
  if (!Array.isArray(c) || c.length !== 2 || c.some((n) => typeof n !== 'number')) erro(onde, 'coordenadas devem ser [longitude, latitude]');
  else {
    const [oeste, sul, leste, norte] = AREA_DETALHADA;
    if (c[0] < oeste || c[0] > leste || c[1] < sul || c[1] > norte) erro(onde, 'posição fora da área detalhada do mapa (longitude e latitude trocadas?)');
    const fora = !emCapinzal(c);
    if (fora && !/Ouro/.test(l.municipio ?? '')) erro(onde, 'a posição cai fora do limite de Capinzal, mas o município não foi declarado como outro');
    if (!fora && !/Capinzal/.test(l.municipio ?? '')) erro(onde, `a posição cai dentro de Capinzal, mas o município declarado é "${l.municipio}"`);
  }

  // Fontes: nenhuma informação sem origem.
  if (!Array.isArray(l.fontes) || !l.fontes.length) erro(onde, 'todo lugar precisa de pelo menos uma fonte');
  for (const [i, f] of (l.fontes ?? []).entries()) {
    if (!f.titulo?.trim()) erro(onde, `fonte ${i + 1} sem título`);
    if (!/^https?:\/\/\S+$/.test(f.url ?? '')) erro(onde, `fonte ${i + 1} com endereço inválido`);
    if (!TIPOS_DE_FONTE.includes(f.tipo)) erro(onde, `fonte ${i + 1} com tipo inválido: ${f.tipo}`);
    if (!DATA.test(f.acesso ?? '')) erro(onde, `fonte ${i + 1} sem data de consulta`);
  }
  if ((l.fontes ?? []).every((f) => f.tipo === 'mapa')) erro(onde, 'o OpenStreetMap sozinho não basta: é preciso outra fonte');

  // Comércio e hospedagem: a situação do funcionamento precisa estar declarada.
  if (['sabores', 'hospedagem'].includes(l.categoria)) {
    if (!['confirmado', 'a-confirmar'].includes(l.situacao)) erro(onde, 'comércio e hospedagem precisam do campo "situacao" (confirmado ou a-confirmar)');
    if (l.situacao === 'confirmado' && !l.evidencia?.trim()) erro(onde, '"situacao": "confirmado" exige o campo "evidencia"');
    // "A confirmar": duas fontes, ou uma só quando ela é oficial ou uma base de dados aberta (registro conferível por qualquer pessoa).
    const fonteUnicaAceita = (l.fontes ?? []).some((f) => f.tipo === 'base' || f.tipo === 'oficial');
    if (l.situacao === 'a-confirmar' && (l.fontes ?? []).length < 2 && !fonteUnicaAceita) erro(onde, 'lugar "a confirmar" precisa de duas fontes, ou de uma fonte oficial ou base de dados aberta');
    if (l.horario) avisos.push(`${onde}: horário de comércio publicado; confirme que vem de fonte datada`);
  }

  // Fotos: só com autoria, licença e página de origem.
  for (const [i, img] of (l.imagens ?? []).entries()) {
    for (const campo of ['arquivo', 'alt', 'autor', 'licenca', 'origem']) {
      if (!img[campo]?.trim()) erro(onde, `foto ${i + 1} sem o campo "${campo}"`);
    }
    if (img.arquivo && !existsSync(new URL(`src/assets/lugares/${img.arquivo}`, raiz))) erro(onde, `foto não encontrada: src/assets/lugares/${img.arquivo}`);
    if (img.origem && !/^https:\/\//.test(img.origem)) erro(onde, `foto ${i + 1}: a origem deve ser um endereço https`);
  }
  if (l.contato?.telefone && !/^\(\d{2}\) \d{4,5}-\d{4}$/.test(l.contato.telefone)) erro(onde, `telefone fora do formato "(49) 3555-0000": ${l.contato.telefone}`);
}

// ---------------------------------------------------------------- roteiros
let roteiros = 0;
for (const arquivo of listar('src/data/rotas/')) {
  const onde = `rotas/${arquivo}`;
  const r = ler(`src/data/rotas/${arquivo}`);
  roteiros += 1;
  if (`${r.id}.json` !== arquivo) erro(onde, 'o campo "id" precisa ser igual ao nome do arquivo');
  for (const campo of ['nome', 'nomeCurto', 'resumo', 'cor', 'corEscuro']) if (!r[campo]?.trim()) erro(onde, `falta o campo "${campo}"`);
  if (!['a-pe', 'carro', 'bicicleta'].includes(r.modo)) erro(onde, `modo inválido: ${r.modo}`);
  if (!Array.isArray(r.paradas) || r.paradas.length < 2) erro(onde, 'um roteiro precisa de pelo menos duas paradas');
  if (!/não é um roteiro oficial/.test(r.aviso ?? '')) erro(onde, 'o aviso precisa dizer que não é um roteiro oficial');
  for (const p of r.paradas ?? []) if (!lugares.has(p.lugar)) erro(onde, `parada inexistente: ${p.lugar}`);

  const gerado = `src/data/rotas-geradas/${arquivo}`;
  if (!existsSync(new URL(gerado, raiz))) {
    erro(onde, 'traçado não calculado: rode "node scripts/geo/calcular-rotas.mjs"');
    continue;
  }
  const g = ler(gerado);
  const esperadas = r.paradas.length;
  if (g.paradasEm?.length !== esperadas) erro(onde, 'as paradas mudaram depois do cálculo do traçado: rode "node scripts/geo/calcular-rotas.mjs"');
  if (g.trechos?.length !== (r.circular ? esperadas : esperadas - 1)) erro(onde, 'número de trechos não corresponde às paradas');
  if (!(g.distanciaM > 0) || !(g.duracaoS > 0) || !(g.geometria?.length > 1) || !(g.perfil?.length > 1)) erro(onde, 'traçado incompleto');
  // Cada parada precisa estar perto da linha desenhada (tolerância maior para posições aproximadas).
  for (const p of r.paradas ?? []) {
    const lugar = lugares.get(p.lugar);
    if (!lugar || !g.geometria) continue;
    const perto = distanciaAteLinha(lugar.coordenadas, g.geometria);
    const limiteM = lugar.precisao === 'exata' ? 120 : 300;
    if (perto > limiteM) erro(onde, `a parada "${p.lugar}" está a ${Math.round(perto)} m do traçado (limite: ${limiteM} m)`);
  }
}

// ---------------------------------------------------------------- resultado
for (const texto of avisos) console.log(`aviso · ${texto}`);
if (erros.length) {
  for (const texto of erros) console.error(`ERRO · ${texto}`);
  console.error(`\n${erros.length} problema(s) nos dados. O site não deve ser publicado assim.`);
  process.exit(1);
}
const fontes = new Set([...lugares.values()].flatMap((l) => l.fontes.map((f) => f.url)));
const fotos = [...lugares.values()].reduce((n, l) => n + (l.imagens?.length ?? 0), 0);
console.log(`Dados conferidos: ${lugares.size} lugares, ${roteiros} roteiros, ${fontes.size} fontes distintas, ${fotos} foto(s) com licença registrada.`);
