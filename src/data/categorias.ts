// Categorias do guia. Cada uma tem cor e ícone próprios: a cor nunca é o único sinal.
// Contrastes (WCAG 2.2, 1.4.3): texto branco sobre "cor" ≥ 5,3:1; "corEscuro" sobre o fundo escuro ≥ 8,4:1.
export const categorias = [
  {
    id: 'historia',
    nome: 'História e cultura',
    curto: 'História',
    icone: 'landmark',
    cor: '#A2452A',
    corEscuro: '#F2987A',
    descricao: 'Patrimônio, memória, museus, pontes e prédios que contam a história do município.',
  },
  {
    id: 'fe',
    nome: 'Fé e devoção',
    curto: 'Fé',
    icone: 'church',
    cor: '#6A4BA6',
    corEscuro: '#BDA8F0',
    descricao: 'Igrejas, capelas e lugares de devoção na cidade e no interior.',
  },
  {
    id: 'natureza',
    nome: 'Natureza e lazer',
    curto: 'Natureza',
    icone: 'trees',
    cor: '#2C7438',
    corEscuro: '#7FCB8A',
    descricao: 'Rio do Peixe, praças, áreas de lazer, paisagens e espaços ao ar livre.',
  },
  {
    id: 'sabores',
    nome: 'Onde comer',
    curto: 'Comer',
    icone: 'utensils',
    cor: '#A85400',
    corEscuro: '#F2B15C',
    descricao: 'Restaurantes, cafés, padarias e produtos locais.',
  },
  {
    id: 'hospedagem',
    nome: 'Onde ficar',
    curto: 'Ficar',
    icone: 'bed-double',
    cor: '#1E5FAF',
    corEscuro: '#8BB9F3',
    descricao: 'Hotéis e pousadas.',
  },
  {
    id: 'apoio',
    nome: 'Apoio ao visitante',
    curto: 'Apoio',
    icone: 'info',
    cor: '#4A5A63',
    corEscuro: '#B3C2CA',
    descricao: 'Rodoviária, serviços públicos e pontos de referência para quem chega.',
  },
] as const;

export type CategoriaId = (typeof categorias)[number]['id'];
export type Categoria = (typeof categorias)[number];

export const categoriaPorId = Object.fromEntries(categorias.map((c) => [c.id, c])) as Record<CategoriaId, Categoria>;
