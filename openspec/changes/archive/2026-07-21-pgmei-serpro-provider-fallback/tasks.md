## 1. N0 — Transporte classificado e testes do client

- [x] 1.1 Em `MeiAutomationClient`, capturar `ConnectionException` em `sendJson` e `downloadArtifact` e relançar `MeiAutomationTransportException('AUTOMATION_TRANSPORT_ERROR', 0)`
- [x] 1.2 Estender `MeiAutomationClientTest` para assertar wrap de falha de conexão → `MeiAutomationTransportException`
  - Evidência: `php artisan test --filter=MeiAutomationClientTest`

## 2. N1 — Fechamento da run no job

- [x] 2.1 Expor API pública em `FiscalMonitoringRunService` para marcar falha não tratada do job (`JOB_UNHANDLED_EXCEPTION`) se a run não for terminal
- [x] 2.2 Em `ExecuteFiscalMonitoringRunJob::failed()`, chamar a API de 2.1 e manter `PgdasdRbt12Service::reconcileTerminalFailure`
  - Depende de: 2.1
- [x] 2.3 Teste cobrindo `failed()` com run PGMEI não-terminal → status `FAILED` + código esperado
  - Depende de: 2.2
  - Evidência: `php artisan test --filter=ExecuteFiscalMonitoringRunJobFailedTest`

## 3. N1 — Defaults e alinhamento local

- [x] 3.1 Confirmar `.env.example` com providers PGMEI/DASN em `serpro`
- [x] 3.2 Alinhar `apps/api/.env` local (`MEI_AUTOMATION_PROVIDER_PGMEI_DEBT` e `DASN_HISTORY` → `serpro`) sem versionar
  - Depende de: 3.1

## 4. N2 — Gates integrados

- [x] 4.1 `vendor/bin/pint --test` nos arquivos PHP tocados e `php artisan test` filtrado (client + router/fallback se tocado + job)
  - Depende de: 1.2, 2.3
- [x] 4.2 `npx @fission-ai/openspec@1.6.0 validate pgmei-serpro-provider-fallback --type change --strict`
  - Depende de: 1.1, 2.2
- [x] 4.3 Reconsultar PGMEI do cliente 14 em dev (sem `mei:8080`; run terminal)
  - Depende de: 2.2, 3.2
  - Evidência: run #61/#62 processadas sem DNS `mei`; status terminal (ex.: BLOCKED/AUTHORIZATION_MISSING via SERPRO); providers runtime `serpro` após recreate php/horizon
