// Gera os estilos do mapa (claro e escuro) em public/mapa/, a partir das camadas do
// Protomaps Basemaps, com as cores da identidade do aplicativo.
// Uso: node scripts/geo/gerar-estilo.mjs
import { writeFileSync } from 'node:fs';
import { layers, namedFlavor } from '@protomaps/basemaps';

const FONTES = { regular: 'noto-sans-regular', bold: 'noto-sans-medium', italic: 'noto-sans-italic' };

const claro = {
  ...namedFlavor('light'),
  ...FONTES,
  background: '#EAF1DC',
  earth: '#F2F4E3',
  park_a: '#BFE3A4',
  park_b: '#A8D98C',
  wood_a: '#B8DDA2',
  wood_b: '#A3D08D',
  scrub_a: '#D2E8B8',
  scrub_b: '#C3DFA6',
  hospital: '#FADADB',
  industrial: '#EAE4D6',
  school: '#F6ECCB',
  pedestrian: '#F3EBD9',
  sand: '#F3E3B4',
  beach: '#F3E3B4',
  water: '#6FC4E3',
  buildings: '#E6D9C3',
  pier: '#E6DFD1',
  minor_service_casing: '#D9CDB4',
  minor_casing: '#D2C5A8',
  link_casing: '#D2C5A8',
  major_casing_early: '#DDAE45',
  major_casing_late: '#DDAE45',
  highway_casing_early: '#D98A24',
  highway_casing_late: '#D98A24',
  other: '#EFE9DC',
  minor_service: '#FBF9F3',
  minor_a: '#FFFFFF',
  minor_b: '#FFFFFF',
  link: '#FFFFFF',
  major: '#FFE89C',
  highway: '#FFC24D',
  railway: '#B9B1A2',
  boundaries: '#9A8F7C',
  bridges_other_casing: '#D5CCBA',
  bridges_minor_casing: '#D5CCBA',
  bridges_link_casing: '#D5CCBA',
  bridges_major_casing: '#D3A53E',
  bridges_highway_casing: '#CC7F1E',
  bridges_other: '#EFE9DC',
  bridges_minor: '#FFFFFF',
  bridges_link: '#FFFFFF',
  bridges_major: '#FFE89C',
  bridges_highway: '#FFC24D',
  roads_label_minor: '#56605A',
  roads_label_minor_halo: '#FFFFFF',
  roads_label_major: '#4E554E',
  roads_label_major_halo: '#FFFFFF',
  ocean_label: '#0B6E8F',
  subplace_label: '#5E6A63',
  subplace_label_halo: '#F7F8EC',
  city_label: '#1F2B27',
  city_label_halo: '#F7F8EC',
  state_label: '#8A9089',
  state_label_halo: '#F7F8EC',
  country_label: '#7A8079',
  address_label: '#8A9089',
  address_label_halo: '#F7F8EC',
  landcover: {
    barren: '#F1EBD6',
    farmland: '#EFF2D2',
    forest: '#B4DBA0',
    glacier: '#FFFFFF',
    grassland: '#D6EBB6',
    scrub: '#C9E3AE',
    urban_area: '#F2E8D4',
  },
};

const escuro = {
  ...namedFlavor('dark'),
  ...FONTES,
  background: '#0F1715',
  earth: '#131D1A',
  park_a: '#17402B',
  park_b: '#1B4A31',
  wood_a: '#153A27',
  wood_b: '#18442D',
  scrub_a: '#172A24',
  scrub_b: '#192E27',
  hospital: '#2A1F22',
  industrial: '#1A2421',
  school: '#1E2620',
  pedestrian: '#1A2421',
  sand: '#25261C',
  beach: '#25261C',
  water: '#0E5A78',
  buildings: '#1E2B27',
  pier: '#22302B',
  minor_service_casing: '#101816',
  minor_casing: '#101816',
  link_casing: '#101816',
  major_casing_early: '#101816',
  major_casing_late: '#101816',
  highway_casing_early: '#101816',
  highway_casing_late: '#101816',
  other: '#22302B',
  minor_service: '#24332E',
  minor_a: '#2B3C36',
  minor_b: '#2B3C36',
  link: '#33463F',
  major: '#44594F',
  highway: '#7A6427',
  railway: '#3A4A44',
  boundaries: '#5B6D66',
  bridges_other_casing: '#0C1311',
  bridges_minor_casing: '#0C1311',
  bridges_link_casing: '#0C1311',
  bridges_major_casing: '#0C1311',
  bridges_highway_casing: '#0C1311',
  bridges_other: '#22302B',
  bridges_minor: '#2B3C36',
  bridges_link: '#33463F',
  bridges_major: '#3B5048',
  bridges_highway: '#5A5334',
  roads_label_minor: '#93A39C',
  roads_label_minor_halo: '#131D1A',
  roads_label_major: '#B4C2BC',
  roads_label_major_halo: '#131D1A',
  ocean_label: '#6FB7C6',
  subplace_label: '#9FB0A9',
  subplace_label_halo: '#131D1A',
  city_label: '#E3ECE8',
  city_label_halo: '#131D1A',
  state_label: '#6F8079',
  state_label_halo: '#131D1A',
  country_label: '#7F9089',
  address_label: '#6F8079',
  address_label_halo: '#131D1A',
  landcover: {
    barren: '#1A211C',
    farmland: '#151F1B',
    forest: '#143524',
    glacier: '#1F2A27',
    grassland: '#16241D',
    scrub: '#16261F',
    urban_area: '#18221F',
  },
};

function estilo(nome, sabor, tema) {
  // Os ícones de comércio do mapa-base saem: quem aparece são os lugares do guia.
  // As setas de mão única e os escudos de rodovia dependem de uma folha de ícones, que não usamos.
  const semIcones = new Set(['pois', 'roads_oneway', 'roads_shields']);
  const base = layers('base', sabor, { lang: 'pt' }).filter((camada) => !semIcones.has(camada.id));
  const antesDe = (id) => {
    const i = base.findIndex((camada) => camada.id === id);
    if (i < 0) throw new Error(`Camada "${id}" não encontrada no estilo-base.`);
    return i;
  };

  // Sombreado do relevo: logo acima do uso do solo, abaixo de água, ruas e nomes.
  const relevo = {
    id: 'relevo-sombreado',
    type: 'hillshade',
    source: 'relevo',
    paint: {
      'hillshade-exaggeration': tema === 'escuro' ? 0.38 : 0.32,
      'hillshade-shadow-color': tema === 'escuro' ? '#000000' : '#3F5A2E',
      'hillshade-highlight-color': tema === 'escuro' ? '#3F5A50' : '#FFFFFF',
      'hillshade-accent-color': tema === 'escuro' ? '#000000' : '#6B7F52',
      'hillshade-illumination-direction': 315,
    },
  };
  base.splice(antesDe('water'), 0, relevo);

  const mascara = {
    id: 'fora-do-municipio',
    type: 'fill',
    source: 'limite',
    filter: ['==', ['get', 'tipo'], 'mascara'],
    paint: {
      'fill-color': tema === 'escuro' ? '#070B0A' : '#FFFFFF',
      'fill-opacity': ['interpolate', ['linear'], ['zoom'], 9, 0.62, 13, 0.5, 15, 0.28, 16.5, 0],
    },
  };
  const limiteBrilho = {
    id: 'limite-municipal-brilho',
    type: 'line',
    source: 'limite',
    filter: ['==', ['get', 'tipo'], 'limite'],
    layout: { 'line-join': 'round' },
    paint: {
      'line-color': tema === 'escuro' ? '#2DD4BF' : '#F59E0B',
      'line-opacity': 0.28,
      'line-width': ['interpolate', ['linear'], ['zoom'], 9, 5, 13, 12, 16, 18],
      'line-blur': 4,
    },
  };
  const limite = {
    id: 'limite-municipal',
    type: 'line',
    source: 'limite',
    filter: ['==', ['get', 'tipo'], 'limite'],
    layout: { 'line-join': 'round', 'line-cap': 'round' },
    paint: {
      'line-color': tema === 'escuro' ? '#5EEAD4' : '#0F766E',
      'line-opacity': 0.95,
      'line-width': ['interpolate', ['linear'], ['zoom'], 9, 1.4, 13, 2, 16, 2.6],
      'line-dasharray': [2.2, 2],
    },
  };
  // Máscara e limite ficam abaixo dos nomes (primeira camada de texto).
  const primeiroRotulo = base.findIndex((camada) => camada.type === 'symbol');
  base.splice(primeiroRotulo, 0, mascara, limiteBrilho, limite);

  return {
    version: 8,
    name: nome,
    glyphs: '/mapa/fontes/{fontstack}/{range}.pbf',
    sources: {
      base: {
        type: 'vector',
        url: 'pmtiles:///mapa/capinzal.pmtiles',
        attribution: '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
      },
      relevo: {
        type: 'raster-dem',
        url: 'pmtiles:///mapa/relevo.pmtiles',
        encoding: 'terrarium',
        tileSize: 256,
        attribution: '<a href="/sobre/#dados">Protomaps, SRTM</a>',
      },
      limite: { type: 'geojson', data: '/mapa/limite.geojson' },
    },
    layers: base,
  };
}

for (const [arquivo, nome, sabor, tema] of [
  ['estilo-claro.json', 'Capinzal (claro)', claro, 'claro'],
  ['estilo-escuro.json', 'Capinzal (escuro)', escuro, 'escuro'],
]) {
  const json = JSON.stringify(estilo(nome, sabor, tema));
  writeFileSync(new URL(`../../public/mapa/${arquivo}`, import.meta.url), json);
  console.log(arquivo, `${(json.length / 1024).toFixed(1)} kB`);
}
