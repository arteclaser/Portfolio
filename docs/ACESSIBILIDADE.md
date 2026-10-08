# Acessibilidade

Meta: **WCAG 2.2, nível AA** ([W3C](https://www.w3.org/TR/WCAG22/)). Base legal para órgãos
públicos: art. 63 da Lei nº 13.146/2015 (Lei Brasileira de Inclusão), que torna obrigatória a
acessibilidade nos sítios mantidos por órgãos de governo, "conforme as melhores práticas e
diretrizes de acessibilidade adotadas internacionalmente", e exige (§ 1º) "símbolo de acessibilidade
em destaque". O art. 8º, § 3º, VIII, da Lei nº 12.527/2011 (LAI) também determina medidas para
garantir a acessibilidade de conteúdo. O rodapé traz o link **Acessibilidade**, com símbolo, para a
página `/acessibilidade`.

## Contraste da paleta (calculado pela fórmula de luminância relativa da WCAG)

Mínimos: 4,5:1 para texto (critério 1.4.3) e 3:1 para componentes e bordas (critério 1.4.11).

| Combinação | Razão | Uso |
| --- | --- | --- |
| `#10243A` sobre `#FFFFFF` | 15,72:1 | títulos |
| `#222831` sobre `#FFFFFF` | 14,83:1 | texto |
| `#5C6670` sobre `#FFFFFF` | 5,85:1 | texto de apoio |
| `#2155E8` sobre `#FFFFFF` | 5,95:1 | links e destaques |
| `#FFFFFF` sobre `#2155E8` | 5,95:1 | botões |
| `#7D8794` sobre `#FFFFFF` | 3,64:1 | bordas de campos (componente) |
| **`#2155E8` sobre `#10243A`** | **2,64:1 — reprovado** | não usado: sobre azul profundo, o destaque passa a `#8FB0FF` |
| `#8FB0FF` sobre `#10243A` | 7,35:1 | categorias no painel escuro do destaque |
| `#D5DCE5` sobre `#10243A` | 11,38:1 | textos de apoio no cabeçalho e no destaque |
| Tema escuro: `#E6EAF0` sobre `#0B1726` | 14,93:1 | texto |
| Tema escuro: `#A9B4C2` sobre `#0B1726` | 8,58:1 | texto de apoio |
| Tema escuro: `#8FB0FF` sobre `#0B1726` | 8,43:1 | links |
| Tema escuro: `#8794A6` sobre `#0B1726` | 5,85:1 | bordas de campos |
| Situações (claro): `#93370D`/`#FFFAEB`, `#067647`/`#ECFDF3`, `#B42318`/`#FEF3F2` | 7,21 · 5,40 · 6,05 | etiquetas de situação |
| Situações (escuro): `#FEC84B`/`#33260B`, `#75E0A7`/`#0F2E22`, `#FDA29B`/`#3A1716` | 9,54 · 9,05 · 8,23 | etiquetas de situação |

Textos sobre fotografias usam uma faixa escura quase opaca (até 95% de `#081220`). Com ela, o texto
`#FFFFFF` fica acima de 4,5:1 independentemente da foto.

## Recursos implementados

- Marcação semântica: cabeçalho, navegação, `main`, `aside`, `figure`/`figcaption`, listas e
  tabelas com `caption` e `scope`.
- Link "Pular para o conteúdo" e foco visível (contorno de 3 px) em todos os controles.
- Galeria em `<dialog>` com fechamento por Esc, setas para navegar, botões rotulados e foco
  devolvido à miniatura.
- Formulários com rótulos visíveis, ajuda e erros ligados ao campo (`aria-describedby`,
  `aria-invalid`), resumo de erros focado e com links para cada campo, e indicação "obrigatório para
  publicar".
- Corpo de texto de 16 px, unidades relativas (respeitam o zoom e o tamanho de fonte do navegador),
  colunas que viram sequência de leitura no celular, sem rolagem horizontal em 390 px.
- Tema escuro automático (`prefers-color-scheme`), com botão para escolher; `prefers-reduced-motion`
  respeitado.
- Alvos de toque de pelo menos 44 px nos botões principais (o critério 2.5.8 da WCAG 2.2 exige 24 px).
- Descrição acessível de cada foto preenchida pela equipe. A revisão alerta quando falta, e imagens
  decorativas usam `alt=""`.
- Vídeos e publicações externas só carregam por escolha do visitante, com o link original sempre
  disponível.

## Como foi verificado

`tests/browser/verificar.cjs` (Playwright + [axe-core](https://github.com/dequelabs/axe-core) 4.10,
regras WCAG 2.0/2.1/2.2 A e AA) verificou, em 08/10/2026, com o conteúdo de demonstração:

- páginas públicas (início, ações, áreas, equipe, página de ação) no celular (390 × 844), nos temas
  claro e escuro: **sem violações** detectadas, sem rolagem horizontal, corpo de texto ≥ 16 px;
- teclado: o primeiro Tab leva ao link de salto, o foco é visível, a busca é enviada com Enter, a
  galeria abre com Enter, navega com as setas, fecha com Esc e devolve o foco;
- painel no celular (visão geral, ações, edição, fotos, revisão, páginas, usuários): **sem violações**
  detectadas e menu operável por teclado.

### Limites desta verificação

Ferramentas automáticas não determinam conformidade sozinhas. A Deque, fabricante do axe, relatou em
seu *Automated Accessibility Coverage Report* (2021) que testes automatizados identificaram cerca de
57% dos problemas, em volume, nas auditorias analisadas. É um dado do próprio fabricante.
Recomenda-se ainda:

1. teste com leitores de tela (NVDA no Windows, VoiceOver no iOS e TalkBack no Android);
2. revisão humana da qualidade das descrições de imagens e da clareza dos textos;
3. avaliação com pessoas com deficiência, antes e depois do lançamento.
