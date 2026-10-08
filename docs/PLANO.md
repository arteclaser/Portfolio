# Plano do aplicativo de portfólio — mostraqui.net

Documento de planejamento e de decisões da primeira versão do portfólio institucional da
Secretaria de Desenvolvimento Econômico, Inovação e Turismo de Capinzal, hospedado no domínio
**mostraqui.net**. Ele parte do documento de requisitos *Prompt_App_Portfolio_Secretaria* e
registra o que foi decidido, por quê e o que fica para depois.

## 1. Ponto de partida verificado

| Fato | Como foi verificado | Consequência |
| --- | --- | --- |
| O domínio usa os servidores de nomes `ns304.hostgator.com.br` e `ns305.hostgator.com.br` e responde em Apache com a página padrão do cPanel (403) | Consulta DNS (Google DNS-over-HTTPS) e requisição HTTP em 08/10/2026 | Hospedagem HostGator Brasil com cPanel. A pilha precisa rodar em PHP + MySQL, sem Node.js no servidor |
| O certificado TLS apresentado não cobre `mostraqui.net` | `curl` recusou a conexão HTTPS por nome divergente | Antes de publicar, é preciso ativar SSL no cPanel |
| Laravel 13 exige PHP 8.3 a 8.5; o Laravel 12 recebe correções de segurança só até 24/02/2027 | Tabela de suporte em [laravel.com/docs/releases](https://laravel.com/docs/master/releases) | Laravel 13, com **PHP 8.3 ou superior** selecionado no MultiPHP Manager do cPanel |
| Desde 2026, a Meta permite chamar o oEmbed do Instagram sem token. As respostas não trazem mais `thumbnail_url` nem `author_name` desde 03/11/2025 | [Changelog da Plataforma do Instagram](https://developers.facebook.com/docs/instagram-platform/changelog) (entrada de 15/05/2026) e [blog da Meta de 15/06/2026](https://developers.facebook.com/blog/post/2026/06/15/tokenless-access-to-meta-oembed-apis/) | O Instagram não fornece miniatura nem título. O cartão usa dados manuais e a incorporação oficial carrega só após o clique |
| O YouTube recomenda player de pelo menos 200 × 200 px e oferece o modo de privacidade avançada (`youtube-nocookie.com`) | [Parâmetros do player](https://developers.google.com/youtube/player_parameters) e [Ajuda do YouTube](https://support.google.com/youtube/answer/171780?hl=pt-BR) | Player oficial em 16:9, carregado só após o clique, no domínio de privacidade avançada |

## 2. Decisões de arquitetura

- **Laravel 13 + MySQL/MariaDB**, com HTML renderizado no servidor e JavaScript só como melhoria
  progressiva. Os formulários, a busca e a navegação funcionam mesmo sem JavaScript.
- **Sem etapa de compilação no servidor.** CSS e JS são escritos à mão em `public/assets`. As fontes
  (Source Serif 4 e Inter, licença SIL OFL) são servidas pelo próprio domínio, sem chamadas a
  terceiros.
- **Portfólio público sem sessão:** as rotas públicas não gravam cookies. A sessão existe só no
  login e no painel.
- **Arquivos sempre em disco privado** (`storage/app/private`). Um controlador entrega a mídia:
  a rota pública só serve arquivos em uso por conteúdo publicado, e a rota do painel exige
  autorização.
- **Pacote pronto para cPanel** (`scripts/empacotar.sh`): o código fica fora de `public_html` e
  apenas `public/` vai para a pasta pública. O `index.php` localiza o código automaticamente.
- **Modelo já separado por `portfolio_id`.** A primeira versão atende um portfólio por instalação, e
  os dados estão prontos para outros portfólios no mesmo domínio (ver seção 8).

## 3. Modelo de dados

```
portfolios ─┬─ pages (árvore: parent_id) ─┬─ page_blocks (trabalho)
            │                             ├─ page_revisions (publicações)
            │                             ├─ custom_fields ── action_field_values
            │                             ├─ page_user (vínculos de acesso por área)
            │                             └─ page_team_member (responsáveis)
            ├─ team_members (cadastro institucional; user_id opcional)
            ├─ partners
            ├─ media (imagens e PDFs, variantes otimizadas)
            └─ actions (identidade) ──< action_versions (conteúdo versionado)
                                          ├─ action_version_page (áreas relacionadas)
                                          ├─ action_version_team_member
                                          ├─ action_version_partner
                                          ├─ action_indicators
                                          ├─ action_media (capa, ordem, legenda, crédito, alt, enquadramento)
                                          └─ action_links (vídeos, reportagens, publicações)
users ── activity_logs (histórico de quem fez o quê)
```

- **Ação = identidade + versões.** `actions.published_version_id` aponta para o que o público vê;
  `actions.working_version_id`, para a versão em edição. Editar o conteúdo publicado cria uma nova
  versão, e o público só a vê depois da aprovação.
- **Uma ação, várias áreas.** A área principal define permissões e as áreas relacionadas são
  associações. Os totais contam ações distintas, então uma ação ligada a duas áreas conta uma vez.
- **Situações independentes:** `activity_status` (planejada, em andamento, concluída, cancelada) e
  situação editorial (rascunho, em revisão, publicado, arquivado; mais lixeira).
- **Campos adicionais** pertencem a uma página e valem para as ações dela e das subordinadas. Campos
  com valores são arquivados em vez de apagados, e mudar o tipo de um campo em uso é bloqueado.

## 4. Perfis e permissões (verificados no servidor)

| Operação | Master | Editor | Colaborador |
| --- | --- | --- | --- |
| Usuários, permissões, configurações, registro de atividades | ✔ | — | — |
| Criar, mover e arquivar páginas; campos adicionais; responsáveis | ✔ | — | — |
| Editar blocos, descrição e capa e publicar páginas | ✔ (todas) | ✔ (áreas autorizadas) | — |
| Criar ações | ✔ | ✔ (áreas autorizadas) | ✔ (áreas autorizadas) |
| Editar a versão de trabalho | ✔ | ✔ (áreas autorizadas) | só os próprios rascunhos e conteúdos devolvidos |
| Encaminhar para revisão | ✔ | ✔ | ✔ |
| Aprovar, devolver e publicar | ✔ | ✔ (áreas autorizadas) | — |
| Arquivar e mover para a lixeira | ✔ | quando autorizado pelo Master | só o próprio rascunho nunca publicado (lixeira) |
| Excluir permanentemente | ✔ (com confirmação do nome) | — | — |
| Administrar a equipe | ✔ | quando autorizado pelo Master (áreas próprias) | — |

O acesso a uma página inclui as páginas subordinadas. As *policies* do Laravel verificam cada
rota; esconder botões não substitui essa checagem.

## 5. Fluxo editorial

```
Nova ação ─► Rascunho ─(encaminhar)─► Em revisão ─(aprovar)─► Publicada
                ▲                         │                      │
                └──────(devolver com ◄────┘                      │ editar
                        observações)                             ▼
                                         Versão de trabalho (o público continua na versão publicada)
```

- Para encaminhar ou publicar, são obrigatórios nome, resumo, descrição, área principal e data ou
  período, além dos campos adicionais marcados como obrigatórios. Rascunhos incompletos podem ser
  salvos.
- Os erros aparecem junto a cada campo e num resumo com links para eles. Se a sessão expirar ou a
  conexão cair, o texto digitado é preservado por uma cópia local no navegador.
- O histórico registra autor, revisor, datas, observações e campos alterados. É possível restaurar
  uma versão antiga como nova versão de trabalho, arquivar, mover para a lixeira e excluir com
  confirmação.

## 6. Correspondência com as verificações de aceite

| Verificação do documento | Teste automatizado |
| --- | --- |
| Master cria página e usuário, define perfil e restringe áreas | `AcceptanceTest::test_master_creates_page_and_user_with_profile_and_restricted_areas` |
| Colaborador salva, envia, não publica nem acessa outra área pela API | `…test_collaborator_drafts_and_submits_but_cannot_publish_or_reach_other_areas` |
| Editor publica; visitantes veem só a versão aprovada | `…test_authorized_editor_publishes_and_visitors_only_see_approved_version` |
| Alterar publicada não muda a versão pública antes da aprovação | `…test_editing_published_action_keeps_public_version_until_new_approval` |
| Limite de fotos recusado com mensagem clara, no servidor | `…test_photo_limits_are_enforced_by_the_server` |
| Link sem prévia recebe título e imagem manuais e abre a origem | `…test_link_without_preview_accepts_manual_title_and_image_and_keeps_origin` |
| Ação em duas áreas: um cadastro, uma contagem | `…test_action_in_two_areas_has_single_record_and_single_count` |
| Conteúdo persiste após sair e entrar; arquivos privados protegidos | `…test_saved_content_persists_across_sessions_and_private_files_are_protected` |
| Busca, filtros, galeria, formulários e navegação no celular e por teclado | `…test_public_search_and_filters` + `tests/browser/verificar.cjs` (Playwright + axe-core) |

Outros 18 testes cobrem SSRF, sanitização, vazamento de rascunhos, contatos internos e capas não publicadas, desativação
de usuários, limite de tentativas de login, instalação segura, convites, campos adicionais,
indicadores, permissões de arquivo e exclusão, edição concorrente, restauração de versões,
publicação de páginas, backup e restauração, e bloqueio de dados de demonstração em produção.

## 7. Fases

**Fase 1 — entregue neste repositório:** portfólio público, painel autenticado, usuários e
permissões por área, páginas com blocos, equipe, parceiros, cadastro de ações, galeria com limites,
links com alternativa manual, revisão editorial, histórico, busca e filtros, backup e restauração,
instalação segura e dados de demonstração isolados.

**Fase 2 — preparada, não implementada** (não apresentar como pronta):

1. *Linha do tempo:* datas indexadas (`action_versions.starts_on`) e histórico de versões prontos.
2. *Relatórios por período:* filtros por ano, área, tipo e situação já existem na consulta pública
   (`PublicActions::filtered`). Falta a exportação (CSV/PDF).
3. *Verificação automática de links:* `action_links` guarda `fetched_at`, `preview_status` e
   `preview_message`, e o `SafeFetcher` já faz consultas seguras. Falta a rotina agendada e o aviso
   no painel.

**Fase 3 — a decidir com a Secretaria:** vários portfólios no mesmo domínio (por exemplo,
`capinzal.mostraqui.net`), autenticação em dois fatores e integração com a política oficial de
privacidade do município.

## 8. Pendências e riscos

| Item | Responsável | Observação |
| --- | --- | --- |
| Confirmar no cPanel que o plano oferece PHP 8.3+ com as extensões `pdo_mysql`, `gd` (com WebP), `fileinfo`, `curl`, `zip`, `mbstring` e `openssl` | Responsável técnico | Sem PHP 8.3, o Laravel 13 não instala |
| Ativar SSL para `mostraqui.net` e `www` | Responsável técnico | Hoje o certificado não cobre o domínio |
| Criar a conta de e-mail de envio (SMTP) | Responsável técnico | Sem ela, os convites usam link copiado pelo Master |
| Agendar o cron de backup e copiar os backups para fora do servidor | Responsável técnico | Ver `docs/BACKUP-E-RESTAURACAO.md` |
| Definir o texto institucional, o e-mail de contato e a política de privacidade oficial | Secretaria | O aviso de privacidade do site é técnico e não substitui a política do órgão |
| Autorização de uso de imagem das pessoas fotografadas e das logomarcas de parceiros | Secretaria | A imagem de pessoas é dado pessoal (LGPD, Lei nº 13.709/2018) |
| Avaliação de acessibilidade com pessoas usuárias | Secretaria + equipe | Testes automatizados cobrem só parte dos problemas |
