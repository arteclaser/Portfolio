# Estado da entrega — primeira versão (19/11/2026)

## Funcionando

- **Mapa interativo** com mapa-base próprio (recorte do OpenStreetMap em PMTiles), relevo sombreado,
  vista em 3D, limite do município, tema claro e escuro, marcadores por categoria com agrupamento,
  ficha do lugar ao clicar e os três roteiros desenhados.
- **Lista equivalente ao mapa**, com busca que ignora acentos e filtros por categoria. No celular, os
  cartões viram um carrossel sobre o mapa, com a opção "Ver em lista".
- **33 páginas de lugar**, com descrição, endereço, contato, posição (exata ou aproximada), mapa
  pequeno, links para Google Maps, Waze e OpenStreetMap, lugares próximos, roteiros e fontes.
- **3 páginas de roteiro**, com traçado pelas ruas, paradas numeradas, distância, tempo, perfil de
  altitude e link para abrir o roteiro no Google Maps.
- **Página da cidade** (IBGE, história, acessos, calendário de eventos) e **página "Sobre"** (método,
  licenças, privacidade, acessibilidade).
- **Modo sem internet** e instalação como aplicativo (service worker e manifesto).
- **Implantação automática** por FTPS, com a troca do aplicativo anterior.

## Testado

| Verificação | Resultado |
| --- | --- |
| `npm run conferir` | 33 lugares, 3 roteiros, 105 fontes distintas, sem erros |
| `npm test` | 15 testes unitários aprovados (distâncias, formatação, busca sem acentos, contraste das cores) |
| `npm run test:navegador` (Playwright + axe-core 4.x, Chromium) | 28 de 28 verificações: computador, celular (Pixel 7), tema escuro, teclado, alvos de toque, axe sem violações WCAG A/AA em todos os tipos de página, nenhum erro de console, nenhum bloqueio de CSP, nenhum pedido a terceiros, modo sem internet |
| Apache 2.4.58 com o `.htaccess` do projeto | Aprovado (ver [IMPLANTACAO.md](IMPLANTACAO.md)) |
| Ensaio da implantação por FTPS | Aprovado em 7 cenários (ver [IMPLANTACAO.md](IMPLANTACAO.md)) |
| GitHub Actions, fluxo *Testes* (dados, testes unitários, montagem e navegador nos servidores do GitHub) | Aprovado na execução nº 27 (commit `3ad5731`) |

## Não testado

- **Implantação na HostGator real**, incluindo a exclusão da pasta do aplicativo anterior.
- **Fluxo de implantação no GitHub Actions**: só roda no branch `main` e ainda não foi executado. A ação
  `actions/setup-node` está referenciada pela versão (`v6`), não por um código fixo como a
  `actions/checkout`.
- **Aparelhos reais**: os testes usaram o Chromium em modo de emulação. Safari (iPhone) e Firefox não
  foram testados; a instalação como aplicativo e o botão "onde estou" precisam de conferência em um
  celular de verdade.
- **Leitores de tela**: houve verificação automática (axe), não teste com NVDA, VoiceOver ou TalkBack.
- **Desempenho em celulares antigos**: o mapa usa WebGL; em aparelhos sem suporte, o site mostra a lista.

## Limitações conhecidas

- **Fotos:** só 1 dos 33 lugares tem foto. Há fotos livres escolhidas para mais 5, que não puderam ser
  baixadas no dia (limite de acessos do Wikimedia Commons). Ver [PENDENCIAS.md](PENDENCIAS.md).
- **10 estabelecimentos "a confirmar"** e 14 posições aproximadas, todas sinalizadas no site.
- **Poucos atrativos de natureza e do interior:** não há fonte pública para cascatas, mirantes, trilhas
  ou propriedades com visitação.
- **Roteiros não oficiais:** são sugestões do guia; o tempo de carro não considera estradas sem
  pavimento.
- **Detalhe do mapa:** as ruas aparecem em detalhe em Capinzal, Ouro e arredores; fora dessa área, o
  mapa só tem o contexto regional.
- **Marcadores do mapa** não são alcançados por teclado; a lista cumpre esse papel.
- **Sem painel de edição:** o conteúdo é mantido nos arquivos do repositório.
- **Só em português.**

## Ideias para as próximas versões

Fotos cedidas pela Secretaria; versão em espanhol e inglês; "meu roteiro" (escolher lugares e montar o
próprio percurso); placas com QR code em cada atrativo, apontando para a página do lugar; trilhas
pedagógicas para escolas; agenda de eventos com datas do ano; painel simples de edição.
