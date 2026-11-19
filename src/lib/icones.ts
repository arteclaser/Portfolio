// Ícones Lucide (licença ISC), lidos do pacote na hora de montar o site e embutidos como SVG.
const arquivos = import.meta.glob<string>('/node_modules/lucide-static/icons/*.svg', { query: '?raw', import: 'default', eager: false });

const cache = new Map<string, string>();

/** Conteúdo interno do SVG (caminhos), sem a tag externa. */
export async function miolo(nome: string): Promise<string> {
  if (cache.has(nome)) return cache.get(nome)!;
  const carregar = arquivos[`/node_modules/lucide-static/icons/${nome}.svg`];
  if (!carregar) throw new Error(`Ícone não encontrado no Lucide: ${nome}`);
  const svg = await carregar();
  const dentro = svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '').replace(/\s+/g, ' ').trim();
  cache.set(nome, dentro);
  return dentro;
}
