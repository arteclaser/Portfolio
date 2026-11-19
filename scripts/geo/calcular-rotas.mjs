// Calcula o traçado dos roteiros pelas ruas reais (dados do OpenStreetMap) e o perfil de altitude.
//
// - Traçado, distância e tempo: OSRM mantido pela FOSSGIS (routing.openstreetmap.de), perfis a pé, de carro e de bicicleta.
// - Altitude: relevo já baixado em public/mapa/relevo.pmtiles (SRTM, cerca de 30 m de resolução).
//
// O resultado fica em src/data/rotas-geradas/. O site não consulta nenhum serviço de rotas ao ser usado.
// Uso: node scripts/geo/calcular-rotas.mjs
import { closeSync, mkdirSync, openSync, readdirSync, readFileSync, readSync, writeFileSync } from 'node:fs';
import { PMTiles } from 'pmtiles';
import sharp from 'sharp';

const raiz = new URL('../../', import.meta.url);
const ler = (caminho) => JSON.parse(readFileSync(new URL(caminho, raiz), 'utf8'));

const lugares = new Map(readdirSync(new URL('src/data/lugares/', raiz)).filter((f) => f.endsWith('.json')).map((f) => {
  const lugar = ler(`src/data/lugares/${f}`);
  return [lugar.id, lugar];
}));
const rotas = readdirSync(new URL('src/data/rotas/', raiz)).filter((f) => f.endsWith('.json')).map((f) => ler(`src/data/rotas/${f}`));

const PERFIL = { 'a-pe': ['routed-foot', 'foot'], carro: ['routed-car', 'driving'], bicicleta: ['routed-bike', 'bike'] };

// ---------------------------------------------------------------- geometria
const R = 6371008.8;
const rad = Math.PI / 180;
function distancia(a, b) {
  const dLat = (b[1] - a[1]) * rad;
  const dLon = (b[0] - a[0]) * rad;
  const s = Math.sin(dLat / 2) ** 2 + Math.cos(a[1] * rad) * Math.cos(b[1] * rad) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(s)));
}

/** Simplificação de Douglas–Peucker, com tolerância em metros. */
function simplificar(pontos, tolerancia) {
  if (pontos.length < 3) return pontos;
  const kx = Math.cos(pontos[0][1] * rad) * 111320;
  const ky = 110540;
  const xy = pontos.map((p) => [p[0] * kx, p[1] * ky]);
  const manter = new Uint8Array(pontos.length);
  manter[0] = manter[pontos.length - 1] = 1;
  const pilha = [[0, pontos.length - 1]];
  while (pilha.length) {
    const [i, j] = pilha.pop();
    let maior = 0;
    let indice = -1;
    const [ax, ay] = xy[i];
    const [bx, by] = xy[j];
    const dx = bx - ax;
    const dy = by - ay;
    const comprimento2 = dx * dx + dy * dy || 1e-12;
    for (let k = i + 1; k < j; k++) {
      const t = Math.max(0, Math.min(1, ((xy[k][0] - ax) * dx + (xy[k][1] - ay) * dy) / comprimento2));
      const d = Math.hypot(xy[k][0] - ax - t * dx, xy[k][1] - ay - t * dy);
      if (d > maior) {
        maior = d;
        indice = k;
      }
    }
    if (maior > tolerancia) {
      manter[indice] = 1;
      pilha.push([i, indice], [indice, j]);
    }
  }
  return pontos.filter((_, k) => manter[k]);
}

// ---------------------------------------------------------------- relevo
class Arquivo {
  constructor(caminho) {
    this.caminho = caminho;
    this.fd = openSync(caminho, 'r');
  }
  getKey() {
    return this.caminho;
  }
  async getBytes(offset, length) {
    const buffer = Buffer.alloc(length);
    const lidos = readSync(this.fd, buffer, 0, length, offset);
    return { data: buffer.buffer.slice(buffer.byteOffset, buffer.byteOffset + lidos) };
  }
  fechar() {
    closeSync(this.fd);
  }
}
const arquivoRelevo = new Arquivo(new URL('public/mapa/relevo.pmtiles', raiz).pathname);
const relevo = new PMTiles(arquivoRelevo);
const Z = 12;
const blocos = new Map();

async function bloco(x, y) {
  const chave = `${x}/${y}`;
  if (!blocos.has(chave)) {
    const resposta = await relevo.getZxy(Z, x, y);
    if (!resposta) throw new Error(`Sem relevo para o bloco ${Z}/${chave}: o ponto está fora da área baixada.`);
    const { data, info } = await sharp(Buffer.from(resposta.data)).removeAlpha().raw().toBuffer({ resolveWithObject: true });
    blocos.set(chave, { data, largura: info.width, canais: info.channels });
  }
  return blocos.get(chave);
}

/** Altitude (m) em um ponto, com interpolação bilinear entre os quatro pixels vizinhos. */
async function altitude([lon, lat]) {
  const n = 2 ** Z;
  const fx = ((lon + 180) / 360) * n;
  const fy = ((1 - Math.asinh(Math.tan(lat * rad)) / Math.PI) / 2) * n;
  const px = fx * 256 - 0.5;
  const py = fy * 256 - 0.5;
  const x0 = Math.floor(px);
  const y0 = Math.floor(py);
  const amostra = async (gx, gy) => {
    const b = await bloco(Math.floor(gx / 256), Math.floor(gy / 256));
    const i = (((gy % 256) + 256) % 256) * b.largura * b.canais + (((gx % 256) + 256) % 256) * b.canais;
    return b.data[i] * 256 + b.data[i + 1] + b.data[i + 2] / 256 - 32768;
  };
  const [a, b, c, d] = await Promise.all([amostra(x0, y0), amostra(x0 + 1, y0), amostra(x0, y0 + 1), amostra(x0 + 1, y0 + 1)]);
  const tx = px - x0;
  const ty = py - y0;
  return (a * (1 - tx) + b * tx) * (1 - ty) + (c * (1 - tx) + d * tx) * ty;
}

// ---------------------------------------------------------------- rotas
async function osrm(modo, pontos) {
  const [servico, perfil] = PERFIL[modo];
  const url = `https://routing.openstreetmap.de/${servico}/route/v1/${perfil}/${pontos.map((p) => p.join(',')).join(';')}?overview=full&geometries=geojson&steps=false&continue_straight=false`;
  for (let tentativa = 1; tentativa <= 4; tentativa++) {
    try {
      const resposta = await fetch(url, { headers: { 'User-Agent': 'mapa-turistico-capinzal/1.0 (calculo pontual de roteiros)' } });
      const json = await resposta.json();
      if (json.code !== 'Ok') throw new Error(`OSRM respondeu ${json.code}: ${json.message ?? ''}`);
      return json;
    } catch (erro) {
      if (tentativa === 4) throw erro;
      await new Promise((r) => setTimeout(r, 2000 * tentativa));
    }
  }
}

mkdirSync(new URL('src/data/rotas-geradas/', raiz), { recursive: true });

for (const rota of rotas.sort((a, b) => a.ordem - b.ordem)) {
  const paradas = rota.paradas.map((p) => {
    const lugar = lugares.get(p.lugar);
    if (!lugar) throw new Error(`Roteiro "${rota.id}": lugar inexistente "${p.lugar}".`);
    return lugar.coordenadas;
  });
  const pontos = rota.circular ? [...paradas, paradas[0]] : paradas;
  const resposta = await osrm(rota.modo, pontos);
  const trajeto = resposta.routes[0];
  const bruto = trajeto.geometry.coordinates;

  // Quanto cada parada ficou longe da rua mais próxima (o serviço "encosta" o ponto na via).
  const encostos = resposta.waypoints.map((w, i) => Math.round(distancia(pontos[i], w.location)));

  // Perfil de altitude: uma amostra a cada 25 m (a pé) ou 100 m (carro), suavizada.
  const passo = rota.modo === 'carro' ? 100 : 25;
  const amostras = [];
  let acumulado = 0;
  let proxima = 0;
  for (let i = 0; i < bruto.length - 1; i++) {
    const trecho = distancia(bruto[i], bruto[i + 1]);
    while (proxima <= acumulado + trecho) {
      const t = trecho ? (proxima - acumulado) / trecho : 0;
      amostras.push({ d: proxima, p: [bruto[i][0] + t * (bruto[i + 1][0] - bruto[i][0]), bruto[i][1] + t * (bruto[i + 1][1] - bruto[i][1])] });
      proxima += passo;
    }
    acumulado += trecho;
  }
  amostras.push({ d: acumulado, p: bruto[bruto.length - 1] });
  const alturas = [];
  for (const a of amostras) alturas.push(await altitude(a.p));
  const janela = 2;
  const suaves = alturas.map((_, i) => {
    const fatia = alturas.slice(Math.max(0, i - janela), i + janela + 1);
    return fatia.reduce((s, v) => s + v, 0) / fatia.length;
  });
  let subida = 0;
  let descida = 0;
  for (let i = 1; i < suaves.length; i++) {
    const delta = suaves[i] - suaves[i - 1];
    if (delta > 0) subida += delta;
    else descida -= delta;
  }

  const geometria = simplificar(bruto, rota.modo === 'carro' ? 4 : 1.5).map((p) => [Math.round(p[0] * 1e5) / 1e5, Math.round(p[1] * 1e5) / 1e5]);
  const paradasEm = [0];
  for (const perna of trajeto.legs) paradasEm.push(Math.round(paradasEm[paradasEm.length - 1] + perna.distance));
  if (rota.circular) paradasEm.pop();

  const gerada = {
    id: rota.id,
    distanciaM: Math.round(trajeto.distance),
    duracaoS: Math.round(trajeto.duration),
    geometria,
    trechos: trajeto.legs.map((perna) => ({ distanciaM: Math.round(perna.distance), duracaoS: Math.round(perna.duration) })),
    perfil: amostras.map((a, i) => ({ d: Math.round(a.d), e: Math.round(suaves[i]) })),
    subidaM: Math.round(subida),
    descidaM: Math.round(descida),
    altitudeMin: Math.round(Math.min(...suaves)),
    altitudeMax: Math.round(Math.max(...suaves)),
    paradasEm,
    motor: `OSRM (routing.openstreetmap.de, perfil ${PERFIL[rota.modo][1]}) sobre dados do OpenStreetMap; altitude: SRTM`,
    geradoEm: new Date().toISOString().slice(0, 10),
  };
  writeFileSync(new URL(`src/data/rotas-geradas/${rota.id}.json`, raiz), `${JSON.stringify(gerada)}\n`);
  console.log(
    `${rota.id}: ${(gerada.distanciaM / 1000).toFixed(2)} km · ${Math.round(gerada.duracaoS / 60)} min · ${geometria.length} pontos (de ${bruto.length}) · ` +
      `subida ${gerada.subidaM} m, descida ${gerada.descidaM} m · altitude ${gerada.altitudeMin}–${gerada.altitudeMax} m · encosto das paradas (m): ${encostos.join(', ')}`,
  );
  await new Promise((r) => setTimeout(r, 1200));
}
arquivoRelevo.fechar();
