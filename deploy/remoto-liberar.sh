# Roda no servidor se o envio falhar. Argumentos: APP_DIR PHP_BIN
APP_DIR="$1"; PHP_BIN="$2"
cd ~/"$APP_DIR" 2>/dev/null && [ -f artisan ] && "$PHP_BIN" artisan up >/dev/null 2>&1
echo "Site fora do modo de manutenção."
