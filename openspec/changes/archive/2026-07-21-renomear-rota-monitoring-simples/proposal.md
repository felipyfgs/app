## Why

O MEI foi desacoplado para `/monitoring/mei`. A superfície de PGDAS-D ainda vive em `/monitoring/simples-mei`, o que sugere um módulo conjunto que já não existe na navegação.

## What Changes

- Path canônico da carteira Simples Nacional: `/monitoring/simples` (antes `/monitoring/simples-mei`).
- Redirect legado 301/replace de `/monitoring/simples-mei` e `/monitoring/simples-mei/:submodule` para a superfície correta (`/monitoring/simples` ou `/monitoring/mei` se PGMEI).
- Atualizar nav, `FISCAL_MODULE_PATHS`, links internos e testes web.
- API `simples_mei` e pasta de componentes internos permanecem (fora do escopo de URL).

## Capabilities

### New Capabilities

- `monitoring-simples-route`: contrato da rota canônica `/monitoring/simples` e redirects legados pós-desacoplamento MEI.

### Modified Capabilities

- (nenhuma em main specs)

## Impact

- Web: `pages/monitoring/simples/**`, redirects em `simples-mei/**`, `monitoring-nav.ts`, `fiscal-modules.ts`, `monitoring-post-create.ts`, home, e2e/unit.
- API: sem mudança de path.
- Non-goals: renomear `module_key` API, pasta `components/monitoring/simples-mei`, endpoints `/api/v1/fiscal/simples-mei/*`.

### Dependências entre changes

- Nível: `C0` (coordenada com desacoplamento MEI já aplicado no código)
- Depende de: nenhuma change ativa bloqueante
- Desbloqueia: nenhuma
