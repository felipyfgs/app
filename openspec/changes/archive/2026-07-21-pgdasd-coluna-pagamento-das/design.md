## Context

`CONSDECLARACAO13` já persiste DAS em `pgdasd_operations` com `payment_located` (`dasPago`). A carteira mostra Situação (entrega), Últ. Declaração e RBT12, mas não o pagamento. Situação acabou de ser fixada como só entrega do PA — pagamento precisa de eixo próprio.

## Goals / Non-Goals

**Goals:**

- Coluna **Pagamento** na spine PGDAS-D após RBT12 (antes de Cliente).
- Estado fail-closed a partir dos DAS do **PA esperado** (mesmo período da Situação).
- Payload de portfolio com `payment_state` no bloco `pgdasd`.

**Non-Goals:**

- Alterar labels/códigos de `PgdasdDeclarationState`.
- Live SERPRO, emissão de DAS, upsert em `tax_guides`.
- Malha / MAED na coluna.
- KPI strip global misturando entrega + pagamento (pode filtrar depois).

## Decisions

1. **Coluna separada, não composite**  
   Alternativa rejeitada: fundir em Situação — perde sinal de “declarou mas não pagou”.

2. **Escopo = PA esperado**  
   Mesmo `expected_period_key` da Situação. DAS de outros PAs não influenciam a badge da linha.

3. **Precedência do estado**  
   - Sem consulta produtiva / sem projeção útil → `UNVERIFIED`  
   - Existe ≥1 DAS do PA com `payment_located === false` → `UNPAID` (Pendências)  
   - Existe ≥1 DAS do PA e nenhum `false`, e ≥1 `true` → `PAID` (Em dia)  
   - Existem DAS do PA mas todos `payment_located === null` → `UNVERIFIED`  
   - Zero DAS do PA → `NO_DAS` (Sem DAS)  
   Alternativa rejeitada: “qualquer true = pago” mesmo com outro unpaid — inseguro.

4. **Cálculo on-read no batch do monitoring query**  
   Alternativa (cache em `tax_obligation_projections`) adiada: volume por office é baixo; evita migration. Se N+1 aparecer, agregar com um `whereIn(client_id)` + `period_key`.

5. **Labels**  
   - `PAID` → Em dia  
   - `UNPAID` → Pendências  
   - `NO_DAS` → Sem DAS  
   - `UNVERIFIED` → Não verificado  

6. **Spine**  
   `Situação · Últ. Declaração · RBT12 · Pagamento · Cliente · Ações · Envio · Hist. comunicação · Consulta`

## Risks / Trade-offs

- [DAS avulso/cobrança no mesmo PA] → Contam no unpaid se `payment_located=false`; aceitável (débito na RFB).  
- [Sem DAS após declaração] → `NO_DAS` ≠ Pendências (ainda pode não haver guia gerada).  
- [Sort/filter KPI] → MVP: coluna + sort opcional; filtro KPI fica follow-up.

## Migration Plan

- Deploy só código. Rollback: reverter coluna UI + campo no payload.

## Open Questions

- Nenhuma bloqueante.
