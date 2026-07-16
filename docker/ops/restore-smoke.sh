#!/bin/sh
set -eu

umask 077

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
project="fiscal-hub-restore-smoke-$$"
work_dir=$(mktemp -d)
env_file="$work_dir/.env.prod"
backup_root="$work_dir/backups"

compose() {
    PROD_ENV_FILE="$env_file" docker compose \
        --env-file "$env_file" \
        -f compose.prod.yml \
        -p "$project" \
        "$@"
}

cleanup() {
    status=$?
    trap - EXIT HUP INT TERM
    cd "$ROOT_DIR"
    compose down -v --remove-orphans >/dev/null 2>&1 || true
    rm -rf -- "$work_dir"
    exit "$status"
}
trap cleanup EXIT HUP INT TERM

command -v openssl >/dev/null 2>&1 || {
    echo "openssl é obrigatório para o smoke de restore" >&2
    exit 1
}

cp "$ROOT_DIR/.env.prod.example" "$env_file"
chmod 600 "$env_file"
app_key="base64:$(openssl rand -base64 32)"
vault_key=$(openssl rand -base64 32)
package_key=$(openssl rand -base64 32)
db_password=$(openssl rand -hex 24)
sed -i \
    -e "s|^APP_KEY=.*|APP_KEY=$app_key|" \
    -e "s|^VAULT_MASTER_KEY=.*|VAULT_MASTER_KEY=$vault_key|" \
    -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$db_password|" \
    -e 's|^ACME_EMAIL=.*|ACME_EMAIL=restore-smoke@invalid.local|' \
    "$env_file"
export BACKUP_PACKAGE_KEY="$package_key"
printf '%s\n' \
    "EDGE_NETWORK=$project-edge" \
    "APP_NETWORK=$project-app" \
    "SOCKET_NETWORK=$project-socket" >> "$env_file"

cd "$ROOT_DIR"
compose up -d --wait postgres redis php

compose exec -T postgres sh -eu -c \
    'psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --set ON_ERROR_STOP=1 -c \
        "CREATE TABLE restore_smoke (value text NOT NULL); INSERT INTO restore_smoke VALUES ('\''before'\'');"' \
    >/dev/null
compose run --rm -T --no-deps --user root --entrypoint sh php -eu -c \
    'printf vault-before > /var/vault/restore-smoke.txt; \
     printf private-before > /var/www/html/storage/app/private/restore-smoke.txt'

STACK_FILE=compose.prod.yml \
STACK_ENV="$env_file" \
STACK_PROJECT="$project" \
BACKUP_PACKAGE_KEY="$package_key" \
    ./docker/ops/backup.sh "$backup_root" >/dev/null

backup_dir=$(find "$backup_root" -mindepth 1 -maxdepth 1 -type d -name 'nfse-backup-*' | head -n 1)
[ -n "$backup_dir" ] || { echo "backup de smoke não foi criado" >&2; exit 1; }

# Prova de pacote cifrado + chave externa (verify-only).
STACK_FILE=compose.prod.yml \
STACK_ENV="$env_file" \
STACK_PROJECT="$project" \
BACKUP_PACKAGE_KEY="$package_key" \
    ./docker/ops/restore.sh --verify-only "$backup_dir" >/dev/null

# Chave errada deve falhar explicitamente.
if BACKUP_PACKAGE_KEY="$(openssl rand -base64 32)" \
    STACK_FILE=compose.prod.yml \
    STACK_ENV="$env_file" \
    STACK_PROJECT="$project" \
    ./docker/ops/restore.sh --verify-only "$backup_dir" >/dev/null 2>&1; then
    echo "restore verify com chave errada deveria falhar" >&2
    exit 1
fi

grep -qx 'formato=nfse-adn-backup-v3' "$backup_dir/MANIFEST.txt" \
    || { echo "backup smoke deveria ser v3 cifrado" >&2; exit 1; }
[ -f "$backup_dir/package.nfsebkp" ] || { echo "package.nfsebkp ausente" >&2; exit 1; }

compose exec -T postgres sh -eu -c \
    'psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" --set ON_ERROR_STOP=1 -c "DROP TABLE restore_smoke"' \
    >/dev/null
compose run --rm -T --no-deps --user root --entrypoint sh php -eu -c \
    'printf vault-after > /var/vault/restore-smoke.txt; \
     printf private-after > /var/www/html/storage/app/private/restore-smoke.txt'

STACK_FILE=compose.prod.yml \
STACK_ENV="$env_file" \
STACK_PROJECT="$project" \
STACK_WEB=web \
BACKUP_PACKAGE_KEY="$package_key" \
    ./docker/ops/restore.sh --force "$backup_dir" >/dev/null

[ "$(compose exec -T postgres sh -eu -c \
    'psql --tuples-only --no-align --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" -c "SELECT value FROM restore_smoke"')" = before ]
[ "$(compose run --rm -T --no-deps --entrypoint cat php /var/vault/restore-smoke.txt)" = vault-before ]
[ "$(compose run --rm -T --no-deps --entrypoint cat php /var/www/html/storage/app/private/restore-smoke.txt)" = private-before ]

printf '%s\n' 'Smoke destrutivo de backup/restore (v3 cifrado + chave externa) aprovado em projeto isolado.'
