## Why

Review Bugbot mostrou que o envio automático pós-consulta PGDAS-D falha em silêncio: a `idempotency_key` com timestamp `YmdHisu` ultrapassa o limite de 64 caracteres da coluna, o insert quebra e a exceção é engolida. Além disso, cada run agendada pode criar novos `QUEUED` para o mesmo cliente/canal/período, e o sort `rbt12` da carteira pode ordenar por um valor diferente do RBT12 exibido na linha.

## What Changes

- Compactar e estabilizar `idempotency_key` de `client_communication_dispatches` para caber em 64 chars.
- Deduplicar envio automático por `period_key` + canal (não reenfileirar o mesmo período).
- Manter envio manual reenviável com chave única curta (sem estourar o limite).
- Alinhar a subquery de sort `rbt12` à precedência de valor exibido em `portfolioDetails` (período de display da declaração, não só PA esperado).
- Testes Feature cobrindo chave ≤64, dedupe automático e sort vs display.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `monitoring-communication-send-guards`: idempotência ≤64 e dedupe automático por período/canal.
- `monitoring-guides-portfolio-consistency`: sort `rbt12` SHALL usar o mesmo critério do valor exibido (período de display da declaração).

## Impact

- API: `PgdasdCommunicationService::queueDispatches` / `maybeQueueAutomaticAfterConsult`; `ModulePortfolioQueryService::pgdasdRbt12SortSubquery`.
- Specs: deltas nas duas capabilities acima; testes em `MonitoringCommunicationSendTest` e `ModulePortfolioSimplesMeiSubmoduleTest`.
- Sem migração de schema; provider externo permanece fail-closed.

### Non-goals

- Worker/provider de egress real; redesign da UI; flags SERPRO/MEI ON; mutações fiscais; mei no Compose; ops backup/restore.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: `openspec/specs/monitoring-communication-send-guards`, `openspec/specs/monitoring-guides-portfolio-consistency`
- Depende de: nenhuma
- Capability/contrato: deltas das duas capabilities modificadas
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: implementação imediata do fix Bugbot
- Paralelismo: pode rodar em paralelo com changes que não editem os mesmos métodos de comunicação/sort RBT12
