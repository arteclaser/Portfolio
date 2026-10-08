#!/usr/bin/env bash
# Gera os pacotes para hospedagem compartilhada (cPanel), sem precisar de Composer no servidor.
#   dist/mostraqui-app.zip  → extrair em ~/mostraqui-app (FORA de public_html)
#   dist/public_html.zip    → extrair dentro de ~/public_html
set -euo pipefail
cd "$(dirname "$0")/.."
ROOT=$(pwd)
BUILD=$(mktemp -d)
mkdir -p dist

echo "→ Copiando código"
rsync -a --exclude '.git' --exclude 'dist' --exclude 'vendor' --exclude 'node_modules' \
  --exclude '.env' --exclude 'database/*.sqlite' --exclude 'storage/logs/*' --exclude 'storage/app/private/*' \
  --exclude 'storage/app/backups/*' --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' \
  --exclude 'storage/framework/cache/data/*' --exclude 'tests' --exclude 'storage/framework/testing' \
  ./ "$BUILD/mostraqui-app/"

echo "→ Instalando dependências de produção"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --working-dir="$BUILD/mostraqui-app"

echo "→ Separando a pasta pública"
mv "$BUILD/mostraqui-app/public" "$BUILD/public_html"

(cd "$BUILD" && rm -f "$ROOT/dist/mostraqui-app.zip" "$ROOT/dist/public_html.zip" \
  && zip -qr "$ROOT/dist/mostraqui-app.zip" mostraqui-app -x "*/.git/*" "*/.github/*" \
  && cd public_html && zip -qr "$ROOT/dist/public_html.zip" . -x '.user.ini' && zip -q "$ROOT/dist/public_html.zip" .user.ini .htaccess)

rm -rf "$BUILD"
echo "Pronto:"
ls -lh dist/*.zip
