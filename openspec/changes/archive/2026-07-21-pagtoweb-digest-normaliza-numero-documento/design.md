## Context

A evidência PAGTOWEB já persiste itens com `document_digest` HMAC e aplica cobertura em `pgdasd_operations`. Em produção, a SERPRO aceita consulta com DAS 17 dígitos (`0` + resto) e devolve `numeroDocumento` sem o zero à esquerda. O digest atual usa a string literal → mismatch → falso `NOT_FOUND` → badge «Pendências».

Evidência local (cliente 5, run 254): observação com `returned_count=5` (pagamentos reais); 5 DAS com `payment_located=true` casam por `ltrim('0')`; todos ficaram `NOT_FOUND`.

## Goals / Non-Goals

**Goals:**
- Forma canônica única de `numeroDocumento` no digest (entrada, resposta e apply).
- Match correto DAS PGDAS ↔ pagamento PAGTOWEB apesar de padding.
- Corrigir cobertura já gravada sem nova bilhetagem quando a observação local existir.

**Non-Goals:**
- Mudar precedência PAID/UNPAID/UNVERIFIED.
- Usar SITFIS ou `dasPago` como autoridade.
- Live SERPRO no portfolio GET.
- Parecer jurídico; mutações; flags ON; mei no Compose.

## Decisions

1. **Canônico = dígitos sem zeros à esquerda**  
   `canonicalizeDocumentNumber`: manter só `[0-9]`, `ltrim('0')`; se vazio, `"0"`. Digest HMAC sempre sobre o canônico.  
   Alternativa rejeitada: padding fixo 17 — a resposta SERPRO varia (16/17) e o canônico é estável.

2. **Um único ponto: `PagtowebPaymentListCodec::documentDigest`**  
   Decode e `PgdasdPagtowebEvidenceService::apply` já chamam `documentDigest`; corrigir ali cobre request digests, items e apply.

3. **Reapply local**  
   Serviço/comando que, por observação PAGTOWEB existente, recalcula digests canônicos e reaplica `PAID`/`NOT_FOUND` nos DAS do lote consultado — sem chamar SERPRO. Depois, backfill só para gaps restantes.

## Risks / Trade-offs

- [Digest antigo em items] → Items já gravados usam número da resposta (já sem zero); canônico não muda o digest deles. Operações passam a gerar o mesmo digest → OK sem migration de items.
- [Colisão teórica após ltrim] → Números que só diferem por zeros à esquerda são o mesmo documento fiscal; desejável.
- [Vazamento tenancy] → Reapply permanece office-scoped.

## Mapa de dependências

| Upstream | Capability | Marco | Relação |
|----------|------------|-------|---------|
| `reconciliar-pagamento-pgdasd-com-pagtoweb` | evidência PAGTOWEB | `apply` | `bloqueante` |

- Nível: **C1**
- Ownership: `PagtowebPaymentListCodec`, `PgdasdPagtowebEvidenceService` (+ teste/reapply)
- Rollout: patch codec → reapply local → refresh portfolio
- Rollback: reverter canônico (restaura falso negativo conhecido)
