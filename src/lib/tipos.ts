import type { CategoriaId } from '../data/categorias';

/** De onde veio uma informação. Todo lugar publicado tem pelo menos uma fonte. */
export interface Fonte {
  titulo: string;
  url: string;
  tipo: 'oficial' | 'imprensa' | 'enciclopedia' | 'mapa' | 'proprio' | 'plataforma' | 'base' | 'acervo' | 'outro';
  /** Data em que a página foi consultada (AAAA-MM-DD). */
  acesso: string;
}

export interface Imagem {
  /** Nome do arquivo em src/assets/lugares/. */
  arquivo: string;
  alt: string;
  autor: string;
  licenca: string;
  /** Página de origem da imagem (onde constam autoria e licença). */
  origem: string;
  ano?: string;
  legenda?: string;
}

export interface Contato {
  telefone?: string;
  whatsapp?: string;
  site?: string;
  instagram?: string;
  facebook?: string;
  email?: string;
}

export interface Lugar {
  id: string;
  nome: string;
  nomeCurto?: string;
  /** Outros nomes pelos quais o lugar é conhecido (entram na busca). */
  outrosNomes?: string[];
  categoria: CategoriaId;
  /** Rótulo curto do tipo de lugar: "Igreja matriz", "Ponte pênsil", "Museu"... */
  tipo: string;
  /** Uma frase. Aparece nos cartões, na busca e no compartilhamento. */
  resumo: string;
  /** Parágrafos factuais, só com o que as fontes afirmam. */
  descricao: string[];
  municipio: string;
  localidade?: string;
  endereco?: string;
  /** [longitude, latitude] em WGS84. */
  coordenadas: [number, number];
  /** "exata": ponto mapeado do próprio lugar. "aproximada": rua, quadra ou comunidade. */
  precisao: 'exata' | 'aproximada';
  /** Como a posição foi obtida, em texto para o leitor. */
  origemCoordenadas: string;
  /** Elemento do OpenStreetMap, quando houver: "way/123", "node/456". */
  osm?: string;
  horario?: string;
  entrada?: string;
  contato?: Contato;
  /**
   * Só para comércio e hospedagem. "confirmado": há registro público datado de 2025 ou 2026 em funcionamento.
   * "a-confirmar": o lugar consta em duas ou mais fontes, mas sem registro recente de funcionamento.
   */
  situacao?: 'confirmado' | 'a-confirmar';
  /** Qual foi o registro mais recente de funcionamento (ex.: "notícia de maio de 2026"). */
  evidencia?: string;
  /** Prioridade no mapa: 3 = cartão-postal, 2 = importante, 1 = normal. */
  destaque: 1 | 2 | 3;
  imagens?: Imagem[];
  fontes: Fonte[];
  /** Data da última conferência das informações (AAAA-MM-DD). */
  conferidoEm: string;
  /** Aviso ao visitante: algo a confirmar antes de ir. */
  aviso?: string;
}

export type Modo = 'a-pe' | 'carro' | 'bicicleta';

export interface RotaDefinicao {
  id: string;
  nome: string;
  nomeCurto: string;
  resumo: string;
  descricao: string[];
  modo: Modo;
  cor: string;
  corEscuro: string;
  /** Lugares na ordem de visita. */
  paradas: { lugar: string; nota?: string }[];
  /** Volta ao ponto de partida. */
  circular: boolean;
  aviso?: string;
  ordem: number;
}

/** Resultado do cálculo do traçado (scripts/geo/calcular-rotas.mjs). */
export interface RotaGerada {
  id: string;
  distanciaM: number;
  duracaoS: number;
  /** Traçado pelas ruas: lista de [longitude, latitude]. */
  geometria: [number, number][];
  /** Um trecho entre cada par de paradas consecutivas. */
  trechos: { distanciaM: number; duracaoS: number }[];
  /** Perfil de elevação: distância acumulada (m) e altitude (m). */
  perfil: { d: number; e: number }[];
  subidaM: number;
  descidaM: number;
  altitudeMin: number;
  altitudeMax: number;
  /** Distância acumulada (m) em que fica cada parada. */
  paradasEm: number[];
  motor: string;
  geradoEm: string;
}

export interface Rota extends RotaDefinicao, RotaGerada {}
