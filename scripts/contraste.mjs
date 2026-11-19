// Calcula a razão de contraste (WCAG 2.x) entre pares de cores. Uso: node scripts/contraste.mjs '#fff' '#0F6B5C' ...
export function luminancia(hex) {
  const n = hex.replace('#', '');
  const [r, g, b] = [0, 2, 4].map((i) => parseInt(n.slice(i, i + 2), 16) / 255)
    .map((c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}
export function contraste(a, b) {
  const [l1, l2] = [luminancia(a), luminancia(b)].sort((x, y) => y - x);
  return (l1 + 0.05) / (l2 + 0.05);
}
if (import.meta.url === `file://${process.argv[1]}`) {
  const cores = process.argv.slice(2);
  for (let i = 0; i + 1 < cores.length; i += 2) {
    console.log(cores[i], 'sobre', cores[i + 1], '→', contraste(cores[i], cores[i + 1]).toFixed(2) + ':1');
  }
}
