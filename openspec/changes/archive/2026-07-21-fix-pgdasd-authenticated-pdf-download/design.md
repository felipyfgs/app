## Context

`resolveApiUrl` prefixa `/api/sanctum` para `<a href>` no HMR. Em nova aba o browser chama o proxy sem o fluxo do `useSanctumClient()` (Origin/credentials do ofetch), e o Laravel devolve 401 Unauthenticated. O padrão já usado em `docs/DetailModal.vue` (blob via Sanctum) resolve isso.

## Goals / Non-Goals

**Goals:**

- Baixar PDF/artefato PGDAS-D autenticado na mesma sessão da SPA.
- Feedback de erro amigável (toast) se 401/404/corpo JSON.
- API path canônico para o client (`/api/v1/...`).

**Non-Goals:**

- Unificar todos os downloads do monorepo.
- Tokens signed/temporary URLs.

## Decisions

1. **Composable `useAuthenticatedDownload`** — strip de `apiBase`, GET Sanctum blob, detecção de JSON de erro, `URL.createObjectURL` + click.
2. **Botões PGDAS-D** — `@click` em vez de `:to`/`external`/`target=_blank`.
3. **Filename** — derivado do kind/id no caller (Content-Disposition opcional depois).

## Risks / Trade-offs

- **[Memória blob]** Mitigação: `revokeObjectURL` imediato; PDFs de monitoramento são pequenos.
- **[Superfícies irmãs ainda quebradas]** Mitigação: change focada; mesmo composable reutilizável depois.

## Migration Plan

- Deploy web (HMR / generate).
- Rollback: reverter componentes + composable.
