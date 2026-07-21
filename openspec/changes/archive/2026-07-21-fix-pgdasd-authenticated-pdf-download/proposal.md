## Why

Em `make dev` (proxy Sanctum em `/api/sanctum`), botões de download PGDAS-D abrem o path da API numa nova aba (`:to` + `external`). A navegação top-level não autentica como o cliente Sanctum da SPA e a API responde `{"message":"Unauthenticated."}` — o PDF não baixa.

## What Changes

- Introduzir download autenticado via cliente Sanctum (`responseType: blob`) + save local.
- Trocar links externos dos downloads PGDAS-D (histórico, DAS, declarações, comunicação) por ação autenticada.
- Manter paths canônicos `/api/v1/...` no cliente (sem depender de navegação ao proxy).

## Capabilities

### New Capabilities

- `fiscal-authenticated-artifact-download`: download de artefatos fiscais na SPA autenticado pelo cliente Sanctum (cookie/proxy), sem navegação top-level unauthenticated.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio)

## Impact

- Web: composable de download; `PgdasdHistoryView`, `PgdasdDasHistoryModal`, `PgdasdDeclarationsHistoryModal`, `PgdasdCommunicationModals`.
- Sem mudança de contrato HTTP da API.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias
- Depende de: nenhuma
- Capability/contrato: `fiscal-authenticated-artifact-download` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma
- Paralelismo: independente de `fix-fiscal-snapshots-platform-privileged-read`

### Non-goals

- Não refatorar todos os downloads do painel (DEFIS/DCTFWeb/MEI) nesta change — só superfície PGDAS-D reportada.
- Não mudar auth da API / rotas Laravel.
- Não ligar flags SERPRO/MEI.
- mei no Compose / ops backup-restore.
