## Context

PGDAS já tem read-model no hub (`DeclarationPgdasdEnrichmentService` + DAS em `ClientGuidesQueryService`). DCTFWeb tem stack completa de consulta (CONSRECIBO32 → `dctfweb_declarations` + evidência) e emissão de DARF (`EMITIR_DARF` → `dctfweb_darf_documents`), mas o hub genérico do detalhe do cliente ignora esses stores.

Diferença importante vs PGDAS: a consulta padrão **não** devolve guia; DARF só existe após `EMITIR_DARF`.

## Goals / Non-Goals

**Goals:**

- Declarações DCTFWeb enriquecidas (recibo, estado efetivo, documento).
- Linhas de declaração local aparecem mesmo sem casca de projeção quando filtrado por cliente.
- Guias do cliente incluem DARFs emitidos localmente.
- Sem SERPRO ao abrir abas.

**Non-Goals:**

- Auto-EMITIR_DARF pós-consulta.
- Inferir pagamento do recibo.
- Aba dedicada DCTFWeb no detalhe do cliente.

## Decisions

1. **Espelhar PGDAS com serviço dedicado** `DeclarationDctfwebEnrichmentService` (não misturar lógica no serviço PGDAS).
2. **Alias `declaration_number` ← `receipt_number`** para a UI existente.
3. **Situação efetiva** (só resposta): `CURRENT`/`NO_MOVEMENT_VALID` → `UP_TO_DATE`; `OVERDUE_NOT_FOUND` → `ATTENTION`; `DUE_WITHIN_DEADLINE` → `PENDING`; se `situation` da declaração já for conclusiva, preferi-la.
4. **Sintéticos sem projeção**: com `client_id`, anexar declarações de `dctfweb_declarations` cujo `period_key` ainda não está na página (id `dctfweb-decl-{id}`).
5. **DARF → guia virtual**: `source=DCTFWEB_DARF`, `identifier_code=document_number`, `amount_cents` de `amount`, `payment_status` mapeado de `FiscalPaymentStatus`.
6. **Documento**: href `/api/v1/fiscal/dctfweb/clients/{client}/evidence/{evidence}/download` quando houver versão RECIBO/DARF.

## Risks / Trade-offs

- [Guias vazias após só CONSRECIBO] → Esperado; documentar na UI/spec que DARF exige emissão.
- [IDs sintéticos sem show] → Linhas só para lista/download de evidência.
- [Conflito com enrichment PGDAS no mesmo controller] → Cadeia: PGDAS depois DCTFWeb sobre arrays já públicos, ou DCTFWeb sobre models e PGDAS depois — preferir: enriquecer models com PGDAS primeiro (retorna arrays), depois DCTFWeb só processa rows DCTFWEB + append sintéticos.

## Migration Plan

- Só código; rollback = reverter enrichment.
