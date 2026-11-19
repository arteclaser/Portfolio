// Traçados dos roteiros para o mapa (arquivo estático gerado na montagem do site).
import type { APIRoute } from 'astro';
import { rotas, urlRota } from '../../lib/dados';

export const GET: APIRoute = () =>
  new Response(
    JSON.stringify(
      rotas.map((rota) => ({ id: rota.id, nome: rota.nome, cor: rota.cor, corEscuro: rota.corEscuro, geometria: rota.geometria, url: urlRota(rota.id) })),
    ),
    { headers: { 'Content-Type': 'application/json; charset=utf-8' } },
  );
