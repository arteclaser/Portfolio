#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Implantação na hospedagem (HostGator/cPanel) por SSH + rsync.
# Executado pelo GitHub Actions (.github/workflows/implantacao.yml), mas pode
# rodar em qualquer máquina com bash, ssh e rsync.
#
# Variáveis de ambiente:
#   SSH_HOST, SSH_USER            servidor e usuário do cPanel (obrigatórias)
#   SSH_PORT                      porta SSH (padrão 2222, a da hospedagem compartilhada)
#   SSH_KEY                       chave privada (conteúdo). Opcional se já houver ~/.ssh configurado
#   SSH_PASSPHRASE                senha da chave, se houver
#   SSH_KNOWN_HOSTS               linha(s) de known_hosts do servidor (recomendado)
#   APP_DIR    (mostraqui-app)    pasta do código, relativa à pasta pessoal, FORA da pasta pública
#   PUBLIC_DIR (public_html)      pasta pública, relativa à pasta pessoal
#   PHP_BIN    (php)              PHP 8.3 do servidor (ex.: /opt/cpanel/ea-php83/root/usr/bin/php)
# ---------------------------------------------------------------------------
set -euo pipefail
cd "$(dirname "$0")/.."

APP_DIR="${APP_DIR:-mostraqui-app}"
PUBLIC_DIR="${PUBLIC_DIR:-public_html}"
PHP_BIN="${PHP_BIN:-php}"
SSH_PORT="${SSH_PORT:-2222}"
TARGET="mostraqui-servidor"

erro() { echo "::error::$*"; exit 1; }

# --- Validações: um caminho errado com --delete poderia apagar arquivos da conta -------------
[[ -n "${SSH_HOST:-}" ]] || erro "Falta o segredo HOSTGATOR_SSH_HOST (endereço do servidor)."
[[ -n "${SSH_USER:-}" ]] || erro "Falta o segredo HOSTGATOR_SSH_USER (usuário do cPanel)."
[[ "$SSH_PORT" =~ ^[0-9]{2,5}$ ]] || erro "Porta SSH inválida: $SSH_PORT"
dir_ok='^[A-Za-z0-9][A-Za-z0-9._-]*(/[A-Za-z0-9._-]+)*$'
[[ "$APP_DIR" =~ $dir_ok ]] || erro "APP_DIR inválido: '$APP_DIR' (use algo como mostraqui-app)."
[[ "$PUBLIC_DIR" =~ $dir_ok ]] || erro "PUBLIC_DIR inválido: '$PUBLIC_DIR' (use algo como public_html)."
[[ "$APP_DIR" != "$PUBLIC_DIR" && "$APP_DIR" != "$PUBLIC_DIR"/* && "$APP_DIR" != "public_html"* ]] \
  || erro "O código (APP_DIR) não pode ficar dentro da pasta pública."
[[ "$PHP_BIN" =~ ^[A-Za-z0-9_./-]+$ ]] || erro "PHP_BIN inválido: '$PHP_BIN'."
[[ -f vendor/autoload.php ]] || erro "Rode 'composer install --no-dev' antes de implantar."

# --- Conexão SSH -------------------------------------------------------------------------------
mkdir -p ~/.ssh && chmod 700 ~/.ssh
KEY_OPT=""
if [[ -n "${SSH_KEY:-}" ]]; then
  printf '%s\n' "$SSH_KEY" | tr -d '\r' > ~/.ssh/mostraqui_deploy
  chmod 600 ~/.ssh/mostraqui_deploy
  if [[ -n "${SSH_PASSPHRASE:-}" ]]; then
    # Remove a senha só nesta cópia temporária (a máquina do GitHub é descartada ao final).
    ssh-keygen -p -q -P "$SSH_PASSPHRASE" -N "" -f ~/.ssh/mostraqui_deploy >/dev/null \
      || erro "Não foi possível abrir a chave SSH com a senha informada (HOSTGATOR_SSH_PASSPHRASE)."
  fi
  KEY_OPT="IdentityFile ~/.ssh/mostraqui_deploy
  IdentitiesOnly yes"
fi
if [[ -n "${SSH_KNOWN_HOSTS:-}" ]]; then
  printf '%s\n' "$SSH_KNOWN_HOSTS" | tr -d '\r' > ~/.ssh/mostraqui_known_hosts
else
  echo "::warning::HOSTGATOR_KNOWN_HOSTS não definido: a identidade do servidor será aceita na primeira conexão. Copie a linha abaixo para esse segredo."
  ssh-keyscan -p "$SSH_PORT" -T 20 "$SSH_HOST" 2>/dev/null > ~/.ssh/mostraqui_known_hosts || true
  [[ -s ~/.ssh/mostraqui_known_hosts ]] || erro "O servidor $SSH_HOST:$SSH_PORT não respondeu. O SSH está liberado na hospedagem?"
  cat ~/.ssh/mostraqui_known_hosts
fi
cat > "$HOME/.ssh/config_mostraqui" <<CFG
Host $TARGET
  HostName $SSH_HOST
  Port $SSH_PORT
  User $SSH_USER
  $KEY_OPT
  UserKnownHostsFile ~/.ssh/mostraqui_known_hosts
  StrictHostKeyChecking yes
  ServerAliveInterval 30
  ConnectTimeout 20
CFG
SSH=(ssh -F "$HOME/.ssh/config_mostraqui")
remoto() { "${SSH[@]}" "$TARGET" "bash -s -- '$APP_DIR' '$PHP_BIN'" < "deploy/$1"; }

# --- 1. Preparar: confere o PHP, cria pastas e coloca o site em manutenção ---------------------
echo "▶ Preparando o servidor"
remoto remoto-preparar.sh

concluido=0
liberar() {
  if [[ $concluido -eq 0 ]]; then
    echo "▶ Falha no envio: tirando o site da manutenção"
    remoto remoto-liberar.sh || true
  fi
}
trap liberar EXIT

# --- 2. Código da aplicação (fora da pasta pública) -------------------------------------------
# --delete remove arquivos que saíram do projeto, mas NUNCA toca no que está excluído:
# .env, storage/ (fotos, documentos, sessões, backups) e a pasta pública.
echo "▶ Enviando o código para ~/$APP_DIR"
rsync -rlptz --delete --max-delete=5000 --chmod=D755,F644 \
  --exclude-from=deploy/rsync-excluir.txt \
  -e "${SSH[*]}" ./ "$TARGET:$APP_DIR/"

# --- 3. Arquivos públicos (sem apagar nada que já exista em public_html) ----------------------
echo "▶ Enviando os arquivos públicos para ~/$PUBLIC_DIR"
rsync -rlptz --chmod=D755,F644 -e "${SSH[*]}" ./public/ "$TARGET:$PUBLIC_DIR/"

# --- 4. Finalizar: migrações do banco, limpeza de cache e fim da manutenção --------------------
echo "▶ Finalizando"
remoto remoto-finalizar.sh
concluido=1
echo "✔ Implantação concluída."
