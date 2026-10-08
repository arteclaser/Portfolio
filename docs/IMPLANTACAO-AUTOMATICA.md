# Implantação automática: GitHub → HostGator

Depois desta configuração, **toda alteração aprovada no branch `main` vai sozinha para o site**. O
GitHub roda os testes, prepara os arquivos, envia para a HostGator por conexão criptografada (SSH),
atualiza o banco de dados e confere se o site respondeu. Não é preciso usar FTP nem o Gerenciador de
arquivos a cada atualização.

```
Você aprova a mudança (main) ─► GitHub testa ─► envia por SSH ─► atualiza o banco ─► site atualizado
                                   │ falhou?                 │ falhou?
                                   └─ nada é enviado          └─ o site sai da manutenção e você é avisado
```

**Por que SSH e não FTP:** segundo a base de conhecimento da HostGator, o FTP com criptografia
(FTPS) só está disponível em servidores dedicados e VPS. Na hospedagem compartilhada, o FTP comum
envia a senha sem proteção. O SSH, na porta 2222, é criptografado, usa uma chave em vez de senha e
permite rodar as atualizações do banco.

O que a implantação **nunca apaga nem substitui** no servidor: o arquivo `.env`, a pasta `storage/`
(fotos, documentos, backups, sessões), o banco de dados e os arquivos que já existiam em
`public_html` (como a pasta `.well-known` do SSL). Isso foi verificado em um ensaio local com a
mesma estrutura de pastas do cPanel.

---

## Parte 1 — Pedir a liberação do SSH (uma vez)

Na hospedagem compartilhada da HostGator, o SSH precisa ser liberado pelo suporte. Abra o chat ou
um chamado e envie:

> Olá! Peço a **liberação do acesso SSH** na minha hospedagem do domínio **mostraqui.net**. Vou usar o
> SSH (porta 2222) com chave para publicar atualizações do site a partir do GitHub. Aproveito para
> confirmar: (1) o PHP 8.3 está disponível no MultiPHP Manager? (2) Qual é o caminho do executável do
> PHP 8.3 na linha de comando? (3) O `rsync` está disponível no servidor? Obrigado!

Guarde a resposta sobre o **caminho do PHP 8.3**: ela será usada na Parte 4.

## Parte 2 — Criar a chave de implantação no cPanel (uma vez)

1. No cPanel, procure **Acesso SSH** e clique em **Gerenciar chaves SSH**.
2. Clique em **Gerar uma nova chave**:
   - *Nome da chave:* `github-implantacao`
   - *Senha da chave:* crie uma senha forte e **anote-a**
   - *Tipo:* RSA, *Tamanho:* 4096 (ou o maior disponível)
3. Volte à lista. Em **Chaves públicas**, na linha `github-implantacao`, clique em **Gerenciar** e
   depois em **Autorizar**.
4. Em **Chaves privadas**, na linha `github-implantacao`, clique em **Exibir/Baixar** e copie
   **todo** o texto, das linhas `-----BEGIN ...` até `-----END ...`.
5. Anote também:
   - o **usuário do cPanel** (aparece no canto do cPanel ou em Portal do Cliente → Hospedagens →
     Gerenciar);
   - o **endereço do servidor**: o IP compartilhado mostrado no cPanel (em *Informações gerais*). Em
     08/10/2026, `mostraqui.net` apontava para `108.167.169.58`; prefira o valor que o cPanel mostrar.

> Se o cPanel não oferecer a chave privada para copiar, gere a chave no seu computador. No Windows
> 10/11, abra o PowerShell e rode `ssh-keygen -t ed25519 -f github-implantacao`. Depois importe o
> arquivo `github-implantacao.pub` em *Gerenciar chaves SSH → Importar chave* e autorize-o. O conteúdo
> do arquivo `github-implantacao` (sem `.pub`) é a chave privada.

## Parte 3 — Banco de dados (uma vez)

1. cPanel → **Bancos de dados MySQL**: crie um banco (ex.: `conta_portfolio`) e um usuário com senha
   forte e adicione o usuário ao banco com **todos os privilégios**. Anote os três dados (o cPanel
   acrescenta o prefixo da conta aos nomes).
2. cPanel → **MultiPHP Manager**: selecione **PHP 8.3** para `mostraqui.net`.

O arquivo `.env` (configurações e senhas do servidor) é criado **automaticamente** na primeira
implantação. Você só vai editá-lo na Parte 5.

## Parte 4 — Cadastrar os dados no GitHub (uma vez)

No repositório, abra **Settings → Secrets and variables → Actions**.

**Aba "Secrets" → "New repository secret"** (ficam criptografados e ninguém consegue lê-los depois):

| Nome | Valor |
| --- | --- |
| `HOSTGATOR_SSH_HOST` | Endereço do servidor (Parte 2, item 5) |
| `HOSTGATOR_SSH_USER` | Usuário do cPanel |
| `HOSTGATOR_SSH_KEY` | Texto completo da chave privada (Parte 2, item 4) |
| `HOSTGATOR_SSH_PASSPHRASE` | Senha da chave (Parte 2, item 2) |
| `HOSTGATOR_KNOWN_HOSTS` | Deixe para depois da primeira implantação (Parte 6) |

**Aba "Variables" → "New repository variable"** (configurações visíveis, sem senhas):

| Nome | Valor | Quando usar |
| --- | --- | --- |
| `PHP_BIN` | Caminho do PHP 8.3 informado pelo suporte (ex.: `/opt/cpanel/ea-php83/root/usr/bin/php`) | Se o PHP padrão do terminal não for o 8.3. A implantação avisa se for o caso |
| `SITE_URL` | `https://mostraqui.net` (ou `http://` antes do SSL) | Para conferir, ao final, se o site respondeu |
| `SSH_PORT` | `2222` | Só se o suporte informar outra porta |
| `APP_DIR` / `PUBLIC_DIR` | `mostraqui-app` / `public_html` | Só se quiser outras pastas |

## Parte 5 — Primeira implantação

O repositório ainda não tem o branch `main`, que é o branch de produção:

1. No GitHub, abra a página do repositório, clique no seletor de branch (onde aparece
   `claude/adoring-bell-cxki2c`), digite `main` e escolha **Create branch: main from
   'claude/adoring-bell-cxki2c'**.
2. Em **Settings → General → Default branch**, troque para `main`.
3. Abra **Actions → Implantação na HostGator**. Se a implantação não tiver começado sozinha com a
   criação do `main`, clique em **Run workflow**, escolha o branch `main` e confirme. Use esse mesmo
   botão sempre que quiser repetir a implantação.

Na primeira execução, o servidor recebe os arquivos e cria o `.env`. O GitHub mostra um aviso
amarelo pedindo para editá-lo:

4. cPanel → **Gerenciador de arquivos** → pasta pessoal → `mostraqui-app` → ative **Configurações →
   Mostrar arquivos ocultos** → clique com o botão direito em `.env` → **Editar**. Preencha:
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: os dados da Parte 3;
   - `APP_URL`: `https://mostraqui.net` (ou `http://` se o SSL ainda não estiver ativo);
   - `SETUP_TOKEN`: um código longo inventado por você (ex.: 30 letras e números);
   - `MAIL_*`: os dados da conta de e-mail, se já existir (opcional);
   - não altere `APP_KEY`, que já foi gerada.
5. Rode a implantação de novo (**Actions → Run workflow**). Agora as tabelas são criadas.
6. Acesse `https://mostraqui.net/instalar`, informe o `SETUP_TOKEN` e os dados do primeiro portfólio
   e da administração da plataforma. Depois, **apague a linha `SETUP_TOKEN`** do `.env`.
7. Quando o certificado SSL estiver ativo, mude `FORCE_HTTPS=false` para `FORCE_HTTPS=true` no `.env`.

## Parte 6 — Fixar a identidade do servidor (recomendado)

Na primeira execução, o registro da implantação mostra uma linha parecida com:

```
[108.167.169.58]:2222 ssh-rsa AAAAB3NzaC1yc2E...
```

Copie essa linha inteira para o segredo **`HOSTGATOR_KNOWN_HOSTS`**. Assim, o GitHub só envia
arquivos para esse servidor, o que protege contra desvio da conexão. Enquanto o segredo não existir,
cada implantação mostra um aviso.

## Parte 7 — No dia a dia

- **Publicar uma atualização:** aprove (faça o *merge* de) um *pull request* no `main`. Em poucos
  minutos o site está atualizado. Durante o envio, o site mostra uma página de manutenção por alguns
  segundos.
- **Acompanhar:** aba **Actions**. Verde ✔ = no ar; vermelho ✗ = algo falhou. Clique para ver o
  motivo; o site não fica fora do ar por causa da falha.
- **Desfazer:** no *pull request* já aprovado, clique em **Revert** e aprove o novo *pull request*. O
  código anterior volta ao ar. Alterações já feitas no banco de dados não são desfeitas
  automaticamente. As atualizações de banco feitas até agora só acrescentam tabelas e colunas.
- **Exigir aprovação antes de cada envio (opcional):** **Settings → Environments → producao →
  Required reviewers**, marcando quem pode aprovar.

## Problemas comuns

| Mensagem na aba Actions | O que fazer |
| --- | --- |
| `Falta o segredo HOSTGATOR_...` | Cadastre o segredo indicado (Parte 4) |
| `não respondeu. O SSH está liberado na hospedagem?` / `Connection refused` / `timed out` | Confirme com o suporte se o SSH foi liberado e se a porta é 2222 |
| `Permission denied (publickey)` | A chave não foi **autorizada** no cPanel (Parte 2, item 3), ou o usuário está errado |
| `Não foi possível abrir a chave SSH com a senha informada` | Confira `HOSTGATOR_SSH_PASSPHRASE` |
| `O PHP do servidor é 8.x... exige 8.3` / `PHP não encontrado` | Selecione PHP 8.3 no MultiPHP Manager e cadastre a variável `PHP_BIN` |
| `Host key verification failed` | O servidor mudou de identidade (ex.: migração de servidor pela HostGator). Confirme com o suporte e atualize `HOSTGATOR_KNOWN_HOSTS` |
| `As migrações do banco falharam` | Confira `DB_*` no `.env` (Parte 5, item 4) |
| `O site não respondeu com HTTP 200` | Veja `~/mostraqui-app/storage/logs/` no Gerenciador de arquivos |
| `O servidor não tem rsync` | Peça ao suporte |

## Segurança

- A chave privada fica só nos *Secrets* do GitHub, criptografada. Para cortar o acesso a qualquer
  momento, desautorize ou exclua a chave em cPanel → Acesso SSH.
- O `.env` nunca é enviado pelo GitHub, nunca é apagado e fica com permissão 600.
- O código fica fora da pasta pública (`~/mostraqui-app`). O script recusa configurações que o
  colocariam dentro de `public_html` ou que usem caminhos suspeitos.
- Só alterações no `main` são implantadas, e só depois de todos os testes passarem.

## Arquivos envolvidos

- `.github/workflows/implantacao.yml`: o fluxo do GitHub (testes → envio);
- `deploy/implantar.sh`: o envio por SSH/rsync (pode ser executado de qualquer computador com as
  mesmas variáveis);
- `deploy/remoto-preparar.sh`, `deploy/remoto-finalizar.sh`, `deploy/remoto-liberar.sh`: o que roda
  no servidor (conferência do PHP, manutenção, `.env`, migrações);
- `deploy/rsync-excluir.txt`: o que nunca é enviado nem apagado.

## Fontes

- HostGator: [SSH em hospedagem compartilhada (porta 2222, liberação pelo suporte)](https://suporte.hostgator.com.br/hc/pt-br/articles/30814927583379-Como-habilitar-o-acesso-SSH-no-plano-de-hospedagem), [gerar chaves SSH no cPanel](https://suporte.hostgator.com.br/hc/pt-br/articles/30819530838291-Como-gerar-chaves-de-acesso-SSH-no-cPanel), [portas liberadas](https://suporte.hostgator.com.br/hc/pt-br/articles/30812710494739-Quais-portas-s%C3%A3o-liberadas-por-padr%C3%A3o-nos-servidores-na-HostGator-Brasil) e [FTPS apenas em dedicados e VPS](https://www.hostgator.com/help/article/how-do-i-start-using-ssl-with-ftp).
- GitHub Actions: segredos e variáveis em *Settings → Secrets and variables → Actions*.
