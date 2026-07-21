## Why

A reconciliação PAGTOWEB (`PAGAMENTOS71`) marcou DAS pagos como `NOT_FOUND` e a carteira exibiu «Pendências» falsas (ex.: A. F. Coelho — CNPJ 26.461.528/0001-51). A SERPRO devolveu pagamentos reais, mas o match por HMAC falhou: o PGDAS grava `das_number` com zero à esquerda (17 dígitos) e o PAGTOWEB devolve `numeroDocumento` sem o zero (16 dígitos).

## What Changes

- Canonicalizar `numeroDocumento` / `das_number` antes do digest HMAC (remover zeros à esquerda sem alterar o valor numérico).
- Garantir que decode da resposta PAGTOWEB e apply da evidência usem a mesma forma canônica.
- Reaplicar evidência já observada (ou backfill) para corrigir `pagtoweb_payment_status` falso `NOT_FOUND` quando houver item pago canonicamente equivalente.
- Testes unitários cobrindo DAS com leading zero ↔ resposta sem leading zero → `PAID`.

## Capabilities

### New Capabilities

- `pagtoweb-das-document-match`: match canônico entre `das_number` PGDAS e `numeroDocumento` PAGTOWEB via digest HMAC.

### Modified Capabilities

- (nenhuma — o contrato de precedência `PAID`/`UNPAID` permanece; só o critério de igualdade do documento muda)

## Impact

- API: `PagtowebPaymentListCodec::documentDigest` (+ helper canônico), `PgdasdPagtowebEvidenceService`, testes codec/evidência; comando ou reapply pontual para cobertura já persistida.
- Web: sem mudança de contrato — badges passam a refletir `PAID` quando a SERPRO já confirmou.
- SERPRO: sem nova bilhetagem obrigatória se reapply usar observação local; backfill só se necessário.
- Non-goals: alterar precedência PAGTOWEB vs `dasPago`; SITFIS na badge; live SERPRO no GET; parecer jurídico; mutações; flags ON; mei no Compose; ops backup/restore.

### Dependências entre changes

- Nível: **C1**
- Bases estáveis: specs `pgdasd-das-payment-column`, `pgdasd-payment-popover-cliente`
- Depende de:
  - `reconciliar-pagamento-pgdasd-com-pagtoweb` — evidência PAGTOWEB em `pgdasd_operations` — marco `apply` — relação `bloqueante`
- Desbloqueia: badge Pagamento alinhada à arrecadação real (fim do falso Pendências por padding)
- Paralelismo: ownership = codec digest + apply evidência; não editar artefatos da change PAGTOWEB irmã
