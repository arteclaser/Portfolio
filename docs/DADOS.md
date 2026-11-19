# Dados do guia: como manter

Tudo o que aparece no site vem de arquivos de texto em `src/data/`. Não há banco de dados: para mudar
uma informação, edite o arquivo, rode `npm run conferir` e publique.

## Princípios

1. **Nada sem fonte.** Cada lugar tem a lista `fontes`, com título, endereço da página, tipo e data da
   consulta. O `npm run conferir` recusa um lugar sem fonte ou que só tenha o OpenStreetMap como fonte.
2. **Nada deduzido.** Se a fonte não informa horário, preço, telefone ou número do imóvel, o campo fica
   de fora e, se for importante, o campo `aviso` explica ao visitante.
3. **Divergências ficam visíveis.** Quando as fontes discordam, o texto mostra as versões.
4. **Posição com origem declarada.** `precisao: "exata"` só quando o ponto do próprio lugar está
   mapeado (em geral no OpenStreetMap, com o campo `osm`). Nos demais casos, `"aproximada"`, com a
   explicação em `origemCoordenadas`.
5. **Fotos só de uso livre**, com autor, licença e página de origem. Fotos geradas por inteligência
   artificial não entram: mostrariam um lugar que não existe daquele jeito.

## Um lugar (`src/data/lugares/<id>.json`)

O nome do arquivo é o `id` do lugar e também o endereço da página (`/lugares/<id>/`).

| Campo | Obrigatório | Conteúdo |
| --- | --- | --- |
| `id`, `nome` | sim | Identificador (minúsculas e hífens) e nome completo |
| `nomeCurto`, `outrosNomes` | não | Nome curto para o mapa; outros nomes, que entram na busca |
| `categoria` | sim | `historia`, `fe`, `natureza`, `sabores`, `hospedagem` ou `apoio` |
| `tipo` | sim | Rótulo curto: "Museu", "Ponte pênsil histórica", "Churrascaria" |
| `destaque` | sim | 3 = cartão-postal, 2 = importante, 1 = normal (define tamanho e ordem) |
| `resumo` | sim | Uma frase, até 200 caracteres |
| `descricao` | sim | Lista de parágrafos, só com o que as fontes afirmam |
| `municipio`, `localidade`, `endereco` | município: sim | Como nas fontes |
| `coordenadas` | sim | `[longitude, latitude]` |
| `precisao`, `origemCoordenadas`, `osm` | sim (osm: se houver) | Ver o princípio 4 |
| `horario`, `entrada`, `contato` | não | Só com fonte; telefone no formato `(49) 3555-0000` |
| `situacao`, `evidencia` | comércio e hospedagem | Ver abaixo |
| `aviso` | não | O que o visitante precisa confirmar antes de ir |
| `imagens` | não | Preenchido pelo `npm run fotos` |
| `fontes`, `conferidoEm` | sim | Fontes e data da última conferência (`AAAA-MM-DD`) |

### Restaurantes, bares e hospedagem

Comércio abre e fecha. Por isso há dois níveis:

- `"situacao": "confirmado"` com `"evidencia"`: existe registro público **datado de 2025 ou 2026**
  mostrando o lugar em atividade (notícia, publicação do próprio estabelecimento).
- `"situacao": "a-confirmar"`: o lugar consta em **duas ou mais fontes**, ou em uma fonte oficial ou
  base de dados aberta (fonte do tipo `base`), mas sem registro recente. A página mostra o selo
  "Funcionamento a confirmar".

**Base de dados aberta (tipo `base`).** O guia usa a base *Places* da Overture Maps Foundation
(licença CDLA Permissive 2.0), que reúne registros de estabelecimentos com nome, endereço, telefone,
posição e um índice de confiança de 0 a 1. Em Capinzal, quase todos os registros vêm da página do
próprio estabelecimento no Facebook. Regras: só entra sozinho o registro com confiança de 0,77 ou mais;
abaixo disso, é preciso uma segunda fonte. A posição é sempre marcada como aproximada, e o endereço
do registro é comparado com a rua no mapa. Para refazer a extração:
`overturemaps download --bbox=-51.76,-27.54,-51.50,-27.30 -f geojson --type=place`.
A situação das empresas na Receita Federal é consultada pelo CNPJ (por exemplo, pela BrasilAPI).

Ficam de fora: lugares com uma só fonte de diretório, endereço conflitante ou indício de fechamento.
O guia não publica notas nem avaliações. Horários de agregadores sem data não são publicados.

Para promover um lugar a "confirmado" depois de conferir no local ou por telefone, registre a
conferência como fonte (por exemplo, a página ou o perfil do próprio estabelecimento, com a data) e
preencha `evidencia` (ex.: `"conferido por telefone em março de 2027"`).

## Um roteiro (`src/data/rotas/<id>.json`)

Defina `nome`, `nomeCurto`, `resumo`, `descricao`, `modo` (`a-pe`, `carro` ou `bicicleta`), as cores,
`circular` e a lista `paradas` (ids dos lugares, na ordem). O `aviso` precisa dizer que o roteiro
**não é oficial**, enquanto o município não tiver um roteiro oficial.

Depois de mudar as paradas, rode:

```bash
npm run mapa:rotas
```

O comando calcula o traçado pelas ruas (OSRM sobre o OpenStreetMap), a distância, o tempo e o perfil
de altitude (relevo SRTM) e grava em `src/data/rotas-geradas/`. O site publicado não consulta nenhum
serviço de rotas.

## Fotos

Inclua a foto em `scripts/fotos/lista.json` (título do arquivo no Wikimedia Commons, nome local, texto
alternativo e legenda opcional) e rode:

```bash
npm run fotos
```

O comando baixa a imagem, confere se a licença é livre e grava autor, licença, ano e página de origem no
arquivo do lugar. Para fotos próprias da Prefeitura ou de moradores, é preciso ter a autorização de uso
por escrito; grave o arquivo em `src/assets/lugares/` e preencha `imagens` à mão, com `autor`,
`licenca` (ex.: "Cedida ao guia pelo autor") e `origem`.

## Página da cidade

Números, história, acessos e eventos ficam em `src/data/capinzal.ts`. Cada trecho aponta para uma
chave da lista `fontes` do mesmo arquivo, e a página numera as referências sozinha.

## Atualizar o mapa-base

O mapa é um recorte do OpenStreetMap. Para pegar ruas e nomes novos:

```bash
pip install pmtiles
python3 scripts/geo/extrair-mapa-base.py 20270115   # data da compilação desejada (AAAAMMDD)
```

O relevo (`scripts/geo/extrair-relevo.py`) não precisa ser atualizado.

## Como o levantamento inicial foi feito (19/11/2026)

- Pesquisa em fontes oficiais (Prefeitura de Capinzal e Portal Municipal de Turismo, Plano Municipal de
  Cultura, IBGE, Ministério do Turismo, ALESC, Prefeitura de Ouro, Diocese de Joaçaba), imprensa local
  (Rádio Capinzal e jornal O Tempo) e diretórios, com apoio de inteligência artificial. Cada trecho
  usado foi conferido contra o texto da fonte.
- As páginas HTML da Prefeitura e do portal de turismo bloqueiam leitura automática. O conteúdo foi lido
  pelos documentos em PDF e pela interface pública de dados dos próprios sites. Vale reabrir essas
  páginas no navegador (lista em [PENDENCIAS.md](PENDENCIAS.md)).
- As posições vêm do OpenStreetMap (consulta Overpass de 19/11/2026) ou foram estimadas pela rua e pela
  numeração, sempre com a indicação "aproximada".
