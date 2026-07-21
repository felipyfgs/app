## Context

SITFIS monitoring já orquestra `SOLICITARPROTOCOLO91` → espera → `RELATORIOSITFIS92`, mas em produção a carteira fica em Erro/Bloqueado: (1) o path monitoring não chama `EnsureClientProcuracaoForConsult` para o poder `00002`; (2) HTTP 304 no `/Apoiar` é tratado como cache vazio — a doc SERPRO define 304 como sucesso com `protocoloRelatorio` no `ETag`; (3) Failed/Blocked sem PDF ainda viram `is_current` e demovem snapshot bom.

Justificativa transversal (2 capabilities): ensure e protocolo/persistência são o mínimo para o mesmo resultado implantável (relatório real na carteira); separar deixaria a change incompleta.

## Goals / Non-Goals

**Goals:**

- Ensure `00002` antes de qualquer call SITFIS no monitoring/refresh.
- Honrar 304 `/Apoiar`: extrair protocolo do ETag e seguir emit.
- Carteira não promove falha sem evidência a `is_current`.

**Non-Goals:**

- Redesign UI; kill-switch ON; mudar `FISCAL_PROFILE`; mei no Compose; bilhetagem Trial live; parecer jurídico.

## Decisions

1. **Ensure em `SitfisFlowService` (não só no adapter)**  
   Espelhar `SimplesMeiAdapter`: chamar `EnsureClientProcuracaoForConsult` com `['00002']` antes do primeiro `call()`. Alternativa (só manual consult) rejeitada — monitoring bulk é o path quebrado.

2. **304 = sucesso de cache (doc SERPRO)**  
   Parsear `$response->etag` com `protocoloRelatorio[=:](.+)`. Remover force-retry como caminho padrão. Alternativa (requeue sem protocolo) rejeitada — perde o protocolo válido já retornado.

3. **`is_current` exige evidência em Failed/Blocked/Skipped**  
   Em `FiscalSnapshotPersistence::createSnapshot`, sem `evidenceId` esses resultados não demovem o corrente. Alternativa (filtrar só na query da carteira) rejeitada — deixa `is_current` mentiroso.

## Risks / Trade-offs

- [Risk] Sync de procurações falha → Mitigation: fail-closed `PROXY_POWER_MISSING` / código do ensure; sem call SITFIS.
- [Risk] ETag malformado → Mitigation: fallback force-retry uma vez; depois `SITFIS_PROTOCOL_MISSING` / requeue curto.
- [Risk] Protocolo no ETag é sensível → Mitigation: não logar token completo; já sanitizado em attempt store.
- [Risk] Bilhetagem emit → Mitigation: inalterado; `/Apoiar` não bilheta; 304 não bilheta.

## Mapa de dependências

- C0; ownership: `SitfisFlowService`, `FiscalSnapshotPersistence`, deltas das 2 specs.
- Coordenada com `completar-sitfis-monitoring-integra` (UI); não editar artefatos dela.
- Rollout: deploy API; rollback = revert PR (comportamento anterior).

## Migration Plan

Sem migration de schema. Após deploy, refresh SITFIS nos clientes com `00002` no e-CAC regenera protocolo/PDF.

## Open Questions

Nenhuma — contrato 304 documentado em apicenter (cache SITFIS).
