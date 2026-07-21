## Context

Bugs encontrados na review do working tree: o match catch-all em `FiscalMonitoringRunService::maybeQueueAutomaticCommunication` trata qualquer sucesso `simples_mei` como PGDAS-D; `PgdasdCommunicationService` calcula `can_send` com documentos na prévia, mas `requestSend` / `resolveAutomaticEffective` ignoram essa guarda; `TaxGuideController::index` unifica guias via `ClientGuidesQueryService::paginate`, que faz `get()` completo das três fontes; o sort `rbt12` da carteira usa `ORDER BY id DESC` em projeções PARSED, diferente da precedência de `PgdasdMonitoringQueryService::portfolioDetails`.

Exceção de 2 capabilities: comunicação (correção de contrato de envio) e guias/RBT12 (consistência de read-model) são fluxos distintos e testáveis em paralelo, mas ambos bloqueiam o merge seguro do working tree — uma change única evita dois cycles propose/apply para o mesmo diff sujo.

## Goals / Non-Goals

**Goals:**

- Roteamento pós-consulta só para o submódulo correspondente ao `service_code`.
- Envio manual e automático PGDAS-D exigem artefato local (mesma semântica de `can_send` da prévia).
- Lista unificada de guias paginável sem carregar o universo do office em Eloquent.
- Sort RBT12 alinhado à seleção de valor exibido.

**Non-Goals:**

- Provider externo / worker de egress; throttle/step-up de senha; redesign da UI; alterar schemas de preferência.

## Decisions

1. **Roteamento por substring explícita** — `str_contains(serviceCode, 'PGDASD')` / `'PGMEI'`; sem fallback catch-all para `simples_mei`. Alternativa (mapa de serviços → submodule) fica para cleanup futuro; substring já é o padrão PGMEI atual.

2. **Guarda de documentos no core** — helper `hasLocalPgdasdDocuments` + `resolveCanSend(..., hasLocalDocuments)` usado em preview, summary, `requestSend` (422 se false) e `maybeQueueAutomaticAfterConsult` (no-op). Wrappers PGMEI/DCTFWeb/etc. não exigem artefato PGDAS-D (`submoduleKey !== pgdasd` → documentos irrelevantes / true).

3. **Guias: índice leve + hidratação da página** — consultar só chaves/sort/payment das três fontes (colunas escalares), dedupe/sort/filter/counters em memória sobre esse índice leve, hidratar shape público só para o slice da página. Alternativa UNION SQL completa rejeitada nesta change por complexidade de mapear payment DAS/DARF e documentos; o índice leve remove o carregamento de relações/modelos completos office-wide (causa do OOM/pressão).

4. **Sort RBT12** — subquery SQL: PARSED com `projection.period_key` = PA esperado; senão qualquer PARSED (`id DESC` só como desempate). Sem subselects correlacionados no `ORDER BY` (SQLite de testes rejeita). Pointer de `portfolioDetails` permanece no enrichment da linha, não no sort.

## Risks / Trade-offs

- [Send 422 sem artefato] → Testes e UI já usam `can_send`; alinhar Feature test com seed de artifact.
- [Índice leve ainda O(n) em PHP] → Mitigação: só escalares; sem `with()`; aceitável vs rewrite UNION nesta change; monitorar se offices >~50k linhas pedirem UNION.
- [SQL sort RBT12 ≠ edge cases de display] → Cobrir com Feature de ordenação com pointer vs period mismatch.
- Vazamento entre offices / segredos / bilhetagem SERPRO / mei Compose: N/A (só read-model + fila local fail-closed).

## Mapa de dependências

- DAG: C0; sem upstream ativo bloqueante.
- Ownership: API Services/Http/tests listados no Impact do proposal; não editar artifacts de outras changes.
- Rollout: deploy API; rollback = revert dos métodos.
- Gates: `php artisan test` filtros comunicação/guias/portfolio + pint + openspec validate da change.

## Migration Plan

Nenhuma migração de schema. Deploy atômico do código API. Rollback por revert.

## Open Questions

Nenhuma — comportamento de rejeição 422 no send sem documentos é o contrato já implícito da prévia (`can_send=false`).
