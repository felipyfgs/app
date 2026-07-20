## Why

A consulta manual PGMEI (dívida ativa) aceita o pedido e enfileira o job, mas com `portal_then_serpro` o sidecar `mei` (fora do Compose) falha por DNS/`ConnectionException` sem classificação — o fallback SERPRO (`DIVIDAATIVA24`) nunca roda e a run fica presa em `RUNNING`.

## What Changes

- Classificar falha de rede/DNS do cliente HTTP do sidecar MEI como `MeiAutomationTransportException`, para o provider portal emitir `PORTAL_UNAVAILABLE` elegível a fallback SERPRO.
- Manter SERPRO (Integra Contador / `PGMEI`+`DIVIDAATIVA24`) como caminho canônico; defaults de `.env.example` permanecem `serpro`.
- Em falha inesperada do `ExecuteFiscalMonitoringRunJob`, marcar a `FiscalMonitoringRun` não-terminal como `FAILED` (hoje só reconcilia PGDASD RBT12).
- Testes unitários/feature cobrindo transporte, fallback e fechamento da run.

## Capabilities

### New Capabilities

- `mei-provider-fallback`: roteamento de operações MEI/PGMEI entre sidecar (Receita portal) e SERPRO, com fallback classificado quando o sidecar está inalcançável, e fechamento terminal da run de monitoramento em falha não tratada do job.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio)

## Impact

- API: `MeiAutomationClient`, `ReceitaPortalProvider` / `MeiProviderRouter` (comportamento já existente de fallback), `ExecuteFiscalMonitoringRunJob`, `FiscalMonitoringRunService` (API pública para marcar falha).
- Env local: alinhar `MEI_AUTOMATION_PROVIDER_PGMEI_DEBT` / `DASN_HISTORY` a `serpro` (não versionar `.env`).
- Sem mudança de contrato HTTP público das rotas `pgmei/consult`; sem Compose `mei`.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias; archive fora do DAG
- Depende de: nenhuma
- Capability/contrato: `mei-provider-fallback` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma change ativa
- Paralelismo: independente das changes ativas de UI/ops

### Non-goals

- Adicionar serviços `mei` / `mei-worker` ao Compose
- Refatorar PARCMEI-ESP / UI de parcelamento
- Ligar kill switches SERPRO/MEI ou flags mutantes
- Parecer jurídico / bilhetagem SERPRO além do caminho de consulta já contratado
- Targets Make de backup/restore/ops indisponíveis
