// Gera os ícones do aplicativo (PNG) a partir do símbolo em SVG.
// Uso: node scripts/gerar-icones.mjs
import { mkdirSync } from 'node:fs';
import sharp from 'sharp';

const destino = new URL('../public/icones/', import.meta.url);
mkdirSync(destino, { recursive: true });

const desenho = `
  <path d="M24 7.500c-5.800 0-10.500 4.600-10.500 10.300 0 6.900 7.400 13.900 9.600 15.800a1.400 1.400 0 0 0 1.800 0c2.200-1.900 9.600-8.900 9.600-15.800C34.500 12.100 29.800 7.500 24 7.500z" fill="#FFFBF5"/>
  <circle cx="24" cy="17.800" r="4.400" fill="#F0A630"/>
  <path d="M8.500 40.500c3.900-2.700 7.300-2.700 10.300 0 3 2.700 6.400 2.700 10.400 0 3.900-2.700 7.300-2.700 10.300 0" fill="none" stroke="#9FD0DC" stroke-width="2.800" stroke-linecap="round"/>`;

// Ícone comum: quadrado de cantos arredondados.
const comum = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect width="48" height="48" rx="13" fill="#0F766E"/>${desenho}</svg>`;
// Ícone "mascarável": fundo até a borda e desenho na área segura central (80%), como pede o Android.
const mascara = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect width="48" height="48" fill="#0F766E"/><g transform="translate(7.2 7.2) scale(0.7)">${desenho}</g></svg>`;
// Ícone do iOS: fundo opaco, sem cantos (o sistema arredonda).
const ios = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect width="48" height="48" fill="#0F766E"/><g transform="translate(3.6 3.6) scale(0.85)">${desenho}</g></svg>`;

const saidas = [
  ['icone-192.png', comum, 192],
  ['icone-512.png', comum, 512],
  ['icone-mascara-512.png', mascara, 512],
  ['apple-touch-icon.png', ios, 180],
];
for (const [nome, svg, tamanho] of saidas) {
  await sharp(Buffer.from(svg), { density: 600 }).resize(tamanho, tamanho).png({ compressionLevel: 9 }).toFile(new URL(nome, destino).pathname);
  console.log(nome, `${tamanho}px`);
}
