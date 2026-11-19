// Gera public/mapa/limite.geojson a partir do limite municipal (relação 296724 do OpenStreetMap,
// baixada pelo Nominatim): uma "máscara" que escurece o que fica fora de Capinzal e a linha do limite.
import { readFileSync, writeFileSync } from 'node:fs';

const origem = JSON.parse(readFileSync(new URL('../../src/data/geo/limite-capinzal-osm.json', import.meta.url), 'utf8'));
const geometria = origem.features[0].geometry;
if (geometria.type !== 'Polygon') throw new Error('Esperado um polígono simples para o limite municipal.');

const arredondar = (p) => [Math.round(p[0] * 1e5) / 1e5, Math.round(p[1] * 1e5) / 1e5];
const anel = geometria.coordinates[0].map(arredondar);
// Retângulo bem maior que a área do mapa, com o município como "furo".
const mundo = [[-56, -31], [-56, -23], [-47, -23], [-47, -31], [-56, -31]];

const saida = {
  type: 'FeatureCollection',
  features: [
    { type: 'Feature', properties: { tipo: 'mascara' }, geometry: { type: 'Polygon', coordinates: [mundo, anel] } },
    { type: 'Feature', properties: { tipo: 'limite' }, geometry: { type: 'LineString', coordinates: anel } },
  ],
};
writeFileSync(new URL('../../public/mapa/limite.geojson', import.meta.url), JSON.stringify(saida));

const lons = anel.map((p) => p[0]);
const lats = anel.map((p) => p[1]);
console.log(`limite.geojson: ${anel.length} vértices · caixa [${Math.min(...lons)}, ${Math.min(...lats)}, ${Math.max(...lons)}, ${Math.max(...lats)}]`);
