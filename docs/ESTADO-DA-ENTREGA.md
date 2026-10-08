# Estado da entrega — primeira versão

## Funcionando

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
- **Operação:** instalação por terminal ou navegador, backup e restauração completos, pacote para
  cPanel e demonstração isolada.

## Testado

| Verificação | Resultado (08/10/2026) |
| --- | --- |
| `php artisan test` em SQLite | 27 testes, 269 verificações, todos aprovados |
| `php artisan test` em MariaDB 10.11 (mesmo motor da hospedagem) | 27 testes aprovados |
| `tests/browser/verificar.cjs` (Playwright + axe-core 4.10) | celular, teclado, tema escuro e painel aprovados, sem violações WCAG A/AA detectadas |
| Ensaio da implantação: pacotes do `scripts/empacotar.sh`, estrutura `mostraqui-app/` + `public_html/`, MariaDB vazio, instalação por `/instalar` sem terminal | aprovado (tabelas, Master, áreas iniciais, `/instalar` desativado em seguida) |
| Backup e restauração no MariaDB | aprovado (acentuação e dados preservados) |
| Prévia de links contra sites reais (W3C, YouTube, Instagram) e recusa de endereços locais | aprovado (YouTube: título e miniatura via oEmbed; W3C: prévia parcial; Instagram: modo manual; `localhost` e porta 8000 recusados) |

## Depende de configuração na hospedagem

| Integração | O que configurar | Sem isso |
| --- | --- | --- |
| PHP 8.3+ | MultiPHP Manager | o sistema não roda |
| E-mail (convites e recuperação) | `MAIL_*` no `.env` (conta SMTP do domínio) | o Master copia o link de convite exibido no painel; a recuperação de senha por e-mail não funciona |
| HTTPS | certificado SSL no cPanel + linhas no `.htaccess` | navegadores marcam o site como inseguro |
| Backup diário | Cron (`docs/IMPLANTACAO-HOSTGATOR.md`, passo 8) | backups só quando executados manualmente |
| Limites de envio | `.user.ini` ou MultiPHP INI Editor | o PHP pode recusar fotos acima de 2 MB (padrão comum do PHP) |
| Prévia de links | saída HTTPS liberada no servidor (padrão em hospedagens) | links entram no modo manual |

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
- vários portfólios no mesmo domínio (o banco já separa por portfólio, mas não há interface).

## Integrações não testadas contra o serviço real

- **Incorporação do Instagram** (`embed.js` oficial): a montagem do código e o carregamento após o
  clique foram implementados conforme o código de incorporação do Instagram, mas não foram testados
  com uma publicação real neste ambiente. Se a publicação não carregar, o visitante vê uma mensagem
  e o link para abrir no Instagram.
- **Envio de e-mail por SMTP:** testado apenas com o modo `log`.
