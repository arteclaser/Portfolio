# Roda no servidor depois do envio. Argumentos: APP_DIR PHP_BIN
set -uo pipefail
APP_DIR="$1"; PHP_BIN="$2"
cd ~/"$APP_DIR"

valor() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '\r'; }

# Primeira vez: cria o .env a partir do modelo (nunca sobrescreve um .env existente).
if [ ! -f .env ]; then
  cp .env.example .env && chmod 600 .env
  "$PHP_BIN" artisan key:generate --force >/dev/null
  echo "::warning::Criei o arquivo ~/$APP_DIR/.env a partir do modelo, com uma APP_KEY nova. Edite-o no Gerenciador de arquivos do cPanel (dados do banco e SETUP_TOKEN) e rode a implantação de novo. Guia: docs/IMPLANTACAO-AUTOMATICA.md, parte 3."
  exit 0
fi
chmod 600 .env 2>/dev/null || true

# Chave da aplicação vazia: gera uma única vez.
if [ -z "$(valor APP_KEY)" ]; then
  "$PHP_BIN" artisan key:generate --force >/dev/null && echo "APP_KEY gerada no .env."
fi

# Banco ainda não configurado: envia os arquivos, mas não tenta migrar.
if [ "$(valor DB_CONNECTION)" != "sqlite" ] && { [ -z "$(valor DB_DATABASE)" ] || [ -z "$(valor DB_USERNAME)" ]; }; then
  "$PHP_BIN" artisan up >/dev/null 2>&1 || true
  echo "::warning::Arquivos enviados. Preencha DB_DATABASE, DB_USERNAME e DB_PASSWORD no ~/$APP_DIR/.env e rode a implantação de novo para criar as tabelas."
  exit 0
fi

status=0
"$PHP_BIN" artisan migrate --force || status=$?
"$PHP_BIN" artisan optimize:clear >/dev/null || true
"$PHP_BIN" artisan up >/dev/null 2>&1 || true
if [ $status -ne 0 ]; then
  echo "::error::As migrações do banco falharam (veja acima). O site voltou ao ar com o código novo; confira os dados do banco no .env."
fi
exit $status
