#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
STACK_FILE=${STACK_FILE:-compose.prod.yml}
STACK_ENV=${STACK_ENV:-.env.prod}
STACK_PROJECT=${STACK_PROJECT:-fiscal-hub}

compose() {
    PROD_ENV_FILE="$STACK_ENV" docker compose \
        --env-file "$STACK_ENV" \
        -f "$STACK_FILE" \
        -p "$STACK_PROJECT" \
        "$@"
}

fail() {
    printf 'Erro: %s\n' "$*" >&2
    exit 1
}

command -v docker >/dev/null 2>&1 || fail "Docker é obrigatório"
[ -f "$ROOT_DIR/$STACK_FILE" ] || fail "Compose ausente: $STACK_FILE"
[ -f "$ROOT_DIR/$STACK_ENV" ] || fail "ambiente ausente: $STACK_ENV"

cd "$ROOT_DIR"

deploy_complete=false
protect_failure() {
    status=$?
    trap - EXIT HUP INT TERM

    if [ "$deploy_complete" != true ]; then
        compose stop web horizon scheduler php >/dev/null 2>&1 || true
        printf '%s\n' \
            'Deploy interrompido; aplicação mantida fora do ar para evitar código/schema incompatíveis.' \
            'Revise migrations e restaure uma imagem compatível antes de liberar o tráfego.' >&2
    fi

    exit "$status"
}
trap protect_failure EXIT HUP INT TERM

running_services=$(compose ps --status running --services)
if printf '%s\n' "$running_services" | grep -qx php; then
    compose exec -T php php artisan down --retry=60
fi

# Nenhum processo antigo atende enquanto a imagem nova altera o schema.
compose stop web horizon scheduler php >/dev/null 2>&1 || true

# Dependências e edge ficam saudáveis antes da migration.
compose up -d --wait postgres redis socket-proxy traefik

# Migration roda uma única vez com a imagem nova e sem tráfego de aplicação.
compose run --rm php php artisan migrate --force

# Aplicação só volta ao ar após processos novos atingirem estado saudável.
compose up -d --wait php horizon scheduler web
compose exec -T php php artisan up

# Smokes internos independem de DNS/ACME e validam os dois runtimes.
compose exec -T php php artisan about --only=environment >/dev/null
compose exec -T web wget -qO- http://127.0.0.1/index.html >/dev/null

deploy_complete=true
printf '%s\n' 'Deploy concluído; serviços saudáveis e smokes internos aprovados.'
