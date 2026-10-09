# Implantação automática: GitHub → HostGator (por FTP, sem SSH)

Depois desta configuração, **toda alteração aprovada no branch `main` vai sozinha para o site**. O
GitHub roda os testes, envia os arquivos para a HostGator por **FTP com criptografia (FTPS)**,
atualiza o banco de dados e confere se o site respondeu. Não é preciso pedir SSH ao suporte nem usar
o Gerenciador de arquivos a cada atualização.

```
Você aprova a mudança (main) ─► GitHub testa ─► envia por FTPS ─► atualiza o banco ─► site atualizado
                                   │ falhou?                 │ falhou?
                                   └─ nada é enviado          └─ o site sai da manutenção e você é avisado
```

**O que a implantação nunca apaga nem substitui no servidor:** o arquivo `.env`, a pasta `storage/`
(fotos, documentos, backups, sessões), o banco de dados e os arquivos que já existiam em
`public_html` (como a pasta `.well-known` do SSL). Ela guarda no servidor uma lista dos arquivos
que enviou e só apaga arquivos dessa lista que deixaram de existir no sistema. Isso foi verificado em
um ensaio local com a mesma estrutura de pastas do cPanel (ver *Como foi testado*).

---

## Parte 1 — Anotar o nome do servidor (uma vez)

A implantação confere o certificado de segurança do FTP. Esse certificado é emitido para o **nome
do servidor da hospedagem**, no formato `br123.hostgator.com.br`, e **não** para `mostraqui.net`,
`ftp.mostraqui.net` ou o IP. Para encontrar esse nome:

- no cPanel, no quadro **Informações gerais** (lateral direita), em *Nome do servidor*; ou
- no endereço do próprio cPanel, na barra do navegador (`https://br123.hostgator.com.br:2083`); ou
- no Portal do Cliente da HostGator, nos dados da hospedagem.

Se tiver dúvida, cadastre qualquer endereço e rode a implantação: quando o nome não confere, a
mensagem de erro do GitHub mostra **para qual nome o certificado foi emitido**.

## Parte 2 — Criar uma conta FTP só para a implantação (uma vez)

Uma conta própria pode ser excluída a qualquer momento sem mexer na senha do cPanel.

1. Em **Contas FTP → Adicionar conta FTP**, preencha:
   - *Login:* `implantacao` (o usuário completo será `implantacao@mostraqui.net`);
   - *Senha:* use o **Gerador de senha** e **anote a senha**;
   - *Diretório:* apague o texto sugerido e digite apenas **`/`** (a pasta pessoal inteira). A
     implantação precisa enxergar `public_html` e a pasta do código, que fica fora dela;
   - *Cota:* **Ilimitado**.
2. Clique em **Criar conta de FTP**. Segundo a HostGator, a conta não pode ser editada depois. Se
   algo ficar errado, exclua e crie de novo.

> **Se a conta adicional não funcionar** (o GitHub mostra "A conta FTP não enxerga a pasta
> 'public_html'" ou recusa o acesso com criptografia), use a **conta FTP principal**: usuário e
> senha do cPanel. A HostGator informa que o FTP com TLS funciona com as credenciais do cPanel. A
> desvantagem é que a senha guardada no GitHub também abre o cPanel. Nesse caso, use uma senha forte
> e exclusiva e troque-a se houver qualquer suspeita.

## Parte 3 — Banco de dados e PHP (uma vez)

1. cPanel → **Bancos de dados MySQL**: crie um banco (ex.: `conta_portfolio`) e um usuário com senha
   forte, e adicione o usuário ao banco com **todos os privilégios**. Anote os três dados (o cPanel
   acrescenta o prefixo da conta aos nomes).
2. cPanel → **MultiPHP Manager**: selecione **PHP 8.3** (ou superior) para `mostraqui.net`.

O arquivo `.env` (configurações e senhas do servidor) é criado **automaticamente** na primeira
implantação. Você só vai editá-lo na Parte 5.

## Parte 4 — Cadastrar os dados no GitHub (uma vez)

No repositório, abra **Settings → Secrets and variables → Actions**.

**Aba "Secrets" → "New repository secret"** (ficam criptografados e ninguém consegue lê-los depois):

| Nome | Valor |
| --- | --- |
| `FTP_SERVIDOR` | Nome do servidor anotado na Parte 1 (ex.: `br123.hostgator.com.br`) |
| `FTP_USUARIO` | `implantacao@mostraqui.net` (ou o usuário do cPanel, se usar a conta principal) |
| `FTP_SENHA` | Senha da conta FTP |

**Aba "Variables" → "New repository variable"** (configurações visíveis, sem senhas):

| Nome | Valor | Quando usar |
| --- | --- | --- |
| `SITE_URL` | `http://mostraqui.net` (troque para `https://` quando o SSL estiver ativo) | **Obrigatória** |
| `FTP_CONFERIR_CERTIFICADO` | `nao` | Só se aparecer erro de certificado mesmo usando o nome do servidor. A conexão continua criptografada |
| `FTP_CRIPTOGRAFIA` | `desligada` | **Evite.** Só se o servidor recusar FTPS: a senha passaria sem proteção |
| `FTP_PORTA` | `21` | Só se o cPanel mostrar outra porta |
| `FTP_CONEXOES` | `4` (de 1 a 6) | Arquivos enviados ao mesmo tempo. A HostGator aceita até 8 conexões por conta |
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

A primeira execução envia todos os arquivos, cerca de 8.800, e por isso demora mais. As seguintes
enviam só o que mudou. Ao final, o GitHub mostra um aviso amarelo pedindo para editar o `.env`:

4. cPanel → **Gerenciador de arquivos** → pasta pessoal → `mostraqui-app` → ative **Configurações →
   Mostrar arquivos ocultos** → clique com o botão direito em `.env` → **Editar**. Preencha:
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: os dados da Parte 3;
   - `APP_URL`: `http://mostraqui.net` (ou `https://` se o SSL já estiver ativo);
   - `SETUP_TOKEN`: um código longo inventado por você (ex.: 30 letras e números);
   - `MAIL_*`: os dados da conta de e-mail, se já existir (opcional);
   - não altere `APP_KEY`, que já foi gerada.
5. Rode a implantação de novo (**Actions → Run workflow**). Agora as tabelas do banco são criadas.
6. Acesse `http://mostraqui.net/instalar`, informe o `SETUP_TOKEN` e os dados do portfólio de
   Capinzal e da administração. Depois, **apague a linha `SETUP_TOKEN`** do `.env`.
7. O site de Capinzal passa a abrir direto em **mostraqui.net**.
8. Quando o certificado SSL estiver ativo, no `.env` mude `FORCE_HTTPS=false` para
   `FORCE_HTTPS=true` e `APP_URL` para `https://mostraqui.net`. No GitHub, mude a variável
   `SITE_URL` para `https://mostraqui.net`.

## Parte 6 — No dia a dia

- **Publicar uma atualização:** aprove (faça o *merge* de) um *pull request* no `main`. Em poucos
  minutos o site está atualizado. Durante o envio, o site mostra uma página de manutenção.
- **Acompanhar:** aba **Actions**. Verde ✔ = no ar; vermelho ✗ = algo falhou. Clique para ver o
  motivo. Se o envio falhar, o site sai da manutenção sozinho; rode de novo para completar.
- **Desfazer:** no *pull request* já aprovado, clique em **Revert** e aprove o novo *pull request*. O
  código anterior volta ao ar. Alterações já feitas no banco de dados não são desfeitas
  automaticamente. As atualizações de banco feitas até agora só acrescentam tabelas e colunas.
- **Exigir aprovação antes de cada envio (opcional):** **Settings → Environments → producao →
  Required reviewers**, marcando quem pode aprovar.

## Problemas comuns

| Mensagem na aba Actions | O que fazer |
| --- | --- |
| `Falta o segredo FTP_...` / `Falta a variável SITE_URL` | Cadastre o item indicado (Parte 4) |
| `Usuário ou senha do FTP recusados` | Confira `FTP_USUARIO` (a conta adicional inclui `@mostraqui.net`) e `FTP_SENHA` |
| `O certificado do servidor não corresponde a FTP_SERVIDOR` | A mensagem mostra o nome do certificado: cadastre-o em `FTP_SERVIDOR` (Parte 1). Se não houver nome utilizável, cadastre `FTP_CONFERIR_CERTIFICADO=nao` |
| `O servidor não aceitou FTP com criptografia` | Confirme com o suporte se o FTPS (FTP sobre TLS explícito) está ativo na conta |
| `A conta FTP não enxerga a pasta 'public_html'` | Recrie a conta FTP com o diretório `/` ou use a conta principal (Parte 2) |
| `O site não reconheceu a implantação (HTTP 404)` | `SITE_URL` deve ser o endereço deste site, e `APP_DIR` a pasta do código (`mostraqui-app`) |
| `O site redirecionou a conclusão (HTTP 301)` | O HTTPS já está ativo: mude `SITE_URL` para `https://` |
| `O servidor respondeu HTTP 500` com "Composer detected issues in your platform" | O PHP do domínio não é o 8.3: ajuste no MultiPHP Manager (Parte 3) |
| `Não foi possível conectar ao banco de dados` | Confira `DB_*` no `.env` (Parte 5, item 4) |
| `421 Too many connections` | Feche programas de FTP abertos (como o FileZilla) ou cadastre `FTP_CONEXOES=2` |
| `O site não respondeu com HTTP 200` | Veja `~/mostraqui-app/storage/logs/` no Gerenciador de arquivos |

## Segurança

- **Criptografia obrigatória:** por padrão, a implantação só se conecta com FTPS e confere o
  certificado do servidor. Senha e arquivos não trafegam abertos.
- **A senha do FTP fica só nos *Secrets* do GitHub**, criptografada, e o GitHub a oculta nos
  registros. Para cortar o acesso, exclua a conta FTP no cPanel.
- **Código de uso único:** para atualizar o banco sem terminal, o GitHub sorteia um código a cada
  implantação. Por FTP, envia ao servidor só o *hash* desse código, fora da pasta pública. Depois
  chama `/_implantacao/finalizar` e apaga o código ao terminar. Sem um código válido, esse endereço
  responde "não encontrado", e um código esquecido perde a validade em uma hora. Antes do SSL, o
  código passa sem criptografia na chamada ao site, mas só vale durante aquela implantação.
- O `.env` nunca é enviado pelo GitHub, nunca é apagado e é criado com permissão 600.
- O código fica fora da pasta pública (`~/mostraqui-app`). O script recusa configurações que o
  colocariam dentro de `public_html` ou que usem caminhos suspeitos.
- Só alterações no `main` são implantadas, e só depois de todos os testes passarem.

## Como foi testado

O fluxo completo foi ensaiado contra um servidor Pure-FTPd local com TLS obrigatório, um certificado
próprio, a mesma estrutura de pastas do cPanel, MariaDB 10.11 e PHP 8.3, com o site rodando com o
usuário da conta. Resultados:

- primeira implantação com 8.833 arquivos em 136 s, sem a latência da internet;
- criação do `.env` (permissão 600, APP_KEY nova) e pedido de configuração;
- segunda implantação com as 5 atualizações do banco;
- instalação por `/instalar`, com Capinzal na raiz do domínio;
- implantação sem mudanças em 3 s;
- envio só dos arquivos alterados e remoção só dos arquivos retirados do sistema;
- preservação de `.env`, fotos em `storage/`, `.well-known` e arquivos alheios em `public_html`;
- falha no meio do envio: o site saiu da manutenção, o código de uso único foi apagado e a execução
  seguinte completou o envio;
- recusa de FTP sem criptografia;
- mensagens claras para senha errada, certificado que não confere, `SITE_URL` errada, conta FTP
  restrita a `public_html` e caminhos inválidos.

O fluxo **ainda não rodou contra a HostGator real**. A primeira execução no GitHub é o teste
definitivo. Envios pela internet são mais lentos que o ensaio local, então a primeira implantação
pode levar dezenas de minutos (o limite configurado é de 3 horas).

## Arquivos envolvidos

- `.github/workflows/implantacao.yml`: o fluxo do GitHub (testes → envio);
- `deploy/implantar-ftp.sh`: o envio por FTPS (pode ser executado de qualquer computador com as
  mesmas variáveis);
- `deploy/excluir.txt`: o que nunca é enviado;
- `app/Services/DeployFinisher.php` e `app/Http/Controllers/DeployController.php`: a conclusão no
  servidor (`.env`, banco, caches, fim da manutenção);
- `tests/Feature/DeployEndpointTest.php`: testes dessa conclusão.

## Fontes

- HostGator Brasil: [dados de acesso do FTP (porta 21, campo "FTP & porta FTPS explícita")](https://suporte.hostgator.com.br/hc/pt-br/articles/30811387611795-Quais-s%C3%A3o-os-dados-de-acesso-do-FTP-na-HostGator), [como criar uma conta FTP no cPanel](https://suporte.hostgator.com.br/hc/pt-br/articles/30808120096403-Como-criar-uma-conta-FTP-no-cPanel), [como configurar o FileZilla (modo passivo; limite de 8 conexões simultâneas por cPanel e erro 421)](https://suporte.hostgator.com.br/hc/pt-br/articles/30814022785427-Como-configurar-o-FileZilla).
- HostGator: [Secure FTP, SFTP and FTPS](https://www.hostgator.com/help/article/secure-ftp-sftp-and-ftps), que informa FTP sobre TLS explícito, na porta 21 e com as credenciais do cPanel, em todos os servidores, exceto os planos Optimize WordPress.
- Diretório `/` para acesso à pasta pessoal: a página da HostGator não menciona essa opção. Ela aparece em guias de outros provedores com cPanel, por exemplo [Z.com](https://web.z.com/us/support/web-hosting/how-to-create-delete-an-ftp-account-in-cpanel/). Por isso a implantação confere a pasta e orienta o uso da conta principal.
- lftp (cliente FTP do Ubuntu): [manual](https://lftp.yar.ru/lftp-man.html).
