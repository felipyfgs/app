#!/bin/sh
set -eu

umask 077

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)
DESTINATION=${1:-backups}
STACK_FILE=${STACK_FILE:-docker-compose.yml}
STACK_ENV=${STACK_ENV:-}
STACK_PROJECT=${STACK_PROJECT:-}
# Chave externa do pacote (base64 32 bytes). Se definida, gera package.nfsebkp (v3).
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

case "$DESTINATION" in
    /*) ;;
    *) DESTINATION="$ROOT_DIR/$DESTINATION" ;;
esac

fail() {
    printf 'Erro: %s\n' "$*" >&2
    exit 1
}

for command_name in docker gzip sha256sum mktemp tar; do
    command -v "$command_name" >/dev/null 2>&1 || fail "comando obrigatório ausente: $command_name"
done

compose version >/dev/null 2>&1 || fail "Docker Compose não está disponível"
[ ! -L "$DESTINATION" ] || fail "o diretório de destino não pode ser um link simbólico"

mkdir -p "$DESTINATION"
chmod 700 "$DESTINATION"

cd "$ROOT_DIR"

running_services=$(compose ps --status running --services)
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
        compose exec -T php php artisan up >/dev/null 2>&1 || true
    fi
    if [ "$horizon_was_running" = true ]; then
        compose start horizon >/dev/null 2>&1 || true
    fi
    if [ "$scheduler_was_running" = true ]; then
        compose start scheduler >/dev/null 2>&1 || true
    fi
    if [ -n "$temporary_dir" ] && [ -d "$temporary_dir" ]; then
        rm -rf -- "$temporary_dir"
    fi

    exit "$status"
}

trap resume_application EXIT HUP INT TERM

compose exec -T php php artisan down --retry=60 >/dev/null
maintenance_enabled=true

services_to_stop=''
[ "$horizon_was_running" = true ] && services_to_stop="$services_to_stop horizon"
[ "$scheduler_was_running" = true ] && services_to_stop="$services_to_stop scheduler"
if [ -n "$services_to_stop" ]; then
    # shellcheck disable=SC2086
    compose stop $services_to_stop >/dev/null
fi

timestamp=$(date -u '+%Y%m%dT%H%M%SZ')
final_dir="$DESTINATION/nfse-backup-$timestamp"
[ ! -e "$final_dir" ] || fail "o destino já existe: $final_dir"
temporary_dir=$(mktemp -d "$DESTINATION/.nfse-backup-$timestamp.XXXXXX")

compose exec -T postgres sh -eu -c \
    'exec pg_dump --clean --if-exists --no-owner --no-privileges --username="$POSTGRES_USER" "$POSTGRES_DB"' \
    > "$temporary_dir/postgres.sql"
gzip -9 "$temporary_dir/postgres.sql"

compose run --rm -T --no-deps --user root --entrypoint sh php -eu -c \
    'exec tar -C /var/vault -czf - .' \
    > "$temporary_dir/vault.tar.gz"

compose run --rm -T --no-deps --user root --entrypoint sh php -eu -c \
    'exec tar -C /var/www/html/storage/app/private -czf - .' \
    > "$temporary_dir/private.tar.gz"

[ -s "$temporary_dir/postgres.sql.gz" ] || fail "dump do PostgreSQL vazio"
[ -s "$temporary_dir/vault.tar.gz" ] || fail "arquivo do cofre vazio"
[ -s "$temporary_dir/private.tar.gz" ] || fail "arquivo do storage privado vazio"

# Empacota os três componentes (unificado DB+vault+private) antes de cifrar.
(
    cd "$temporary_dir"
    tar -cf bundle.tar postgres.sql.gz vault.tar.gz private.tar.gz
)

package_encrypted=nao
formato=nfse-adn-backup-v2
componentes=postgres.sql.gz,vault.tar.gz,private.tar.gz

if [ -n "$BACKUP_PACKAGE_KEY" ]; then
    # Cifra+autentica via PHP/sodium. Prefere php no host; fallback: container php
    # da stack (CI/dev sem php-cli). A chave NUNCA é gravada no artefato.
    cat > "$temporary_dir/_seal_package.php" <<'PHPEOF'
<?php
$keyB64 = getenv('BACKUP_PACKAGE_KEY') ?: '';
$key = base64_decode($keyB64, true);
if ($key === false || strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
    fwrite(STDERR, "BACKUP_PACKAGE_KEY inválida (base64 de 32 bytes)\n");
    exit(1);
}
$in = $argv[1] ?? '';
$out = $argv[2] ?? '';
$plain = file_get_contents($in);
if ($plain === false || $plain === '') {
    fwrite(STDERR, "bundle vazio\n");
    exit(1);
}
$nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
$ptLen = strlen($plain);
$aad = 'nfse-backup-package-v1|len='.$ptLen;
$ct = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $aad, $nonce, $key);
$bin = 'NFSEBKP1'.chr(1).$nonce.pack('J', $ptLen).$ct;
if (file_put_contents($out, $bin) === false) {
    exit(1);
}
PHPEOF
    if command -v php >/dev/null 2>&1; then
        BACKUP_PACKAGE_KEY="$BACKUP_PACKAGE_KEY" php \
            "$temporary_dir/_seal_package.php" \
            "$temporary_dir/bundle.tar" "$temporary_dir/package.nfsebkp" \
            || fail "falha ao cifrar pacote de backup (php host)"
    else
        # Container root-owned → chown de volta ao uid do host antes do chmod 600.
        host_uid=$(id -u)
        host_gid=$(id -g)
        compose run --rm -T --no-deps --user root \
            -e BACKUP_PACKAGE_KEY="$BACKUP_PACKAGE_KEY" \
            -v "$temporary_dir:/backup-crypto:rw" \
            --entrypoint sh php -eu -c \
            "php /backup-crypto/_seal_package.php /backup-crypto/bundle.tar /backup-crypto/package.nfsebkp \
             && chown ${host_uid}:${host_gid} /backup-crypto/package.nfsebkp" \
            || fail "falha ao cifrar pacote de backup (php container; instale php-cli no host se preferir)"
    fi
    rm -f -- "$temporary_dir/bundle.tar" "$temporary_dir/_seal_package.php"
    package_encrypted=sim
    formato=nfse-adn-backup-v3
    # v3: package.nfsebkp é o único payload no destino (sem plaintext irmãos).
    componentes=package.nfsebkp
    rm -f -- "$temporary_dir/postgres.sql.gz" "$temporary_dir/vault.tar.gz" \
        "$temporary_dir/private.tar.gz"
fi

(
    cd "$temporary_dir"
    if [ "$package_encrypted" = sim ]; then
        sha256sum package.nfsebkp > SHA256SUMS
    else
        sha256sum postgres.sql.gz vault.tar.gz private.tar.gz > SHA256SUMS
        rm -f -- bundle.tar
    fi
)

cat > "$temporary_dir/MANIFEST.txt" <<EOF
formato=$formato
criado_em_utc=$timestamp
componentes=$componentes
chave_mestra_incluida=nao
cofre_separado=sim
pacote_cifrado=$package_encrypted
EOF

chmod 600 "$temporary_dir"/*
mv "$temporary_dir" "$final_dir"
temporary_dir=''

printf 'Backup concluído: %s (formato=%s pacote_cifrado=%s)\n' "$final_dir" "$formato" "$package_encrypted"
