// Dados gerais do site, usados em títulos, metadados e no manifesto do aplicativo.
export const site = {
  nome: 'Mostraqui Capinzal',
  nomeCurto: 'Mostraqui',
  lema: 'Mapa turístico de Capinzal',
  descricao:
    'Mapa turístico interativo de Capinzal (SC): lugares para visitar, onde comer, onde ficar e roteiros traçados pelas ruas. Funciona no celular, inclusive sem internet.',
  url: 'https://mostraqui.net',
  // Canal para correções e sugestões.
  contato: 'contato@mostraqui.net',
  corTema: '#0F6B5C',
  corFundo: '#FAF7F0',
  // Centro da cidade e área coberta pelo mapa próprio.
  centro: [-51.6110, -27.3441] as [number, number],
  areaDoMapa: [-51.80, -27.57, -51.46, -27.27] as [number, number, number, number],
  areaDoMunicipio: [-51.73722, -27.51966, -51.532, -27.329] as [number, number, number, number],
};

export const navegacao = [
  { id: 'mapa', rotulo: 'Mapa', url: '/', icone: 'map' },
  { id: 'rotas', rotulo: 'Roteiros', url: '/rotas/', icone: 'route' },
  { id: 'lugares', rotulo: 'Lugares', url: '/lugares/', icone: 'map-pin' },
  { id: 'capinzal', rotulo: 'Capinzal', url: '/capinzal/', icone: 'book-open' },
  { id: 'sobre', rotulo: 'Sobre', url: '/sobre/', icone: 'info' },
] as const;

export type PaginaAtual = (typeof navegacao)[number]['id'] | null;
