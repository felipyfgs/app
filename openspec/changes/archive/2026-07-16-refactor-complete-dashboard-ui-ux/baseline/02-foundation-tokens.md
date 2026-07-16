# 2.1 / 2.2 Fundação visual — comparação com template `0f30c09`

## Arquivos

| Produto | Template | Divergência autorizada |
|---------|----------|------------------------|
| `app/app.vue` | `app/app.vue` | `pt_br` locale; meta PWA/viewport-fit; título produto; sem ogImage de marketing do template |
| `app/app.config.ts` | `app/app.config.ts` | Mesmas cores `primary: green`, `neutral: zinc`; `alert` default subtle (domínio: dark mode legível) |
| `app/assets/css/main.css` | idêntico | Public Sans + escala green estática do template — **sem paleta paralela** |
| `nuxt.config.ts` | simplificado no template | SPA `ssr: false`, Sanctum, PWA, Nitro static, HMR/polling Docker — domínio/ops |

## Tokens semânticos (canônicos)

- **Cor primária / neutra:** somente via `app.config.ts` → Nuxt UI (`primary`, `neutral`).
- **Semântica de status:** `error` / `warning` / `success` / `info` / `neutral` dos componentes `U*` — nunca hex raw em páginas.
- **Tipografia:** `--font-sans: Public Sans` em `main.css`.
- **Superfícies:** `bg-elevated`, `bg-default`, `text-highlighted`, `text-muted`, `text-dimmed`, `ring-default`, `border-default`.
- **Densidade tabular:** só `DASHBOARD_TABLE_UI` | `DENSE_DASHBOARD_TABLE_UI` | `COMPACT_DASHBOARD_TABLE_UI`.

## Proibido

- Segunda paleta de marca, cores Tailwind arbitrárias em páginas de dados, wrapper visual paralelo ao Nuxt UI.
