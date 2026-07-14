# Frontend — NFS-e ADN

SPA Nuxt 4 + Nuxt UI 4, baseada no template MIT [`nuxt-ui-templates/dashboard`](https://github.com/nuxt-ui-templates/dashboard) (commit `0f30c09`).

## Stack

- Nuxt 4 SPA (`ssr: false`, `nitro.preset: static`)
- Nuxt UI 4 (dashboard layout)
- `nuxt-auth-sanctum` (cookie session same-origin)
- Sem mocks `server/api` — só API Laravel

## Padrões de tela

| Rota | Padrão |
|------|--------|
| `/` | Dashboard analítico (`UPageGrid` + indicadores reais) |
| `/clients` | Lista administrativa (tabela server-side + modal `UForm`) |
| `/clients/:id` (+ `/cadastro`, `/estabelecimentos`, `/certificado`, `/sincronizacao`) | Settings com rotas aninhadas (`NuxtPage`), como o template |
| `/notes` e `/notes/:accessKey` | Mestre–detalhe (painel redimensionável no desktop; slideover no mobile) |
| `/exports` | Lista administrativa + modal de solicitação |
| `/syncs` | Lista administrativa + detalhe em slideover |
| `/admin` | Settings (conteúdo restrito a `ADMIN` com 2FA) |

### Hierarquia de ações

1. **Navbar** — título, collapse da sidebar, no máximo uma ação primária
2. **Compactas** — botões ghost com tooltip e `aria-label`
3. **Toolbar** — subnavegação (detalhe de cliente)
4. **Faixa utilitária no corpo** — busca/filtros de tabela
5. **Linha** — ações secundárias no fim

### Tabelas

Preset visual compartilhado em `app.config.ts` / `utils/table-ui.ts` (cabeçalho elevado, bordas, cantos). Paginação:

- Clientes: offset numerado (`page` na URL)
- Notas e Sincronizações: cursor (`cursor` opcional na URL de Notas, com filtros)

### Permissões

Sidebar, command palette e atalhos derivam de `utils/navigation.ts` + `utils/permissions.ts` (`ADMIN` / `OPERATOR` / `VIEWER`).

## Desenvolvimento

```bash
make dev
```

Abra `http://localhost:3000`. O volume `./frontend:/app` habilita HMR, e o proxy do `nuxt-auth-sanctum` encaminha API, CSRF e login ao Laravel sem CORS.

## Validação

```bash
# Dentro do container frontend-dev (recomendado) ou com node_modules locais:
pnpm lint
pnpm typecheck
pnpm test
pnpm test:e2e   # requer app em PLAYWRIGHT_BASE_URL (default http://127.0.0.1:3000)
```

Viewports de referência: **1440×900**, **390×844** e inspeção manual a **360 px** (sem rolagem horizontal do documento).

## Produção

```bash
pnpm generate
```

Artefatos em `.output/public`, servidos pelo Nginx no monorepo.

## Segurança na UI

- Nunca renderizar PFX, senha, chave privada, PEM, XML fiscal bruto ou resposta ADN não sanitizada
- Escritório ativo vem da sessão (sem seletor arbitrário de “team”)
- Download de XML é ação explícita auditada
