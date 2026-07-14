# Frontend — NFS-e ADN

SPA Nuxt 4 + Nuxt UI 4, baseada no template MIT [`nuxt-ui-templates/dashboard`](https://github.com/nuxt-ui-templates/dashboard) (commit `0f30c09`).

## Stack

- Nuxt 4 SPA (`ssr: false`, `nitro.preset: static`)
- Nuxt UI 4 (dashboard layout)
- `nuxt-auth-sanctum` (cookie session same-origin)
- Sem mocks `server/api` — só API Laravel

## Desenvolvimento

```bash
make dev
```

Abra `http://localhost:3000`. O volume `./frontend:/app` habilita HMR, e o proxy do `nuxt-auth-sanctum` encaminha API, CSRF e login ao Laravel sem CORS.

## Produção

```bash
pnpm generate
```

Artefatos em `.output/public`, servidos pelo Nginx no monorepo.
