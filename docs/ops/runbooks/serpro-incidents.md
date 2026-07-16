# Runbooks SERPRO / Integra Contador

Todos os passos usam respostas sanitizadas. Nunca cole Key/Secret/PFX/token/XML em issue, chat ou log.

## 1. Consumer Key/Secret ou PFX comprometido

1. `POST /api/v1/platform/serpro/kill-switch` com `active=true` e motivo (imediato).
2. Marcar versão exposta: CLI `serpro:credential-version mark-exposed` (ou fluxo de contenção).
3. Invalidar caches/tokens (`token` vault refs limpas no cutover).
4. Registrar nova versão `PENDING` via vault (arquivo + prompt; sem argv de segredo).
5. Verificar (`verify-pending`), obter 2× `PLATFORM_ADMIN` approvals CUTOVER, cutover com OAuth prévio.
6. Confirmar `serpro:readiness` / `GET /api/v1/platform/serpro/readiness`.
7. Remover cópias transitórias do host (ver `docs/ops/serpro-transient-secret-removal.md`).
8. Auditoria: `audit:verify-chain`.

## 2. Certificado (PFX) próximo do vencimento

1. Alertas do `serpro:lifecycle-scan` (90/60/30/15/7/1 dias).
2. Importar novo PFX como versão pendente; **não** assinar Termo automaticamente.
3. Cutover com quatro olhos antes do horizonte mínimo (`SERPRO_CONTRACTOR_PFX_MIN_HORIZON_DAYS`).

## 3. Termo rejeitado / validação falhou

1. Consultar `GET /api/v1/serpro/authorization` (tenant) — status sanitizado.
2. Não reenviar em loop; corrigir layout/assinatura (XMLDSig) no fluxo draft→upload.
3. Invalidar token/ETag derivados; novo aceite só após validação.

## 4. HTTP 401 (OAuth/gateway)

1. Verificar kill switch e health: `GET /api/v1/platform/serpro/health`.
2. Confirmar versão ACTIVE, fingerprint e validade do e-CNPJ.
3. Retry único de OAuth (comportamento do autenticador); se persistir, rotacionar credencial.
4. Não habilitar fake clients em produção.

## 5. HTTP 403 bilhetável

1. Tratar como possível cobrança: ledger `POSSIBLY_BILLABLE` / classe faturável.
2. Verificar Termo aceito, poder do contribuinte e cadeia autor→cliente.
3. **Não** abrir circuit breaker global só por 403 de um office.
4. Corrigir representação; reconciliar consumo depois.

## 6. HTTP 429

1. Respeitar `Retry-After` / janela; sem retry agressivo.
2. Revisar limiter versionado (`SERPRO_RATE_LIMIT_*`) e quotas oficiais (ex.: Eventos 1.000 PF/PJ/dia).
3. Isolar office ruidoso via budget/allowlist se necessário.

## 7. HTTP 5xx / indisponibilidade SERPRO

1. Breaker por dependência/solução; half-open com probes limitados.
2. Jobs: backoff; preservar cursor/reserva.
3. Alerta referencia este runbook; labels sem PII.
4. Reset breaker só com `PLATFORM_ADMIN` + motivo: `POST .../breaker/reset`.

## 8. Custo anômalo / estouro de orçamento

1. Conferir budgets: `GET /api/v1/platform/serpro/budgets`.
2. Kill switch se necessário.
3. Conciliação oficial via rotas platform de usage; divergências abrem incidente sem correção destrutiva.
4. Tenant só vê o próprio consumo: `GET /api/v1/serpro/usage`.

## 9. Procuração revogada

1. Sync de poderes encerra ausentes; status `REVOKED`.
2. Eligibility falha fechado para o par office/cliente/serviço.
3. Reimportar/verificar evidência válida antes de reativar.

## 10. Suspeita de cross-tenant

1. Kill switch global.
2. Auditar `correlation_id` / request tags (opacos).
3. Confirmar que controllers usam `CurrentOffice` e que `office_id` do body foi stripped.
4. Preservar evidência; não “consertar” ledger apagando linhas.

## 11. Vault / master key loss

1. **Não** tentar recriar chave aleatória sobre o mesmo vault.
2. Restaurar backup cifrado com `BACKUP_PACKAGE_KEY` + `VAULT_MASTER_KEY` corretos (`docker/ops/restore-smoke.sh`).
3. Rewrap só com keyring versionado e dry-run auditado.
4. Se chave irrecuperável: incident response + reimport controlado de material.

## 12. Outage da plataforma / Redis flush

1. Kill switch e approvals são **duráveis no Postgres** (`serpro_runtime_controls`, `serpro_rollout_approvals`).
2. Após flush Redis: `hydrateCacheFromDurable` / próximo `isGlobalActive()` relê o DB — **não reabre** segurança.
3. Confirmar: `GET /api/v1/platform/serpro/kill-switch` e `audit:verify-chain`.

## 13. Integridade de auditoria quebrada

Ver `docs/ops/runbooks/serpro-audit-integrity.md`.
