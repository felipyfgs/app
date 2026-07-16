# Frontend — painel do hub fiscal

SPA Nuxt 4 + Nuxt UI 4, baseada no template MIT [`nuxt-ui-templates/dashboard`](https://github.com/nuxt-ui-templates/dashboard) (commit `0f30c09`).

## Stack

- Nuxt 4 SPA (`ssr: false`, `nitro.preset: static`)
- Nuxt UI 4 (dashboard layout)
- `nuxt-auth-sanctum` (cookie session same-origin)
- Sem mocks `server/api` — só API Laravel

## Skills do monorepo

Não remover. Orquestração e arquétipo:

- `.grok/skills/frontend-nuxt-stack` + `.grok/skills/nuxt-dashboard-template`
- Espelhos em `.agents/`, `.codex/` e `.opencode/` (multi-tool)

## Rotas principais

| Rota | Padrão |
|------|--------|
| `/` | Dashboard operacional |
| `/clients` | Lista + detalhe aninhado (cadastro, estabelecimentos, certificado, sync, saídas) |
| `/docs` | Catálogo de documentos (URLs legadas `/notes` redirecionam) |
| `/docs/imports` | Import em massa |
| `/monitoring/*` | Hub fiscal (SITFIS, Simples/MEI, DCTFWeb, mailbox, guias, …) |
| `/syncs` | Saúde de canais DistDFe / autXML / CT-e |
| `/settings` | Integra Contador, CT-e autXML, procurações, uso, assinatura |
| `/admin` | Administração de escritório (ADMIN + 2FA) |
| `/exports`, `/health`, `/closing` | Exportações, saúde, fechamento |

### Hierarquia de ações

1. **Navbar** — título, collapse da sidebar, no máximo uma ação primária  
2. **Compactas** — botões ghost com tooltip e `aria-label`  
3. **Toolbar** — subnavegação (detalhe de cliente / settings)  
4. **Faixa utilitária** — busca/filtros de tabela  
5. **Linha** — ações secundárias  

### Tabelas

Preset em `app.config.ts` / `utils/table-ui.ts`. Paginação server-side ou cursor conforme a API.

### Permissões e tenancy

- Papéis `ADMIN` / `OPERATOR` / `VIEWER` no escritório ativo  
- `PLATFORM_ADMIN` é fluxo separado  
- Troca de escritório só entre memberships válidas (`OfficeIdentity`)

## Desenvolvimento

Preferir a stack do monorepo (`make dev`). No host:

```bash
corepack enable
pnpm install --frozen-lockfile
pnpm run dev
```

Testes: `pnpm test` (unit), `pnpm test:e2e` (Playwright).

Ver também [`../README.md`](../README.md) e [`../AGENTS.md`](../AGENTS.md).
