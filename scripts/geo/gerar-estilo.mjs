// Gera os estilos do mapa (claro e escuro) em public/mapa/, a partir das camadas do
// Protomaps Basemaps, com as cores da identidade do aplicativo.
// Uso: node scripts/geo/gerar-estilo.mjs
import { writeFileSync } from 'node:fs';
import { layers, namedFlavor } from '@protomaps/basemaps';

const FONTES = { regular: 'noto-sans-regular', bold: 'noto-sans-medium', italic: 'noto-sans-italic' };

const claro = {
  ...namedFlavor('light'),
  ...FONTES,
  background: '#EDE7D9',
  earth: '#F0EADC',
  park_a: '#D5E6C3',
  park_b: '#C6DEB0',
  wood_a: '#D3E3C2',
  wood_b: '#C3D9B0',
  scrub_a: '#DDE8CB',
  scrub_b: '#D2E1BD',
  hospital: '#F3E3E0',
  industrial: '#E9E4DA',
  school: '#EFE9D6',
  pedestrian: '#EFE9DC',
  sand: '#EDE3C8',
  beach: '#EDE3C8',
  water: '#93C9D8',
  buildings: '#DCD2BF',
  pier: '#E6DFD1',
  minor_service_casing: '#D6CCB8',
  minor_casing: '#CFC4AD',
  link_casing: '#CFC4AD',
  major_casing_early: '#C9B88F',
  major_casing_late: '#C9B88F',
  highway_casing_early: '#CFA94F',
  highway_casing_late: '#CFA94F',
  other: '#EFE9DC',
  minor_service: '#FBF9F3',
  minor_a: '#FFFFFF',
  minor_b: '#FFFFFF',
  link: '#FFFFFF',
  major: '#FFF3CF',
  highway: '#FFDD8C',
  railway: '#B9B1A2',
  boundaries: '#9A8F7C',
  bridges_other_casing: '#D5CCBA',
  bridges_minor_casing: '#D5CCBA',
  bridges_link_casing: '#D5CCBA',
  bridges_major_casing: '#D3C4A3',
  bridges_highway_casing: '#D1AE58',
  bridges_other: '#EFE9DC',
  bridges_minor: '#FFFFFF',
  bridges_link: '#FFFFFF',
  bridges_major: '#FFF3CF',
  bridges_highway: '#FFDD8C',
  roads_label_minor: '#5C635C',
  roads_label_minor_halo: '#FFFFFF',
  roads_label_major: '#4E554E',
  roads_label_major_halo: '#FFFFFF',
  ocean_label: '#2F7F90',
  subplace_label: '#5E6A63',
  subplace_label_halo: '#F0EADC',
  city_label: '#2B3531',
  city_label_halo: '#F0EADC',
  state_label: '#8A9089',
  state_label_halo: '#F0EADC',
  country_label: '#7A8079',
  address_label: '#8A9089',
  address_label_halo: '#F0EADC',
  landcover: {
    barren: '#EFE9DA',
    farmland: '#EFEAD8',
    forest: '#CFE1BD',
    glacier: '#FFFFFF',
    grassland: '#E0EACD',
    scrub: '#D9E5C6',
    urban_area: '#E8E0CF',
  },
};

const escuro = {
  ...namedFlavor('dark'),
  ...FONTES,
  background: '#0F1715',
  earth: '#131D1A',
  park_a: '#17302A',
  park_b: '#1A382F',
  wood_a: '#162C26',
  wood_b: '#19342C',
  scrub_a: '#172A24',
  scrub_b: '#192E27',
  hospital: '#2A1F22',
  industrial: '#1A2421',
  school: '#1E2620',
  pedestrian: '#1A2421',
  sand: '#25261C',
  beach: '#25261C',
  water: '#0F3D47',
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
  major: '#3B5048',
  highway: '#5A5334',
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
    forest: '#15291F',
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
      'hillshade-shadow-color': tema === 'escuro' ? '#000000' : '#4A5A3C',
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
      'fill-color': tema === 'escuro' ? '#070B0A' : '#F7F4EC',
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
      'line-color': tema === 'escuro' ? '#2FBF9F' : '#0F6B5C',
      'line-opacity': 0.14,
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
      'line-color': tema === 'escuro' ? '#3CCBAA' : '#0F6B5C',
      'line-opacity': 0.85,
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
