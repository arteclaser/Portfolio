// Núcleo do mapa: MapLibre GL com mapa-base próprio (PMTiles), relevo e tema claro/escuro.
// Nenhum serviço externo é consultado: blocos, fontes e relevo saem do próprio site.
import * as maplibregl from 'maplibre-gl';
import urlTrabalhador from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import 'maplibre-gl/dist/maplibre-gl.css';
import { Protocol } from 'pmtiles';
import { temaAtual } from '../comum';

export { maplibregl };

// Área coberta pelo mapa-base: a região em zoom baixo; o detalhe das ruas vai do entorno de Capinzal até Ouro.
export const AREA_DO_MAPA: [[number, number], [number, number]] = [[-52.04, -27.77], [-51.21, -27.09]];
export const AREA_DO_MUNICIPIO: [[number, number], [number, number]] = [[-51.73722, -27.51966], [-51.532, -27.329]];
export const CENTRO: [number, number] = [-51.611, -27.3441];

const ESTILOS = { claro: '/mapa/estilo-claro.json', escuro: '/mapa/estilo-escuro.json' } as const;

// Textos dos controles em português.
const TEXTOS = {
  'AttributionControl.ToggleAttribution': 'Mostrar ou ocultar os créditos do mapa',
  'AttributionControl.MapFeedback': 'Comentar o mapa',
  'FullscreenControl.Enter': 'Tela cheia',
  'FullscreenControl.Exit': 'Sair da tela cheia',
  'GeolocateControl.FindMyLocation': 'Mostrar onde estou',
  'GeolocateControl.LocationNotAvailable': 'Localização indisponível',
  'LogoControl.Title': 'MapLibre',
  'Map.Title': 'Mapa',
  'Marker.Title': 'Marcador',
  'NavigationControl.ResetBearing': 'Apontar o mapa para o norte',
  'NavigationControl.ZoomIn': 'Aproximar',
  'NavigationControl.ZoomOut': 'Afastar',
  'Popup.Close': 'Fechar',
  'ScaleControl.Meters': 'm',
  'ScaleControl.Kilometers': 'km',
  'TerrainControl.Enable': 'Ativar relevo em 3D',
  'TerrainControl.Disable': 'Desativar relevo em 3D',
  'CooperativeGesturesHandler.WindowsHelpText': 'Use Ctrl + rolagem para aproximar o mapa',
  'CooperativeGesturesHandler.MacHelpText': 'Use ⌘ + rolagem para aproximar o mapa',
  'CooperativeGesturesHandler.MobileHelpText': 'Use dois dedos para mover o mapa',
};

let preparado = false;
function preparar() {
  if (preparado) return;
  preparado = true;
  maplibregl.setWorkerUrl(urlTrabalhador);
  maplibregl.addProtocol('pmtiles', new Protocol().tile);
}

export function temWebGL(): boolean {
  try {
    const tela = document.createElement('canvas');
    return Boolean(tela.getContext('webgl2') || tela.getContext('webgl'));
  } catch {
    return false;
  }
}

export interface OpcoesDoMapa {
  centro?: [number, number];
  zoom?: number;
  limites?: [[number, number], [number, number]];
  /** Margens (px) ocupadas por painéis sobre o mapa. */
  margem?: maplibregl.PaddingOptions;
  /** Mapa pequeno dentro de uma página: não "prende" a rolagem. */
  embutido?: boolean;
  /** Mostra o botão "onde estou". */
  localizacao?: boolean;
}

export interface Mapa {
  mapa: maplibregl.Map;
  /** Executa agora (se o estilo já carregou) e de novo a cada troca de tema. */
  aoEstilo(fn: () => void): void;
  localizador: maplibregl.GeolocateControl | null;
  definir3D(ligado: boolean): void;
}

export function criarMapa(elemento: HTMLElement, opcoes: OpcoesDoMapa = {}): Mapa {
  preparar();
  const mapa = new maplibregl.Map({
    container: elemento,
    style: ESTILOS[temaAtual()],
    center: opcoes.centro ?? CENTRO,
    zoom: opcoes.zoom ?? 13.4,
    bounds: opcoes.limites,
    fitBoundsOptions: opcoes.limites ? { padding: opcoes.margem ?? 48, maxZoom: 16 } : undefined,
    minZoom: 8.6,
    maxZoom: 18.4,
    maxPitch: 72,
    maxBounds: AREA_DO_MAPA,
    attributionControl: false,
    cooperativeGestures: opcoes.embutido ?? false,
    locale: TEXTOS,
    // O relevo vem em blocos de 256 px; manter poucos em memória basta para a região.
    maxTileCacheZoomLevels: 4,
  });

  mapa.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');
  mapa.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');

  let localizador: maplibregl.GeolocateControl | null = null;
  if (opcoes.localizacao !== false && 'geolocation' in navigator) {
    localizador = new maplibregl.GeolocateControl({
      positionOptions: { enableHighAccuracy: true, timeout: 12000 },
      trackUserLocation: true,
      showAccuracyCircle: true,
      fitBoundsOptions: { maxZoom: 15.5 },
    });
    mapa.addControl(localizador, 'top-right');
  }

  // Botões próprios: relevo em 3D e "ver o município inteiro".
  let em3D = false;
  const botao3D = document.createElement('button');
  const definir3D = (ligado: boolean) => {
    em3D = ligado;
    botao3D.setAttribute('aria-pressed', String(ligado));
    botao3D.setAttribute('aria-label', ligado ? 'Voltar ao mapa plano' : 'Ver o relevo em 3D');
    botao3D.title = ligado ? 'Voltar ao mapa plano' : 'Ver o relevo em 3D';
    if (!mapa.isStyleLoaded() && !mapa.getSource('relevo')) return;
    mapa.setTerrain(ligado ? { source: 'relevo', exaggeration: 1.35 } : null);
    mapa.easeTo({ pitch: ligado ? 58 : 0, bearing: ligado ? mapa.getBearing() || -18 : 0, duration: 900 });
  };
  mapa.addControl(
    {
      onAdd() {
        const grupo = document.createElement('div');
        grupo.className = 'maplibregl-ctrl maplibregl-ctrl-group';
        botao3D.type = 'button';
        botao3D.className = 'ctrl-3d';
        botao3D.textContent = '3D';
        botao3D.addEventListener('click', () => definir3D(!em3D));
        const inicio = document.createElement('button');
        inicio.type = 'button';
        inicio.className = 'ctrl-inicio';
        inicio.title = 'Ver o município inteiro';
        inicio.setAttribute('aria-label', 'Ver o município inteiro');
        inicio.innerHTML =
          '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>';
        inicio.addEventListener('click', () => mapa.fitBounds(AREA_DO_MUNICIPIO, { padding: mapa.getPadding(), pitch: em3D ? 58 : 0, duration: 900 }));
        grupo.append(botao3D, inicio);
        return grupo;
      },
      onRemove() {},
    },
    'top-right',
  );
  definir3D(false);

  mapa.addControl(new maplibregl.ScaleControl({ unit: 'metric', maxWidth: 110 }), 'bottom-left');

  // Troca de tema: o estilo muda e as camadas próprias (rotas) são recolocadas.
  const aoCarregarEstilo: (() => void)[] = [];
  mapa.on('style.load', () => {
    if (em3D) mapa.setTerrain({ source: 'relevo', exaggeration: 1.35 });
    for (const fn of aoCarregarEstilo) fn();
  });
  document.addEventListener('tema', () => mapa.setStyle(ESTILOS[temaAtual()]));

  return {
    mapa,
    localizador,
    definir3D,
    aoEstilo(fn) {
      aoCarregarEstilo.push(fn);
      if (mapa.isStyleLoaded()) fn();
    },
  };
}

/** Desenha um roteiro: contorno claro por baixo e linha colorida por cima. */
export function desenharRota(
  mapa: maplibregl.Map,
  id: string,
  geometria: [number, number][],
  cor: string,
  opcoes: { destaque?: boolean; tracejada?: boolean } = {},
) {
  const fonte = `rota-${id}`;
  if (mapa.getSource(fonte)) return;
  mapa.addSource(fonte, {
    type: 'geojson',
    data: { type: 'Feature', properties: { id }, geometry: { type: 'LineString', coordinates: geometria } },
  });
  const destaque = opcoes.destaque ?? false;
  // As linhas ficam abaixo dos nomes das ruas e lugares.
  const antes = mapa.getStyle().layers.find((camada) => camada.type === 'symbol')?.id;
  mapa.addLayer(
    {
      id: `${fonte}-contorno`,
      type: 'line',
      source: fonte,
      layout: { 'line-join': 'round', 'line-cap': 'round' },
      paint: {
        'line-color': '#FFFFFF',
        'line-opacity': destaque ? 0.95 : 0.7,
        'line-width': ['interpolate', ['linear'], ['zoom'], 10, destaque ? 5 : 3.5, 14, destaque ? 9 : 6, 17, destaque ? 13 : 9],
      },
    },
    antes,
  );
  mapa.addLayer(
    {
      id: `${fonte}-linha`,
      type: 'line',
      source: fonte,
      layout: { 'line-join': 'round', 'line-cap': 'round' },
      paint: {
        'line-color': cor,
        'line-opacity': destaque ? 1 : 0.78,
        'line-width': ['interpolate', ['linear'], ['zoom'], 10, destaque ? 3 : 2, 14, destaque ? 5.5 : 3.5, 17, destaque ? 8 : 5.5],
        ...(opcoes.tracejada ? { 'line-dasharray': [0.2, 1.8] } : {}),
      },
    },
    antes,
  );
}
