## Context

Consultas PGMEI de monitoramento/manual passam por `ExecuteFiscalMonitoringRunJob` → adaptadores Simples/MEI. Quando a automação MEI está habilitada, `MeiProviderPolicy` pode ordenar `[ReceitaPortal, Serpro]` (`portal_then_serpro`). O portal chama o sidecar via `MeiAutomationClient` (`MEI_AUTOMATION_URL`, default `http://mei:8080`). O serviço `mei` **não** existe no Compose (código em `services/mei` apenas).

Hoje, HTTP ≠ 2xx vira `MeiAutomationTransportException` e o portal devolve `PORTAL_UNAVAILABLE` (fallback). DNS/conexão (`Illuminate\Http\Client\ConnectionException`) sobe crua → job FAIL e run `#59` permanece `RUNNING` porque `failed()` só chama `PgdasdRbt12Service::reconcileTerminalFailure` (no-op fora de `CONSULTAR_EXTRATO`).

SERPRO Integra Contador já cobre `PGMEI`/`DIVIDAATIVA24` (power-matrix + doc oficial). Defaults em `.env.example` já são `serpro`; o `.env` local com `portal_then_serpro` é desvio.

Áreas: `apps/api` (Horizon/queue). Sem mudança em `apps/web` nem Compose.

## Goals / Non-Goals

**Goals:**

- Indisponibilidade de rede/DNS do sidecar classificada → fallback SERPRO em modo `portal_then_serpro`.
- SERPRO canônico para PGMEI dívida ativa nos defaults de produto.
- Job que falha com run não-terminal marca `FAILED` com código sanitizado.
- Testes cobrem client, fallback e `failed()` do job.

**Non-Goals:**

- Sidecar no Compose; UI PARCMEI-ESP; flags ON por default; mutações fiscais novas.

## Decisions

1. **Wrap no `MeiAutomationClient`, não só no provider**  
   `sendJson` e `downloadArtifact` capturam `ConnectionException` (e falhas de conexão equivalentes do HTTP client) e relançam `MeiAutomationTransportException('AUTOMATION_TRANSPORT_ERROR', 0)`.  
   *Alternativa descartada:* catch genérico de `Throwable` no provider — mascararia bugs de validação.

2. **Reusar `PORTAL_UNAVAILABLE` no provider**  
   O `catch (MeiAutomationTransportException)` em `ReceitaPortalProvider` já emite outcome elegível a fallback; sem mudar a lista `FALLBACK_REASONS` do router.

3. **API pública em `FiscalMonitoringRunService` para falha de job**  
   Expor método fino (ex.: `failUnhandledJob`) que, se a run não for terminal, chama a lógica de `markFailed` com `JOB_UNHANDLED_EXCEPTION` e mensagem sanitizada. `ExecuteFiscalMonitoringRunJob::failed()` usa isso + mantém reconcile RBT12.

4. **Defaults**  
   Manter `.env.example` em `serpro`. Alinhar `.env` local no apply (não versionar). Documentar: `portal_then_serpro` exige sidecar alcançável; sem Compose `mei`, o caminho local esperado é SERPRO.

## Risks / Trade-offs

- [Fallback SERPRO gera bilhetagem quando o portal cai] → aceitável: modo explícito `portal_then_serpro`; default produto é `serpro`.
- [Status HTTP 0 em exception] → só sinaliza ausência de resposta; logs usam `errorCode`.
- [Corrida: run já terminal quando `failed()` roda] → no-op se `isTerminal()`.
- [Mutações MEI no mesmo client] → mesmo wrap beneficia `MeiPortalFiscalMutationTransport` sem mudança extra se já captura `MeiAutomationTransportException`.

## Migration Plan

1. Deploy API; reiniciar Horizon se necessário.
2. Runs `RUNNING` órfãs pré-fix: marcar manualmente ou reconsultar.
3. Rollback: reverter commits; comportamento antigo (sem fallback DNS) volta.

## Open Questions

- Nenhuma bloqueante.
