## Context

A API já expõe `document.href` canônico (`/api/v1/fiscal/.../download`) nos hubs de guias/declarações (ex.: PGDAS-D). Em `make dev`, o Nuxt HMR (`:3000`) não faz proxy de `/api/v1/*` para a API: um `<a href="/api/v1/...">` ou `UButton :to` vira rota SPA → «Page not found».

Já existe `useAuthenticatedDownload` (change `fix-pgdasd-authenticated-pdf-download`) usado no histórico PGDAS-D. O buraco restante é o componente compartilhado `FiscalDocumentAction` (`:href` + `target=_blank`) e o botão «Documento» em `monitoring/guides.vue` (`to: doc.href`).

## Goals / Non-Goals

**Goals:**

- Download a partir de `FiscalDocumentDescriptor.href` via cliente Sanctum (blob + save), sem navegação top-level para o path da API.
- Mesmo comportamento no detalhe do cliente, SITFIS, parcelamentos (consumidores do componente) e na central de guias.

**Non-Goals:**

- Não migrar DEFIS/DCTFWeb/MEI que ainda usam `resolveApiUrl` + `:to` nesta change.
- Não alterar contrato do descriptor nem rotas Laravel.
- Não ligar SERPRO/MEI/SEFAZ.

## Decisions

1. **Centralizar no `FiscalDocumentAction`** — `@click` + `useAuthenticatedDownload`; filename derivado de `label`/`kind` ou fallback genérico. Alternativa (`resolveApiUrl` no href): ainda abre nova aba sem cookie Sanctum do ofetch → 401; rejeitada.
2. **`guides.vue`** — trocar `to: doc.href` por `onClick` que chama o mesmo composable (ou montar `FiscalDocumentAction` se couber sem redesenho). Preferência: reusar o componente quando o shape for o descriptor; senão `onClick` mínimo.
3. **Sem mudança de API** — path canônico `/api/v1/...` continua; `toSanctumApiPath` stripa `apiBase` no client.

## Risks / Trade-offs

- **[Consumidores do componente mudam de “abrir aba” para “download imediato”]** → Mitigação: alinhado ao padrão PGDAS-D já aceito; toast em erro.
- **[Filename genérico]** → Mitigação: usar `label` sanitizado ou `documento.pdf`; Content-Disposition da API prevalece se o browser respeitar no blob (hoje o composable usa o filename passado).

## Migration Plan

- Deploy web (HMR / generate).
- Rollback: reverter os dois arquivos de UI (+ teste se adicionado).

## Open Questions

- Nenhum bloqueante.
