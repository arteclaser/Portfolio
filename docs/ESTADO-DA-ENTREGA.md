# Estado da entrega — primeira versão

## Funcionando

- **Portfólio de Capinzal direto em `mostraqui.net`** (modo portfólio único, padrão). Antes da
  instalação, o domínio mostra "instalação pendente".
- **Preparado para vários portfólios** (ativável no `.env`, não ativo): por caminho
  (`mostraqui.net/endereço`) ou subdomínio (`endereço.mostraqui.net`), com criação pelo painel,
  isolamento de dados, usuários e arquivos por portfólio, e redirecionamento 301 dos endereços já
  divulgados ao mudar de modo.
- **Portfólio público** (sem cadastro e sem cookies): página inicial editorial com destaque principal
  e dois secundários, filtros por área e galeria de ações; busca e filtros por área, período, tipo e
  situação; página própria de cada ação, com capa, contexto, descrição, equipe, galeria ampliável,
  resultados documentados, campos adicionais, vídeos, reportagens e metadados de compartilhamento
  (Open Graph); páginas de áreas, programas e projetos montadas por blocos; equipe; páginas de
  acessibilidade e privacidade; tema claro e escuro.
- **Painel autenticado:** visão geral, ações (dados, fotos, links, revisão, prévia em computador e
  celular, histórico, arquivo e lixeira), páginas (blocos, configurações, campos adicionais,
  publicação e histórico), equipe, parceiros, mídias, usuários e permissões, configurações e
  registro de atividades.
- **Fluxo editorial** com versões: rascunho, revisão, devolução com observações, publicação,
  versão de trabalho sobre conteúdo publicado, restauração de versões, arquivo, lixeira e exclusão
  confirmada.
- **Segurança:** permissões por perfil e área no servidor, convites, recuperação de senha, limite de
  tentativas de login, desativação de usuários, arquivos privados, validação de envios, proteção
  contra SSRF, sanitização e CSP.
- **Operação:** instalação pelo navegador (ou terminal), backup e restauração completos, pacote
  para cPanel, demonstração isolada e **implantação automática GitHub → HostGator por FTP com
  criptografia, sem SSH**. Ela roda os testes antes do envio, envia só o que mudou, usa modo de
  manutenção, cria `.env` e `APP_KEY` na primeira vez, roda as migrações por um código de uso único,
  se recupera de falhas e faz a verificação final.

## Testado

| Verificação | Resultado (08/10/2026) |
| --- | --- |
| `php artisan test` em SQLite | 62 testes (44 de funcionalidade e 18 unitários), 456 verificações, todos aprovados |
| `php artisan test` em MariaDB 10.11 (mesmo motor da hospedagem) | 62 testes aprovados |
| GitHub Actions, fluxo *Testes* (SQLite e MariaDB 10.11 nos servidores do GitHub) | aprovado na execução nº 4 (commit `7938f99`). As três execuções anteriores falharam porque a pasta `tests/Unit` estava vazia e o Git não versiona pastas vazias; isso foi corrigido |
| Isolamento entre portfólios (`MultiPortfolioTest`) e modo subdomínio (`SubdomainModeTest`) | aprovados |
| `tests/browser/verificar.cjs` (Playwright + axe-core 4.10), modo portfólio único | 62 verificações: celular, teclado, tema escuro e painel, sem violações WCAG A/AA detectadas (no modo com vários portfólios, a versão anterior também verificou as telas da plataforma: 68 verificações) |
| Ensaio da implantação: pacotes do `scripts/empacotar.sh`, estrutura `mostraqui-app/` + `public_html/`, MariaDB vazio, instalação por `/instalar` sem terminal | aprovado (tabelas, Master, áreas iniciais, `/instalar` desativado em seguida) |
| Backup e restauração no MariaDB | aprovado (acentuação e dados preservados) |
| Ensaio da implantação automática por FTP (`deploy/implantar-ftp.sh`) contra Pure-FTPd local com TLS obrigatório e a estrutura do cPanel | aprovado: 8.833 arquivos na primeira vez, `.env` criado (600, APP_KEY nova), migrações no MariaDB, instalação por `/instalar`, reenvio só do que mudou, remoção só do que saiu do sistema, preservação de `.env`/fotos/`.well-known`/arquivos alheios, recuperação após falha no meio do envio, recusa de FTP sem criptografia e mensagens para senha, certificado, `SITE_URL` e pasta errados (detalhes em `docs/IMPLANTACAO-AUTOMATICA.md`) |
| Prévia de links contra sites reais (W3C, YouTube, Instagram) e recusa de endereços locais | aprovado (YouTube: título e miniatura via oEmbed; W3C: prévia parcial; Instagram: modo manual; `localhost` e porta 8000 recusados) |

## Depende de configuração na hospedagem

| Integração | O que configurar | Sem isso |
| --- | --- | --- |
| PHP 8.3+ | MultiPHP Manager | o sistema não roda |
| E-mail (convites e recuperação) | `MAIL_*` no `.env` (conta SMTP do domínio) | o Master copia o link de convite exibido no painel; a recuperação de senha por e-mail não funciona |
| HTTPS | certificado SSL no cPanel + `FORCE_HTTPS=true` no `.env` | navegadores marcam o site como inseguro |
| Implantação automática | conta FTP, segredos e variável `SITE_URL` no GitHub, branch `main` (`docs/IMPLANTACAO-AUTOMATICA.md`) | publicação manual pelo Gerenciador de arquivos |
| Backup diário | Cron (`docs/IMPLANTACAO-HOSTGATOR.md`, passo 8) | backups só quando executados manualmente |
| Limites de envio | `.user.ini` ou MultiPHP INI Editor | o PHP pode recusar fotos acima de 2 MB (padrão comum do PHP) |
| Prévia de links | saída HTTPS liberada no servidor (padrão em hospedagens) | links entram no modo manual |
| Vários portfólios por subdomínio (futuro) | subdomínio `*` + DNS curinga + SSL curinga (`docs/MULTIPORTFOLIO-E-SUBDOMINIOS.md`) | vários portfólios só por caminho (`mostraqui.net/capinzal`); hoje o modo é portfólio único |

## Não disponível nesta versão

Estes recursos **não estão implementados** e não devem ser apresentados como funcionalidades:

- linha do tempo, exportação de relatórios por período e verificação automática de links (os dados
  já estão preparados; ver `docs/PLANO.md`, seção 7);
- incorporação de publicações do Facebook e do Threads (aparecem como cartão com link);
- vídeos de outras plataformas além do YouTube (aparecem como cartão com link);
- fotos HEIC (converta para JPEG antes de enviar);
- editor visual de texto (o texto usa Markdown simples, com barra de botões);
- reordenação por arrastar (a ordem usa botões "subir" e "descer", que também funcionam por teclado);
- autenticação em dois fatores;
- uma mesma conta (e-mail) participando de dois portfólios (exceto a administração da plataforma);
- cadastro de portfólios pelo próprio público (quem cria é a administração da plataforma).

## Integrações não testadas contra o serviço real

- **Incorporação do Instagram** (`embed.js` oficial): a montagem do código e o carregamento após o
  clique foram implementados conforme o código de incorporação do Instagram, mas não foram testados
  com uma publicação real neste ambiente. Se a publicação não carregar, o visitante vê uma mensagem
  e o link para abrir no Instagram.
- **Envio de e-mail por SMTP:** testado apenas com o modo `log` e com o envio simulado dos testes.
- **Implantação automática na HostGator real:** depende da conta FTP e dos segredos no GitHub; o
  fluxo foi ensaiado contra um servidor FTPS local, não contra a HostGator. Pontos a confirmar na
  primeira execução: se a conta FTP adicional aceita TLS e enxerga a pasta pessoal (há alternativa
  com a conta principal) e o tempo real da primeira transferência. Primeira execução real
  (09/10/2026): os testes passaram e o servidor da HostGator aceitou FTP com criptografia (FTPS), mas
  o certificado apresentado não correspondia ao endereço cadastrado em `FTP_SERVIDOR`. A mensagem de
  erro passou a mostrar o nome do certificado (`*.hostgator.com.br`). A investigação mostrou que o
  domínio principal da conta é arteclaser.com.br (WordPress em `public_html`). Por isso, a
  implantação passou a descobrir sozinha a pasta pública de `mostraqui.net` e a se recusar a
  escrever na pasta de outro site; ambos os casos foram ensaiados com um WordPress simulado. Ela
  também passou a conferir o certificado do FTP como `*.hostgator.com.br` ao conectar pelo endereço
  cadastrado, sem exigir o nome `brNNN` do servidor (ensaiado: aceita o certificado esperado e
  recusa certificado de outro domínio).
- **Modo subdomínio na hospedagem real:** testado automaticamente com domínios de teste; depende do
  subdomínio curinga e do certificado curinga na HostGator, ainda não configurados.
