# Roda no servidor. Argumentos: APP_DIR PHP_BIN
set -euo pipefail
APP_DIR="$1"; PHP_BIN="$2"
cd ~

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "::error::PHP não encontrado em '$PHP_BIN'. Informe o caminho do PHP 8.3 na variável PHP_BIN (ex.: /opt/cpanel/ea-php83/root/usr/bin/php)."
  exit 1
fi
if ! "$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);'; then
  echo "::error::O PHP do servidor é $("$PHP_BIN" -r 'echo PHP_VERSION;'), mas o sistema exige 8.3 ou superior. Selecione o PHP 8.3 no MultiPHP Manager e informe o caminho na variável PHP_BIN."
  exit 1
fi
echo "PHP do servidor: $("$PHP_BIN" -r 'echo PHP_VERSION;')"
command -v rsync >/dev/null 2>&1 || { echo "::error::O servidor não tem rsync. Peça ao suporte da hospedagem."; exit 1; }

mkdir -p "$APP_DIR"/storage/app/private "$APP_DIR"/storage/app/backups \
         "$APP_DIR"/storage/framework/cache/data "$APP_DIR"/storage/framework/sessions \
         "$APP_DIR"/storage/framework/views "$APP_DIR"/storage/logs "$APP_DIR"/bootstrap/cache
chmod 750 "$APP_DIR"/storage 2>/dev/null || true

if [ -f "$APP_DIR/.env" ] && [ -f "$APP_DIR/artisan" ] && [ -f "$APP_DIR/vendor/autoload.php" ]; then
  (cd "$APP_DIR" && "$PHP_BIN" artisan down --retry=30 --refresh=15 >/dev/null 2>&1) && echo "Site em manutenção durante o envio." || true
else
  echo "Primeira implantação (ou .env ausente): sem modo de manutenção."
fi
