// Testes das funções de distância e formatação usadas no mapa e nas páginas.
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { distanciaM, formatarDistancia, formatarDuracao, normalizar } from '../../src/lib/geo.ts';

test('distância entre a Igreja Matriz e a Ponte Pênsil fica perto de 370 m', () => {
  const metros = distanciaM([-51.61255, -27.34425], [-51.61602, -27.34296]);
  assert.ok(metros > 350 && metros < 390, `obtido: ${metros}`);
});

test('um grau de latitude mede cerca de 111 km', () => {
  const metros = distanciaM([-51.6, -27], [-51.6, -28]);
  assert.ok(Math.abs(metros - 111195) < 200, `obtido: ${metros}`);
});

test('distâncias curtas em metros, longas em quilômetros com vírgula', () => {
  assert.equal(formatarDistancia(64), '60 m');
  assert.equal(formatarDistancia(848), '850 m');
  assert.equal(formatarDistancia(1234), '1,2 km');
  assert.equal(formatarDistancia(2490), '2,5 km');
  assert.equal(formatarDistancia(27230), '27 km');
});

test('durações em minutos e horas', () => {
  assert.equal(formatarDuracao(20), '1 min');
  assert.equal(formatarDuracao(33 * 60), '33 min');
  assert.equal(formatarDuracao(70 * 60), '1 h 10 min');
  assert.equal(formatarDuracao(120 * 60), '2 h');
  assert.equal(formatarDuracao(119 * 60), '2 h');
});

test('a busca ignora acentos e maiúsculas', () => {
  assert.equal(normalizar('  Ponte PÊNSIL '), 'ponte pensil');
  assert.equal(normalizar('São Cristóvão'), 'sao cristovao');
  assert.equal(normalizar('Área de Lazer'), 'area de lazer');
});
