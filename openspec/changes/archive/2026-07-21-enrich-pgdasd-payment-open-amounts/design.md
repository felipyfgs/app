## Context

`openPaymentCompetencies` já lista PAs unpaid e tenta `tax_guides.amount_cents` via `das_number` = `identifier_code`. MONITOR não traz valor. GERAR_DAS local guarda `dados.total` + `dados.numeroDocumento` na evidência (vault), mas o DTO `DasGuideDto::fromIntegraBody` só lê `amount`/`valor` e `document_number`/`numero_documento` — então o snapshot normalizado quase nunca leva o montante.

Upstream ativo: `pgdasd-pagamento-e-cnpj-cliente` (C1) — ownership do contrato da lista; esta change só estende a resolução de `amount_cents`.

## Goals / Non-Goals

**Goals:**

- Preencher `amount_cents` quando houver fonte local confiável (`tax_guides` ou evidência/snapshot GERAR_DAS).
- Manter agregação por `period_key` (soma se todos tiverem valor; null se algum faltar).
- Fail-closed: sem fonte → `null` / UI `—`; sem live SERPRO.

**Non-Goals:**

- Parse de PDF/extrato; RBT12 como proxy.
- Materializar `tax_guides` / ligar `GuideIssuanceService` nesta change.
- Alterar badge Pagamento ou filtro Envio.
- Backfill em massa offline (pode ficar follow-up).

## Decisions

1. **Ordem de fontes**  
   (a) `tax_guides.amount_cents` por `client_id|identifier_code`;  
   (b) mapa local GERAR_DAS (`numeroDocumento` → cents) a partir de runs SUCCESS office-scoped.  
   Alternativa rejeitada: só consertar o DTO — não cobre evidências já arquivadas com normalized incompleto.

2. **Corrigir `DasGuideDto` + ler evidência/snapshot**  
   Mapear `numeroDocumento` → `document_number` e `total` (ou `principal`) → `amount` no DTO (reais → persistir como float no DTO; conversão para cents no enrich). Para histórico, no batch do portfolio: carregar evidências GERAR_DAS dos `client_id` com unpaid sem guide e montar mapa `das_number → amount_cents`. Preferir snapshot `normalized.das_guide` quando `document_number` e `amount` já estiverem válidos (evita decrypt).

3. **Escopo de leitura de vault**  
   Só para clientes do batch que ainda tenham DAS unpaid sem `amount_cents` após `tax_guides`. Usar `FiscalEvidenceStore::readAuthorized` com `office_id` do tenant. Não logar body.

4. **Ownership de arquivos**  
   Editar `PgdasdMonitoringQueryService` (+ helper privado ou serviço fino de resolução), `DasGuideDto`, testes Feature. Não reeditar artefatos OpenSpec da change irmã.

## Mapa de dependências

| Upstream | Capability | Marco | Relação | Notas |
|----------|------------|-------|---------|-------|
| `pgdasd-pagamento-e-cnpj-cliente` | `pgdasd-payment-popover-cliente` | `apply` | `bloqueante` | Lista `payment_open_competencies` já no código |

- Nível: **C2**
- Rollout: só código API. Rollback: remover fallback GERAR_DAS (volta a null/`—`).

## Risks / Trade-offs

- [Decrypt de vault no portfolio] → Mitigação: só clientes com gap; batch; preferir snapshot normalized quando completo.
- [Cliente sem GERAR_DAS local] → Continua `—` (honesto; MONITOR não tem valor).
- [Vários GERAR_DAS para o mesmo número] → Usar o mais recente SUCCESS.
- [Total em reais vs cents] → Converter com regra explícita (multiplicar por 100 com arredondamento bancário / inteiro) coberta por teste.
- [Tenancy] → Toda query filtrada por `office_id` do CurrentOffice; nunca confiar office do client HTTP.

## Migration Plan

- Deploy só API. Sem migration.
- Rollback: reverter enrich + DTO mapping.

## Open Questions

- Nenhuma bloqueante. Se evidência GERAR_DAS for rara no ambiente do usuário, o popover ainda mostrará `—` até haver geração local — esperado.
