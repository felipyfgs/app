## Why

O painel `/monitoring/declarations` hoje é uma carteira agregada sem abas (uma linha por cliente / próxima obrigação). O produto de referência (MonitorHub) organiza Declarações por obrigação — PGDAS, DCTFWeb, FGTS, DEFIS, DIRF — com lista específica e histórico DAS em dois níveis. Sem esse hub, o usuário mistura agenda genérica com superfícies operacionais já existentes (Simples MEI, DCTFWeb, FGTS) e não encontra o fluxo de histórico alinhado ao domínio.

## What Changes

- Transformar `/monitoring/declarations` em hub com **abas locais** (URL permanece `/monitoring/declarations`): PGDAS | DCTFWeb | FGTS | DEFIS | DIRF.
- Aba **PGDAS** com fidelidade à referência: colunas Situação / Últ. Declaração / Cliente / Última Busca / Histórico de Busca; modal “DAS Simples Nacional - Histórico”; modal aninhado “Histórico de Declarações” com downloads de artefatos.
- Abas **DCTFWeb / FGTS / DEFIS** com lista filtrada e reuso de contratos/modais existentes.
- Aba **DIRF** visível com estado honesto `UNSUPPORTED` / vazio (sem API/catálogo; sem dados fake).
- Backend: `FiscalModuleKey::Declarations` passa a aceitar submódulos `PGDAS`, `DCTFWEB`, `FGTS`, `DEFIS`, `DIRF` e o portfolio filtra por obrigação / origem quando aplicável.
- Sidebar **não** colapsa: Simples Nacional | MEI, DCTFWeb e FGTS Digital permanecem itens próprios.

## Capabilities

### New Capabilities

- `declarations-obligation-hub`: hub de Declarações com abas por obrigação, carteira filtrada, histórico PGDAS em dois níveis (reuso do domínio PGDAS-D já existente) e estados honestos para obrigações sem cobertura (DIRF).

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio; o reuso de PGDAS-D fica coberto pela capability nova)

## Impact

- Front: `apps/web/app/pages/monitoring/declarations.vue`, tipos `DECLARATIONS_TABS`, componentes `Pgdasd*History*`, testes/fidelity de monitoring.
- API: `FiscalModuleKey::knownSubmodules()`, `ModulePortfolioQueryService` (filtro `submodule` → `obligation_code` / origem).
- Sem mudança de rotas de sidebar; sem integração SERPRO DIRF; sem seed DEMO/SIMULATED.
