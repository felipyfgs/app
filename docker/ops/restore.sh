#!/bin/sh
set -eu

umask 077

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
force=false
verify_only=false

usage() {
    cat >&2 <<'EOF'
Uso:
  docker/ops/restore.sh --verify-only DIRETORIO_DO_BACKUP
  docker/ops/restore.sh --force DIRETORIO_DO_BACKUP
EOF
    exit 2
}

fail() {
    printf 'Erro: %s\n' "$*" >&2
    exit 1
}

[ "$#" -eq 2 ] || usage
case "$1" in
    --force) force=true ;;
    --verify-only) verify_only=true ;;
    *) usage ;;
esac

backup_dir=$2
[ -d "$backup_dir" ] || fail "diretório de backup inexistente: $backup_dir"
[ ! -L "$backup_dir" ] || fail "o diretório de backup não pode ser um link simbólico"
backup_dir=$(CDPATH= cd -- "$backup_dir" && pwd -P)

for required_file in postgres.sql.gz vault.tar.gz SHA256SUMS MANIFEST.txt; do
    [ -f "$backup_dir/$required_file" ] || fail "arquivo ausente: $required_file"
    [ ! -L "$backup_dir/$required_file" ] || fail "links simbólicos não são aceitos: $required_file"
done

grep -qx 'formato=nfse-adn-backup-v1' "$backup_dir/MANIFEST.txt" \
    || fail "formato de backup incompatível"
grep -qx 'chave_mestra_incluida=nao' "$backup_dir/MANIFEST.txt" \
    || fail "manifesto de separação da chave mestra inválido"

(
    cd "$backup_dir"
    sha256sum --check --strict SHA256SUMS
)
gzip -t "$backup_dir/postgres.sql.gz"
gzip -t "$backup_dir/vault.tar.gz"

listing=$(mktemp)
trap 'rm -f -- "$listing"' EXIT HUP INT TERM
tar -tzf "$backup_dir/vault.tar.gz" > "$listing"
if awk '
    /^\// || /(^|\/)\.\.($|\/)/ || /^\.\/\.restore($|\/)/ { invalid = 1 }
    END { exit invalid ? 0 : 1 }
' "$listing"; then
    fail "o arquivo do cofre contém caminho inseguro"
fi

printf 'Checksums e arquivos válidos: %s\n' "$backup_dir"
[ "$verify_only" = true ] && exit 0
[ "$force" = true ] || usage

for command_name in docker gzip sha256sum mktemp; do
    command -v "$command_name" >/dev/null 2>&1 || fail "comando obrigatório ausente: $command_name"
done
docker compose version >/dev/null 2>&1 || fail "Docker Compose não está disponível"

cd "$ROOT_DIR"
running_services=$(docker compose ps --status running --services)
printf '%s\n' "$running_services" | grep -qx postgres || fail "o serviço postgres precisa estar em execução"
printf '%s\n' "$running_services" | grep -qx php || fail "o serviço php precisa estar em execução"

service_was_running() {
    printf '%s\n' "$running_services" | grep -qx "$1"
}

nginx_was_running=false
horizon_was_running=false
scheduler_was_running=false
service_was_running nginx && nginx_was_running=true
service_was_running horizon && horizon_was_running=true
service_was_running scheduler && scheduler_was_running=true

work_dir=$(mktemp -d)
restore_succeeded=false

finish_restore() {
    status=$?
    trap - EXIT HUP INT TERM
    rm -rf -- "$work_dir"
    rm -f -- "$listing"

    if [ "$restore_succeeded" = true ]; then
        docker compose start php >/dev/null
        docker compose exec -T php php artisan up >/dev/null
        [ "$nginx_was_running" = true ] && docker compose start nginx >/dev/null
        [ "$horizon_was_running" = true ] && docker compose start horizon >/dev/null
        [ "$scheduler_was_running" = true ] && docker compose start scheduler >/dev/null
    else
        printf '%s\n' 'Restauração interrompida; aplicação mantida em manutenção e serviços de aplicação parados.' >&2
    fi

    exit "$status"
}

trap finish_restore EXIT HUP INT TERM

docker compose exec -T php php artisan down --retry=60 >/dev/null
services_to_stop='php'
[ "$nginx_was_running" = true ] && services_to_stop="$services_to_stop nginx"
[ "$horizon_was_running" = true ] && services_to_stop="$services_to_stop horizon"
[ "$scheduler_was_running" = true ] && services_to_stop="$services_to_stop scheduler"
# shellcheck disable=SC2086
docker compose stop $services_to_stop >/dev/null

gzip -dc "$backup_dir/postgres.sql.gz" > "$work_dir/postgres.sql"
docker compose exec -T postgres sh -eu -c '
    dropdb --force --if-exists --maintenance-db=postgres --username="$POSTGRES_USER" "$POSTGRES_DB"
    createdb --maintenance-db=postgres --username="$POSTGRES_USER" --owner="$POSTGRES_USER" "$POSTGRES_DB"
'
docker compose exec -T postgres sh -eu -c \
    'exec psql --set ON_ERROR_STOP=1 --username="$POSTGRES_USER" --dbname="$POSTGRES_DB"' \
    < "$work_dir/postgres.sql"

gzip -dc "$backup_dir/vault.tar.gz" > "$work_dir/vault.tar"
docker compose run --rm -T --no-deps --user root --entrypoint sh php -eu -c '
    restore_dir=/var/vault/.restore
    rm -rf -- "$restore_dir"
    mkdir -m 700 "$restore_dir"
    tar --no-same-owner --no-same-permissions -xf - -C "$restore_dir"
    find /var/vault -mindepth 1 -maxdepth 1 ! -name .restore -exec rm -rf -- {} +
    find "$restore_dir" -mindepth 1 -maxdepth 1 -exec mv -- {} /var/vault/ \;
    rmdir "$restore_dir"
    chown -R www-data:www-data /var/vault
    find /var/vault -type d -exec chmod 700 {} +
    find /var/vault -type f -exec chmod 600 {} +
' < "$work_dir/vault.tar"

restore_succeeded=true
printf 'Restauração concluída: %s\n' "$backup_dir"

