#!/usr/bin/env bash
# Gera manifesto sanitizado da release (SHA, digests/IDs locais, horário, migrations).
# Destino fora do repositório, modo 600. Sem segredos.
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)"
cd "$ROOT_DIR"

RELEASE_SHA="${RELEASE_SHA:-$(git rev-parse HEAD 2>/dev/null || echo unknown)}"
RELEASE_TAG="${RELEASE_TAG:-sha-${RELEASE_SHA:0:12}}"
MANIFEST_DIR="${MANIFEST_DIR:-/var/lib/fiscal-hub/releases}"
PROD_ENV="${PROD_ENV:-.env}"
STACK_PROJECT="${STACK_PROJECT:-fiscal-hub}"
MIGRATION_BATCH="${MIGRATION_BATCH:-}"

mkdir -p "$MANIFEST_DIR"
chmod 700 "$MANIFEST_DIR" 2>/dev/null || true

php_image="fiscal-hub-php:${RELEASE_TAG}"
web_image="fiscal-hub-web:${RELEASE_TAG}"

php_id="$(docker image inspect --format '{{.Id}}' "$php_image" 2>/dev/null | tr -d '\r\n' || true)"
web_id="$(docker image inspect --format '{{.Id}}' "$web_image" 2>/dev/null | tr -d '\r\n' || true)"
php_rev="$(docker image inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' "$php_image" 2>/dev/null | tr -d '\r\n' || true)"
web_rev="$(docker image inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' "$web_image" 2>/dev/null | tr -d '\r\n' || true)"
[ -n "$php_id" ] || php_id=missing
[ -n "$web_id" ] || web_id=missing

# Optional migration batch from running container
if [[ -z "$MIGRATION_BATCH" ]] && [[ -f "$PROD_ENV" ]]; then
  if PROD_ENV_FILE="$PROD_ENV" docker compose --env-file "$PROD_ENV" -f compose.prod.yml -p "$STACK_PROJECT" \
      ps --status running --services 2>/dev/null | grep -qx php; then
    MIGRATION_BATCH="$(
      PROD_ENV_FILE="$PROD_ENV" docker compose --env-file "$PROD_ENV" -f compose.prod.yml -p "$STACK_PROJECT" \
        exec -T php php artisan tinker --execute='echo (int) Illuminate\Support\Facades\DB::table("migrations")->max("batch");' 2>/dev/null \
        | tr -dc '0-9' || true
    )"
  fi
fi

out="$MANIFEST_DIR/release-${RELEASE_SHA:0:12}-$(date -u +%Y%m%dT%H%M%SZ).json"
umask 077
cat >"$out" <<EOF
{
  "release_sha": "$RELEASE_SHA",
  "release_tag": "$RELEASE_TAG",
  "created_at": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "images": {
    "php": {"tag": "$php_image", "id": "$php_id", "oci_revision": "$php_rev"},
    "web": {"tag": "$web_image", "id": "$web_id", "oci_revision": "$web_rev"}
  },
  "migration_batch": ${MIGRATION_BATCH:-null}
}
EOF
chmod 600 "$out" 2>/dev/null || true

# Ensure no secrets by scanning the manifest path
if command -v grep >/dev/null 2>&1; then
  if grep -Eiq 'password|secret|private_key|BEGIN |smtp|token=' "$out"; then
    echo "manifest appears to contain sensitive patterns" >&2
    rm -f "$out"
    exit 1
  fi
fi

printf 'release_manifest=%s\n' "$out"
