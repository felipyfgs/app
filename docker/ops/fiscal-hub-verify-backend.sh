#!/usr/bin/env bash
# Verificação backend do hub fiscal (task 16.1).
# Uso (na raiz do monorepo):
#   bash ./docker/ops/fiscal-hub-verify-backend.sh
#   bash ./docker/ops/fiscal-hub-verify-backend.sh --full
#   bash ./docker/ops/fiscal-hub-verify-backend.sh --preflight-strict
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

FULL=0
PREFLIGHT_STRICT=0
for arg in "$@"; do
  case "$arg" in
    --full) FULL=1 ;;
    --preflight-strict) PREFLIGHT_STRICT=1 ;;
    -h|--help)
      sed -n '2,8p' "$0"
      exit 0
      ;;
    *)
      echo "Argumento desconhecido: $arg" >&2
      exit 2
      ;;
  esac
done

if ! docker compose ps --status running --services 2>/dev/null | grep -qx 'php'; then
  echo "Serviço php não está rodando. Suba a stack (make up / docker compose up -d)." >&2
  exit 1
fi

echo "==> Preflight isolamento multi-tenant"
if [[ "$PREFLIGHT_STRICT" -eq 1 ]]; then
  docker compose exec -T php php artisan ops:preflight-tenant-isolation --fail-on-issues
else
  docker compose exec -T php php artisan ops:preflight-tenant-isolation || true
fi

if [[ "$FULL" -eq 1 ]]; then
  echo "==> Suite completa backend (php artisan test)"
  docker compose exec -T php php artisan test
else
  echo "==> Suite filtrada Fiscal|Serpro|Platform|Architecture|Integra|Tenant"
  docker compose exec -T php php artisan test \
    --filter='Fiscal|Serpro|Platform|Architecture|Integra|Tenant'
fi

echo "==> OK — ver docs/ops/fiscal-hub-verification-2026-07-15.md para registrar evidência"
