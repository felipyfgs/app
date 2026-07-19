#!/usr/bin/env bash
# Varredura de artefatos sensíveis no workspace (sem imprimir conteúdo).
# Uso (raiz do monorepo): ./infra/docker/ops/secret-scan.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

FAIL=0
REPORT=()

is_allowlisted() {
  local f="$1"
  case "$f" in
    ./apps/api/tests/fixtures/*) return 0 ;;
    ./apps/api/storage/app/certs/*.pem) return 0 ;; # cadeias TLS públicas (não chaves privadas)
    ./.local/reference/*) return 0 ;;
    ./apps/web/node_modules/*|./apps/api/vendor/*) return 0 ;;
    ./services/*/.venv/*) return 0 ;;
  esac
  return 1
}

PRUNE=(
  -path './.git'
  -o -path './apps/api/vendor'
  -o -path '*/node_modules'
  -o -path './apps/web/.output'
  -o -path './apps/web/.nuxt'
  -o -path './apps/web/dist'
  -o -path './apps/api/storage/framework'
  -o -path './apps/api/storage/app/private'
  -o -path './.local'
  -o -path './services/*/.venv'
  -o -path '*/__pycache__'
  -o -path '*/.pytest_cache'
  -o -path '*/.mypy_cache'
  -o -path '*/.ruff_cache'
)

scan_globs() {
  local pattern="$1"
  local label="$2"
  while IFS= read -r -d '' f; do
    if is_allowlisted "$f"; then
      continue
    fi
    REPORT+=("$label:$f")
    FAIL=1
  done < <(find . \
    \( "${PRUNE[@]}" \) -prune -o \
    -type f -name "$pattern" -print0 2>/dev/null)
}

scan_globs '*.pfx' 'PFX'
scan_globs '*.p12' 'P12'
scan_globs '*.pem' 'PEM'

while IFS= read -r -d '' f; do
  if is_allowlisted "$f"; then
    continue
  fi
  REPORT+=("KEY:$f")
  FAIL=1
done < <(find . \
  \( "${PRUNE[@]}" \) -prune -o \
  -type f \( -name '*.key' -o -name '*_rsa' -o -name 'id_rsa' \) -print0 2>/dev/null)

while IFS= read -r -d '' f; do
  case "$f" in
    ./infra/docker/*|./docs/*|./openspec/*) continue ;;
  esac
  REPORT+=("DUMP:$f")
  FAIL=1
done < <(find . \
  \( "${PRUNE[@]}" \) -prune -o \
  -type f \( -name '*.sql' -o -name '*.sql.gz' -o -name '*.dump' \) -print0 2>/dev/null)

if [[ -d ./vault ]] || [[ -d ./.vault ]]; then
  REPORT+=("VAULT_DIR:./vault")
  FAIL=1
fi

echo "==> Secret scan (paths only, no content)"
if [[ "$FAIL" -eq 0 ]]; then
  echo "SECRET_SCAN_OK"
  if docker compose ps --status running --services 2>/dev/null | grep -qx php; then
    docker compose exec -T php php artisan fiscal-model:secret-scan --json || true
  fi
  exit 0
fi

echo "SECRET_SCAN_FINDINGS count=${#REPORT[@]}"
for line in "${REPORT[@]}"; do
  echo "  $line"
done
echo "Remova artefatos ou mova para fora do tree. Ver docs/ops/serpro-transient-secret-removal.md" >&2
exit 1
