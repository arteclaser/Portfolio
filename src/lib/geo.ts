/** Distância em metros entre dois pontos [lon, lat] (fórmula de haversine, raio médio da Terra). */
export function distanciaM(a: readonly [number, number], b: readonly [number, number]): number {
  const R = 6371008.8;
  const rad = Math.PI / 180;
  const dLat = (b[1] - a[1]) * rad;
  const dLon = (b[0] - a[0]) * rad;
  const s = Math.sin(dLat / 2) ** 2 + Math.cos(a[1] * rad) * Math.cos(b[1] * rad) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.min(1, Math.sqrt(s)));
}

/** "850 m", "1,2 km", "38 km". */
export function formatarDistancia(metros: number): string {
  if (metros < 950) return `${Math.round(metros / 10) * 10} m`;
  const km = metros / 1000;
  return `${km < 10 ? km.toFixed(1).replace('.', ',') : Math.round(km)} km`;
}

/** "25 min", "1 h 10 min", "2 h". */
export function formatarDuracao(segundos: number): string {
  const min = Math.max(1, Math.round(segundos / 60));
  if (min < 60) return `${min} min`;
  const h = Math.floor(min / 60);
  const resto = Math.round((min % 60) / 5) * 5;
  if (resto === 60) return `${h + 1} h`;
  return resto ? `${h} h ${resto} min` : `${h} h`;
}

/** Texto sem acentos e em minúsculas, para a busca. */
export function normalizar(texto: string): string {
  return String(texto).normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim();
}
