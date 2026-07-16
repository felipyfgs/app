# Runbook — smoke TLS / OAuth mTLS (sem rota de negócio)

**Objetivo:** evidência `TLS_OK` e `OAUTH_OK` no endpoint oficial, sem Consultar/Emitir/Declarar.

## Controles de segurança

| Controle | Valor |
|----------|--------|
| Default | `SERPRO_SMOKE_ENABLED=false` |
| Live | exige `SERPRO_SMOKE_ENABLED=true` **e** `--confirm=I_UNDERSTAND_LIVE_SERPRO` |
| CI | **hard block** se `CI` / `GITHUB_ACTIONS` / `GITLAB_CI` |
| Rotas proibidas | `/Consultar`, `/Emitir`, `/Declarar` |
| Saída | só metadados sanitizados (sem token/PFX/secret) |

## 12.4 — TLS / cadeia

```bash
export SERPRO_SMOKE_ENABLED=true   # somente na janela ops; não commitar
php artisan serpro:smoke tls \
  --serpro-env=PRODUCTION \
  --confirm=I_UNDERSTAND_LIVE_SERPRO \
  --record-readiness \
  --json
php artisan serpro:readiness --serpro-env=PRODUCTION --json
# Desligar ao terminar
export SERPRO_SMOKE_ENABLED=false
```

Registre no template `evidence/12-tls-oauth-smoke.md`: host, ok/fail, latency, prefixo fingerprint peer, **sem** certificado completo.

## 12.5 — OAuth mTLS

Pré-requisito: versão de credencial ACTIVE pós-cutover (12.1–12.2).

```bash
export SERPRO_SMOKE_ENABLED=true
php artisan serpro:smoke oauth \
  --serpro-env=PRODUCTION \
  --contract-id=<CONTRACT_ID> \
  --confirm=I_UNDERSTAND_LIVE_SERPRO \
  --record-readiness \
  --json
export SERPRO_SMOKE_ENABLED=false
```

Valida: `has_access_token`, `has_jwt_token`, `expires_at` (sem imprimir tokens).

## Status offline (sempre seguro)

```bash
php artisan serpro:smoke status --json
php artisan serpro:smoke checklist --json
```

## Falhas comuns

- `SERPRO_SMOKE_ENABLED=false` → esperado fora da janela.
- CI detectado → nunca forçar live.
- OAuth 401/403 → não repetir cegamente; revisar cutover/fingerprint; kill switch se suspeita de abuso.
