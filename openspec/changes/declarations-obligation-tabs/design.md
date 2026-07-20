## Context

O monitoring já tem:

- Portfolio genérico `GET /api/v1/fiscal/modules/{module}/overview|clients` com filtro `submodule` validado por `FiscalModuleKey::knownSubmodules()`.
- Páginas com abas locais (padrão `ShellScrollableTabs` em `simples-mei` / `dctfweb`) — submódulo **não** entra na URL.
- Domínio PGDAS-D completo: `usePgdasdMonitoring`, `PgdasdHistoryView`, endpoints de history/documents/artifacts.
- Hub Declarações atual: carteira agregada sem abas; detalhe = slideover de projeção.

A referência MonitorHub exige Declarações como hub de obrigações com abas e histórico DAS em dois níveis.

## Goals / Non-Goals

**Goals:**

- Hub `/monitoring/declarations` com abas PGDAS, DCTFWeb, FGTS, DEFIS, DIRF.
- Portfolio `declarations` filtrável por `submodule` → obrigação/origem.
- PGDAS: lista fiel + modal DAS + modal aninhado de declarações (reuso PGDAS-D).
- Estados honestos (DIRF unsupported; FGTS cobertura parcial).
- Testes/fidelity cobrindo tabs, default PGDAS e abertura de histórico.

**Non-Goals:**

- Colapsar sidebar (Simples MEI / DCTFWeb / FGTS Digital permanecem).
- Integração SERPRO DIRF.
- Seed DEMO/SIMULATED.
- OpenSpec formal de outras superfícies além deste hub.

## Decisions

### 1. Abas locais, URL limpa

- **Decisão:** `submodule` é estado Vue (`ref`), default `PGDAS`; path fixo `/monitoring/declarations`.
- **Por quê:** Alinha ao padrão já adotado em simples-mei/dctfweb e à regra de nav “tabs não entram na URL”.
- **Alternativa:** path `/monitoring/declarations/pgdas` — rejeitada para não duplicar middleware legado e poluir bookmarks.

### 2. Valores de submodule e mapeamento de obrigação

| Tab UI | `submodule` API | Filtro portfolio |
|--------|-----------------|------------------|
| PGDAS | `PGDAS` | `obligation_code = PGDAS_D` |
| DCTFWeb | `DCTFWEB` | `obligation_code = DCTFWEB` |
| FGTS | `FGTS` | origem FGTS / sem obrigação de hub (lista parcial ou vazia honesta) |
| DEFIS | `DEFIS` | `obligation_code = DEFIS` |
| DIRF | `DIRF` | sem catálogo → carteira vazia + surface unsupported |

- **Decisão:** expandir `knownSubmodules()` de `Declarations` para esses cinco valores (manter `DECLARACOES` como alias legado se já houver callers).
- **Por quê:** o controller já rejeita submodule fora da lista.

### 3. Extrair modais PGDAS em vez de reimplementar

- **Decisão:** extrair de `PgdasdHistoryView` (ou encapsular) `PgdasdDasHistoryModal` + `PgdasdDeclarationsHistoryModal`; consumir na aba Declarações e, quando possível, no detalhe do cliente.
- **Por quê:** histórico e downloads já existem; a gap é layout/fluxo de dois níveis da referência.
- **Alternativa:** só embutir `PgdasdHistoryView` no slideover — rejeitada por não bater com a hierarquia das fotos (lista mensal DAS → nested declarações).

### 4. Demais abas: mínimo útil

- DCTFWeb: lista filtrada + `DctfwebHistoryModal`.
- DEFIS: lista + modais DEFIS existentes.
- FGTS: colunas/contrato FGTS sem inventar guia/pagamento.
- DIRF: empty + alert unsupported.

### 5. Sidebar inalterada

Hub Declarações **complementa** as superfícies operacionais; não as substitui nesta change.

## Risks / Trade-offs

- **[Duplicação de superfície]** Usuário vê DCTFWeb/FGTS na sidebar e nas abas de Declarações → Mitigação: copy/título deixam claro que Declarações é agenda/histórico por obrigação; sidebar operacional permanece.
- **[DIRF vazio]** Expectativa de dados → Mitigação: badge/estado `UNSUPPORTED` explícito, sem placeholders.
- **[Filtro portfolio incompleto]** Se `detailDeclaracoes` não filtrar por obrigação, abas mostram a mesma carteira → Mitigação: filtrar projections por `obligation_code` no query service e nos counters.
- **[Regressão PGDAS-D]** Extração de componentes quebra detalhe do cliente → Mitigação: reusar os novos modais no client detail ou manter `PgdasdHistoryView` como composição dos mesmos blocos.

## Migration Plan

1. Deploy API (knownSubmodules + filtro) antes ou junto do front.
2. Front: abas + PGDAS completo; demais abas mínimas.
3. Rollback: reverter page/components; API com submodules extras é backward-compatible (clients antigos sem `submodule` continuam no comportamento agregado se `DECLARACOES`/null for default).

## Open Questions

- (nenhuma bloqueante) Ordem futura de colapsar sidebar vs hub fica fora desta change.
