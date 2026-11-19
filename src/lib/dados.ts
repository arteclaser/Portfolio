import type { ImageMetadata } from 'astro';
import { categoriaPorId } from '../data/categorias';
import { distanciaM } from './geo';
import type { Lugar, Rota, RotaDefinicao, RotaGerada } from './tipos';

// Um arquivo JSON por lugar e por roteiro: fácil de conferir, corrigir e revisar.
const arquivosLugares = import.meta.glob<Lugar>('../data/lugares/*.json', { eager: true, import: 'default' });
const arquivosRotas = import.meta.glob<RotaDefinicao>('../data/rotas/*.json', { eager: true, import: 'default' });
const arquivosGerados = import.meta.glob<RotaGerada>('../data/rotas-geradas/*.json', { eager: true, import: 'default' });
const arquivosImagens = import.meta.glob<ImageMetadata>('../assets/lugares/*.{jpg,jpeg,png,webp}', { eager: true, import: 'default' });

const ordemCategoria = Object.keys(categoriaPorId);

// Ordem dos cartões: primeiro os cartões-postais, depois os que têm foto, depois por categoria e nome.
const temFoto = (lugar: Lugar) => (lugar.imagens?.length ? 1 : 0);
export const lugares: Lugar[] = Object.values(arquivosLugares).sort(
  (a, b) =>
    b.destaque - a.destaque ||
    temFoto(b) - temFoto(a) ||
    ordemCategoria.indexOf(a.categoria) - ordemCategoria.indexOf(b.categoria) ||
    a.nome.localeCompare(b.nome, 'pt-BR'),
);

export const lugarPorId = new Map(lugares.map((l) => [l.id, l]));

const geradas = new Map(Object.values(arquivosGerados).map((g) => [g.id, g]));

export const rotas: Rota[] = Object.values(arquivosRotas)
  .filter((definicao) => geradas.has(definicao.id))
  .map((definicao) => ({ ...definicao, ...geradas.get(definicao.id)! }))
  .sort((a, b) => a.ordem - b.ordem);

export const rotaPorId = new Map(rotas.map((r) => [r.id, r]));

/** Imagem (com dimensões) a partir do nome do arquivo em src/assets/lugares/. */
export function imagem(arquivo: string): ImageMetadata {
  const achada = arquivosImagens[`../assets/lugares/${arquivo}`];
  if (!achada) throw new Error(`Imagem não encontrada em src/assets/lugares/: ${arquivo}`);
  return achada;
}

/** Os lugares mais próximos de um lugar, em linha reta. */
export function vizinhos(lugar: Lugar, quantos = 3): { lugar: Lugar; metros: number }[] {
  return lugares
    .filter((outro) => outro.id !== lugar.id)
    .map((outro) => ({ lugar: outro, metros: distanciaM(lugar.coordenadas, outro.coordenadas) }))
    .sort((a, b) => a.metros - b.metros)
    .slice(0, quantos);
}

/** Roteiros que passam por um lugar. */
export function rotasDoLugar(id: string): Rota[] {
  return rotas.filter((rota) => rota.paradas.some((p) => p.lugar === id));
}

export function urlLugar(id: string): string {
  return `/lugares/${id}/`;
}

export function urlRota(id: string): string {
  return `/rotas/${id}/`;
}

/** Link "como chegar" no Google Maps: usa o ponto exato ou, se a posição for aproximada, o nome e o endereço. */
export function linkGoogleMaps(lugar: Lugar): string {
  const [lon, lat] = lugar.coordenadas;
  const destino =
    lugar.precisao === 'exata'
      ? `${lat},${lon}`
      : [lugar.nome, lugar.endereco, `${lugar.municipio} SC`].filter(Boolean).join(', ');
  return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(destino)}`;
}

/** Busca do lugar no Google Maps, onde ficam as fotos publicadas por visitantes (o guia não as copia). */
export function linkFotosGoogle(lugar: Lugar): string {
  const cidade = lugar.municipio.includes('Capinzal') ? 'Capinzal' : lugar.municipio;
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${lugar.nome}, ${cidade} SC`)}`;
}

export function linkWaze(lugar: Lugar): string {
  const [lon, lat] = lugar.coordenadas;
  return `https://waze.com/ul?ll=${lat},${lon}&navigate=yes`;
}

export function linkOpenStreetMap(lugar: Lugar): string {
  const [lon, lat] = lugar.coordenadas;
  return lugar.osm
    ? `https://www.openstreetmap.org/${lugar.osm}`
    : `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=18/${lat}/${lon}`;
}

/** Roteiro inteiro no Google Maps (origem, paradas intermediárias e destino). */
export function linkGoogleMapsRota(rota: Rota): string {
  const pontos = rota.paradas.map((p) => lugarPorId.get(p.lugar)!).map((l) => `${l.coordenadas[1]},${l.coordenadas[0]}`);
  if (rota.circular) pontos.push(pontos[0]);
  const modo = { 'a-pe': 'walking', carro: 'driving', bicicleta: 'bicycling' }[rota.modo];
  const meio = pontos.slice(1, -1).join('|');
  return (
    `https://www.google.com/maps/dir/?api=1&travelmode=${modo}` +
    `&origin=${encodeURIComponent(pontos[0])}&destination=${encodeURIComponent(pontos[pontos.length - 1])}` +
    (meio ? `&waypoints=${encodeURIComponent(meio)}` : '')
  );
}

export const dataPorExtenso = (iso: string) =>
  new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR', { day: 'numeric', month: 'long', year: 'numeric' });

export const dataCurta = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString('pt-BR');
