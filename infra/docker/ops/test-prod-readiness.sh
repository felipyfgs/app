#!/usr/bin/env bash
# Cenários fail-closed de prod-readiness (sem stack prod, sem DNS real).
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)"
cd "$ROOT_DIR"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

export EVIDENCE_DIR="$TMP/evidence"
export RELEASE_SHA
RELEASE_SHA="$(git rev-parse HEAD)"

echo "==> source com SHA divergente deve falhar"
set +e
PHASE=source RELEASE_SHA=deadbeefdeadbeefdeadbeefdeadbeefdeadbeef \
  ./infra/docker/ops/prod-readiness.sh
rc=$?
set -e
test "$rc" -ne 0

echo "==> source com SHA=HEAD (worktree pode estar dirty em dev)"
set +e
PHASE=source RELEASE_SHA="$RELEASE_SHA" ./infra/docker/ops/prod-readiness.sh
src_rc=$?
set -e
# Em CI worktree limpo → 0; em dev com mudanças → 1. Ambos gravam evidence.
test -d "$EVIDENCE_DIR"
ls "$EVIDENCE_DIR"/readiness-source-*.json >/dev/null
if [[ -z "$(git status --porcelain)" ]]; then
  test "$src_rc" -eq 0
  echo "    worktree limpo: source OK"
else
  test "$src_rc" -ne 0
  echo "    worktree dirty: source fail-closed OK"
fi

echo "==> predeploy com .env.example (placeholders) deve falhar"
set +e
PHASE=predeploy PROD_ENV=.env.example ./infra/docker/ops/prod-readiness.sh
rc=$?
set -e
test "$rc" -ne 0

echo "==> predeploy com env sanitizado (pode falhar só por portas dev)"
cp .env.example "$TMP/env"
chmod 600 "$TMP/env"
app_key="base64:$(openssl rand -base64 32)"
vault_key="$(openssl rand -base64 32)"
db_pass="$(openssl rand -hex 24)"
sed -i \
  -e 's|^APP_ENV=.*|APP_ENV=production|' \
  -e 's|^APP_DEBUG=.*|APP_DEBUG=false|' \
  -e 's|^APP_URL=.*|APP_URL=https://app.inovaicontabil.com.br|' \
  -e 's|^SESSION_DOMAIN=.*|SESSION_DOMAIN=app.inovaicontabil.com.br|' \
  -e 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' \
  -e 's|^SANCTUM_STATEFUL_DOMAINS=.*|SANCTUM_STATEFUL_DOMAINS=app.inovaicontabil.com.br|' \
  -e 's|^AUTH_TWO_FACTOR_REQUIRED=.*|AUTH_TWO_FACTOR_REQUIRED=true|' \
  -e "s|^APP_KEY=.*|APP_KEY=$app_key|" \
  -e "s|^VAULT_MASTER_KEY=.*|VAULT_MASTER_KEY=$vault_key|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$db_pass|" \
  -e 's|^ACME_EMAIL=.*|ACME_EMAIL=ops@inovaicontabil.com.br|' \
  -e 's|^MAIL_HOST=.*|MAIL_HOST=smtp.inovaicontabil.com.br|' \
  -e 's|^MAIL_USERNAME=.*|MAIL_USERNAME=ops-mail|' \
  -e 's|^MAIL_PASSWORD=.*|MAIL_PASSWORD=smtp-test-secret-value-xyz|' \
  -e 's|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=noreply@inovaicontabil.com.br|' \
  -e 's|^MAIL_MAILER=.*|MAIL_MAILER=smtp|' \
  -e 's|^LOG_LEVEL=.*|LOG_LEVEL=warning|' \
  -e 's|^SERPRO_DEFAULT_ENVIRONMENT=.*|SERPRO_DEFAULT_ENVIRONMENT=PRODUCTION|' \
  -e 's|^SERPRO_PROD_CHECK_STRICT=.*|SERPRO_PROD_CHECK_STRICT=true|' \
  "$TMP/env"
# strip residual placeholders
grep -Evi 'substitua|example\.com|change-me' "$TMP/env" >"$TMP/env.clean" || true
mv "$TMP/env.clean" "$TMP/env"
chmod 600 "$TMP/env"
# ensure required lines survived
for line in 'APP_ENV=production' 'LOG_CHANNEL=stderr' 'MAIL_MAILER=smtp' 'SERPRO_KILL_SWITCH=true'; do
  grep -qx "$line" "$TMP/env" || echo "$line" >>"$TMP/env"
done
grep -q '^APP_KEY=base64:' "$TMP/env" || echo "APP_KEY=$app_key" >>"$TMP/env"
grep -q '^VAULT_MASTER_KEY=' "$TMP/env" || echo "VAULT_MASTER_KEY=$vault_key" >>"$TMP/env"
grep -q '^DB_PASSWORD=' "$TMP/env" || echo "DB_PASSWORD=$db_pass" >>"$TMP/env"
grep -q '^MAIL_HOST=' "$TMP/env" || echo 'MAIL_HOST=smtp.inovaicontabil.com.br' >>"$TMP/env"
grep -q '^MAIL_USERNAME=' "$TMP/env" || echo 'MAIL_USERNAME=ops-mail' >>"$TMP/env"
grep -q '^MAIL_PASSWORD=' "$TMP/env" || echo 'MAIL_PASSWORD=smtp-test-secret-value-xyz' >>"$TMP/env"
grep -q '^MAIL_FROM_ADDRESS=' "$TMP/env" || echo 'MAIL_FROM_ADDRESS=noreply@inovaicontabil.com.br' >>"$TMP/env"
grep -q '^ACME_EMAIL=' "$TMP/env" || echo 'ACME_EMAIL=ops@inovaicontabil.com.br' >>"$TMP/env"
grep -q '^MAIL_PORT=' "$TMP/env" || echo 'MAIL_PORT=587' >>"$TMP/env"
grep -q '^MAIL_SCHEME=' "$TMP/env" || echo 'MAIL_SCHEME=smtp' >>"$TMP/env"
chmod 600 "$TMP/env"

cat >"$TMP/backup.env" <<EOF
BACKUP_PACKAGE_KEY=$(openssl rand -base64 32)
BACKUP_DIR=$TMP/backups
BACKUP_RETENTION_LOCAL=7
BACKUP_RETENTION_OFFSITE_REFS=30
BACKUP_RPO_HOURS=24
BACKUP_RTO_HOURS=4
OFFSITE_BACKUP_REFERENCE=ci-offsite-ref-$(openssl rand -hex 4)
OFFSITE_MAX_AGE_HOURS=24
EOF
chmod 600 "$TMP/backup.env"

set +e
PHASE=predeploy PROD_ENV="$TMP/env" BACKUP_ENV="$TMP/backup.env" \
  ./infra/docker/ops/prod-readiness.sh
set -e
test -d "$EVIDENCE_DIR"
ls "$EVIDENCE_DIR"/readiness-predeploy-*.json >/dev/null

echo "==> evidence JSON não deve conter segredos"
if grep -REiq 'smtp-test-secret|private_key|BEGIN RSA|BEGIN OPENSSH|vault_key=|BACKUP_PACKAGE_KEY=' "$EVIDENCE_DIR"; then
  echo "evidence vazou padrão sensível" >&2
  exit 1
fi
# allowlist de campos no JSON de evidence
for f in "$EVIDENCE_DIR"/readiness-*.json; do
  python3 - "$f" <<'PY' || { echo "JSON inválido: $f" >&2; exit 1; }
import json,sys
data=json.load(open(sys.argv[1]))
allowed={"phase","ok","checked_at","release_sha","checks"}
assert set(data.keys())<=allowed|{"checks"}
for c in data.get("checks",[]):
    assert set(c.keys())=={"id","ok","detail"}
print("allowlist ok", sys.argv[1])
PY
done

echo "==> secret-scan do tree"
./infra/docker/ops/secret-scan.sh

echo "test-prod-readiness: OK"
