// As cores de categorias e roteiros precisam manter o contraste mínimo da WCAG 2.2 (4,5:1 para texto).
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { test } from 'node:test';
import { contraste } from '../../scripts/contraste.mjs';
import { categorias } from '../../src/data/categorias.ts';

const FUNDO_CLARO = '#FAF7F0';
const FUNDO_ESCURO = '#0E1513';

test('contraste de referência: preto sobre branco é 21:1', () => {
  assert.equal(Math.round(contraste('#000000', '#FFFFFF')), 21);
});

for (const categoria of categorias) {
  test(`categoria "${categoria.nome}": texto e marcador legíveis nos dois temas`, () => {
    assert.ok(contraste('#FFFFFF', categoria.cor) >= 4.5, 'ícone branco sobre a cor (marcador do mapa)');
    assert.ok(contraste(categoria.cor, FUNDO_CLARO) >= 4.5, 'cor sobre o fundo claro');
    assert.ok(contraste(categoria.corEscuro, FUNDO_ESCURO) >= 4.5, 'cor sobre o fundo escuro');
  });
}

const pasta = new URL('../../src/data/rotas/', import.meta.url);
for (const arquivo of readdirSync(pasta).filter((f) => f.endsWith('.json'))) {
  const rota = JSON.parse(readFileSync(new URL(arquivo, pasta), 'utf8'));
  test(`roteiro "${rota.nome}": número da parada legível sobre a cor do roteiro`, () => {
    assert.ok(contraste('#FFFFFF', rota.cor) >= 4.5);
    assert.ok(contraste(rota.corEscuro, FUNDO_ESCURO) >= 4.5);
  });
}
