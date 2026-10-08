# Vários portfólios: endereço por caminho ou por subdomínio

A plataforma mostraqui.net hospeda **vários portfólios na mesma instalação**. Cada portfólio tem o
próprio endereço, equipe, áreas, ações e arquivos, todos isolados dos demais. Há dois formatos de
endereço, e o sistema funciona com qualquer um deles:

| | **Por caminho** — `mostraqui.net/capinzal` | **Por subdomínio** — `capinzal.mostraqui.net` |
| --- | --- | --- |
| Criação de novos portfólios | Automática, pelo painel | Automática, pelo painel (depois da configuração única) |
| Configuração na hospedagem | Nenhuma | Uma única vez: subdomínio curinga `*`, DNS curinga e certificado SSL curinga |
| Certificado HTTPS | O mesmo do domínio (SSL gratuito da hospedagem) | Precisa cobrir `*.mostraqui.net` (ver seção 3) |
| Custo adicional | Nenhum | Possivelmente o certificado Wildcard (ver seção 3) |
| Identidade de cada portfólio | Boa | Mais forte (parece um site próprio) |
| Mudar depois | Sim: os endereços antigos redirecionam (301) para os subdomínios | — |

## 1. Recomendação

1. **Começar agora por caminho** (`mostraqui.net/capinzal`). É o padrão do sistema, funciona no
   plano atual sem configurar nada e é 100% automático: cada portfólio criado no painel já está no
   ar no instante em que é salvo.
2. **Migrar para subdomínios quando o SSL curinga estiver resolvido.** A troca é uma linha no `.env`.
   Os links antigos (`mostraqui.net/capinzal/...`) passam a redirecionar permanentemente para
   `capinzal.mostraqui.net/...`, sem quebrar nada que já tenha sido divulgado.

O que **não** é viável é criar um subdomínio no cPanel para cada portfólio, à mão. Por isso o
sistema nunca depende disso: no modo subdomínio, uma única regra curinga (`*`) atende a todos os
portfólios, atuais e futuros.

## 2. Como criar um portfólio (automático)

Quem administra a plataforma entra em **Painel → Plataforma → Portfólios → Novo portfólio** e
preenche:

- nome, nome curto do cabeçalho e **endereço** (ex.: `capinzal`; se ficar em branco, é sugerido a
  partir do nome curto);
- áreas iniciais, uma por linha (editáveis depois);
- nome e e-mail do **Master do portfólio** (opcional). A pessoa recebe um convite para definir a
  senha e passa a administrar **apenas** aquele portfólio.

Ao salvar, o portfólio é criado com as áreas publicadas e fica disponível no seu endereço. Pelo
terminal, o equivalente é:

```bash
php artisan mostraqui:novo-portfolio "Secretaria de Turismo de Exemplo" --curto="Exemplo" --endereco=exemplo \
  --areas="Turismo" --areas="Cultura" --master-nome="Nome" --master-email="pessoa@exemplo.gov.br"
```

Regras do endereço: 3 a 40 caracteres, letras minúsculas sem acento, números e hífens. Palavras
usadas pelo sistema ou pelo cPanel (`painel`, `entrar`, `www`, `mail`, `cpanel`, `webmail` etc.)
são recusadas. Assim, o mesmo endereço vale como caminho e como subdomínio.

Outras operações em **Portfólios**: editar nome e endereço, **desativar** (o site sai do ar e a
equipe daquele portfólio perde o acesso ao painel), deixar de **listar** na página inicial da
plataforma e **Gerenciar** (o painel passa a mostrar aquele portfólio).

## 3. Ativar subdomínios (uma única vez)

> Só faça isto quando quiser os endereços `capinzal.mostraqui.net`. Até lá, nada precisa ser
> configurado.

### 3.1 Subdomínio curinga no cPanel

1. cPanel → **Domínios** (ou **Subdomínios**) → criar subdomínio.
2. Em *Subdomínio*, digite `*` (asterisco). Em *Domínio*, escolha `mostraqui.net`.
3. Em *Raiz do documento*, informe **a mesma pasta do domínio principal**: `public_html`.
4. Salve. Os subdomínios que já existem (como `mail` e `www`) continuam funcionando como antes,
   porque registros específicos têm prioridade sobre o curinga.

### 3.2 Conferir o DNS curinga

O DNS de mostraqui.net está na própria HostGator (servidores `ns304/ns305.hostgator.com.br`,
verificados em 08/10/2026), então o registro pode ser feito no cPanel:

1. cPanel → **Zone Editor** → `mostraqui.net` → **Gerenciar**.
2. Deve existir um registro **A** com nome `*.mostraqui.net` apontando para o mesmo IP do domínio
   principal. Em 08/10/2026, o domínio apontava para `108.167.169.58`; confira o IP atual no próprio
   Zone Editor. Se o registro não existir, adicione-o.
3. A propagação pode levar algumas horas. Para testar, abra `http://qualquercoisa.mostraqui.net`:
   deve abrir a página inicial da plataforma, e não um erro de "servidor não encontrado". Depois do
   passo 3.4, endereços de portfólios inexistentes passam a mostrar a página "não encontrado".

### 3.3 Certificado HTTPS para `*.mostraqui.net`

Sem um certificado que cubra os subdomínios, os navegadores exibem alerta de site inseguro. Opções:

| Opção | Custo | Observações |
| --- | --- | --- |
| **A. Verificar se o SSL gratuito cobre o curinga.** cPanel → *SSL/TLS Status*: veja se `*.mostraqui.net` aparece como coberto após criar o subdomínio `*` | Gratuito | Pelas fontes consultadas, o AutoSSL do cPanel só emite certificados curinga com o provedor Let's Encrypt e validação por DNS no próprio servidor. A HostGator não documenta isso para hospedagem compartilhada. **Pergunte ao suporte** (modelo na seção 4) |
| **B. Contratar o SSL Wildcard da HostGator** | R$ 443,88/ano (valor na página da HostGator em 08/10/2026; confirme) | Cobre o domínio e os subdomínios. A instalação dos certificados pagos é feita por chamado ao suporte |
| **C. Usar o Cloudflare na frente do site** | Gratuito | O SSL Universal do Cloudflare cobre o domínio e os subdomínios de primeiro nível. Exige mudar os servidores de nomes para o Cloudflare, recriar lá os registros de e-mail e instalar no cPanel um certificado de origem do Cloudflare. É a opção mais trabalhosa, e recomendo apoio técnico |

### 3.4 Ligar o modo subdomínio no sistema

No arquivo `~/mostraqui-app/.env`:

```
PORTFOLIO_ROUTING=subdomain
PORTFOLIO_BASE_DOMAIN=mostraqui.net
APP_URL=https://mostraqui.net
```

Se o cache de configuração tiver sido gerado (`php artisan config:cache`), limpe-o com
`php artisan config:clear`, pelo Terminal ou por um Cron que rode uma vez. A partir daí:

- `capinzal.mostraqui.net` abre o portfólio de Capinzal, e cada novo portfólio criado no painel já
  abre no seu subdomínio, sem nenhum passo adicional;
- `mostraqui.net` mostra a página da plataforma, e o painel e o acesso da equipe ficam em
  `mostraqui.net/painel` e `mostraqui.net/entrar`;
- `mostraqui.net/capinzal/...` redireciona (301) para `capinzal.mostraqui.net/...`;
- `www.mostraqui.net` redireciona para `mostraqui.net`.

Para voltar ao modo caminho, basta `PORTFOLIO_ROUTING=path`.

## 4. Mensagem pronta para o suporte da HostGator

Copie e cole no chat ou em um chamado:

> Olá! Tenho o domínio **mostraqui.net** em minha hospedagem e vou publicar uma aplicação PHP (Laravel)
> que atende vários portfólios por subdomínio (ex.: capinzal.mostraqui.net). Preciso confirmar:
>
> 1. Meu plano permite selecionar **PHP 8.3** (ou superior) no MultiPHP Manager, com as extensões
>    pdo_mysql, gd (com WebP), fileinfo, curl, zip, mbstring e openssl?
> 2. Posso criar um **subdomínio curinga** (`*.mostraqui.net`) apontando para `public_html`, com o
>    registro DNS A `*` no Zone Editor?
> 3. O **SSL gratuito (AutoSSL/Let's Encrypt)** do meu plano cobre `*.mostraqui.net`? Se não cobrir,
>    qual é o procedimento e o valor atual do **SSL Wildcard**?
> 4. Meu plano tem **acesso SSH** ou o **Terminal** do cPanel?
> 5. Posso agendar **tarefas Cron** diárias com o PHP 8.3? Qual é o caminho do executável do PHP 8.3?
>
> Obrigado!

## 5. O que está isolado entre portfólios

- Cada consulta do sistema é filtrada automaticamente pelo portfólio da requisição: no site, pelo
  endereço; no painel, pela conta do usuário. Isso vale também para a resolução de endereços como
  `/painel/acoes/15`: ações, páginas, mídias, integrantes e usuários de outro portfólio respondem
  "não encontrado".
- As validações de formulário só aceitam áreas, parceiros, mídias e integrantes do próprio
  portfólio. As permissões ainda conferem o portfólio uma segunda vez.
- Os arquivos públicos de um portfólio não abrem pelo endereço de outro.
- O mesmo endereço de ação (ex.: `feira-de-inovacao`) pode existir em portfólios diferentes.
- Testes automatizados cobrem esses casos (`tests/Feature/MultiPortfolioTest.php` e
  `tests/Feature/SubdomainModeTest.php`).

### Perfis

| Perfil | Alcance |
| --- | --- |
| **Administração da plataforma** | Cria, edita, desativa e gerencia qualquer portfólio, com poderes de Master em todos |
| **Master** | Tudo dentro do próprio portfólio (usuários, páginas, configurações) |
| **Editor** e **Colaborador** | As áreas autorizadas do próprio portfólio |

Limitação desta versão: um e-mail corresponde a uma conta, que pertence a um único portfólio. Uma
pessoa que trabalhe em dois portfólios precisa de dois e-mails ou da conta de administração da
plataforma.

## Fontes consultadas (08/10/2026)

- cPanel: subdomínio curinga com `*` e raiz do documento: [guia Kualo](https://www.kualo.com/knowledgebase/cpanel-domains/what-is-wildcard-subdomain-and-how-to-create-it-in-cpanel) e [guia Brasil Cloud](https://brasilcloud.com.br/tutorial/como-criar-um-subdominio-wildcard-curinga-cpanel/).
- cPanel: certificados curinga no AutoSSL exigem o provedor Let's Encrypt e validação por DNS no servidor: [documentação do plugin Let's Encrypt](https://docs.cpanel.net/knowledge-base/third-party/the-lets-encrypt-plugin/).
- HostGator Brasil: [tipos de certificado](https://suporte.hostgator.com.br/hc/pt-br/articles/30822417916435-Qual-a-diferen%C3%A7a-entre-os-certificados-SSL), [instalação do SSL Wildcard](https://suporte.hostgator.com.br/hc/pt-br/articles/30812081563155-Como-instalar-o-certificado-SSL-Wildcard-na-HostGator-ou-em-outra-empresa) e [página de certificados e preços](https://www.hostgator.com.br/certificado-ssl).
- Cloudflare: [SSL Universal](https://developers.cloudflare.com/ssl/edge-certificates/universal-ssl/) e [certificados de origem](https://developers.cloudflare.com/ssl/origin-configuration/origin-ca/).
