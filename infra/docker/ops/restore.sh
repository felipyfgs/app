#!/bin/sh
set -eu

umask 077

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
force=false
verify_only=false
STACK_FILE=${STACK_FILE:-docker-compose.yml}
STACK_ENV=${STACK_ENV:-}
STACK_PROJECT=${STACK_PROJECT:-}
STACK_WEB=${STACK_WEB:-nginx}
BACKUP_PACKAGE_KEY=${BACKUP_PACKAGE_KEY:-}

compose() {
    if [ -n "$STACK_ENV" ] && [ -n "$STACK_PROJECT" ]; then
        PROD_ENV_FILE="$STACK_ENV" docker compose --env-file "$STACK_ENV" -f "$STACK_FILE" -p "$STACK_PROJECT" "$@"
    elif [ -n "$STACK_ENV" ]; then
        PROD_ENV_FILE="$STACK_ENV" docker compose --env-file "$STACK_ENV" -f "$STACK_FILE" "$@"
    elif [ -n "$STACK_PROJECT" ]; then
        docker compose -f "$STACK_FILE" -p "$STACK_PROJECT" "$@"
    else
        docker compose -f "$STACK_FILE" "$@"
    fi
}

usage() {
    cat >&2 <<'EOF'
Uso:
  infra/docker/ops/restore.sh --verify-only DIRETORIO_DO_BACKUP
  infra/docker/ops/restore.sh --force DIRETORIO_DO_BACKUP

Pacotes v3 (pacote_cifrado=sim) exigem BACKUP_PACKAGE_KEY no ambiente para
validar/descriptografar package.nfsebkp. A chave mestra do vault NÃO está no backup.
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

for required_file in SHA256SUMS MANIFEST.txt; do
    [ -f "$backup_dir/$required_file" ] || fail "arquivo ausente: $required_file"
    [ ! -L "$backup_dir/$required_file" ] || fail "links simbólicos não são aceitos: $required_file"
done

format=$(sed -n 's/^formato=//p' "$backup_dir/MANIFEST.txt")
case "$format" in
    nfse-adn-backup-v1) has_private=false; has_package=false ;;
    nfse-adn-backup-v2) has_private=true; has_package=false ;;
    nfse-adn-backup-v3) has_private=true; has_package=true ;;
    *) fail "formato de backup incompatível" ;;
esac

grep -qx 'chave_mestra_incluida=nao' "$backup_dir/MANIFEST.txt" \
    || fail "manifesto de separação da chave mestra inválido"

# material_dir: origem dos componentes plaintext usados no restore.
# v3 materializa a partir de package.nfsebkp (único payload autenticado).
material_dir=$backup_dir
material_cleanup=
cleanup_material() {
    if [ -n "$material_cleanup" ] && [ -d "$material_cleanup" ]; then
        rm -rf -- "$material_cleanup"
    fi
}

if [ "$has_package" = true ]; then
    [ -f "$backup_dir/package.nfsebkp" ] || fail "arquivo ausente: package.nfsebkp"
    [ ! -L "$backup_dir/package.nfsebkp" ] || fail "links simbólicos não são aceitos: package.nfsebkp"
    grep -qx 'pacote_cifrado=sim' "$backup_dir/MANIFEST.txt" \
        || fail "manifesto v3 sem pacote_cifrado=sim"
    grep -qx 'cofre_separado=sim' "$backup_dir/MANIFEST.txt" \
        || fail "manifesto não declara a separação do cofre"
    [ -n "$BACKUP_PACKAGE_KEY" ] || fail "BACKUP_PACKAGE_KEY obrigatória para validar pacote v3"

    (
        cd "$backup_dir"
        sha256sum --check --strict SHA256SUMS
    )

    material_cleanup=$(mktemp -d)
    material_dir=$material_cleanup
    open_script=$(mktemp)
    cat > "$open_script" <<'PHPEOF'
<?php
$keyB64 = getenv('BACKUP_PACKAGE_KEY') ?: '';
$key = base64_decode($keyB64, true);
if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
    fwrite(STDERR, "BACKUP_PACKAGE_KEY inválida\n");
    exit(1);
}
$bin = file_get_contents($argv[1] ?? '');
if ($bin === false || strlen($bin) < 41) {
    fwrite(STDERR, "pacote truncado\n");
    exit(1);
}
if (substr($bin, 0, 8) !== 'NFSEBKP1') {
    fwrite(STDERR, "magic inválido\n");
    exit(1);
}
$nonceLen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
$nonce = substr($bin, 9, $nonceLen);
$ptLenPacked = substr($bin, 9 + $nonceLen, 8);
$ct = substr($bin, 9 + $nonceLen + 8);
$u = unpack('J', $ptLenPacked);
$ptLen = (int) ($u[1] ?? 0);
$aad = 'nfse-backup-package-v1|len='.$ptLen;
$plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ct, $aad, $nonce, $key);
if ($plain === false) {
    fwrite(STDERR, "descriptografia falhou (chave errada ou adulteração)\n");
    exit(1);
}
if (strlen($plain) !== $ptLen) {
    fwrite(STDERR, "tamanho não confere\n");
    exit(1);
}
$out = $argv[2] ?? '';
if ($out === '' || file_put_contents($out, $plain) === false) {
    fwrite(STDERR, "falha ao gravar bundle descriptografado\n");
    exit(1);
}
PHPEOF
    bundle_tar="$material_dir/bundle.tar"
    if command -v php >/dev/null 2>&1; then
        BACKUP_PACKAGE_KEY="$BACKUP_PACKAGE_KEY" php "$open_script" \
            "$backup_dir/package.nfsebkp" "$bundle_tar" \
            || { rm -f -- "$open_script"; cleanup_material; fail "falha ao abrir package.nfsebkp (php host)"; }
    else
        compose run --rm -T --no-deps \
            -e BACKUP_PACKAGE_KEY="$BACKUP_PACKAGE_KEY" \
            -v "$backup_dir:/backup-crypto:ro" \
            -v "$material_dir:/backup-out:rw" \
            -v "$open_script:/backup-crypto-open.php:ro" \
            --entrypoint php php /backup-crypto-open.php \
            /backup-crypto/package.nfsebkp /backup-out/bundle.tar \
            || { rm -f -- "$open_script"; cleanup_material; fail "falha ao abrir package.nfsebkp (php container)"; }
    fi
    rm -f -- "$open_script"
    tar -xf "$bundle_tar" -C "$material_dir" \
        || { cleanup_material; fail "bundle do package.nfsebkp inválido"; }
    rm -f -- "$bundle_tar"
    for required_file in postgres.sql.gz vault.tar.gz private.tar.gz; do
        [ -f "$material_dir/$required_file" ] || { cleanup_material; fail "pacote v3 sem componente: $required_file"; }
    done
else
    for required_file in postgres.sql.gz vault.tar.gz; do
        [ -f "$backup_dir/$required_file" ] || fail "arquivo ausente: $required_file"
        [ ! -L "$backup_dir/$required_file" ] || fail "links simbólicos não são aceitos: $required_file"
    done
    if [ "$has_private" = true ]; then
        [ -f "$backup_dir/private.tar.gz" ] || fail "arquivo ausente: private.tar.gz"
        [ ! -L "$backup_dir/private.tar.gz" ] || fail "links simbólicos não são aceitos: private.tar.gz"
        grep -qx 'cofre_separado=sim' "$backup_dir/MANIFEST.txt" \
            || fail "manifesto não declara a separação do cofre"
    fi
    (
        cd "$backup_dir"
        sha256sum --check --strict SHA256SUMS
    )
fi

gzip -t "$material_dir/postgres.sql.gz"
gzip -t "$material_dir/vault.tar.gz"
[ "$has_private" = false ] || gzip -t "$material_dir/private.tar.gz"

listing=$(mktemp)
trap 'rm -f -- "$listing"; cleanup_material' EXIT HUP INT TERM
validate_archive() {
    archive=$1
    tar -tzf "$archive" > "$listing"
    if awk '
        /^\// || /(^|\/)\.\.($|\/)/ || /^\.\/\.restore($|\/)/ { invalid = 1 }
        END { exit invalid ? 0 : 1 }
    ' "$listing"; then
        fail "arquivo contém caminho inseguro: $archive"
    fi
}

validate_archive "$material_dir/vault.tar.gz"
[ "$has_private" = false ] || validate_archive "$material_dir/private.tar.gz"

printf 'Checksums e arquivos válidos: %s\n' "$backup_dir"
[ "$verify_only" = true ] && exit 0
[ "$force" = true ] || usage

for command_name in docker gzip sha256sum mktemp; do
    command -v "$command_name" >/dev/null 2>&1 || fail "comando obrigatório ausente: $command_name"
done
compose version >/dev/null 2>&1 || fail "Docker Compose não está disponível"

cd "$ROOT_DIR"
running_services=$(compose ps --status running --services)
printf '%s\n' "$running_services" | grep -qx postgres || fail "o serviço postgres precisa estar em execução"
printf '%s\n' "$running_services" | grep -qx php || fail "o serviço php precisa estar em execução"

service_was_running() {
    printf '%s\n' "$running_services" | grep -qx "$1"
}

web_was_running=false
horizon_was_running=false
scheduler_was_running=false
service_was_running "$STACK_WEB" && web_was_running=true
service_was_running horizon && horizon_was_running=true
service_was_running scheduler && scheduler_was_running=true

work_dir=$(mktemp -d)
restore_succeeded=false

finish_restore() {
    status=$?
    trap - EXIT HUP INT TERM
    rm -rf -- "$work_dir"
    rm -f -- "$listing"
    cleanup_material

    if [ "$restore_succeeded" = true ]; then
        compose start php >/dev/null
        compose exec -T php php artisan up >/dev/null
        [ "$web_was_running" = true ] && compose start "$STACK_WEB" >/dev/null
        [ "$horizon_was_running" = true ] && compose start horizon >/dev/null
        [ "$scheduler_was_running" = true ] && compose start scheduler >/dev/null
    else
        printf '%s\n' 'Restauração interrompida; aplicação mantida em manutenção e serviços de aplicação parados.' >&2
    fi

    exit "$status"
}

trap finish_restore EXIT HUP INT TERM

compose exec -T php php artisan down --retry=60 >/dev/null
services_to_stop='php'
[ "$web_was_running" = true ] && services_to_stop="$services_to_stop $STACK_WEB"
[ "$horizon_was_running" = true ] && services_to_stop="$services_to_stop horizon"
[ "$scheduler_was_running" = true ] && services_to_stop="$services_to_stop scheduler"
# shellcheck disable=SC2086
compose stop $services_to_stop >/dev/null

gzip -dc "$material_dir/postgres.sql.gz" > "$work_dir/postgres.sql"
compose exec -T postgres sh -eu -c '
    dropdb --force --if-exists --maintenance-db=postgres --username="$POSTGRES_USER" "$POSTGRES_DB"
    createdb --maintenance-db=postgres --username="$POSTGRES_USER" --owner="$POSTGRES_USER" "$POSTGRES_DB"
'
compose exec -T postgres sh -eu -c \
    'exec psql --set ON_ERROR_STOP=1 --username="$POSTGRES_USER" --dbname="$POSTGRES_DB"' \
    < "$work_dir/postgres.sql"

gzip -dc "$material_dir/vault.tar.gz" > "$work_dir/vault.tar"
compose run --rm -T --no-deps --user root --entrypoint sh php -eu -c '
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

if [ "$has_private" = true ]; then
    gzip -dc "$material_dir/private.tar.gz" > "$work_dir/private.tar"
    compose run --rm -T --no-deps --user root --entrypoint sh php -eu -c '
        restore_dir=/var/www/html/storage/app/private/.restore
        rm -rf -- "$restore_dir"
        mkdir -m 700 "$restore_dir"
        tar --no-same-owner --no-same-permissions -xf - -C "$restore_dir"
        find /var/www/html/storage/app/private -mindepth 1 -maxdepth 1 ! -name .restore -exec rm -rf -- {} +
        find "$restore_dir" -mindepth 1 -maxdepth 1 -exec mv -- {} /var/www/html/storage/app/private/ \;
        rmdir "$restore_dir"
        chown -R www-data:www-data /var/www/html/storage/app/private
        find /var/www/html/storage/app/private -type d -exec chmod 700 {} +
        find /var/www/html/storage/app/private -type f -exec chmod 600 {} +
    ' < "$work_dir/private.tar"
else
    printf '%s\n' 'Aviso: backup v1 não contém private_storage; conteúdo atual foi preservado.' >&2
fi

restore_succeeded=true
printf 'Restauração concluída: %s\n' "$backup_dir"
