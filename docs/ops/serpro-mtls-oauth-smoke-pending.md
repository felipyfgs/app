# Smoke mTLS/OAuth2 Integra Contador — PENDING_OPS

## Status

**PENDING_OPS** — smoke com certificado contratante real **não** roda em CI e ainda não foi executado neste ambiente (sem PFX de produção/homologação provisionado).

Config:

```env
SERPRO_SMOKE_ENABLED=false
SERPRO_SMOKE_STATUS=PENDING_OPS
SERPRO_USE_FAKE_CLIENTS=true
```

## Pré-requisitos

1. Evidência comercial/jurídica anexada (gate bloqueante).
2. Contrato cadastrado e ACTIVE em `HOMOLOGATION` (preferencial) ou coorte piloto.
3. PFX e-CNPJ contratante + senha em mídia controlada (não no git).
4. Consumer Key/Secret do ambiente.
5. `VAULT_MASTER_KEY` da instância de smoke.
6. Kill switch OFF; circuit breaker closed.

## Procedimento (fora de CI)

```bash
# 1. Cadastrar/ativar contrato
php artisan serpro:contract replace \
  --env=HOMOLOGATION \
  --pfx=/secure/contratante-hom.pfx \
  --consumer-key="$SERPRO_CK" \
  --consumer-secret="$SERPRO_CS"

# 2. Desligar fakes
# SERPRO_USE_FAKE_CLIENTS=false

# 3. Forçar renovação OAuth (app container)
php artisan tinker --execute="
\$c = app(\\App\\Services\\Serpro\\SerproContractService::class)->activeFor(\\App\\Enums\\SerproEnvironment::Homologation);
\$t = app(\\App\\Contracts\\SerproContractAuthenticator::class)->authenticate(\$c);
echo json_encode(\$t->toSanitizedArray());
"

# 4. Registrar evidência sanitizada (correlação, HTTP status, latência) em ticket ops
# NUNCA colar access_token, JWT, Consumer Secret, PFX ou senha
```

## Critérios de sucesso

- HTTP 200 no token endpoint
- `health_status=OK` no contrato
- Metadados sanitizados em `/api/v1/platform/serpro/health`
- Nenhum segredo em `storage/logs` nem `audit_logs.context`

## Em falha

- 401/403: bloquear contrato, kill switch, abrir incidente (runbook de rotação)
- Timeout/TLS: verificar cadeia ICP-Brasil, hostname, TLS≥1.2

## Atualização deste doc

Quando o smoke real for executado, substituir `PENDING_OPS` por `PASS`/`FAIL` com data, ambiente, correlação e operador — sem material sensível.
