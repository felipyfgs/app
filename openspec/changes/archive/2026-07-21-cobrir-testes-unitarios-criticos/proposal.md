## Why

O inventário canônico (2026-07-20) mostra **444 rotas HTTP** e **94 páginas Nuxt**, enquanto a suíte atual (~51 PHPUnit + ~27 Vitest) cobre uma fração pequena e desigual. Sem inventário versionado, smoke por domínio e testes behavioral nos caminhos fail-closed, regressões (PGMEI/sidecar, limites SERPRO, elegibilidade Integra) escapam do CI. A propose P0-only era insuficiente para o critério de “completo e robusto” pedido pelo time.

## What Changes

- Versionar o **inventário de superfície** (rotas API + páginas) como artefato da change e gate que falha se o inventário ficar defasado.
- Definir modelo de cobertura **L0–L3** (inventário → smoke de domínio → unit crítico → behavioral/Nuxt) aplicado a **todos** os grupos de rota e seções de página.
- Implementar nesta change:
  - **L0:** inventário + gates de paridade
  - **L1:** smoke Feature/auth-tenant por cluster de domínio (fiscal, serpro/mei, office/auth, clients/docs, monitoring/platform, work/outbound)
  - **L2:** unit fail-closed SERPRO/Integra/MEI + codecs PGMEI
  - **L3:** behavioral utils/composables (monitoring, serpro admin, clients/conta) + mounts Nuxt âncora (projeto Vitest Nuxt deixa de estar vazio)
- Manter source gates existentes; complementar, não substituir.
- Live SERPRO / sidecar `mei` / Playwright E2E continuam fora do gate desta change (documentados como Non-goals).

## Capabilities

### New Capabilities

- `surface-test-coverage`: inventário canônico de rotas/páginas, níveis L0–L3, smokes por domínio, unitários fail-closed e behavioral do painel — cobertura completa e robusta da superfície do hub sem exigir 1 teste por rota/página.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio; a capability anterior `critical-unit-test-coverage` desta change é substituída/renomeada por `surface-test-coverage` no delta)

## Impact

- API: `apps/api/tests/**` (Unit + Feature smoke); artefato de inventário sob a change; possível helper de listagem de rotas nos testes.
- Web: `apps/web/tests/unit/**` (+ `*.nuxt.test.ts`); inventário de páginas; sem redesign de UI.
- CI: suites novas devem passar filtradas; inventário gate faz parte do verify da change.
- Sem alteração de comportamento de produto, salvo correção mínima se teste revelar bug.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias; archive fora do DAG
- Depende de: nenhuma (coordenada com `pgmei-serpro-provider-fallback` — não bloqueante)
- Capability/contrato: `surface-test-coverage` (nova; substitui o foco estreito P0-only)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: refinamentos P2+ (Vault, Sitfis profundo, E2E) em changes futuras
- Paralelismo: independente de UI/ops ativas

### Non-goals

- Um Feature por cada uma das 444 rotas ou mount de cada uma das 94 pages
- Playwright E2E no gate CI
- Live SERPRO, sidecar `mei` no Compose, probes de produção
- Ligar flags SERPRO/MEI/SEFAZ
- Targets Make backup/restore/ops indisponíveis
- Mutações fiscais novas ou mudança de copy de produto (exceto se bug bloqueante)
