## Why

Em `make dev`, clicar «Baixar DAS» / «Ver declaração» no detalhe do cliente (e «Documento» na central de guias) navega para `/api/v1/fiscal/simples-mei/pgdasd/artifacts/{id}/download` no Nuxt (`:3000`). O Vue Router trata o path da API como página e mostra «Page not found». A change `fix-pgdasd-authenticated-pdf-download` já corrigiu o histórico PGDAS-D, mas o componente compartilhado `FiscalDocumentAction` e a central de guias ainda usam `:href`/`:to` top-level.

## What Changes

- Trocar `FiscalDocumentAction` de navegação/`target=_blank` para download autenticado via `useAuthenticatedDownload` (mesmo padrão do histórico PGDAS-D).
- Corrigir o botão «Documento» em `monitoring/guides.vue` que usa `to: doc.href`.
- Reutilizar o composable existente; sem mudança de contrato HTTP da API.

## Capabilities

### New Capabilities

- `fiscal-document-descriptor-download`: download autenticado a partir do descriptor público `document.href` (hub cliente e central de guias), sem tratar o path da API como rota SPA.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio; a capability irmã `fiscal-authenticated-artifact-download` ainda não está nas main specs)

## Impact

- Web: `FiscalDocumentAction.vue`, `pages/monitoring/guides.vue`; reuso de `useAuthenticatedDownload`.
- Superfícies que já montam o componente (detalhe do cliente, SITFIS, parcelamentos) passam a baixar via Sanctum.
- Sem mudança de rotas Laravel nem de shape do descriptor.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias; composable já entregue em `fix-pgdasd-authenticated-pdf-download` (código no tree)
- Depende de: nenhuma (change ativa)
- Capability/contrato: `fiscal-document-descriptor-download` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma
- Paralelismo: independente de changes de layout/PGDAS em andamento (não toca ownership delas)

### Non-goals

- Não unificar DEFIS/DCTFWeb/MEI que ainda usam `resolveApiUrl` + `:to` nesta change.
- Não alterar auth da API / rotas de download.
- Não ligar flags SERPRO/MEI nem ops backup/restore.
