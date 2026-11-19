// Categorias do guia. Cada uma tem cor e ícone próprios: a cor nunca é o único sinal.
// Contrastes (WCAG 2.2, 1.4.3): texto branco sobre "cor" ≥ 5,0:1; "corEscuro" sobre o fundo escuro ≥ 9,8:1.
export const categorias = [
  {
    id: 'historia',
    nome: 'História e cultura',
    curto: 'História',
    icone: 'landmark',
    cor: '#BE123C',
    corEscuro: '#FDA4AF',
    descricao: 'Patrimônio, memória, museus, pontes e prédios que contam a história do município.',
  },
  {
    id: 'fe',
    nome: 'Fé e devoção',
    curto: 'Fé',
    icone: 'church',
    cor: '#7C3AED',
    corEscuro: '#C4B5FD',
    descricao: 'Igrejas, capelas e lugares de devoção na cidade e no interior.',
  },
  {
    id: 'natureza',
    nome: 'Natureza e lazer',
    curto: 'Natureza',
    icone: 'trees',
    cor: '#15803D',
    corEscuro: '#86EFAC',
    descricao: 'Rio do Peixe, praças, áreas de lazer, paisagens e espaços ao ar livre.',
  },
  {
    id: 'sabores',
    nome: 'Onde comer',
    curto: 'Comer',
    icone: 'utensils',
    cor: '#C2410C',
    corEscuro: '#FDBA74',
    descricao: 'Restaurantes, cafés, padarias e produtos locais.',
  },
  {
    id: 'hospedagem',
    nome: 'Onde ficar',
    curto: 'Ficar',
    icone: 'bed-double',
    cor: '#1D4ED8',
    corEscuro: '#93C5FD',
    descricao: 'Hotéis e pousadas.',
  },
  {
    id: 'apoio',
    nome: 'Apoio ao visitante',
    curto: 'Apoio',
    icone: 'info',
    cor: '#0E7490',
    corEscuro: '#67E8F9',
    descricao: 'Rodoviária, serviços públicos e pontos de referência para quem chega.',
  },
] as const;

export type CategoriaId = (typeof categorias)[number]['id'];
export type Categoria = (typeof categorias)[number];

export const categoriaPorId = Object.fromEntries(categorias.map((c) => [c.id, c])) as Record<CategoriaId, Categoria>;
