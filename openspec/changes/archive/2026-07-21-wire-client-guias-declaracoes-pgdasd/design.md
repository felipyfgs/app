## Context

A consulta produtiva PGDAS-D (`CONSULTAR_DECLARACAO` / MONITOR) já persiste operações em `pgdasd_operations` (kinds `DECLARATION` e `DAS`) e atualiza campos PGDAS em `tax_obligation_projections`. As abas **Declarações** e **Guias** do detalhe do cliente, porém, leem apenas o hub genérico (`tax_obligation_projections.situation/delivery_status` e `tax_guides`), que permanecem vazios ou `PENDING` após a consulta.

Exemplo observado (client_id=10): 6 declarações + 19 DAS em `pgdasd_operations`, 0 `tax_guides`, projeções ainda `PENDING` (mesmo com `pgdasd_declaration_state=CURRENT` no PA esperado).

## Goals / Non-Goals

**Goals:**

- Declarações do cliente refletem declarações encontradas na consulta local (nº, situação derivada, documento se houver artefato).
- Guias do cliente listam DAS encontrados na consulta local (nº DAS, emissão, pagamento localizado).
- Sem chamada SERPRO ao abrir as abas; tenant via `CurrentOffice`.

**Non-Goals:**

- Upsert permanente em `tax_guides` no pós-consulta.
- Alterar aba PGDAS-D / histórico (já correto).
- Emitir/baixar DAS novo; materializar valor em centavos ausente no payload consultado.

## Decisions

1. **Read-model enrichment (não materialização)**  
   - Declarações: serviço de enriquecimento pós-query no hub (`DeclarationHubQueryService` ou decorator no controller) cruza projeções `PGDAS_D` com `pgdasd_operations` do mesmo `client_id`+`period_key`.  
   - Guias: quando `client_id` filtrado, união de `tax_guides` + DAS virtuais de `pgdasd_operations` (kind DAS), dedupe por `identifier_code`/`das_number` se já existir guia emitida.  
   - Alternativa rejeitada: só redirecionar UI para aba PGDAS-D — usuário espera ver dados nas abas nomeadas Guias/Declarações.

2. **Mapeamento de situação (exibição, sem reescrever colunas hub)**  
   - Declaração encontrada no período → `delivery_status`/`situation` efetivos `UP_TO_DATE` na resposta pública (campos originais do calendário permanecem no DB).  
   - Sem declaração + `OVERDUE_NOT_FOUND` → `ATTENTION`; `DUE_WITHIN_DEADLINE`/`UNVERIFIED` sem op → manter `PENDING`.  
   - Incluir `declaration_number`, `pgdasd_declaration_state`, `source: PGDASD_CONSULT` quando enriquecido.

3. **DAS virtual como guia pública**  
   - Shape compatível com `TaxGuide::toPublicArray`: `id` negativo ou prefixado (`pgdasd_das_{id}` via campo `source_id` + `id` string-safe no front), `identifier_code=das_number`, `competence_period_key=period_key`, `system_code=INTEGRA_SN`, `service_code=PGDASD`, `payment_status` de `payment_located` (`CONFIRMED`/`NOT_CONFIRMED`/`UNKNOWN`), `amount_cents=null`, `current_version.emission_status` derivado de `issued_at`.  
   - Paginação: para filtro `client_id`, carregar DAS + guias em memória ordenados (volumes tipicamente pequenos por cliente); lista office-wide sem `client_id` permanece só `tax_guides` nesta change.

4. **Documento**  
   - Se existir `PgdasdArtifact` / `FiscalEvidenceArtifact` ligado à operação/período, anexar `FiscalDocumentDescriptorDto`; senão coluna Documento fica vazia/unavailable — não inventar PDF.

5. **Frontend**  
   - Ajustar labels: Declarações mostram nº declaração quando houver; Guias mostram `DAS {numero} · {PA}` em vez de só `Guia #id`.  
   - Continuar consumindo os mesmos endpoints.

## Risks / Trade-offs

- [Situação “efetiva” diverge do DB] → Mitigação: documentar no payload `source`/`enriched_from`; não persistir overwrite silencioso.  
- [IDs virtuais de guia quebram deep-link show] → Mitigação: linhas virtuais `source=PGDASD_CONSULT` não abrem `GET /guides/{id}`; UI só lista/download via artefato PGDAS quando existir.  
- [Paginação office-wide incompleta] → Aceito nesta change; escopo = detalhe do cliente.  
- [Vazamento tenant] → Queries sempre `office_id` do `CurrentOffice`.

## Migration Plan

- Deploy puro de código; sem migration.  
- Rollback: reverter enrichment (abas voltam ao estado anterior).

## Open Questions

- (nenhuma bloqueante) Materialização futura de `tax_guides` no pós-consulta fica para change separada se emissão/pagamento unificado for necessário.
