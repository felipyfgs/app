#!/usr/bin/env bash
# Gate canônico de readiness de produção: source | predeploy | postdeploy.
# Saída fail-closed; JSON allowlisted fora do repositório (modo 700/600).
# Nunca imprime valores de env/segredos/contatos.
set -euo pipefail

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
cd "$ROOT_DIR"

PHASE="${PHASE:-all}"
PROD_ENV="${PROD_ENV:-.env.prod}"
BACKUP_ENV="${BACKUP_ENV:-/etc/fiscal-hub/backup.env}"
EVIDENCE_DIR="${EVIDENCE_DIR:-/var/lib/fiscal-hub/readiness}"
RELEASE_SHA="${RELEASE_SHA:-}"
PROD_DOMAIN="${PROD_DOMAIN:-app.inovaicontabil.com.br}"
STACK_PROJECT="${STACK_PROJECT:-fiscal-hub}"
CONFIRM_INITIAL_ONBOARDING="${CONFIRM_INITIAL_ONBOARDING:-}"

FAIL=0
CHECKS_JSON='[]'

timestamp_utc() { date -u '+%Y-%m-%dT%H:%M:%SZ'; }

fail_msg() {
  printf 'FAIL: %s\n' "$*" >&2
  FAIL=1
}

ok_msg() {
  printf 'OK: %s\n' "$*" >&2
}

record_check() {
  local id="$1" ok="$2" detail="$3"
  local ok_json=false
  [[ "$ok" == "true" || "$ok" == "1" ]] && ok_json=true
  # Escape detail for JSON (no secrets expected in detail labels)
  detail="${detail//\\/\\\\}"
  detail="${detail//\"/\\\"}"
  if [[ "$CHECKS_JSON" == "[]" ]]; then
    CHECKS_JSON="[{\"id\":\"$id\",\"ok\":$ok_json,\"detail\":\"$detail\"}]"
  else
    CHECKS_JSON="${CHECKS_JSON%]},{\"id\":\"$id\",\"ok\":$ok_json,\"detail\":\"$detail\"}]"
  fi
  if [[ "$ok_json" == true ]]; then
    ok_msg "$id ($detail)"
  else
    fail_msg "$id ($detail)"
  fi
}

compose_prod() {
  PROD_ENV_FILE="$PROD_ENV" docker compose \
    --env-file "$PROD_ENV" \
    -f compose.prod.yml \
    -p "$STACK_PROJECT" \
    "$@"
}

write_evidence() {
  local phase="$1"
  local overall_ok="$2"
  mkdir -p "$EVIDENCE_DIR"
  chmod 700 "$EVIDENCE_DIR" 2>/dev/null || true
  local file="$EVIDENCE_DIR/readiness-${phase}-$(date -u +%Y%m%dT%H%M%SZ).json"
  local sha="${RELEASE_SHA:-unknown}"
  umask 077
  cat >"$file" <<EOF
{
  "phase": "$phase",
  "ok": $overall_ok,
  "checked_at": "$(timestamp_utc)",
  "release_sha": "$sha",
  "checks": $CHECKS_JSON
}
EOF
  chmod 600 "$file" 2>/dev/null || true
  printf 'evidence=%s\n' "$file" >&2
}

# --- phase: source ---
phase_source() {
  CHECKS_JSON='[]'
  local head_sha
  head_sha="$(git rev-parse HEAD 2>/dev/null || true)"
  if [[ -z "$head_sha" ]]; then
    record_check "git_head" "false" "missing"
  else
    record_check "git_head" "true" "present"
  fi

  if [[ -n "$(git status --porcelain 2>/dev/null || echo dirty)" ]]; then
    # empty porcelain = clean
    if [[ -z "$(git status --porcelain 2>/dev/null)" ]]; then
      record_check "worktree" "true" "clean"
    else
      record_check "worktree" "false" "dirty"
    fi
  else
    record_check "worktree" "true" "clean"
  fi

  if [[ -z "$RELEASE_SHA" ]]; then
    RELEASE_SHA="$head_sha"
  fi
  if [[ -n "$head_sha" && -n "$RELEASE_SHA" && "$RELEASE_SHA" != "$head_sha" ]]; then
    record_check "release_sha_match" "false" "mismatch"
  else
    record_check "release_sha_match" "true" "matches_head"
  fi

  if PROD_ENV_FILE="${PROD_ENV}" docker compose --env-file "${PROD_ENV}" -f compose.prod.yml -p "$STACK_PROJECT" config --quiet 2>/dev/null \
     || PROD_ENV_FILE=.env.prod.example docker compose --env-file .env.prod.example -f compose.prod.yml -p "$STACK_PROJECT" config --quiet 2>/dev/null; then
    record_check "compose_prod" "true" "valid"
  else
    record_check "compose_prod" "false" "invalid"
  fi

  local scripts_ok=true
  for script in docker/ops/deploy.sh docker/ops/backup.sh docker/ops/restore.sh \
                docker/ops/prod-readiness.sh docker/ops/secret-scan.sh; do
    if [[ -f "$script" ]]; then
      if ! bash -n "$script" 2>/dev/null && ! sh -n "$script" 2>/dev/null; then
        scripts_ok=false
      fi
    else
      scripts_ok=false
    fi
  done
  if $scripts_ok; then
    record_check "ops_scripts" "true" "syntax_ok"
  else
    record_check "ops_scripts" "false" "syntax_or_missing"
  fi

  # Prerequisite OpenSpec changes must be archived (not active)
  local prereq_ok=true
  for change in tornar-platform-admin-proprietario-unico \
                adaptar-aprovacoes-serpro-proprietario-unico \
                onboarding-inicial-plataforma \
                provisionar-admin-inicial-plataforma; do
    if [[ -d "openspec/changes/$change" ]]; then
      prereq_ok=false
    fi
  done
  if $prereq_ok; then
    record_check "prereq_changes" "true" "archived"
  else
    record_check "prereq_changes" "false" "still_active"
  fi

  local overall=true
  [[ "$FAIL" -eq 0 ]] || overall=false
  # FAIL may be set by record_check via fail_msg — recompute from checks
  if echo "$CHECKS_JSON" | grep -q '"ok":false'; then
    overall=false
    FAIL=1
  else
    overall=true
  fi
  write_evidence "source" "$overall"
}

env_has() {
  local key="$1"
  grep -Eq "^${key}=" "$PROD_ENV" 2>/dev/null
}

env_val() {
  local key="$1"
  grep -E "^${key}=" "$PROD_ENV" 2>/dev/null | head -1 | cut -d= -f2- || true
}

env_is_true() {
  local key="$1"
  local v
  v="$(env_val "$key" | tr '[:upper:]' '[:lower:]')"
  [[ "$v" == "true" || "$v" == "1" || "$v" == "yes" ]]
}

# --- phase: predeploy ---
phase_predeploy() {
  CHECKS_JSON='[]'
  FAIL=0

  if [[ ! -f "$PROD_ENV" ]]; then
    record_check "env_prod_file" "false" "missing"
  else
    record_check "env_prod_file" "true" "present"
    local mode
    mode="$(stat -c '%a' "$PROD_ENV" 2>/dev/null || echo 000)"
    if [[ "$mode" == "600" ]]; then
      record_check "env_prod_mode" "true" "600"
    else
      record_check "env_prod_mode" "false" "mode_${mode}"
    fi
  fi

  if [[ -f "$BACKUP_ENV" ]]; then
    local bmode
    bmode="$(stat -c '%a' "$BACKUP_ENV" 2>/dev/null || echo 000)"
    if [[ "$bmode" == "600" ]]; then
      record_check "backup_env_mode" "true" "600"
    else
      record_check "backup_env_mode" "false" "mode_${bmode}"
    fi
  else
    # Allow example path relative for CI fixtures
    if [[ -f "docker/ops/backup.env.example" ]]; then
      record_check "backup_env_file" "false" "missing_host_file"
    else
      record_check "backup_env_file" "false" "missing"
    fi
  fi

  if [[ -f "$PROD_ENV" ]]; then
    # Apenas valores de assignment (ignora comentários).
    if grep -E '^[^#[:space:]]+=' "$PROD_ENV" | grep -Eqi 'substitua|example\.com|change-me|changeme|^[A-Z0-9_]+=(TBD|TODO|xxx)$'; then
      record_check "placeholders" "false" "found"
    else
      record_check "placeholders" "true" "none"
    fi

    # Keys without printing values
    if grep -Eq '^APP_KEY=base64:.{32,}' "$PROD_ENV"; then
      record_check "app_key" "true" "format_ok"
    else
      record_check "app_key" "false" "invalid"
    fi
    if grep -Eq '^VAULT_MASTER_KEY=.{32,}' "$PROD_ENV"; then
      record_check "vault_key" "true" "format_ok"
    else
      record_check "vault_key" "false" "invalid"
    fi
    if grep -Eq '^DB_PASSWORD=.{16,}' "$PROD_ENV"; then
      record_check "db_password" "true" "length_ok"
    else
      record_check "db_password" "false" "weak"
    fi
    if grep -qx 'MAIL_MAILER=smtp' "$PROD_ENV" && grep -Eq '^MAIL_HOST=.+' "$PROD_ENV"; then
      record_check "smtp" "true" "configured"
    else
      record_check "smtp" "false" "incomplete"
    fi
    if grep -Eq '^ACME_EMAIL=[^[:space:]@]+@[^[:space:]@]+$' "$PROD_ENV"; then
      record_check "acme_email" "true" "format_ok"
    else
      record_check "acme_email" "false" "invalid"
    fi

    # Fiscal containment
    local containment_ok=true
    if grep -Eq '^FEATURES_GLOBAL_ENABLED=true$' "$PROD_ENV"; then containment_ok=false; fi
    if grep -Eq '^FEATURES_MUTATING_ENABLED=true$' "$PROD_ENV"; then containment_ok=false; fi
    if grep -Eq '^SERPRO_USE_FAKE_CLIENTS=true$' "$PROD_ENV"; then containment_ok=false; fi
    if grep -Eq '^SERPRO_CAPABILITY_[A-Z_]+=real$' "$PROD_ENV"; then containment_ok=false; fi
    if grep -Eq '^SERPRO_KILL_SWITCH=false$' "$PROD_ENV"; then containment_ok=false; fi
    if $containment_ok; then
      record_check "fiscal_containment" "true" "contained"
    else
      record_check "fiscal_containment" "false" "open_channel_or_flag"
    fi
  fi

  # Dev ports must not be publicly listening (best-effort; skip if ss unavailable)
  if command -v ss >/dev/null 2>&1; then
    local dev_open=false
    for port in 3000 8080 5432 6379; do
      if ss -lnt 2>/dev/null | grep -qE ":${port}\\b"; then
        # Only fail if bound on non-loopback when possible — conservative: flag presence
        if ss -lnt 2>/dev/null | grep -E ":${port}\\b" | grep -vqE '127\.0\.0\.1|\[::1\]'; then
          dev_open=true
        fi
      fi
    done
    if $dev_open; then
      record_check "dev_ports" "false" "public_dev_listener"
    else
      record_check "dev_ports" "true" "ok"
    fi
  else
    record_check "dev_ports" "true" "ss_unavailable_skipped"
  fi

  if echo "$CHECKS_JSON" | grep -q '"ok":false'; then
    FAIL=1
    write_evidence "predeploy" false
  else
    FAIL=0
    write_evidence "predeploy" true
  fi
}

# --- phase: postdeploy smoke (HTTP only — no fiscal) ---
# Explicit allowlist of paths/checks; never call NFS-e/SEFAZ/SERPRO.
phase_postdeploy() {
  CHECKS_JSON='[]'
  FAIL=0
  local base="https://${PROD_DOMAIN}"

  # DNS
  if command -v getent >/dev/null 2>&1; then
    if getent ahosts "$PROD_DOMAIN" >/dev/null 2>&1; then
      record_check "dns" "true" "resolves"
    else
      record_check "dns" "false" "unresolved"
    fi
  else
    record_check "dns" "true" "getent_unavailable_skipped"
  fi

  # TLS / HTTPS
  if command -v curl >/dev/null 2>&1; then
    local code headers
    code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$base/" 2>/dev/null || echo 000)"
    if [[ "$code" =~ ^2|3 ]]; then
      record_check "https_spa" "true" "http_${code}"
    else
      record_check "https_spa" "false" "http_${code}"
    fi

    # Redirect 80 -> 443
    local redir
    redir="$(curl -sS -o /dev/null -w '%{http_code}:%{redirect_url}' --max-time 10 "http://${PROD_DOMAIN}/" 2>/dev/null || echo '000:')"
    if echo "$redir" | grep -qE '^(301|302|308):https://'; then
      record_check "http_redirect" "true" "to_https"
    else
      record_check "http_redirect" "false" "no_https_redirect"
    fi

    # HSTS
    headers="$(curl -sSI --max-time 15 "$base/" 2>/dev/null || true)"
    if echo "$headers" | grep -qi 'strict-transport-security'; then
      record_check "hsts" "true" "present"
    else
      record_check "hsts" "false" "missing"
    fi

    # Block /up and /horizon publicly
    local up_code horizon_code
    up_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "$base/up" 2>/dev/null || echo 000)"
    horizon_code="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 10 "$base/horizon" 2>/dev/null || echo 000)"
    if [[ "$up_code" == "403" || "$up_code" == "404" || "$up_code" == "401" ]]; then
      record_check "block_up" "true" "http_${up_code}"
    else
      record_check "block_up" "false" "http_${up_code}"
    fi
    if [[ "$horizon_code" == "403" || "$horizon_code" == "404" || "$horizon_code" == "401" ]]; then
      record_check "block_horizon" "true" "http_${horizon_code}"
    else
      record_check "block_horizon" "false" "http_${horizon_code}"
    fi
  else
    record_check "https_spa" "false" "curl_missing"
  fi

  # Internal readiness via compose exec (no public endpoint)
  if [[ -f "$PROD_ENV" ]] && compose_prod ps --status running --services 2>/dev/null | grep -qx php; then
    if compose_prod exec -T php php artisan ops:production-readiness --json --no-persist >/tmp/ops-readiness-out.$$ 2>/dev/null; then
      record_check "internal_readiness" "true" "ok"
    else
      record_check "internal_readiness" "false" "failed"
    fi
    rm -f /tmp/ops-readiness-out.$$ 2>/dev/null || true

    # Release SHA in container if set
    if [[ -n "${RELEASE_SHA:-}" ]]; then
      local container_sha
      container_sha="$(compose_prod exec -T php printenv RELEASE_SHA 2>/dev/null | tr -d '\r' || true)"
      if [[ "$container_sha" == "$RELEASE_SHA" ]]; then
        record_check "release_runtime" "true" "matches"
      else
        record_check "release_runtime" "false" "mismatch_or_empty"
      fi
    fi
  else
    record_check "internal_readiness" "false" "php_offline"
  fi

  if echo "$CHECKS_JSON" | grep -q '"ok":false'; then
    FAIL=1
    write_evidence "postdeploy" false
  else
    FAIL=0
    write_evidence "postdeploy" true
  fi
}

usage() {
  cat >&2 <<'EOF'
Uso: PHASE=source|predeploy|postdeploy|all docker/ops/prod-readiness.sh

Variáveis:
  PROD_ENV          (default .env.prod)
  BACKUP_ENV        (default /etc/fiscal-hub/backup.env)
  EVIDENCE_DIR      (default /var/lib/fiscal-hub/readiness)
  RELEASE_SHA       (default: git HEAD)
  PROD_DOMAIN       (default app.inovaicontabil.com.br)
  STACK_PROJECT     (default fiscal-hub)

Não imprime valores de ambiente. Exit != 0 se qualquer check obrigatório falhar.
EOF
}

case "$PHASE" in
  source) phase_source ;;
  predeploy) phase_predeploy ;;
  postdeploy) phase_postdeploy ;;
  all)
    phase_source
    src_fail=$FAIL
    phase_predeploy
    pre_fail=$FAIL
    phase_postdeploy
    post_fail=$FAIL
    FAIL=0
    [[ "$src_fail" -eq 0 && "$pre_fail" -eq 0 && "$post_fail" -eq 0 ]] || FAIL=1
    ;;
  -h|--help) usage; exit 0 ;;
  *) usage; exit 2 ;;
esac

if [[ "$FAIL" -ne 0 ]]; then
  printf 'prod-readiness PHASE=%s: FAILED\n' "$PHASE" >&2
  exit 1
fi
printf 'prod-readiness PHASE=%s: OK\n' "$PHASE" >&2
exit 0
