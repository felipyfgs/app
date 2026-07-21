## Context

Nav já tem “Simples Nacional” e “MEI” separados. O path `/monitoring/simples-mei` ficou inconsistente com o label e com `/monitoring/mei`.

## Goals / Non-Goals

**Goals:**

- Canônico: `/monitoring/simples` → Portfolio PGDASD.
- Legado: `/monitoring/simples-mei` e `/monitoring/simples-mei/:submodule` redirecionam.
- PGMEI em path legado continua indo para `/monitoring/mei`.

**Non-Goals:**

- Renomear API `simples_mei` / componentes Vue.
- Alterar DCTFWeb paths.

## Decisions

1. **Slug `simples`** — curto, alinhado ao label “Simples Nacional”; evita `simples-nacional` longo.
2. **Páginas novas + redirect nas antigas** — `pages/monitoring/simples/index.vue` canônica; `simples-mei/index.vue` vira redirect; `[submodule].vue` legado atualiza middleware/comentários.
3. **`monitoringModuleBasePath('simples_mei')` → `/monitoring/simples`**.
4. **Nav id** pode permanecer `monitoring-simples-mei` (estável) ou virar `monitoring-simples` — preferir `monitoring-simples` para clareza, com pathPrefix novo.

## Risks / Trade-offs

- [Bookmarks antigos] → Mitigação: redirect replace nas rotas legadas.
- [Testes/e2e hardcode] → Atualizar na mesma change.

## Migration Plan

- Deploy web; redirects cobrem URLs antigas. Rollback: reverter paths.
