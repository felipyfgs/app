## Context

Hoje RBT12 só nasce do extrato DAS (`CONSEXTRATO16` + `numeroDas`). Sem DAS no PA → `reserveNoDas` → coluna `—`.

Produto: **mesmo sem movimento**, a declaração (e o recibo/extrato do período) do PA esperado já contêm RBT12. Fallback para DAS de outro mês é a abordagem errada.

## Goals / Non-Goals

**Goals:**

- Com declaração do PA esperado e sem DAS: ler RBT12 do documento da **declaração desse PA**.
- Com DAS: manter CONSEXTRATO como hoje.
- Fail-closed: sem artefato legível / valor ambíguo → não inventar.

**Non-Goals:**

- Usar DAS histórico de outro PA como fonte primária.
- Estimar receita; varrer todos os períodos; mudar copy dos KPIs.

## Decisions

1. **Fonte primária sem DAS = declaração do PA esperado** — não DAS de mês anterior.
2. **Com DAS = extrato DAS** (inalterado).
3. **Spike curto no apply:** confirmar se o PDF/recibo da declaração já está em `PgdasdArtifact` local após MONITOR e se o layout do RBT12 é compatível com `PgdasdRbt12Parser` ou precisa de variante.

## Risks / Trade-offs

- [Layout da declaração ≠ extrato DAS] → adaptar parser ou parser dedicado; fail-closed.
- [Declaração sem PDF local] → enfileirar download do documento da declaração antes do parse (custo SERPRO); só se artefato local ausente.

## Open Questions

- Resolvido no spike: Eliane (PA 2026-06) tem declaração local sem DAS e sem PDF de declaração armazenado; RBT12 estava `NO_DAS`. Pipeline novo usa `CONSDECREC15` no próximo MONITOR para baixar declaração/recibo e parsear RBT12 com o parser existente (fail-closed).
