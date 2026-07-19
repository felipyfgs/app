#!/usr/bin/env bash
# Job host de backup diário: carrega env root-only e invoca make prod-backup.
# Não monta Docker socket nos containers da aplicação; a chave permanece no host.
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd)"
BACKUP_ENV="${BACKUP_ENV:-/etc/fiscal-hub/backup.env}"

if [[ ! -f "$BACKUP_ENV" ]]; then
  echo "BACKUP_ENV ausente: $BACKUP_ENV" >&2
  exit 2
fi
mode="$(stat -c '%a' "$BACKUP_ENV" 2>/dev/null || echo 000)"
if [[ "$mode" != "600" ]]; then
  echo "BACKUP_ENV deve usar modo 600 (atual: $mode)" >&2
  exit 2
fi

# shellcheck disable=SC1090
set -a
# shellcheck source=/dev/null
source "$BACKUP_ENV"
set +a

: "${BACKUP_PACKAGE_KEY:?BACKUP_PACKAGE_KEY obrigatório}"
: "${BACKUP_DIR:=/var/backups/fiscal-hub}"
KEEP="${BACKUP_RETENTION_LOCAL:-7}"

cd "$ROOT_DIR"
BACKUP_DIR="$BACKUP_DIR" BACKUP_PACKAGE_KEY="$BACKUP_PACKAGE_KEY" make prod-backup

# Retenção local
if [[ -d "$BACKUP_DIR" ]]; then
  # shellcheck disable=SC2012
  ls -1dt "$BACKUP_DIR"/nfse-backup-* 2>/dev/null | tail -n +"$((KEEP + 1))" | xargs -r rm -rf --
fi

# Evidência sanitizada de off-site (referência opaca; sem path real se não configurado)
EVIDENCE_DIR="${EVIDENCE_DIR:-/var/lib/fiscal-hub/readiness}"
mkdir -p "$EVIDENCE_DIR"
chmod 700 "$EVIDENCE_DIR" 2>/dev/null || true
ref="${OFFSITE_BACKUP_REFERENCE:-pending}"
age_max="${OFFSITE_MAX_AGE_HOURS:-24}"
umask 077
cat >"$EVIDENCE_DIR/backup-last.json" <<EOF
{
  "ok": true,
  "kind": "full",
  "completed_at": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "retention_local": $KEEP,
  "retention_offsite_refs": ${BACKUP_RETENTION_OFFSITE_REFS:-30},
  "offsite_reference": "$ref",
  "offsite_max_age_hours": $age_max,
  "rpo_hours": ${BACKUP_RPO_HOURS:-24},
  "rto_hours": ${BACKUP_RTO_HOURS:-4}
}
EOF
chmod 600 "$EVIDENCE_DIR/backup-last.json" 2>/dev/null || true

echo "host-backup-run: OK"
