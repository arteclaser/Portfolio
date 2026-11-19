#!/usr/bin/env bash
# Implantação do site estático por FTP com criptografia (FTPS explícito, porta 21), sem SSH.
#
# Usado pelo GitHub Actions (.github/workflows/implantacao.yml). Também roda em qualquer computador
# com bash, lftp, rsync, curl, openssl e sha256sum, a partir da raiz do projeto, depois de "npm run build".
#
# Obrigatórias: FTP_SERVIDOR, FTP_USUARIO, FTP_SENHA, SITE_URL
# Opcionais:    FTP_PORTA (21), FTP_CRIPTOGRAFIA (obrigatoria | desligada),
#               FTP_CONFERIR_CERTIFICADO (sim | nao), FTP_CONEXOES (4),
#               FTP_DOMINIO_CERTIFICADO (ex.: hostgator.com.br), PUBLIC_DIR (vazio = descobrir sozinho),
#               APP_ANTIGO (mostraqui-app), REMOVER_APP_ANTIGO (sim | nao)
#
# Como funciona:
#  1. conecta e descobre a pasta pública do site: envia um arquivo de teste para as pastas prováveis
#     e confere qual delas o site devolve. A pasta de outro site da mesma conta nunca é usada;
#  2. monta a lista (manifesto) dos arquivos de dist/ com o hash de cada um e compara com a lista
#     da implantação anterior, guardada no servidor: só o que mudou é enviado, e só é apagado o que a
#     própria implantação enviou antes e deixou de existir;
#  3. na primeira vez depois do aplicativo anterior (Laravel), retira os arquivos públicos dele, que
#     constam no manifesto antigo, e apaga a pasta do código (com o .env e os arquivos enviados);
#  4. confere pela internet que a versão publicada é a que foi enviada.
set -euo pipefail

aviso() { echo "::warning::$*"; }
erro() { echo "::error::$*" >&2; exit 1; }
etapa() { echo; echo "==> $*"; }

# ---------------------------------------------------------------- configuração
FTP_PORTA="${FTP_PORTA:-21}"
FTP_CRIPTOGRAFIA="${FTP_CRIPTOGRAFIA:-obrigatoria}"
FTP_CONFERIR_CERTIFICADO="${FTP_CONFERIR_CERTIFICADO:-sim}"
FTP_CONEXOES="${FTP_CONEXOES:-4}"
PUBLIC_DIR="${PUBLIC_DIR:-}"
APP_ANTIGO="${APP_ANTIGO:-mostraqui-app}"
REMOVER_APP_ANTIGO="${REMOVER_APP_ANTIGO:-sim}"
LIMITE_EXCLUSOES="${LIMITE_EXCLUSOES:-2000}"
ORIGEM="${ORIGEM:-dist}"
# Domínio do certificado do FTP. Na HostGator, o FTP apresenta um certificado *.hostgator.com.br,
# que não corresponde a ftp.seudominio nem ao IP. Com esta opção, a conexão vai ao endereço de
# FTP_SERVIDOR, mas o certificado é conferido como *.FTP_DOMINIO_CERTIFICADO (cadeia e nome).
FTP_DOMINIO_CERTIFICADO="${FTP_DOMINIO_CERTIFICADO:-}"

[ -n "${FTP_SERVIDOR:-}" ] || erro "Falta o segredo FTP_SERVIDOR (Settings → Secrets and variables → Actions)."
[ -n "${FTP_USUARIO:-}" ] || erro "Falta o segredo FTP_USUARIO."
[ -n "${FTP_SENHA:-}" ] || erro "Falta o segredo FTP_SENHA."
[ -n "${SITE_URL:-}" ] || erro "Falta a variável SITE_URL (ex.: https://mostraqui.net)."

FTP_SERVIDOR="${FTP_SERVIDOR#ftp://}"; FTP_SERVIDOR="${FTP_SERVIDOR#ftpes://}"; FTP_SERVIDOR="${FTP_SERVIDOR%%/*}"
SITE_URL="${SITE_URL%/}"
[[ "$FTP_SERVIDOR" =~ ^[A-Za-z0-9.-]+$ ]] || erro "FTP_SERVIDOR inválido: use só o endereço do servidor (ex.: br123.hostgator.com.br)."
[[ "$FTP_USUARIO" =~ ^[A-Za-z0-9._@+-]+$ ]] || erro "FTP_USUARIO contém caracteres não aceitos."
[[ "$FTP_PORTA" =~ ^[0-9]{1,5}$ ]] || erro "FTP_PORTA inválida."
[[ "$FTP_CONEXOES" =~ ^[1-6]$ ]] || erro "FTP_CONEXOES deve ser de 1 a 6."
[[ "$SITE_URL" =~ ^https?://[A-Za-z0-9.-]+(:[0-9]+)?$ ]] || erro "SITE_URL inválida: use só o endereço do site, como https://mostraqui.net."
[[ -z "$FTP_DOMINIO_CERTIFICADO" || "$FTP_DOMINIO_CERTIFICADO" =~ ^[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)+$ ]] || erro "FTP_DOMINIO_CERTIFICADO inválido (ex.: hostgator.com.br)."
case "$FTP_CRIPTOGRAFIA" in
  obrigatoria) SSL_ALLOW=yes; SSL_FORCE=yes ;;
  desligada)
    SSL_ALLOW=no; SSL_FORCE=no
    aviso "FTP_CRIPTOGRAFIA=desligada: a senha do FTP e os arquivos trafegam sem proteção. Use só se o servidor não aceitar FTPS." ;;
  *) erro "FTP_CRIPTOGRAFIA deve ser 'obrigatoria' ou 'desligada'." ;;
esac
case "$FTP_CONFERIR_CERTIFICADO" in
  sim) VERIFICAR=yes ;;
  nao) VERIFICAR=no; [ "$SSL_FORCE" = yes ] && aviso "FTP_CONFERIR_CERTIFICADO=nao: a conexão é criptografada, mas a identidade do servidor não é conferida." ;;
  *) erro "FTP_CONFERIR_CERTIFICADO deve ser 'sim' ou 'nao'." ;;
esac
case "$REMOVER_APP_ANTIGO" in sim|nao) ;; *) erro "REMOVER_APP_ANTIGO deve ser 'sim' ou 'nao'." ;; esac

# Caminhos relativos à pasta inicial da conta FTP, sem "..".
caminho_valido='^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$'
validar_caminho() { # $1 = nome da variável
  local valor="${!1}"
  [[ "$valor" =~ $caminho_valido ]] && [[ "/$valor/" != *"/../"* ]] && [[ "/$valor/" != *"/./"* ]] \
    || erro "$1 inválido: '$valor'. Use um caminho relativo simples."
}
[ -z "$PUBLIC_DIR" ] || validar_caminho PUBLIC_DIR
[[ "$APP_ANTIGO" =~ ^[A-Za-z0-9_-][A-Za-z0-9._-]*$ ]] || erro "APP_ANTIGO precisa ser um nome simples de pasta (ex.: mostraqui-app)."

for programa in lftp rsync curl openssl sha256sum; do
  command -v "$programa" >/dev/null || erro "Programa necessário não encontrado: $programa."
done
[ -f "$ORIGEM/index.html" ] && [ -f "$ORIGEM/versao.json" ] && [ -f "$ORIGEM/.htaccess" ] \
  || erro "A pasta '$ORIGEM' não tem o site montado. Rode 'npm run build' antes."
VERSAO_LOCAL="$(sed -n 's/.*"versao": *"\([^"]*\)".*/\1/p' "$ORIGEM/versao.json" | head -1)"
[ -n "$VERSAO_LOCAL" ] || erro "Não foi possível ler a versão em $ORIGEM/versao.json."

# Se o site redireciona http → https para o mesmo endereço, passa a usar https.
if [[ "$SITE_URL" == http://* ]]; then
  destino=$(curl -s -o /dev/null --max-time 20 -w '%{redirect_url}' "$SITE_URL/versao.json" || true)
  if [ "$destino" = "https://${SITE_URL#http://}/versao.json" ]; then
    SITE_URL="https://${SITE_URL#http://}"
    echo "O site redireciona para HTTPS: usando $SITE_URL (atualize a variável SITE_URL quando puder)."
  fi
fi

TRABALHO="$(mktemp -d)"
CONEXAO="$FTP_SERVIDOR"
NOME_HOSTS=""

# ---------------------------------------------------------------- FTP
lftp_executar() { # $1 = arquivo com os comandos (depois da conexão)
  {
    cat <<EOF
set cmd:fail-exit yes
set cmd:interactive no
set net:timeout 30
set net:max-retries 3
set net:reconnect-interval-base 5
set net:reconnect-interval-max 30
set ftp:passive-mode yes
set ftp:ssl-allow $SSL_ALLOW
set ftp:ssl-force $SSL_FORCE
set ftp:ssl-protect-data $SSL_FORCE
set ftp:ssl-protect-list $SSL_FORCE
set ssl:verify-certificate $VERIFICAR
set xfer:use-temp-file yes
set xfer:clobber yes
open --env-password -u "$FTP_USUARIO" -p $FTP_PORTA $CONEXAO
EOF
    cat "$1"
  } > "$TRABALHO/sessao.lftp"
  LFTP_PASSWORD="$FTP_SENHA" lftp -f "$TRABALHO/sessao.lftp"
}

lftp_comandos() { printf '%s\n' "$@" > "$TRABALHO/comandos.lftp"; lftp_executar "$TRABALHO/comandos.lftp"; }

finalizar() {
  local status=$?
  set +e
  if [ -n "$NOME_HOSTS" ]; then
    if [ -w /etc/hosts ]; then sed -i "/ $NOME_HOSTS\$/d" /etc/hosts; else sudo -n sed -i "/ $NOME_HOSTS\$/d" /etc/hosts 2>/dev/null; fi
  fi
  rm -rf "$TRABALHO"
  exit "$status"
}
trap finalizar EXIT

# ---------------------------------------------------------------- 1. conexão
etapa "Conectando a $FTP_SERVIDOR:$FTP_PORTA (criptografia: $FTP_CRIPTOGRAFIA)"
if [ "$VERIFICAR" = yes ] && [ -n "$FTP_DOMINIO_CERTIFICADO" ]; then
  case "$FTP_SERVIDOR" in
    *."$FTP_DOMINIO_CERTIFICADO") ;; # o nome já é coberto pelo certificado
    *)
      ip=$(getent ahostsv4 "$FTP_SERVIDOR" 2>/dev/null | awk 'NR==1 {print $1}' || true)
      [ -n "$ip" ] || erro "Não foi possível encontrar o endereço de FTP_SERVIDOR. Confira o valor do segredo."
      # Nome de uso único dentro do domínio do certificado, apontado para o IP do servidor.
      NOME_HOSTS="implantacao-$(openssl rand -hex 4).$FTP_DOMINIO_CERTIFICADO"
      if [ -w /etc/hosts ]; then echo "$ip $NOME_HOSTS" >> /etc/hosts
      elif sudo -n true 2>/dev/null; then echo "$ip $NOME_HOSTS" | sudo tee -a /etc/hosts >/dev/null
      else erro "Para conferir o certificado como *.$FTP_DOMINIO_CERTIFICADO é preciso poder editar /etc/hosts. Use em FTP_SERVIDOR o nome do servidor (ex.: br123.$FTP_DOMINIO_CERTIFICADO)."
      fi
      CONEXAO="$NOME_HOSTS"
      echo "O certificado do FTP será conferido como *.$FTP_DOMINIO_CERTIFICADO."
      ;;
  esac
fi
if ! lftp_comandos "cls -1" > "$TRABALHO/raiz.txt" 2> "$TRABALHO/conexao.err"; then
  cat "$TRABALHO/conexao.err" >&2
  dica="Confira FTP_SERVIDOR, FTP_USUARIO e FTP_SENHA."
  if grep -qi "certificate\|certificado" "$TRABALHO/conexao.err"; then
    dica="O certificado do servidor não corresponde a FTP_SERVIDOR. Use em FTP_SERVIDOR o nome do servidor da hospedagem (ex.: br123.hostgator.com.br) ou cadastre o domínio do certificado na variável FTP_DOMINIO_CERTIFICADO."
    [ -n "$NOME_HOSTS" ] && dica="O certificado do servidor não é um certificado válido de *.$FTP_DOMINIO_CERTIFICADO. Por segurança, a conexão foi recusada."
  fi
  grep -qi "ssl\|tls\|auth" "$TRABALHO/conexao.err" && ! grep -qi "certificate" "$TRABALHO/conexao.err" && dica="O servidor não aceitou FTP com criptografia (FTPS). Confirme com o suporte da hospedagem."
  grep -qi "login incorrect\|530" "$TRABALHO/conexao.err" && dica="Usuário ou senha do FTP recusados. Confira FTP_USUARIO (com @dominio, se for conta adicional) e FTP_SENHA."
  erro "Não foi possível conectar ao FTP. $dica"
fi
# (|| true: com "set -o pipefail", um grep sem resultado encerraria o script sem mensagem)
vista=$(sed -E 's#/$##' "$TRABALHO/raiz.txt" | grep -vxE '\.|\.\.' | head -15 | tr '\n' ' ' || true)
PASTA_PESSOAL=1
grep -qxE 'public_html/?' "$TRABALHO/raiz.txt" || PASTA_PESSOAL=0
echo "Conectado. A conta FTP vê: ${vista:-(pasta vazia)}"

# ---------------------------------------------------------------- 2. pasta pública do site
etapa "Descobrindo a pasta pública de $SITE_URL"
HOST="${SITE_URL#*://}"; HOST="${HOST%%:*}"; HOST="${HOST#www.}"
if [ -n "$PUBLIC_DIR" ]; then
  candidatas=("$PUBLIC_DIR")
elif [ "$PASTA_PESSOAL" = 0 ]; then
  # A conta FTP não vê a pasta pessoal: ela começa direto na pasta do domínio.
  candidatas=(".")
else
  # Domínio adicional (pasta própria) primeiro; public_html só se for o domínio principal da conta.
  candidatas=("$HOST" "public_html/$HOST" "${HOST%%.*}" "public_html/${HOST%%.*}" "public_html")
fi
SONDA="mostraqui-sonda-$(openssl rand -hex 12).txt"
openssl rand -hex 24 > "$TRABALHO/sonda.txt"
PUBLIC_DIR=""
for pasta in "${candidatas[@]}"; do
  lftp_comandos "put \"$TRABALHO/sonda.txt\" -o \"$pasta/$SONDA\"" >/dev/null 2>&1 || { echo "  $pasta: não existe ou sem permissão"; continue; }
  resposta=$(curl -sSL --max-time 20 "$SITE_URL/$SONDA" 2>/dev/null || true)
  lftp_comandos "rm -f \"$pasta/$SONDA\"" >/dev/null 2>&1 || aviso "Não foi possível apagar o arquivo de teste $pasta/$SONDA. Apague-o pelo Gerenciador de arquivos."
  if [ "$resposta" = "$(cat "$TRABALHO/sonda.txt")" ]; then
    PUBLIC_DIR="$pasta"; echo "  $pasta: é a pasta pública de $SITE_URL"; break
  fi
  echo "  $pasta: não é a pasta de $SITE_URL"
done
[ -n "$PUBLIC_DIR" ] || erro "Não encontrei a pasta pública de $SITE_URL (testadas: ${candidatas[*]}). Veja em cPanel → Domínios a 'Raiz do documento' de $HOST e cadastre-a na variável PUBLIC_DIR."

# Pasta do aplicativo anterior: ao lado da pasta pública ou, na conta presa à pasta do domínio, dentro dela.
[ "$APP_ANTIGO" != "$PUBLIC_DIR" ] || erro "APP_ANTIGO e PUBLIC_DIR não podem ser a mesma pasta."
case "$PUBLIC_DIR/" in "$APP_ANTIGO"/*) erro "PUBLIC_DIR não pode ficar dentro de APP_ANTIGO." ;; esac
MANIFESTO_REMOTO="$PUBLIC_DIR/.implantacao/manifesto.txt"
MANIFESTO_ANTIGO="$APP_ANTIGO/.implantacao/manifesto.txt"

# ---------------------------------------------------------------- 3. manifesto
etapa "Montando a lista de arquivos (versão $VERSAO_LOCAL)"
(cd "$ORIGEM" && find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum | sed 's#  \./#  #') > "$TRABALHO/manifesto.novo"
grep -q '^\\' "$TRABALHO/manifesto.novo" && erro "Há arquivos com nomes não suportados (quebra de linha ou barra invertida)."
if grep -v '^[0-9a-f]\{64\}  [A-Za-z0-9._/@+~()-]*$' "$TRABALHO/manifesto.novo" | head -3 | grep -q .; then
  grep -v '^[0-9a-f]\{64\}  [A-Za-z0-9._/@+~()-]*$' "$TRABALHO/manifesto.novo" | head -5 | cut -c67- >&2
  erro "Há arquivos com caracteres não suportados no nome (acima)."
fi
grep -q '  \.implantacao/' "$TRABALHO/manifesto.novo" && erro "A pasta '$ORIGEM' não pode conter .implantacao/."
echo "$(wc -l < "$TRABALHO/manifesto.novo") arquivos no site."

etapa "Comparando com a implantação anterior"
MIGRACAO=0
: > "$TRABALHO/antigos-publicos.txt"
if lftp_comandos "get \"$MANIFESTO_REMOTO\" -o \"$TRABALHO/manifesto.atual\"" >/dev/null 2>&1 && [ -s "$TRABALHO/manifesto.atual" ]; then
  echo "Implantação anterior do site estático encontrada."
else
  : > "$TRABALHO/manifesto.atual"
  if lftp_comandos "get \"$MANIFESTO_ANTIGO\" -o \"$TRABALHO/manifesto.laravel\"" >/dev/null 2>&1 && [ -s "$TRABALHO/manifesto.laravel" ]; then
    MIGRACAO=1
    # Arquivos públicos que o aplicativo anterior enviou (linhas "hash  public/caminho").
    sed -n 's#^[0-9a-f]\{64\}  public/##p' "$TRABALHO/manifesto.laravel" | LC_ALL=C sort -u > "$TRABALHO/antigos-publicos.txt"
    grep -Eq '(^|/)\.\.(/|$)|^/|^\.well-known/' "$TRABALHO/antigos-publicos.txt" && erro "O manifesto do aplicativo anterior tem caminhos inesperados. Implantação interrompida por segurança."
    echo "Aplicativo anterior encontrado em '$APP_ANTIGO': $(wc -l < "$TRABALHO/antigos-publicos.txt") arquivos públicos dele serão substituídos ou retirados."
  else
    echo "Nenhuma implantação anterior encontrada: todos os arquivos serão enviados."
  fi
  # Nunca substitui outro site: a pasta pública não pode ter outro sistema que não seja o aplicativo anterior.
  lftp_comandos "cls -1a \"$PUBLIC_DIR\"" > "$TRABALHO/publica.txt" 2>/dev/null \
    || erro "Não foi possível listar a pasta pública '$PUBLIC_DIR' para conferir se ela já tem outro site."
  outros=$(sed -E 's#/$##; s#.*/##' "$TRABALHO/publica.txt" | grep -xE 'index\.php|index\.html|wp-config\.php|wp-content|wp-admin|wp-includes|configuration\.php|artisan' | sort -u || true)
  estranhos=""
  for nome in $outros; do
    grep -qxF "$nome" "$TRABALHO/antigos-publicos.txt" || estranhos="$estranhos $nome"
  done
  [ -z "$estranhos" ] || erro "A pasta pública '$PUBLIC_DIR' já tem outro site (${estranhos# }). A implantação não substitui sites desconhecidos. Confira em cPanel → Domínios se essa é mesmo a pasta de $HOST."
fi

LC_ALL=C sort "$TRABALHO/manifesto.atual" > "$TRABALHO/atual.ord"
LC_ALL=C sort "$TRABALHO/manifesto.novo" > "$TRABALHO/novo.ord"
LC_ALL=C comm -13 "$TRABALHO/atual.ord" "$TRABALHO/novo.ord" | cut -c67- > "$TRABALHO/enviar.txt"
# Para apagar: o que a implantação anterior enviou (site estático ou aplicativo antigo) e não existe mais.
{ cut -c67- "$TRABALHO/atual.ord"; cat "$TRABALHO/antigos-publicos.txt"; } | LC_ALL=C sort -u > "$TRABALHO/enviados-antes.txt"
LC_ALL=C comm -23 "$TRABALHO/enviados-antes.txt" <(cut -c67- "$TRABALHO/novo.ord" | LC_ALL=C sort -u) > "$TRABALHO/apagar.txt"

# Só apaga o que a implantação enviou, nunca arquivos do servidor.
if grep -Eq '(^|/)\.\.(/|$)|^/|^\.well-known/|^\.implantacao/' "$TRABALHO/apagar.txt"; then
  erro "A lista de exclusões tem caminhos protegidos. Implantação interrompida por segurança."
fi
[ "$(wc -l < "$TRABALHO/apagar.txt")" -le "$LIMITE_EXCLUSOES" ] \
  || erro "Mais de $LIMITE_EXCLUSOES arquivos seriam apagados. Confira o que mudou (ou defina LIMITE_EXCLUSOES)."
echo "Para enviar: $(wc -l < "$TRABALHO/enviar.txt") · para apagar: $(wc -l < "$TRABALHO/apagar.txt")"

# ---------------------------------------------------------------- 4. envio
etapa "Enviando arquivos ($FTP_CONEXOES conexões)"
ENVIO="$TRABALHO/envio"
mkdir -p "$ENVIO"
if [ -s "$TRABALHO/enviar.txt" ]; then
  rsync -a --files-from="$TRABALHO/enviar.txt" "$ORIGEM/" "$ENVIO/"
fi
# O .htaccess da raiz vai separado e por último: até lá, o site anterior continua respondendo.
HTACCESS_RAIZ=""
if [ -f "$ENVIO/.htaccess" ]; then
  HTACCESS_RAIZ="$TRABALHO/htaccess-raiz"
  mv "$ENVIO/.htaccess" "$HTACCESS_RAIZ"
fi
{
  echo "mkdir -p -f \"$PUBLIC_DIR/.implantacao\""
  if [ -n "$(find "$ENVIO" -mindepth 1 -print -quit)" ]; then
    echo "mirror -R --parallel=$FTP_CONEXOES --transfer-all --no-perms --no-symlinks \"$ENVIO\" \"$PUBLIC_DIR\""
  fi
  [ -n "$HTACCESS_RAIZ" ] && echo "put \"$HTACCESS_RAIZ\" -o \"$PUBLIC_DIR/.htaccess\""
  while IFS= read -r arquivo; do
    echo "rm -f \"$PUBLIC_DIR/$arquivo\""
  done < "$TRABALHO/apagar.txt"
  # O manifesto só é atualizado depois que todos os arquivos chegaram.
  echo "put \"$TRABALHO/manifesto.novo\" -o \"$MANIFESTO_REMOTO\""
} > "$TRABALHO/envio.lftp"
inicio=$(date +%s)
lftp_executar "$TRABALHO/envio.lftp" || erro "O envio por FTP falhou (veja a mensagem acima). Rode a implantação de novo para completar o envio."
echo "Envio concluído em $(( $(date +%s) - inicio )) s."

# ---------------------------------------------------------------- 5. conferência pela internet
etapa "Conferindo o site publicado"
publicada=""
for tentativa in 1 2 3 4 5 6; do
  publicada=$(curl -sSL --max-time 20 -H 'Cache-Control: no-cache' "$SITE_URL/versao.json?t=$(date +%s)" 2>/dev/null | sed -n 's/.*"versao": *"\([^"]*\)".*/\1/p' | head -1 || true)
  [ "$publicada" = "$VERSAO_LOCAL" ] && break
  sleep 5
done
[ "$publicada" = "$VERSAO_LOCAL" ] || erro "O site respondeu com a versão '${publicada:-nenhuma}', mas a enviada foi '$VERSAO_LOCAL'. Confira SITE_URL e a pasta pública."
codigo=$(curl -sL -o "$TRABALHO/inicio.html" --max-time 30 -w '%{http_code}' "$SITE_URL/" || true)
[ "$codigo" = "200" ] && grep -q 'Mostraqui Capinzal' "$TRABALHO/inicio.html" || erro "A página inicial não abriu como esperado (HTTP $codigo)."
for caminho in ".implantacao/manifesto.txt" ".htaccess"; do
  codigo=$(curl -s -o /dev/null --max-time 20 -w '%{http_code}' "$SITE_URL/$caminho" || true)
  echo "  /$caminho → HTTP $codigo"
  case "$codigo" in 403|404) ;; *) erro "O arquivo /$caminho ficou acessível pela internet (HTTP $codigo; o esperado é 403). Confira se a hospedagem aplica os arquivos .htaccess." ;; esac
done
echo "Versão $VERSAO_LOCAL no ar em $SITE_URL."

# ---------------------------------------------------------------- 6. aplicativo anterior
if lftp_comandos "cls -1a \"$APP_ANTIGO\"" > "$TRABALHO/antigo.txt" 2>/dev/null && [ -s "$TRABALHO/antigo.txt" ]; then
  etapa "Aplicativo anterior ('$APP_ANTIGO')"
  # Antes de qualquer coisa: a pasta do código antigo (onde fica o .env) precisa estar bloqueada para a internet.
  for caminho in "$APP_ANTIGO/.env" "$APP_ANTIGO/artisan" "$APP_ANTIGO/composer.json"; do
    codigo=$(curl -s -o /dev/null --max-time 20 -w '%{http_code}' "$SITE_URL/$caminho" || true)
    case "$codigo" in 403|404) ;; *) erro "A pasta do aplicativo anterior ficou acessível pela internet (/$caminho respondeu HTTP $codigo). Apague-a pelo Gerenciador de arquivos e troque as senhas do .env." ;; esac
  done
  nomes=$(sed -E 's#/$##; s#.*/##' "$TRABALHO/antigo.txt")
  if [ "$REMOVER_APP_ANTIGO" = nao ]; then
    aviso "A pasta '$APP_ANTIGO' do aplicativo anterior continua no servidor, bloqueada para a internet (REMOVER_APP_ANTIGO=nao). Ela guarda o .env com a senha do banco: apague-a quando não precisar mais."
  elif grep -qx 'artisan' <<< "$nomes" && grep -qx 'vendor' <<< "$nomes"; then
    echo "Apagando a pasta do aplicativo anterior (código, .env e arquivos enviados). Isso pode levar vários minutos..."
    inicio=$(date +%s)
    if lftp_comandos "rm -r -f \"$APP_ANTIGO\"" > "$TRABALHO/remocao.log" 2>&1; then
      echo "Pasta '$APP_ANTIGO' apagada em $(( $(date +%s) - inicio )) s. O banco de dados MySQL não é alterado: se não for mais usado, apague-o em cPanel → Bancos de dados MySQL."
    else
      tail -5 "$TRABALHO/remocao.log" >&2
      aviso "Não foi possível apagar toda a pasta '$APP_ANTIGO'. Ela continua bloqueada para a internet; a próxima implantação tenta de novo."
    fi
  else
    aviso "A pasta '$APP_ANTIGO' existe, mas não parece ser o aplicativo anterior (sem 'artisan' e 'vendor'): não foi apagada."
  fi
fi

echo
echo "Implantação concluída."
