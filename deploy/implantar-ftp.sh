#!/usr/bin/env bash
# Implantação por FTP com criptografia (FTPS explícito, porta 21), para hospedagem sem SSH.
#
# Usado pelo GitHub Actions (.github/workflows/implantacao.yml). Também roda em qualquer
# computador com bash, lftp, rsync, curl, openssl e sha256sum, a partir da raiz do projeto
# já com "composer install --no-dev".
#
# Obrigatórias: FTP_SERVIDOR, FTP_USUARIO, FTP_SENHA, SITE_URL
# Opcionais:    FTP_PORTA (21), FTP_CRIPTOGRAFIA (obrigatoria | desligada),
#               FTP_CONFERIR_CERTIFICADO (sim | nao), FTP_CONEXOES (4),
#               APP_DIR (mostraqui-app), PUBLIC_DIR (public_html)
#
# Como funciona:
#  1. monta o pacote (código → APP_DIR; public/ → PUBLIC_DIR) e um manifesto com o hash de
#     cada arquivo;
#  2. compara com o manifesto da implantação anterior, guardado no servidor, e envia só o que
#     mudou; apaga só arquivos que a própria implantação enviou antes e que deixaram de existir
#     (.env, storage/, fotos, backups e arquivos alheios em public_html nunca são tocados);
#  3. coloca o site em manutenção durante o envio (exceto na primeira vez);
#  4. chama POST /_implantacao/finalizar com um código de uso único, que cria o .env na
#     primeira vez, atualiza o banco, limpa os caches e tira o site da manutenção.
set -euo pipefail

aviso() { echo "::warning::$*"; }
erro() { echo "::error::$*" >&2; exit 1; }
etapa() { echo; echo "==> $*"; }

# ---------------------------------------------------------------- configuração
FTP_PORTA="${FTP_PORTA:-21}"
FTP_CRIPTOGRAFIA="${FTP_CRIPTOGRAFIA:-obrigatoria}"
FTP_CONFERIR_CERTIFICADO="${FTP_CONFERIR_CERTIFICADO:-sim}"
FTP_CONEXOES="${FTP_CONEXOES:-4}"
APP_DIR="${APP_DIR:-mostraqui-app}"
PUBLIC_DIR="${PUBLIC_DIR:-public_html}"
LIMITE_EXCLUSOES="${LIMITE_EXCLUSOES:-5000}"

[ -n "${FTP_SERVIDOR:-}" ] || erro "Falta o segredo FTP_SERVIDOR (Settings → Secrets and variables → Actions)."
[ -n "${FTP_USUARIO:-}" ] || erro "Falta o segredo FTP_USUARIO."
[ -n "${FTP_SENHA:-}" ] || erro "Falta o segredo FTP_SENHA."
[ -n "${SITE_URL:-}" ] || erro "Falta a variável SITE_URL (ex.: http://mostraqui.net antes do SSL, https://mostraqui.net depois)."

FTP_SERVIDOR="${FTP_SERVIDOR#ftp://}"; FTP_SERVIDOR="${FTP_SERVIDOR#ftpes://}"; FTP_SERVIDOR="${FTP_SERVIDOR%%/*}"
SITE_URL="${SITE_URL%/}"
[[ "$FTP_SERVIDOR" =~ ^[A-Za-z0-9.-]+$ ]] || erro "FTP_SERVIDOR inválido: use só o endereço do servidor (ex.: br123.hostgator.com.br)."
[[ "$FTP_USUARIO" =~ ^[A-Za-z0-9._@+-]+$ ]] || erro "FTP_USUARIO contém caracteres não aceitos."
[[ "$FTP_PORTA" =~ ^[0-9]{1,5}$ ]] || erro "FTP_PORTA inválida."
[[ "$FTP_CONEXOES" =~ ^[1-6]$ ]] || erro "FTP_CONEXOES deve ser de 1 a 6 (a HostGator aceita até 8 conexões simultâneas por conta)."
[[ "$SITE_URL" =~ ^https?://[A-Za-z0-9.-]+(:[0-9]+)?$ ]] || erro "SITE_URL inválida: use só o endereço do site, como https://mostraqui.net."
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

# Caminhos relativos à pasta inicial da conta FTP, sem "..": o código nunca fica dentro da pasta pública.
caminho_valido='^[A-Za-z0-9._-]+(/[A-Za-z0-9._-]+)*$'
for nome in APP_DIR PUBLIC_DIR; do
  valor="${!nome}"
  [[ "$valor" =~ $caminho_valido ]] && [[ "/$valor/" != *"/../"* ]] && [[ "/$valor/" != *"/./"* ]] \
    || erro "$nome inválido: '$valor'. Use um caminho relativo simples, como mostraqui-app."
done
[ "$APP_DIR" != "$PUBLIC_DIR" ] || erro "APP_DIR e PUBLIC_DIR não podem ser a mesma pasta."
case "$APP_DIR/" in "$PUBLIC_DIR"/*) erro "APP_DIR não pode ficar dentro de PUBLIC_DIR: o .env ficaria acessível pela internet." ;; esac
case "$PUBLIC_DIR/" in "$APP_DIR"/*) erro "PUBLIC_DIR não pode ficar dentro de APP_DIR." ;; esac

for programa in lftp rsync curl openssl sha256sum; do
  command -v "$programa" >/dev/null || erro "Programa necessário não encontrado: $programa."
done
[ -f artisan ] && [ -f vendor/autoload.php ] || erro "Rode a partir da raiz do projeto, depois de 'composer install --no-dev'."

TRABALHO="$(mktemp -d)"
EM_MANUTENCAO=0
CODIGO_ENVIADO=0
CONCLUIDO=0

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
open --env-password -u "$FTP_USUARIO" -p $FTP_PORTA $FTP_SERVIDOR
EOF
    cat "$1"
  } > "$TRABALHO/sessao.lftp"
  LFTP_PASSWORD="$FTP_SENHA" lftp -f "$TRABALHO/sessao.lftp"
}

lftp_comandos() { printf '%s\n' "$@" > "$TRABALHO/comandos.lftp"; lftp_executar "$TRABALHO/comandos.lftp"; }

finalizar() {
  local status=$?
  set +e
  if [ "$CODIGO_ENVIADO" = 1 ] || { [ "$EM_MANUTENCAO" = 1 ] && [ "$CONCLUIDO" = 0 ]; }; then
    comandos=("rm -f \"$APP_DIR/.implantacao/token.sha256\"")
    if [ "$EM_MANUTENCAO" = 1 ] && [ "$CONCLUIDO" = 0 ]; then
      comandos+=("rm -f \"$APP_DIR/storage/framework/down\"")
      echo "Tirando o site da manutenção após a falha..."
    fi
    lftp_comandos "${comandos[@]}" >/dev/null 2>&1 || aviso "Não foi possível limpar os arquivos temporários da implantação no servidor."
  fi
  rm -rf "$TRABALHO"
  exit "$status"
}
trap finalizar EXIT

# ---------------------------------------------------------------- 1. conexão
etapa "Conectando a $FTP_SERVIDOR:$FTP_PORTA (criptografia: $FTP_CRIPTOGRAFIA)"
if ! lftp_comandos "cls -1" > "$TRABALHO/raiz.txt" 2> "$TRABALHO/conexao.err"; then
  cat "$TRABALHO/conexao.err" >&2
  dica="Confira FTP_SERVIDOR, FTP_USUARIO e FTP_SENHA."
  if grep -qi "certificate\|certificado" "$TRABALHO/conexao.err"; then
    dica="O certificado do servidor não corresponde a FTP_SERVIDOR. Use em FTP_SERVIDOR o nome do servidor da hospedagem (cPanel → Informações gerais, ou o endereço do próprio cPanel), como br123.hostgator.com.br."
    # Mostra em nome de quem o certificado foi emitido, para indicar o valor correto.
    nomes=$(timeout 20 openssl s_client -connect "$FTP_SERVIDOR:$FTP_PORTA" -starttls ftp -servername "$FTP_SERVIDOR" </dev/null 2>/dev/null \
      | openssl x509 -noout -subject -ext subjectAltName 2>/dev/null \
      | grep -oE '(CN ?= ?|DNS:)[^,/ ]+' | sed -E 's/^(CN ?= ?|DNS:)//' | sort -u | tr '\n' ' ')
    [ -n "$nomes" ] && dica="$dica O certificado apresentado foi emitido para: ${nomes% }. Cadastre esse nome em FTP_SERVIDOR (se começar com *., troque o * pelo nome do servidor, como br123)."
  fi
  grep -qi "ssl\|tls\|auth" "$TRABALHO/conexao.err" && ! grep -qi "certificate" "$TRABALHO/conexao.err" && dica="O servidor não aceitou FTP com criptografia (FTPS). Confirme com o suporte da HostGator."
  grep -qi "login incorrect\|530" "$TRABALHO/conexao.err" && dica="Usuário ou senha do FTP recusados. Confira FTP_USUARIO (com @dominio, se for conta adicional) e FTP_SENHA."
  grep -qi "530" "$TRABALHO/conexao.err" && [ "$SSL_FORCE" = no ] && dica="$dica Com FTP_CRIPTOGRAFIA=desligada, o servidor também pode estar recusando o acesso sem criptografia."
  erro "Não foi possível conectar ao FTP. $dica"
fi
primeira_pasta="${PUBLIC_DIR%%/*}"
grep -qx "$primeira_pasta" "$TRABALHO/raiz.txt" || grep -qx "$primeira_pasta/" "$TRABALHO/raiz.txt" \
  || erro "A conta FTP não enxerga a pasta '$primeira_pasta' no início. Crie a conta FTP com o diretório '/' (pasta pessoal inteira) ou use a conta FTP principal do cPanel."
echo "Conectado. Pasta inicial com: $(tr '\n' ' ' < "$TRABALHO/raiz.txt" | cut -c1-200)"

# ---------------------------------------------------------------- 2. pacote e manifesto
etapa "Montando o pacote"
PACOTE="$TRABALHO/pacote"
mkdir -p "$PACOTE/app" "$PACOTE/public"
rsync -a --copy-links --exclude-from=deploy/excluir.txt ./ "$PACOTE/app/"
rsync -a --copy-links ./public/ "$PACOTE/public/"
(cd "$PACOTE" && find app public -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum) > "$TRABALHO/manifesto.novo"
grep -q '^\\' "$TRABALHO/manifesto.novo" && erro "Há arquivos com nomes não suportados (quebra de linha ou barra invertida) no pacote."
if grep -v '^[0-9a-f]\{64\}  \(app\|public\)/[A-Za-z0-9._/@+~()-]*$' "$TRABALHO/manifesto.novo" | head -3 | grep -q .; then
  grep -v '^[0-9a-f]\{64\}  \(app\|public\)/[A-Za-z0-9._/@+~()-]*$' "$TRABALHO/manifesto.novo" | head -5 | cut -c67- >&2
  erro "Há arquivos com caracteres não suportados no nome (acima)."
fi
echo "$(wc -l < "$TRABALHO/manifesto.novo") arquivos no pacote."

etapa "Comparando com a implantação anterior"
if lftp_comandos "get \"$APP_DIR/.implantacao/manifesto.txt\" -o \"$TRABALHO/manifesto.atual\"" >/dev/null 2>&1 && [ -s "$TRABALHO/manifesto.atual" ]; then
  PRIMEIRA=0
else
  PRIMEIRA=1
  : > "$TRABALHO/manifesto.atual"
  echo "Nenhuma implantação anterior encontrada: todos os arquivos serão enviados (a primeira vez demora mais)."
fi
LC_ALL=C sort "$TRABALHO/manifesto.atual" > "$TRABALHO/atual.ord"
LC_ALL=C sort "$TRABALHO/manifesto.novo" > "$TRABALHO/novo.ord"
LC_ALL=C comm -13 "$TRABALHO/atual.ord" "$TRABALHO/novo.ord" | cut -c67- > "$TRABALHO/enviar.txt"
LC_ALL=C comm -23 <(cut -c67- "$TRABALHO/atual.ord" | LC_ALL=C sort -u) <(cut -c67- "$TRABALHO/novo.ord" | LC_ALL=C sort -u) > "$TRABALHO/apagar.txt"

# Só apaga o que a implantação enviou, nunca dados do servidor.
if grep -Ev '^(app|public)/' "$TRABALHO/apagar.txt" | grep -q . \
  || grep -Eq '^app/(\.env|storage/|\.implantacao/)|^public/\.well-known/' "$TRABALHO/apagar.txt"; then
  erro "O manifesto do servidor pede a exclusão de caminhos protegidos. Implantação interrompida por segurança."
fi
[ "$(wc -l < "$TRABALHO/apagar.txt")" -le "$LIMITE_EXCLUSOES" ] \
  || erro "Mais de $LIMITE_EXCLUSOES arquivos seriam apagados. Confira o que mudou (ou defina LIMITE_EXCLUSOES)."
echo "Para enviar: $(wc -l < "$TRABALHO/enviar.txt") · para apagar: $(wc -l < "$TRABALHO/apagar.txt")"

# ---------------------------------------------------------------- 3. manutenção
if [ "$PRIMEIRA" = 0 ]; then
  etapa "Colocando o site em manutenção"
  printf '{"except":[],"redirect":null,"retry":60,"refresh":15,"secret":null,"status":503,"template":null}' > "$TRABALHO/down"
  lftp_comandos "mkdir -p -f \"$APP_DIR/storage/framework\"" "put \"$TRABALHO/down\" -o \"$APP_DIR/storage/framework/down\""
  EM_MANUTENCAO=1
fi

# ---------------------------------------------------------------- 4. envio
etapa "Enviando arquivos ($FTP_CONEXOES conexões)"
ENVIO="$TRABALHO/envio"
mkdir -p "$ENVIO"
if [ -s "$TRABALHO/enviar.txt" ]; then
  rsync -a --files-from="$TRABALHO/enviar.txt" "$PACOTE/" "$ENVIO/"
fi
{
  echo "mkdir -p -f \"$APP_DIR\""
  echo "mkdir -p -f \"$PUBLIC_DIR\""
  [ -d "$ENVIO/app" ] && echo "mirror -R --parallel=$FTP_CONEXOES --transfer-all --no-perms --no-symlinks \"$ENVIO/app\" \"$APP_DIR\""
  [ -d "$ENVIO/public" ] && echo "mirror -R --parallel=$FTP_CONEXOES --transfer-all --no-perms --no-symlinks \"$ENVIO/public\" \"$PUBLIC_DIR\""
  while IFS= read -r arquivo; do
    case "$arquivo" in
      app/*) echo "rm -f \"$APP_DIR/${arquivo#app/}\"" ;;
      public/*) echo "rm -f \"$PUBLIC_DIR/${arquivo#public/}\"" ;;
    esac
  done < "$TRABALHO/apagar.txt"
  for pasta in storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache .implantacao; do
    echo "mkdir -p -f \"$APP_DIR/$pasta\""
  done
  # O manifesto só é atualizado depois que todos os arquivos chegaram.
  echo "put \"$TRABALHO/manifesto.novo\" -o \"$APP_DIR/.implantacao/manifesto.txt\""
} > "$TRABALHO/envio.lftp"
inicio=$(date +%s)
lftp_executar "$TRABALHO/envio.lftp" || erro "O envio por FTP falhou (veja a mensagem acima). O site sai da manutenção; rode a implantação de novo para completar o envio."
echo "Envio concluído em $(( $(date +%s) - inicio )) s."

# ---------------------------------------------------------------- 5. conclusão no servidor
etapa "Atualizando o banco e liberando o site"
CODIGO="$(openssl rand -hex 32)"
printf '%s\n' "$(printf '%s' "$CODIGO" | sha256sum | cut -c1-64)" > "$TRABALHO/token.sha256"
CODIGO_ENVIADO=1
lftp_comandos "put \"$TRABALHO/token.sha256\" -o \"$APP_DIR/.implantacao/token.sha256\"" >/dev/null

http=$(curl -sS --max-time 300 -o "$TRABALHO/resposta.json" -w '%{http_code}' -X POST \
  -H "X-Implantacao-Token: $CODIGO" -H 'Accept: application/json' \
  "$SITE_URL/_implantacao/finalizar") || http=000

mensagens() {
  php -r '$d = json_decode((string) file_get_contents($argv[1]), true);
    if (! is_array($d)) { exit(1); }
    foreach ($d["mensagens"] ?? [] as $m) { echo "  - ", $m, PHP_EOL; }
    foreach ($d["migracoes"] ?? [] as $m) { echo "    migração: ", $m, PHP_EOL; }
    echo "  PHP do servidor: ", $d["php"] ?? "?", PHP_EOL;' "$TRABALHO/resposta.json" 2>/dev/null \
  || { echo "  Resposta do servidor (início):"; head -c 600 "$TRABALHO/resposta.json" | sed 's/<[^>]*>//g' | tr -s '[:space:]' ' '; echo; }
}

case "$http" in
  200) mensagens; CONCLUIDO=1; echo "Implantação concluída." ;;
  202) mensagens; CONCLUIDO=1
       aviso "Arquivos enviados, mas falta configurar o servidor: $(php -r '$d=json_decode((string) file_get_contents($argv[1]), true); echo implode(" ", $d["mensagens"] ?? []);' "$TRABALHO/resposta.json" 2>/dev/null)" ;;
  301|302|307|308)
       erro "O site redirecionou a conclusão (HTTP $http). Se o HTTPS já está ativo (FORCE_HTTPS=true), mude a variável SITE_URL para https://." ;;
  404) erro "O site não reconheceu a implantação (HTTP 404). Confira se SITE_URL é o endereço deste servidor e se APP_DIR é a pasta do código (o public_html/index.php procura ../mostraqui-app)." ;;
  000) erro "O site não respondeu em $SITE_URL. Confira SITE_URL e se o domínio aponta para a HostGator." ;;
  *)   mensagens; erro "O servidor respondeu HTTP $http ao concluir a implantação. Veja as mensagens acima e storage/logs/laravel.log no Gerenciador de arquivos." ;;
esac
