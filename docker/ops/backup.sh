#!/bin/sh
set -eu

umask 077

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
DESTINATION=${1:-backups}

case "$DESTINATION" in
    /*) ;;
    *) DESTINATION="$ROOT_DIR/$DESTINATION" ;;
esac

fail() {
    printf 'Erro: %s\n' "$*" >&2
    exit 1
}

for command_name in docker gzip sha256sum mktemp; do
    command -v "$command_name" >/dev/null 2>&1 || fail "comando obrigatório ausente: $command_name"
done

docker compose version >/dev/null 2>&1 || fail "Docker Compose não está disponível"
[ ! -L "$DESTINATION" ] || fail "o diretório de destino não pode ser um link simbólico"

mkdir -p "$DESTINATION"
chmod 700 "$DESTINATION"

cd "$ROOT_DIR"

running_services=$(docker compose ps --status running --services)
printf '%s\n' "$running_services" | grep -qx postgres || fail "o serviço postgres precisa estar em execução"
printf '%s\n' "$running_services" | grep -qx php || fail "o serviço php precisa estar em execução"

service_was_running() {
    printf '%s\n' "$running_services" | grep -qx "$1"
}

horizon_was_running=false
scheduler_was_running=false
service_was_running horizon && horizon_was_running=true
service_was_running scheduler && scheduler_was_running=true

maintenance_enabled=false
temporary_dir=''

resume_application() {
    status=$?
    trap - EXIT HUP INT TERM

    if [ "$maintenance_enabled" = true ]; then
        docker compose exec -T php php artisan up >/dev/null 2>&1 || true
    fi
    if [ "$horizon_was_running" = true ]; then
        docker compose start horizon >/dev/null 2>&1 || true
    fi
    if [ "$scheduler_was_running" = true ]; then
        docker compose start scheduler >/dev/null 2>&1 || true
    fi
    if [ -n "$temporary_dir" ] && [ -d "$temporary_dir" ]; then
        rm -rf -- "$temporary_dir"
    fi

    exit "$status"
}

trap resume_application EXIT HUP INT TERM

docker compose exec -T php php artisan down --retry=60 >/dev/null
maintenance_enabled=true

services_to_stop=''
[ "$horizon_was_running" = true ] && services_to_stop="$services_to_stop horizon"
[ "$scheduler_was_running" = true ] && services_to_stop="$services_to_stop scheduler"
if [ -n "$services_to_stop" ]; then
    # shellcheck disable=SC2086
    docker compose stop $services_to_stop >/dev/null
fi

timestamp=$(date -u '+%Y%m%dT%H%M%SZ')
final_dir="$DESTINATION/nfse-backup-$timestamp"
[ ! -e "$final_dir" ] || fail "o destino já existe: $final_dir"
temporary_dir=$(mktemp -d "$DESTINATION/.nfse-backup-$timestamp.XXXXXX")

docker compose exec -T postgres sh -eu -c \
    'exec pg_dump --clean --if-exists --no-owner --no-privileges --username="$POSTGRES_USER" "$POSTGRES_DB"' \
    > "$temporary_dir/postgres.sql"
gzip -9 "$temporary_dir/postgres.sql"

docker compose run --rm -T --no-deps --user root --entrypoint sh php -eu -c \
    'exec tar -C /var/vault -czf - .' \
    > "$temporary_dir/vault.tar.gz"

[ -s "$temporary_dir/postgres.sql.gz" ] || fail "dump do PostgreSQL vazio"
[ -s "$temporary_dir/vault.tar.gz" ] || fail "arquivo do cofre vazio"

(
    cd "$temporary_dir"
    sha256sum postgres.sql.gz vault.tar.gz > SHA256SUMS
)

cat > "$temporary_dir/MANIFEST.txt" <<EOF
formato=nfse-adn-backup-v1
criado_em_utc=$timestamp
componentes=postgres.sql.gz,vault.tar.gz
chave_mestra_incluida=nao
EOF

chmod 600 "$temporary_dir"/*
mv "$temporary_dir" "$final_dir"
temporary_dir=''

printf 'Backup concluído: %s\n' "$final_dir"

