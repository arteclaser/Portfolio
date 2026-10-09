# Implantação em mostraqui.net (HostGator, cPanel)

Guia passo a passo para publicar o portfólio de Capinzal em hospedagem compartilhada com cPanel,
como a do domínio `mostraqui.net` (servidores de nomes `ns304/ns305.hostgator.com.br`, verificados
em 08/10/2026). O portfólio abre **direto em `mostraqui.net`** (modo portfólio único,
`PORTFOLIO_ROUTING=single`). Os nomes dos menus seguem o cPanel padrão. Este guia **não depende de
SSH**; o Terminal só aparece como alternativa, para quem tiver.

> **Recomendado:** configure a **implantação automática pelo GitHub, por FTP**
> ([IMPLANTACAO-AUTOMATICA.md](IMPLANTACAO-AUTOMATICA.md)). Ela substitui os passos 1, 2 e 10
> deste guia e cria o `.env` do passo 4: envia os arquivos e atualiza o banco a cada aprovação no
> branch `main`. Os passos abaixo continuam válidos para quem preferir publicar manualmente.

> Este procedimento foi ensaiado localmente com a mesma estrutura de pastas do cPanel, MariaDB 10.11
> e PHP 8.3: instalação pelo navegador, login, áreas iniciais, backup e restauração.

## 0. Pré-requisitos

| Requisito | Onde conferir |
| --- | --- |
| PHP **8.3 ou superior** para o domínio | cPanel → **MultiPHP Manager** |
| Extensões PHP: `pdo_mysql`, `mbstring`, `openssl`, `gd` (com WebP), `fileinfo`, `curl`, `zip`, `dom`, `intl` (opcional) | cPanel → **Select PHP Version** / **MultiPHP INI Editor**, ou um `phpinfo()` temporário |
| Banco MySQL ou MariaDB | cPanel → **Bancos de dados MySQL** |
| Certificado SSL para `mostraqui.net` e `www.mostraqui.net` | cPanel → **SSL/TLS Status** (AutoSSL, se o plano oferecer) |
| Conta de e-mail para envios (ex.: `nao-responda@mostraqui.net`) | cPanel → **Contas de e-mail** |

## 1. Gerar os pacotes (no seu computador)

Requer PHP 8.3, Composer, `rsync` e `zip`:

```bash
./scripts/empacotar.sh
```

Isso gera:

- `dist/mostraqui-app.zip`: o código da aplicação, com dependências de produção;
- `dist/public_html.zip`: somente os arquivos públicos (`index.php`, `.htaccess`, `.user.ini`, `assets/`).

## 2. Enviar os arquivos

> **Atenção, nesta hospedagem:** o domínio principal da conta é **arteclaser.com.br**, e o
> `public_html` é a pasta do site WordPress dele. **Não extraia nada no `public_html`.** O
> `mostraqui.net` é um domínio adicional: veja em cPanel → **Domínios** a *Raiz do documento* dele
> (por exemplo, `~/mostraqui.net`) e use essa pasta onde este guia diz `public_html`. Se a pasta for
> `~/public_html/mostraqui.net`, edite no `index.php` a linha `$appPath` para
> `__DIR__.'/../../mostraqui-app'`. A implantação automática faz esses ajustes sozinha.

1. cPanel → **Gerenciador de arquivos**. Na pasta pessoal (acima de `public_html`), envie
   `mostraqui-app.zip` e extraia. O resultado deve ser `~/mostraqui-app/`.
2. Faça uma cópia de segurança do que já existir em `~/public_html` e envie e extraia
   `public_html.zip` **dentro** de `~/public_html`.
3. O `public_html/index.php` encontra o código em `../mostraqui-app` automaticamente. **Nunca coloque
   `mostraqui-app` dentro de `public_html`.**

Estrutura final:

```
/home/SUA_CONTA/
├── mostraqui-app/        ← código, .env, storage (privado)
└── public_html/          ← index.php, .htaccess, .user.ini, assets/
```

> Alternativa: se o portfólio for um domínio adicional, aponte a raiz do documento direto para
> `~/mostraqui-app/public` e dispense o passo 2.

## 3. Banco de dados

cPanel → **Bancos de dados MySQL**:

1. Crie o banco (ex.: `conta_portfolio`).
2. Crie um usuário com senha forte e adicione-o ao banco com **todos os privilégios**.
3. Anote os nomes completos. O cPanel acrescenta o prefixo da conta.

## 4. Arquivo `.env`

Em `~/mostraqui-app/`, copie `.env.example` para `.env` e preencha:

| Variável | Valor |
| --- | --- |
| `APP_KEY` | Gere no seu computador com `php artisan key:generate --show` e cole (formato `base64:...`) |
| `APP_URL` | `https://mostraqui.net` |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Dados do passo 3 (`DB_HOST=localhost`) |
| `MAIL_*` | Servidor, porta e senha da conta de e-mail (dados em cPanel → Contas de e-mail → Conectar dispositivos) |
| `SETUP_TOKEN` | Só se for instalar pelo navegador (passo 5B): um código longo e aleatório |

Proteja o arquivo: permissão **600** ou **640**.

## 5. Criar as tabelas, a administração da plataforma e o primeiro portfólio

Não existe usuário nem senha padrão no sistema. A instalação cria o portfólio de Capinzal, que abre
direto em `mostraqui.net`, e a conta de **administração**, com todos os poderes de Master. Sem SSH,
use o caminho **5B**.

### 5A. Com Terminal (só se o SSH estiver liberado)

```bash
cd ~/mostraqui-app
php artisan migrate --force
php artisan mostraqui:instalar
# Pergunta o nome, o nome curto e o endereço do primeiro portfólio (ex.: capinzal),
# e o nome e o e-mail da administração da plataforma; a senha é digitada sem aparecer na tela.
# Para gerar um link de convite em vez de digitar a senha: php artisan mostraqui:instalar --convite
```

### 5B. Sem terminal, pelo navegador (caminho usado nesta hospedagem)

1. Defina `SETUP_TOKEN` no `.env`.
2. Acesse `https://mostraqui.net/instalar` e informe o código, os dados do primeiro portfólio
   (nome, nome curto e endereço) e os da administração da plataforma. O instalador cria as
   tabelas, o portfólio, as áreas iniciais e a conta.
3. **Apague a linha `SETUP_TOKEN` do `.env`.** O painel avisa enquanto ela existir. O endereço
   `/instalar` deixa de funcionar assim que existe um usuário.

### Outros portfólios no futuro

Nesta configuração, o domínio mostra apenas o portfólio de Capinzal e o painel não exibe a gestão
de vários portfólios. O sistema já está preparado para hospedar outros órgãos depois
(`mostraqui.net/outro-orgao` ou `outro-orgao.mostraqui.net`) com a troca de uma linha no `.env`.
Veja **[MULTIPORTFOLIO-E-SUBDOMINIOS.md](MULTIPORTFOLIO-E-SUBDOMINIOS.md)**.

## 6. Limites de envio de fotos

O padrão do produto é de **10 imagens por ação**, com até **5 MB** cada, ajustável em Painel →
Configurações. Para o PHP aceitar esses envios, o `public_html/.user.ini` já define:

```
upload_max_filesize = 8M
post_max_size = 64M
memory_limit = 256M
max_execution_time = 120
```

Se o servidor não ler `.user.ini`, aplique os mesmos valores em cPanel → **MultiPHP INI Editor**.
A tela de Configurações mostra o limite efetivo do servidor.

## 7. HTTPS

1. Ative o certificado (cPanel → SSL/TLS Status).
2. No `.env`, defina `FORCE_HTTPS=true` (o cookie de sessão passa a ser seguro automaticamente). O
   redirecionamento para HTTPS fica na aplicação, então a implantação automática não o desfaz.
   Antes do certificado, mantenha `FORCE_HTTPS=false`, ou o acesso ao painel não funciona.

## 8. Tarefas agendadas (backup diário)

As tarefas Cron são cadastradas pelo próprio cPanel, sem terminal. A HostGator descreve o menu
**Tarefas Cron** para hospedagem compartilhada, com intervalo mínimo de 15 minutos
([blog da HostGator](https://www.hostgator.com.br/blog/cron-job-guia-completo-para-automatizar-tarefas/)).
Se o menu não aparecer no seu cPanel, confirme com o suporte.

cPanel → **Tarefas Cron** → adicione uma tarefa diária, por exemplo às 03:00:

```
0 3 * * * cd /home/SUA_CONTA/mostraqui-app && /usr/local/bin/php artisan mostraqui:backup --manter=14 >> /dev/null 2>&1
```

Ela gera uma cópia completa (banco + arquivos) em `~/mostraqui-app/storage/app/backups/` e mantém as
14 mais recentes. Se `/usr/local/bin/php` não for o PHP 8.3, use o caminho do MultiPHP (em cPanel
com EasyApache 4, geralmente `/opt/cpanel/ea-php83/root/usr/bin/php`). Confirme o caminho com o
suporte. Depois da primeira execução, o arquivo aparece em `storage/app/backups/` no Gerenciador de
arquivos; se não aparecer, o e-mail do Cron (sem o `>> /dev/null 2>&1`) mostra o erro.

Alternativa: agendar `php artisan schedule:run` de hora em hora (`0 * * * *`). O agendador do
sistema roda o mesmo backup às 03:00.

## 9. Verificação pós-instalação

- [ ] `https://mostraqui.net` abre com cadeado (HTTPS) e sem aviso de erro.
- [ ] `https://mostraqui.net/.env` responde 403 ou 404.
- [ ] `https://mostraqui.net` mostra o portfólio de Capinzal.
- [ ] Login em `/entrar` funciona, e o painel não mostra avisos de configuração pendentes.
- [ ] Em Usuários, convide um Editor: o e-mail chega (ou copie o link exibido).
- [ ] Crie uma ação de teste, envie uma foto, publique, confira no site e arquive.
- [ ] Rode um backup (`php artisan mostraqui:backup` ou aguarde o cron) e baixe a cópia.

## 10. Atualizações futuras

Com a implantação automática ([IMPLANTACAO-AUTOMATICA.md](IMPLANTACAO-AUTOMATICA.md)), basta aprovar
a mudança no branch `main`: o GitHub envia os arquivos e atualiza o banco. Para atualizar à mão:

1. Faça um backup.
2. Gere novos pacotes (`./scripts/empacotar.sh`) e substitua `~/mostraqui-app` **preservando**
   `.env` e a pasta `storage/`. Depois substitua os arquivos de `~/public_html`.
3. Sem terminal, rode uma única vez pelo Cron
   `cd /home/SUA_CONTA/mostraqui-app && php artisan migrate --force && php artisan optimize:clear`
   e apague a tarefa em seguida.

## Ambiente de demonstração

Para apresentar o sistema sem dados reais, use **outra instalação** (por exemplo, um subdomínio
`demo.mostraqui.net` com banco próprio) e execute `php artisan mostraqui:demo`. O comando recusa
rodar com `APP_ENV=production`, marca o site como demonstração (faixa de aviso e `noindex`) e cria
usuários com senhas aleatórias exibidas uma única vez. A produção começa limpa.
