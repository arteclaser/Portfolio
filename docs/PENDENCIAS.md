# Pendências para validação local

O levantamento de 19/11/2026 foi feito à distância, só com fontes públicas. Esta lista reúne o que
depende de quem conhece Capinzal. A cada item resolvido, atualize o arquivo do lugar em
`src/data/lugares/` (ver [DADOS.md](DADOS.md)).

## 1. Estabelecimentos com "funcionamento a confirmar"

Constam em duas ou mais fontes, mas sem registro público de 2025 ou 2026. Basta um telefonema:

| Estabelecimento | Endereço nas fontes | Telefone nas fontes |
| --- | --- | --- |
| Sense Hotel Premium (também SH Hotel; antigo Hotel Baiolim) | Rua Luiz Dorini, 90 – Centro | (49) 3555-2001 |
| Hotel São Cristóvão | Acesso Cidade Alta, 3400 – São Cristóvão | (49) 3555-4869 |
| Hotel Tradição | Acesso Cidade Alta, 4975 – São Cristóvão | (49) 3555-6068 |
| Massa Fina Restaurante (também listado como Kellma) | Rua Dona Maria Angélica Almeida, 95 – Centro | (49) 3555-6655 |
| Massa Fina Padaria e Pizzaria | Rua Luiz Dorini, 15 | (49) 3555-6555 |
| Expresso Pizzaria e Lanches | Rua Ernesto Hachmann, 40 – Centro | (49) 3555-1968 |
| Corujão Lanches | Avenida XV de Novembro, 695 – Centro | (49) 3555-3537 |
| Dall'i Trattoria | Rua Carmelo Zocolli, 85 (Calçadão) – Centro | (49) 99914-7064 |
| Padaria Novo Sabor | Acesso Cidade Alta, 1298 | (49) 3555-4680 |
| Panificadora Tradicional | Rua Dona Linda Santos, 177 | (49) 3555-5602 |

Perguntas úteis: está aberto? Qual é o nome em uso? Horário? O número do imóvel confere?

## 2. Lugares que ficaram de fora, e por quê

| Lugar | Motivo |
| --- | --- |
| Hotel Antares (Av. XV de Novembro, 491) | Uma listagem não conferida indica "fechado"; sem registro recente |
| Hotel Pousada Vale das Águas | Só plataformas de reserva, endereço sem número |
| Via Vêneto Pizzaria | Mesmo número da Dall'i Trattoria (Rua Carmelo Zocolli, 85); não se sabe se coexistem |
| Restaurante Fino Grill | No mesmo endereço e telefone aparece "Restaurante Ionara"; nome atual incerto |
| Bar e Restaurante Bier Platz | A Rua João Siviero não foi localizada no mapa; sem registro recente |
| Cozinha da Sil – Restaurante e Café | Aberto em 2025 (notícia), mas o endereço não tem número (Av. José Leonardo Santos, Vila 7 de Julho) |
| Vila Picolés e Sorvetes | Endereço sem número (Rua Lourenço Coas Neto) |
| Padaria Pão Doce e Padaria Doce Pecado | Em atividade em 2026 (notícia), mas com endereços conflitantes |
| Restaurante e Casa Colonial Caminho da Mesa | Citado no curso de turismo de 2026; endereço não confirmado |
| Pastelão e Cia, Top Grill, Mr. Burguer, Das Antigas Bar | Fonte única ou endereço incompleto |
| Feira livre (Feira dos Agricultores) | Existe (notícias de 2026), mas nenhuma fonte informa local e horário |
| Capela São Cristóvão | Sede de festa tradicional (62ª edição em 2026); endereço não consta nas fontes |
| Capela São Leopoldo Mandic, igrejas do Bairro Santa Maria e de Linha Gramado | Fonte única; posição não confirmada |
| Sítio Costenaro (Vila Anchieta) | Sediou o encerramento do curso de turismo; não há fonte de que receba visitantes |
| Cavernas do Avaí (Ricardópolis), Morro do Filipini | Menções isoladas na imprensa, sem informação de acesso |

Também não encontramos fonte verificável para cascatas, mirantes, trilhas, balneários, pesque-pagues,
campings rurais, vinícolas ou propriedades com visitação regular. Se existirem, entram com a indicação
de quem conhece o local e uma fonte (pode ser a própria Secretaria).

## 3. Páginas oficiais a reabrir no navegador

Os sites da Prefeitura e do Portal Municipal de Turismo recusam leitura automática. Vale conferir à mão
se há atrativos listados só ali:

- `turismo.capinzal.sc.gov.br`: "Lugares", "Turismo Religioso", "Passeios e Aventura", "Lazer e
  Entretenimento", "Roteiros Municipais", "Roteiros Intermunicipais", "Lendas e Curiosidades";
- `capinzal.sc.gov.br`: página do Museu Histórico Vitor Almeida (horário atual), página da Biblioteca
  e notícias sobre a Praça Pedro Lélis da Rocha e a limpeza da caixa d'água da ferrovia;
- Cadastur (cadastro federal de prestadores de turismo): nenhum cadastro foi consultado.

## 4. Informações a confirmar nos atrativos

- **Ponte Pênsil:** o acesso pelo lado de Ouro estava parcialmente interditado em 22/10/2026. Quando
  for liberado, retirar o aviso.
- **Museu Vitor Almeida:** horário publicado é de 2021; posição exata do prédio (anexo à Casa Paroquial).
- **Museu Jandir Coronetti:** posição exata (o guia marca o centro da comunidade de Empresa Velha),
  contato e regra de visita.
- **Estação ferroviária:** uso atual do prédio e se há visita.
- **Igreja Matriz:** horários de missa e de visitação.
- **Área de Lazer:** se ainda há área de camping depois da reforma.
- **Parque de Exposições:** se abre fora de eventos.
- **American Bus Glamping:** posição exata (o guia marca o centro da comunidade de Barro Preto).
- **Igrejas de Engenho Novo e Barro Branco:** horários de celebração e se recebem visitantes.
- **Nome do órgão de turismo:** as notícias oficiais alternam três grafias; a lei de estrutura
  administrativa não foi localizada.

## 5. Fotos

Só a Igreja Matriz tem foto publicada. As demais fotos livres já escolhidas (Ponte Pênsil, Museu Vitor
Almeida, Terminal Rodoviário, Centro Educacional, Prefeitura) não puderam ser baixadas no dia do
levantamento, porque o Wikimedia Commons limitou os acessos. Para baixá-las:

```bash
npm run fotos
```

Para os outros lugares não há foto de uso livre. A Secretaria pode ceder fotos próprias (com
autorização por escrito do autor) ou publicar fotos no Wikimedia Commons com licença livre.

## 6. Decisões de quem responde pelo guia

- **Canal para correções:** definir um e-mail ou formulário e incluir na página "Sobre".
- **Identificação institucional:** o site não usa brasão nem se apresenta como canal oficial da
  Prefeitura. Se for adotado oficialmente, incluir a identificação e os créditos.
- **Roteiros:** os três roteiros são sugestões do guia. Quando o município definir a rota oficial
  (em planejamento desde 2023, com INCOTUR e Sebrae), substituir ou marcar como oficial.
