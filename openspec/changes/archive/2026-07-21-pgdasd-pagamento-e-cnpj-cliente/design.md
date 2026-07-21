## Context

A coluna **Pagamento** PGDAS-D já existe (change `pgdasd-coluna-pagamento-das`): badge `payment_state` no PA esperado e popover com Situação/Detalhe (reason code) + contagens DAS. O operador não precisa dessas contagens — só saber se o cliente pagou; se não, quais competências estão em aberto.

A célula Cliente (`FiscalClientCell`) nas carteiras consome `cnpj_masked` no formato ocultador da API (`2646******0151`). O escritório precisa da máscara BR na tela e dos dígitos no clipboard.

## Goals / Non-Goals

**Goals:**

- Popover Pagamento no nível do cliente: pago → “Em dia”; não pago → lista de competências em aberto (+ valor opcional).
- Payload `detail.pgdasd.payment_open_competencies` no portfolio.
- Portfolio expõe `cnpj` (14 chars normalizados); célula exibe `formatCnpj` e copia dígitos no clique.
- Badge/precedência do PA esperado inalterados.

**Non-Goals:**

- Extrair valor monetário do PDF/SERPRO quando não houver em `tax_guides`.
- Badge agregando todos os PAs do histórico.
- KPI/filtro por pagamento; live SERPRO; flags ON.
- Trocar `cnpj_masked` em modais, histórico ou exports.
- Migration; mei no Compose.

## Decisions

1. **Badge permanece no PA esperado; lista do popover cobre o histórico local do cliente**  
   Alternativa rejeitada: mudar a badge para “qualquer PA unpaid” — altera o eixo alinhado à Situação sem pedido explícito. A lista de competências em aberto dá o contexto multi-PA sem mudar a spine.

2. **`payment_open_competencies` no batch do monitoring query**  
   Em `PgdasdMonitoringQueryService::portfolioDetails`, além dos DAS do PA esperado para o estado, carregar DAS com `payment_located=false` por `client_id` (`whereIn`), agregar por `period_key` desc. Valor: left join/`tax_guides.amount_cents` por `das_number` quando existir; senão `null`.  
   Alternativa (só PA esperado na lista) rejeitada — o pedido é listar competências em aberto do cliente.

3. **Agregação por competência**  
   Uma entrada por `period_key`. Se vários DAS unpaid no mesmo PA: somar `amount_cents` só quando todos tiverem valor; se algum for null → `amount_cents` da competência = null.

4. **Popover UI sem contagens nem reason codes**  
   `pgdasdPaymentDetailItems` / `PaymentValue.vue`: `PAID` → estado + texto humano; `UNPAID` → lista formatada `MM/YYYY` (+ moeda pt-BR se houver cents); `NO_DAS`/`UNVERIFIED` → estado + descrição. Campos `payment_*_count` podem permanecer no payload sem uso na UI.

5. **`cnpj` no `ModuleClientRowDto`**  
   Incluir dígitos normalizados na linha do portfolio (office-scoped, autenticado). Manter `cnpj_masked` por compat. UI: prop `cnpj` em `FiscalClientCell`; fallback ao masked legado sem copy de dígitos. Clique no CNPJ não navega (só o nome linka). Toast no padrão do catálogo de clientes.

6. **Duas capabilities na mesma change**  
   Justificativa transversal: mesmo deliverable de UX da carteira PGDAS/monitoramento; CNPJ é compartilhado via `FiscalClientCell` em todas as grades que já usam a célula. Ownership: API portfolio + web célula/popover; sem conflito com a change irmã além de ler o contrato da coluna Pagamento.

## Mapa de dependências

| Upstream | Capability | Marco | Relação | Notas |
|----------|------------|-------|---------|-------|
| `pgdasd-coluna-pagamento-das` | `pgdasd-das-payment-column` | `apply` | `coordenada` | Badge/estado já na spine; não reeditar artefatos dessa change |

- Nível desta change: **C1**
- Paralelo interno: API `payment_open_competencies` ∥ API `cnpj` (N0); UI popover depende da lista; UI célula depende de `cnpj`
- Rollout: só código. Rollback: reverter popover/UI + campos novos no payload (campos opcionais)

## Risks / Trade-offs

- [Lista multi-PA com badge só no PA esperado] → Badge “Em dia” pode coexistir com competências antigas na lista; aceitável neste MVP; follow-up se o escritório quiser badge global.
- [Valor quase sempre null] → CONSDECLARACAO13 não persiste valor; UI MUST NÃO inventar número; só competência.
- [Expor `cnpj` completo no portfolio] → Já autenticado por office; alinhado ao catálogo de clientes; não logar CNPJ em claro em novos logs.
- [Clique vs navegação] → Isolar handler no CNPJ; link permanece só no nome.

## Migration Plan

- Deploy só código. Sem migration.
- Rollback: reverter UI + campos opcionais do JSON.

## Open Questions

- Nenhuma bloqueante.
