# Implantação: GitHub → HostGator (por FTP com criptografia, sem SSH)

Toda alteração aprovada no branch `main` vai sozinha para **mostraqui.net**. O GitHub confere os dados,
roda os testes, monta o site e envia os arquivos para a HostGator por **FTPS**. No fim, confere pela
internet se a versão publicada é a que foi enviada.

```
Você aprova a mudança (main) ─► GitHub confere e monta ─► envia por FTPS ─► confere o site no ar
                                   │ falhou?                  │ falhou?
                                   └─ nada é enviado           └─ o site anterior continua no ar
```

O site é estático: não há PHP, banco de dados nem arquivo de senhas no servidor.

## O que a implantação nunca apaga

Ela guarda no servidor a lista dos arquivos que enviou (`.implantacao/manifesto.txt`, bloqueada para a
internet) e **só apaga arquivos dessa lista** que deixaram de existir. A pasta `.well-known` (do
certificado SSL) e qualquer arquivo colocado por outra pessoa ficam intactos. Ela também se recusa a
escrever em uma pasta que tenha outro site (WordPress ou outro `index.php` desconhecido) e descobre
sozinha qual é a pasta de `mostraqui.net`, sem tocar na pasta do domínio principal da conta.

## Primeira implantação: troca do aplicativo anterior

Na primeira vez, a implantação encontra o portfólio em Laravel que estava no ar e faz a troca:

1. envia o site novo; o `.htaccess` novo vai por último, e até lá o site anterior continua respondendo;
2. retira os arquivos públicos do aplicativo anterior (os que constam no manifesto dele);
3. confere que o site novo abriu e que a pasta do código antigo continua bloqueada para a internet;
4. **apaga a pasta `mostraqui-app`**, com o código, o arquivo `.env` (senha do banco) e os arquivos
   enviados pelo painel antigo. Essa exclusão **não tem volta**.

O **banco de dados MySQL não é alterado**. Se não for mais usado, apague o banco e o usuário em
cPanel → Bancos de dados MySQL.

Para manter a pasta antiga no servidor (bloqueada, sem uso), crie no GitHub a variável
`REMOVER_APP_ANTIGO` com o valor `nao` **antes** de aprovar a mudança no `main`. O código do
aplicativo anterior continua no histórico do Git (commit `d53576d`), mas os dados que estavam só no
servidor (banco e arquivos enviados) não.

## Configuração no GitHub (já feita para o aplicativo anterior)

Os mesmos segredos e variáveis continuam valendo. Em **Settings → Secrets and variables → Actions**:

| Tipo | Nome | Valor |
| --- | --- | --- |
| Segredo | `FTP_SERVIDOR` | Endereço do FTP (ex.: `ftp.mostraqui.net`) |
| Segredo | `FTP_USUARIO` | Usuário da conta FTP (ex.: `implantacao@mostraqui.net`) |
| Segredo | `FTP_SENHA` | Senha da conta FTP |
| Variável | `SITE_URL` | `https://mostraqui.net` |
| Variável (opcional) | `FTP_DOMINIO_CERTIFICADO` | `hostgator.com.br` (padrão): o certificado do FTP da HostGator é `*.hostgator.com.br` |
| Variável (opcional) | `PUBLIC_DIR` | Pasta pública, só se a descoberta automática falhar |
| Variável (opcional) | `REMOVER_APP_ANTIGO` | `sim` (padrão) ou `nao` |
| Variável (opcional) | `FTP_PORTA`, `FTP_CONEXOES`, `FTP_CRIPTOGRAFIA`, `FTP_CONFERIR_CERTIFICADO` | Como antes |

**O site precisa de HTTPS.** O `.htaccess` redireciona `http` para `https`, e o modo sem internet e o
botão "onde estou" só funcionam em conexão segura. O certificado SSL de `mostraqui.net` já estava ativo
na implantação anterior.

## No dia a dia

- **Publicar:** aprove (faça o *merge* de) um *pull request* no `main`. Em poucos minutos o site está
  atualizado; só os arquivos que mudaram são enviados.
- **Acompanhar:** aba **Actions** → *Implantação na HostGator*. Verde = no ar; vermelho = algo falhou,
  e a mensagem diz o quê.
- **Repetir:** **Actions → Implantação na HostGator → Run workflow**.
- **Desfazer uma alteração:** no *pull request* aprovado, clique em **Revert** e aprove o novo. A versão
  anterior do site volta ao ar.

Quem já abriu o site continua vendo a versão guardada no aparelho até a visita seguinte com internet,
quando o navegador busca a versão nova.

## Como foi testado (19/11/2026)

A implantação foi ensaiada neste formato: servidor FTPS local com TLS obrigatório, Apache 2.4.58
servindo a mesma pasta e uma cópia do aplicativo anterior montada a partir do histórico do Git.

| Cenário | Resultado |
| --- | --- |
| Primeira implantação sobre o aplicativo anterior | 106 arquivos enviados, 14 arquivos antigos retirados, pasta `mostraqui-app` apagada, `.well-known` e arquivos de terceiros preservados |
| Segunda execução, sem mudanças | Nada enviado, nada apagado |
| Uma página alterada, um arquivo novo e um removido | 3 enviados, 1 apagado |
| Pasta com outro site (WordPress) | Implantação recusada, arquivos intactos |
| `REMOVER_APP_ANTIGO=nao` | Pasta antiga mantida e bloqueada (HTTP 403 pela internet) |
| Senha errada; `SITE_URL` de outro site | Recusa com mensagem clara |
| Regras do `.htaccess` no Apache 2.4 | Páginas, erro 404, bloqueio de arquivos ocultos, tipos de arquivo, compressão, cache e cabeçalhos de segurança conferidos |

**Ainda não testado na HostGator real:** a troca do aplicativo anterior acontece na primeira execução
no `main`. Pontos a observar nessa execução: o tempo para apagar a pasta antiga (cerca de 8.800
arquivos por FTP) e se a hospedagem aceita todas as diretivas do `.htaccess`. Se a página der erro 500,
o registro de erros do cPanel indica a diretiva recusada.
