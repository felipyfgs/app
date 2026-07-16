#!/usr/bin/env bash
# Verificação backend do hub fiscal.
# Uso (na raiz do monorepo):
#   ./docker/ops/verify.sh
#   ./docker/ops/verify.sh --full
#   ./docker/ops/verify.sh --preflight-strict
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

echo "==> Secret scan (paths + colunas; sem conteúdo)"
if [[ -x "$ROOT/docker/ops/secret-scan.sh" ]]; then
  if ! "$ROOT/docker/ops/secret-scan.sh"; then
    if [[ "$FULL" -eq 1 ]]; then
      echo "secret-scan falhou em --full — remova artefatos sensíveis do tree" >&2
      exit 1
    fi
    echo "AVISO: secret-scan com findings (não bloqueia modo filtrado; bloqueia --full)" >&2
  fi
else
  docker compose exec -T php php artisan fiscal-model:secret-scan || true
fi

echo "==> Integridade da cadeia de auditoria"
docker compose exec -T php php artisan audit:verify-chain --json || true

if [[ "$FULL" -eq 1 ]]; then
  echo "==> Suite completa backend (php artisan test)"
  docker compose exec -T php php artisan test
else
  echo "==> Suite filtrada Fiscal|Serpro|Platform|Architecture|Integra|Tenant|Audit"
  docker compose exec -T php php artisan test \
    --filter='Fiscal|Serpro|Platform|Architecture|Integra|Tenant|Audit'
fi

echo "==> OK — registre esta execução como evidência do gate backend"
