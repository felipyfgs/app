## Why

Reservas RBT12 que falham por `RATE_LIMIT_LOCAL` (ou falha transitória de extrato) ficam `FAILED` / `EXTRACT_QUERY_FAILED` e nunca são reabertas: um MONITOR posterior trata a `source_reference_key` como já resolvida e não redispara `CONSULTAR_EXTRATO`. A carteira fica com `—` mesmo após reconsulta bem-sucedida (caso E DE A BRITO).

## What Changes

- Reabrir reservas RBT12 com falha **recuperável** no pós-MONITOR: reset para `PENDING` e redisparo de `FetchPgdasdRbt12Job`.
- Não tratar rate-limit local do extrato como terminal permanente do RBT12 (falha recuperável).
- Limitar o fan-out de extratos: no caminho automático da carteira, reservar/disparar apenas o DAS mais recente do PA esperado (evita rajada de N jobs).
- Corrigir reuso de run `CONSULTAR_EXTRATO` já `FAILED` por correlação: permitir re-enqueue em vez de retornar sem despacho.
- Testes unitários cobrindo retry e não-retry de falhas irrecuperáveis do parser.

## Capabilities

### New Capabilities

- `pgdasd-rbt12-extract-retry`: retry de reservas RBT12 com falha recuperável e fan-out controlado do extrato automático.

### Modified Capabilities

- (nenhuma em `openspec/specs/` — capability nova; a change `fix-pgdasd-rbt12-extrato-parser` cobre o parser/UI e permanece coordenada, sem alteração de contrato dela).

## Impact

- Código: `PgdasdRbt12Service`, `PgdasdMonitoringQueryService::enqueueAutomaticRbt12Extract`, eventualmente `reconcileTerminalFailure` / classificação de erros recuperáveis; testes em `apps/api/tests`.
- API/UI: sem mudança de contrato HTTP; a carteira passa a receber `PARSED` após retry bem-sucedido.
- SERPRO: pode gerar novos `CONSULTAR_EXTRATO` sob rate-limit — mitigado pelo fan-out de 1 DAS/PA esperado.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs (vazio para esta capability), archive
- Depende de: nenhuma (change ativa `fix-pgdasd-rbt12-extrato-parser` é coordenada no domínio RBT12, marco `apply`, relação `coordenada` — não bloqueia)
- Desbloqueia: correção operacional da carteira PGDAS-D para clientes com RBT12 stuck
- Paralelismo: pode seguir em paralelo com changes UI/monitoring sem ownership de `PgdasdRbt12Service`

### Non-goals

- Chamadas SERPRO live em teste; parecer jurídico; mutações fiscais; ligar flags fail-closed; canais SEFAZ; serviços mei no Compose; ops backup/restore.
- Reparse automático de `NOT_FOUND`/`AMBIGUOUS` já parseados (só falhas de consulta/extrato recuperáveis).
