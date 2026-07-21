## Context

Hoje a badge Pagamento lê `pgdasd_operations.payment_located` (`dasPago` de CONSDECLARACAO13). PAGTOWEB `PAGAMENTOS71` já existe como consult manual guias (`PagtowebPaymentList*`), mas sanitiza `numeroDocumento` para HMAC e **não** alimenta o estado PGDAS. Certidão/SITFIS limpa e índice PGDAS com `dasPago=false` (guias reemitidas / atraso) geram falso “Pendências”.

Upstream ativo (código já apply): `pgdasd-pa-pago-qualquer-das` (any-paid em `dasPago`), `persist-pgdasd-operation-das-amount`, `enrich-pgdasd-payment-open-amounts`. Esta change **substitui** a autoridade da badge/`payment_open_competencies` para evidência PAGTOWEB local; não reedita artefatos das irmãs.

## Goals / Non-Goals

**Goals:**

- Autoridade canônica: `pagtoweb.pagamentos` confirma pagamento por `numeroDocumento` = `das_number`.
- Automático pós-MONITOR + backfill limitado; portfolio GET só lê local.
- Fail-closed: sem `00004` / erro / cobertura incompleta / TTL vencido → `UNVERIFIED`, nunca inventar pendência.
- Matching por digest HMAC (sem persistir número em claro nas projeções).

**Non-Goals:**

- Live SERPRO no portfolio GET.
- SITFIS na coluna Pagamento.
- Emissão automática `COMPARRECADACAO72` (PDF só sob demanda).
- Parecer jurídico; mutações; flags ON; mei no Compose.

## Decisions

1. **Autoridade PAGTOWEB sobre `dasPago`**  
   `dasPago` continua gravado para auditoria/UI histórica de operação, mas `PgdasdDasPaymentStateResolver` e `openPaymentCompetencies` MUST decidir com colunas de cobertura PAGTOWEB.  
   Alternativa rejeitada: manter any-paid em `dasPago` — continua falso positivo vs arrecadação.  
   Alternativa rejeitada: SITFIS na badge — granularidade agregada, não prova por DAS.

2. **Precedência do PA esperado**  
   1. Sem DAS no PA + consulta PGDAS produtiva → `NO_DAS`  
   2. Qualquer DAS do PA com evidência PAGTOWEB `PAID` (match exato) → `PAID` (permanente)  
   3. Cobertura PAGTOWEB **completa** de todos os DAS do PA, todos `NOT_FOUND`, e `verified_at` dentro do TTL (default 24h, config) → `UNPAID`  
   4. Caso contrário (sem consulta, parcial, erro, sem poder `00004`, TTL vencido, DAS sem coverage row) → `UNVERIFIED`  
   `dasPago` NÃO entra na precedência.

3. **Consulta por `numeroDocumentoLista`**  
   Estender `PagtowebPaymentListCodec` / adapter para aceitar lista de documentos (≤100/página oficial), além do filtro por intervalo de data já existente. Pós-MONITOR: coletar DAS do cliente sem evidência `PAID` (ou com cobertura negativa vencida), fatiar em lotes ≤100, enqueue `pagtoweb.pagamentos` via `SerproOperationService` / run monitoring (idempotência por office+client+digest-lote).  
   Matching: HMAC(`numeroDocumento`) == `document_digest` das items; se bate → marcar operação `pagtoweb_payment_status=PAID`, `pagtoweb_paid_at`, `pagtoweb_amount_cents`, refs run/item. Documentos da lista sem retorno → `NOT_FOUND` + `pagtoweb_verified_at`.  
   Sem gravar `numeroDas` em claro nas tables PAGTOWEB list (mantém digest).

4. **Persistência em `pgdasd_operations`**  
   Migration aditiva:  
   - `pagtoweb_payment_status` nullable string (`PAID`|`NOT_FOUND`)  
   - `pagtoweb_verified_at` timestamptz nullable  
   - `pagtoweb_paid_at` date/timestamptz nullable  
   - `pagtoweb_amount_cents` bigint nullable  
   - `pagtoweb_source_run_id` / `pagtoweb_source_item_id` nullable  
   Observações PAGTOWEB existentes continuam histórico sanitizado.

5. **`openPaymentCompetencies`**  
   Incluir `period_key` só se: nenhum DAS do PA tem `pagtoweb_payment_status=PAID` **e** todos os DAS do PA têm `NOT_FOUND` com `verified_at` fresco.  
   `amount_cents`: preferir `pagtoweb_amount_cents` dos DAS `NOT_FOUND` (soma fail-closed se algum null) → depois tax_guides / operation.amount_cents / GERAR_DAS vault (ordem já existente).

6. **Autorização e bilhetagem**  
   Antes de enqueue: verificar poder/procuração `00004` e capability guides; se ausente → não enqueue e deixar `UNVERIFIED`. Respeitar kill switch, rate limit, orçamento. Correlation/idempotency keys estáveis.  
   Backfill: job limitado (ex. N clientes/DAS por ciclo) para ativos com DAS conhecidos sem cobertura fresca.

7. **COMPARRECADACAO72**  
   Fora desta change para automação. Endpoint/manual já existente pode emitir PDF para DAS já `PAID` sob demanda.

8. **Web**  
   Labels existentes (`Em dia` / `Pendências` / `Não verificado` / `Sem DAS`). MAY expor `payment_source=PAGTOWEB` e `payment_verified_at` no detail; descrição humana no popover: “Confirmado no PAGTOWEB” / “Pendência confirmada (PAGTOWEB)” / “Não verificado”.

## Risks / Trade-offs

- [TTL negativo] → Pendência some após 24h sem reconsulta → Mitigação: backfill/schedule + badge `UNVERIFIED` honesta.  
- [Bilhetagem CONSULTA] → Mitigação: lote ≤100, só gaps, idempotência, budget.  
- [Sem poder 00004] → Mitigação: fail-closed `UNVERIFIED`, nunca Pendências.  
- [Vazamento tenancy] → Mitigação: queries sempre `office_id`; digest com app key, match só no office do run.  
- [Segredos/log] → Mitigação: não logar lista completa de DAS; vault já usado no path SERPRO.  
- [Conflito com any-paid `dasPago`] → Esta change supersede a autoridade da badge; código das irmãs permanece para amount/`dasPago` auxiliar.

## Migration Plan

1. Migration aditiva em `pgdasd_operations` + codec `numeroDocumentoLista`.  
2. Wire pós-MONITOR + backfill; resolver/open competencies.  
3. Deploy; backfill oportunista.  
4. Rollback: ignorar colunas PAGTOWEB e reverter resolver para regra `dasPago` (any-paid) se necessário.

## Mapa de dependências

| Upstream | Capability | Marco | Relação |
|----------|------------|-------|---------|
| `pgdasd-pa-pago-qualquer-das` | `pgdasd-das-payment-column` | `apply` | `bloqueante` |
| `persist-pgdasd-operation-das-amount` | amount ops | `apply` | `coordenada` |
| `enrich-pgdasd-payment-open-amounts` | enrich valor | `apply` | `coordenada` |

- Nível: **C1**
- Ownership: `PgdasdDasPaymentStateResolver`, `openPaymentCompetencies`, ingest PAGTOWEB→PGDAS; não editar `openspec/changes/<irmãs>/`
- Rollout: migration + API; UI opcional. Rollback: colunas nullable + flag de leitura.
