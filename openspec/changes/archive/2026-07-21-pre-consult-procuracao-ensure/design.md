## Context

Hoje o plano de sync de procurações (`ClientProcuracaoSyncService`, `SyncClientProcuracaoJob`, `HttpIntegraProcuracoesClient`) está desacoplado do plano de consulta (`SimplesMeiAdapter` → `IntegraEligibilityService` → `SerproOperationService`). Consultas leem só `tax_proxy_powers` / snapshots. Troca de A1 revoga poderes (`INVALIDATED:a1_*`) sem re-sync por cliente. Manual consult em Production só enfileira refresh e devolve `power_refreshing` — não espera nem cobre monitoring/SimplesMei.

Stakeholders: contador (consultas Simples/MEI); plataforma (bilhetagem `procuracoes.obter`).

## Goals / Non-Goals

**Goals:**

- Antes de consulta que exige poder e-CAC: ensure local → sync Integra se necessário → revalidar → consultar.
- Cobrir o caminho de runs fiscais Simples/MEI (PGDASD e irmãos no adapter), não só manual Production.
- Após invalidação de A1/autor, snapshots deixam de parecer autorizados até novo sync.

**Non-Goals:**

- Ligar `SERPRO_PROCURACOES_SCHEDULER_ENABLED` por default.
- Importação manual de poderes / UI de override.
- Mutações fiscais; mei no Compose; abrir kill switches.
- Esperar indefinidamente Horizon se o sync remoto travar (timeout curto + fail-closed).

## Decisions

1. **Serviço único `EnsureClientProcuracaoForConsult` (apps/api Domain/Services/Integra)**  
   - Entrada: office, client, environment, lista de poderes exigidos (códigos oficiais).  
   - Se `findUsablePower` já ok para algum poder exigido (ANY-of) → no-op.  
   - Senão → `ClientProcuracaoSyncService::syncOfficial()` **síncrono** (reuso do caminho que já chama `TaxProxyPowerService::syncFromApi` → `procuracoes.obter`).  
   - Reavalia `findUsablePower`; se ainda falhar → retorna código (`PROXY_POWER_MISSING` / stale / etc.) sem chamar a consulta alvo.  
   - **Alternativa rejeitada:** só enfileirar job + `power_refreshing` (já existe no manual e não resolve o run de monitoring nem a expectativa “antes da consulta”).

2. **Ponto de inserção**  
   - Primário: `SimplesMeiAdapter::execute()` imediatamente **antes** de `IntegraEligibilityService::evaluate()`.  
   - Secundário: alinhar `ManualConsultExecutionService` para chamar o mesmo ensure (evitar dois comportamentos).  
   - Não duplicar dentro de `HttpIntegraContadorClient` (tarde demais / bilhetagem parcial).

3. **Trial/Dev**  
   - Mesmo ensure (não pular Trial). Em `FISCAL_PROFILE=dev`, se o driver de procurações for fixture/disabled, o sync SHALL falhar de forma explícita **ou** usar fixture documentado de poderes — sem inventar ACTIVE silencioso sem evidência. Preferência: fixture de sync em Dev alinhado ao token fixture do office, se o client Integra já for disabled.

4. **Pós `invalidateDerivedAuthorization`**  
   - Além de revogar `TaxProxyPower`, marcar `ClientProcuracaoSnapshot` do office/ambiente como `unverified` (ou equivalente) para não mascarar o ensure.  
   - Não disparar N jobs síncronos no upload do A1; o ensure na próxima consulta cobre o cliente sob demanda.

5. **Idempotência / custo SERPRO**  
   - Sync só quando local não usável.  
   - `ShouldBeUnique` / lock já existentes no job; no sync síncrono usar o mesmo lock de `syncOfficial` para evitar corrida com Horizon.

## Risks / Trade-offs

- **[Latência]** Sync síncrono adiciona tempo à consulta → Mitigação: só quando necessário; timeout/fail-closed; cache de freshness existente.
- **[Bilhetagem]** `procuracoes.obter` conta no contrato → Mitigação: não sync se poder já usável; auditar `office.procuracao.ensure`.
- **[Vazamento tenant]** Sync sempre scoped a `CurrentOffice` / office do client → Mitigação: assert `client.office_id === office.id` (já no eligibility).
- **[Snapshot mentiroso pós-A1]** → Mitigação: invalidar snapshot no invalidate derived.
- **[Dev sem Integra real]** → Mitigação: decisão 3 (fixture explícito ou erro claro).

## Migration Plan

- Deploy API only; sem migration de schema obrigatória (reuso tabelas).
- Rollback: remover chamada do ensure no adapter (comportamento anterior: bloqueia sem sync).
- Após deploy: consultas que falhavam com `PROXY_POWER_MISSING` por poder revogado passam a tentar sync antes.

## Open Questions

- Timeout máximo aceitável do sync síncrono em Production (sugestão: alinhar ao timeout HTTP Integra já configurado).
- Se sync remoto retornar “sem poderes”, UX: manter bloqueio `PROXY_POWER_MISSING` (esperado — cliente sem outorga real no e-CAC).
