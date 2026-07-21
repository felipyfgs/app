## Context

O fluxo Integra SITFIS (solicit `SOLICITARPROTOCOLO91` → espera mínima → emit `RELATORIOSITFIS92`) e a carteira Nuxt já existem. Lacunas: UI de comunicação stub, `publicView` sem link de evidência tipado, ausência de cenários Trial em config, e cobertura de testes insuficiente para a coverage matrix.

Exceção de 2 capabilities: `sitfis-monitoring-surface` (nova superfície operacional) + delta em `sitfis-protocol-persist` (evidência no show) — justificado porque o contrato de protocolo/refresh e a superfície de carteira são ownerships distintos mas a evidência no `publicView` pertence ao contrato de leitura do snapshot.

## Goals / Non-Goals

**Goals**

- Wire comunicação SITFIS na UI ao contrato HTTP já existente (`MonitoringModuleCommunicationController` + `SitfisCommunicationService`).
- Expor download autenticado de evidência no `GET /fiscal/sitfis`.
- Tipar show/refresh no cliente web.
- Registrar cenários Trial oficiais e garantir fixtures utilizáveis no driver fixture.
- Testes de fases async, parser, controller e smoke de comunicação.

**Non-Goals**

- Reescrever `SitfisFlowService` ou inventar `operation_key`/`idServico`.
- Ligar providers de comunicação (fail-closed).
- Smoke live PRODUCTION / `PRODUCTION_VALIDATED`.
- Mutações fiscais, flags ON, mei no Compose, ops backup/restore.

## Decisions

1. **Evidência no `publicView`, não endpoint novo**  
   Reusar `GET /api/v1/fiscal/evidence/{id}/download` já autenticado. `SitfisSnapshotService::publicView` passa a incluir `evidence_artifact_id` e `links.evidence_download` quando o snapshot tiver artefato.  
   Alternativa rejeitada: endpoint `/fiscal/sitfis/.../download` dedicado — duplicaria o gate de download genérico.

2. **Cliente web espelha DCTFWeb**  
   Bloco `api.fiscal.sitfis.communication` + modais de preview/tracking/prefs na página; `detail.communication` do portfolio já enriquecido.  
   Alternativa rejeitada: composable genérico novo nesta change — escopo mínimo é wire SITFIS.

3. **Trial scenarios só como identidades mock oficiais**  
   Entradas em `serpro.environments.TRIAL.scenarios` para `sitfis.solicitar_protocolo` / `sitfis.emitir_relatorio` com CNPJs/dados da doc de cenários Trial — sem hardcodar tokens. Fixtures sintéticas já existem; testes unitários do Flow usam doubles/fixtures, não gateway live.

4. **Entrypoint SERPRO inalterado**  
   Domínio continua só via `SerproOperationExecutor` + catálogo. Testes mockam o executor ou usam fixture driver.

## Risks / Trade-offs

- [Fixture sintético com `EXEMPLO_SINTETICO` não exercita parse PDF real] → Mitigação: testes do parser com JSON de layout conhecido + caminho de layout desconhecido; Flow testa orquestração de fases, não bytes oficiais.
- [UI de comunicação sem enrichment em linhas antigas] → Mitigação: fail-closed se `communication` ausente (controles desabilitados), igual DCTF/PGDAS.
- [Dois specs na mesma change] → Aceito: ownership protocolo vs superfície; archive sincroniza ambos.

## Migration Plan

- Deploy backward-compatible: campos novos no `publicView` são aditivos; clientes antigos ignoram.
- Rollback: reverter PR; endpoints de comunicação e fluxo async permanecem.

## Open Questions

Nenhuma — escopo fechado no proposal.
