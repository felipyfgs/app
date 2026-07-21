## Context

A carteira Simples/MEI (submódulo PGDASD) exibe Situação via `PgdasdDeclarationState` na célula e agrega KPI via `tax_obligation_projections.situation` (`FiscalSituation`). O resolver já é fail-closed e cobre só entrega do PA esperado. Hoje:

- UI: `DUE_WITHIN_DEADLINE` rotulado **Pendências** (ambíguo; DCTFWeb usa **No prazo**).
- Pós-consulta: `OVERDUE_NOT_FOUND` → `PENDING` (mesmo KPI de “ainda no prazo”).
- Hub cliente (enrichment) já trata atraso como `ATTENTION` na resposta — diverge do DB.

SERPRO/`malha` permanece em `pgdasd_operations` e no histórico; fora da Situação.

## Goals / Non-Goals

**Goals:**

- Contrato único: Situação = entrega do PA esperado.
- Labels de célula alinhadas (No prazo / Em dia / Atrasado / Não verificado).
- Mapeamento canônico estado → `FiscalSituation` no pós-consulta e nas linhas já persistidas.
- Override UI “Sem procuração” permanece precedência visual, sem alterar o enum.

**Non-Goals:**

- Incorporar malha, MAED ou `dasPago` na Situação.
- Renomear códigos do enum (`CURRENT`, etc.).
- Alterar DCTFWeb (exceto alinhar labels PGDAS onde já coincidem).
- Live SERPRO, flags ON, mei no Compose.

## Decisions

1. **Manter códigos do enum; alinhar labels e `FiscalSituation`**  
   - Alternativa rejeitada: renomear `OVERDUE_NOT_FOUND` → breaking desnecessário na API/UI.  
   - Mapeamento canônico:
     - `CURRENT` → `UP_TO_DATE`
     - `DUE_WITHIN_DEADLINE` → `PENDING`
     - `OVERDUE_NOT_FOUND` → `ATTENTION`
     - `UNVERIFIED` → `UNKNOWN`

2. **Labels pt_BR na célula**  
   - `CURRENT` → Em dia  
   - `DUE_WITHIN_DEADLINE` → No prazo  
   - `OVERDUE_NOT_FOUND` → Atrasado  
   - `UNVERIFIED` → Não verificado  
   - Fonte única: `PgdasdDeclarationState::label()` (PHP) + `DECLARATION_META` (web).

3. **Migration de dados (não de schema)**  
   - Atualizar `tax_obligation_projections.situation` para obrigações `PGDAS_D` com `pgdasd_declaration_state` preenchido, segundo o mapeamento.  
   - Não tocar projeções sem estado PGDAS ou de outros códigos.  
   - Alternativa rejeitada: só corrigir no próximo consult — KPI/filtros ficariam inconsistentes até reconsulta de toda a carteira.

4. **Helper de mapeamento**  
   - Extrair `PgdasdDeclarationState::toFiscalSituation()` (ou método dedicado no enum) usado pelo pós-consulta e documentado nos testes — evita drift com o hub.

## Risks / Trade-offs

- [Filtro KPI “Pendências” perde os atrasados] → Esperado: atrasados vão para **Atenção**; documentar no design/UX.  
- [Backfill em massa em `tax_obligation_projections`] → Escopo só `PGDAS_D` + estado não nulo; migration idempotente.  
- [Enrichment hub vs DB] → Após backfill + pós-consulta, enriquecimento e coluna ficam coerentes para atraso.

## Migration Plan

1. Deploy código (enum label/map + pós-consulta + UI).  
2. Rodar migration data-fix.  
3. Rollback: reverter código; migration `down` opcional restaura `OVERDUE_NOT_FOUND`→`PENDING` apenas nas linhas tocadas (best-effort via estado).

## Open Questions

- Nenhuma bloqueante.
