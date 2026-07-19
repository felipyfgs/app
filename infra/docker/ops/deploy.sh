#!/bin/sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)
STACK_FILE=${STACK_FILE:-compose.prod.yml}
STACK_ENV=${STACK_ENV:-.env}
STACK_PROJECT=${STACK_PROJECT:-fiscal-hub}
RELEASE_SHA=${RELEASE_SHA:-}
RELEASE_TAG=${RELEASE_TAG:-}
CONFIRM_FRESH_PROD=${CONFIRM_FRESH_PROD:-}
PRE_DEPLOY_BACKUP=${PRE_DEPLOY_BACKUP:-}
MANIFEST_DIR=${MANIFEST_DIR:-/var/lib/fiscal-hub/releases}
BACKUP_PACKAGE_KEY=${BACKUP_PACKAGE_KEY:-}

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

if [ -z "$RELEASE_SHA" ]; then
    RELEASE_SHA=$(git rev-parse HEAD 2>/dev/null || true)
fi
[ -n "$RELEASE_SHA" ] || fail "RELEASE_SHA obrigatório"
if [ -z "$RELEASE_TAG" ]; then
    RELEASE_TAG="sha-$(printf '%s' "$RELEASE_SHA" | cut -c1-12)"
fi
export RELEASE_SHA RELEASE_TAG
export BUILD_DATE="${BUILD_DATE:-$(date -u +%Y-%m-%dT%H:%M:%SZ)}"

deploy_complete=false
protect_failure() {
    status=$?
    trap - EXIT HUP INT TERM

    if [ "$deploy_complete" != true ]; then
        compose stop web horizon scheduler php >/dev/null 2>&1 || true
        printf '%s\n' \
            'Deploy interrompido; aplicação mantida fora do ar para evitar código/schema incompatíveis.' \
            'Revise migrations, restaure backup pré-deploy e a tag SHA anterior antes de liberar o tráfego.' \
            'Rollback automático de schema é proibido.' >&2
    fi

    exit "$status"
}
trap protect_failure EXIT HUP INT TERM

# --- Classificar instalação: fresh vs existente ---
classify_instance() {
    # returns via stdout: fresh | existing | indeterminate
    volumes_exist=false
    if docker volume ls -q | grep -qE "${STACK_PROJECT}_postgres_data|${STACK_PROJECT}_vault_data|${STACK_PROJECT}_private_storage"; then
        volumes_exist=true
    fi

    php_running=false
    if compose ps --status running --services 2>/dev/null | grep -qx php; then
        php_running=true
    fi

    if [ "$php_running" = true ]; then
        # Tabela migrations indica schema existente
        if compose exec -T php php artisan tinker --execute='echo Illuminate\Support\Facades\Schema::hasTable("migrations") ? "yes" : "no";' 2>/dev/null \
            | grep -q yes; then
            mig_count=$(compose exec -T php php artisan tinker --execute='echo (int) Illuminate\Support\Facades\DB::table("migrations")->count();' 2>/dev/null | tr -dc '0-9' || echo 0)
            if [ "${mig_count:-0}" -gt 0 ]; then
                printf 'existing\n'
                return
            fi
        fi
    fi

    # Postgres volume com dados mas sem PHP: indeterminate (não assumir fresh)
    if [ "$volumes_exist" = true ] && [ "$php_running" = false ]; then
        # Tentar subir só postgres para inspecionar
        if compose up -d --wait postgres >/dev/null 2>&1; then
            tables=$(compose exec -T postgres sh -c \
                'psql --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" -Atc "SELECT count(*) FROM information_schema.tables WHERE table_schema='\''public'\''"' \
                2>/dev/null | tr -dc '0-9' || echo '')
            if [ -n "$tables" ] && [ "$tables" -gt 0 ]; then
                printf 'existing\n'
                return
            fi
            if [ -n "$tables" ] && [ "$tables" -eq 0 ]; then
                printf 'fresh\n'
                return
            fi
        fi
        printf 'indeterminate\n'
        return
    fi

    if [ "$volumes_exist" = false ]; then
        printf 'fresh\n'
        return
    fi

    printf 'indeterminate\n'
}

instance_class=$(classify_instance)
printf 'Instância classificada como: %s\n' "$instance_class"

case "$instance_class" in
    indeterminate)
        fail "Estado indeterminado (volumes presentes sem prova de base vazia). Use backup offline e restaure antes de migrar."
        ;;
    existing)
        [ -n "$PRE_DEPLOY_BACKUP" ] || fail "Instância existente exige PRE_DEPLOY_BACKUP=/caminho/nfse-backup-... verificado"
        [ -d "$PRE_DEPLOY_BACKUP" ] || fail "PRE_DEPLOY_BACKUP inexistente"
        grep -qx 'formato=nfse-adn-backup-v3' "$PRE_DEPLOY_BACKUP/MANIFEST.txt" \
            || fail "PRE_DEPLOY_BACKUP deve ser pacote v3"
        STACK_FILE="$STACK_FILE" STACK_ENV="$STACK_ENV" STACK_PROJECT="$STACK_PROJECT" \
            BACKUP_PACKAGE_KEY="$BACKUP_PACKAGE_KEY" \
            ./infra/docker/ops/restore.sh --verify-only "$PRE_DEPLOY_BACKUP" \
            || fail "PRE_DEPLOY_BACKUP falhou na verificação"
        printf 'Backup pré-deploy verificado: %s\n' "$PRE_DEPLOY_BACKUP"
        ;;
    fresh)
        if [ "$CONFIRM_FRESH_PROD" != "SIM" ]; then
            fail "Instalação fresh exige CONFIRM_FRESH_PROD=SIM (CONFIRM_PROD=SIM não basta)"
        fi
        printf 'Instalação fresh confirmada (CONFIRM_FRESH_PROD=SIM).\n'
        ;;
    *)
        fail "classificação desconhecida: $instance_class"
        ;;
esac

running_services=$(compose ps --status running --services 2>/dev/null || true)
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

# Manifesto sanitizado da release (fora do repo)
RELEASE_SHA="$RELEASE_SHA" RELEASE_TAG="$RELEASE_TAG" \
    MANIFEST_DIR="$MANIFEST_DIR" PROD_ENV="$STACK_ENV" STACK_PROJECT="$STACK_PROJECT" \
    ./infra/docker/ops/release-manifest.sh || fail "falha ao gravar manifesto de release"

deploy_complete=true
printf '%s\n' "Deploy concluído; release=${RELEASE_SHA}; tag=${RELEASE_TAG}; serviços saudáveis."
printf '%s\n' 'Tags SHA anteriores são preservadas (não removidas automaticamente).'
printf '%s\n' 'Rollback de schema automático é proibido — restaure backup + tag SHA anterior.'
