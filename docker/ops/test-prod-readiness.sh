#!/usr/bin/env bash
# Cenários fail-closed de prod-readiness (sem stack prod, sem DNS real).
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
cd "$ROOT_DIR"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

export EVIDENCE_DIR="$TMP/evidence"
export RELEASE_SHA
RELEASE_SHA="$(git rev-parse HEAD)"

echo "==> source com SHA divergente deve falhar"
set +e
PHASE=source RELEASE_SHA=deadbeefdeadbeefdeadbeefdeadbeefdeadbeef \
  ./docker/ops/prod-readiness.sh
rc=$?
set -e
test "$rc" -ne 0

echo "==> source com SHA=HEAD (worktree pode estar dirty em dev)"
set +e
PHASE=source RELEASE_SHA="$RELEASE_SHA" ./docker/ops/prod-readiness.sh
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

echo "==> predeploy com .env.prod.example (placeholders) deve falhar"
set +e
PHASE=predeploy PROD_ENV=.env.prod.example ./docker/ops/prod-readiness.sh
rc=$?
set -e
test "$rc" -ne 0

echo "==> predeploy com env sanitizado (pode falhar só por portas dev)"
cp .env.prod.example "$TMP/env.prod"
chmod 600 "$TMP/env.prod"
app_key="base64:$(openssl rand -base64 32)"
vault_key="$(openssl rand -base64 32)"
db_pass="$(openssl rand -hex 24)"
sed -i \
  -e "s|^APP_KEY=.*|APP_KEY=$app_key|" \
  -e "s|^VAULT_MASTER_KEY=.*|VAULT_MASTER_KEY=$vault_key|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$db_pass|" \
  -e 's|^ACME_EMAIL=.*|ACME_EMAIL=ops@inovaicontabil.com.br|' \
  -e 's|^MAIL_HOST=.*|MAIL_HOST=smtp.inovaicontabil.com.br|' \
  -e 's|^MAIL_USERNAME=.*|MAIL_USERNAME=ops-mail|' \
  -e 's|^MAIL_PASSWORD=.*|MAIL_PASSWORD=smtp-test-secret-value-xyz|' \
  -e 's|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS=noreply@inovaicontabil.com.br|' \
  "$TMP/env.prod"
# strip residual placeholders
grep -Evi 'substitua|example\.com|change-me' "$TMP/env.prod" >"$TMP/env.prod.clean" || true
mv "$TMP/env.prod.clean" "$TMP/env.prod"
chmod 600 "$TMP/env.prod"
# ensure required lines survived
for line in 'APP_ENV=production' 'LOG_CHANNEL=stderr' 'MAIL_MAILER=smtp' 'SERPRO_KILL_SWITCH=true'; do
  grep -qx "$line" "$TMP/env.prod" || echo "$line" >>"$TMP/env.prod"
done
grep -q '^APP_KEY=base64:' "$TMP/env.prod" || echo "APP_KEY=$app_key" >>"$TMP/env.prod"
grep -q '^VAULT_MASTER_KEY=' "$TMP/env.prod" || echo "VAULT_MASTER_KEY=$vault_key" >>"$TMP/env.prod"
grep -q '^DB_PASSWORD=' "$TMP/env.prod" || echo "DB_PASSWORD=$db_pass" >>"$TMP/env.prod"
grep -q '^MAIL_HOST=' "$TMP/env.prod" || echo 'MAIL_HOST=smtp.inovaicontabil.com.br' >>"$TMP/env.prod"
grep -q '^MAIL_USERNAME=' "$TMP/env.prod" || echo 'MAIL_USERNAME=ops-mail' >>"$TMP/env.prod"
grep -q '^MAIL_PASSWORD=' "$TMP/env.prod" || echo 'MAIL_PASSWORD=smtp-test-secret-value-xyz' >>"$TMP/env.prod"
grep -q '^MAIL_FROM_ADDRESS=' "$TMP/env.prod" || echo 'MAIL_FROM_ADDRESS=noreply@inovaicontabil.com.br' >>"$TMP/env.prod"
grep -q '^ACME_EMAIL=' "$TMP/env.prod" || echo 'ACME_EMAIL=ops@inovaicontabil.com.br' >>"$TMP/env.prod"
grep -q '^MAIL_PORT=' "$TMP/env.prod" || echo 'MAIL_PORT=587' >>"$TMP/env.prod"
grep -q '^MAIL_SCHEME=' "$TMP/env.prod" || echo 'MAIL_SCHEME=smtp' >>"$TMP/env.prod"
chmod 600 "$TMP/env.prod"

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
PHASE=predeploy PROD_ENV="$TMP/env.prod" BACKUP_ENV="$TMP/backup.env" \
  ./docker/ops/prod-readiness.sh
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
./docker/ops/secret-scan.sh

echo "test-prod-readiness: OK"
