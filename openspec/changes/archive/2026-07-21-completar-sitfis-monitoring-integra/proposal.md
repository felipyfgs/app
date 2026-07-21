## Why

A carteira `/monitoring/sitfis` e o fluxo Integra Contador (solicit → espera → emit → parse) já existem, mas a UI ainda trata comunicação como indisponível, o contrato `show`/`refresh` não tipa evidência/download, e o caminho Trial/fixtures + testes profundos do fluxo async estão incompletos — o escritório não consegue operar SITFIS como as demais carteiras.

## What Changes

- Expor no `GET /api/v1/fiscal/sitfis` (`publicView`) o `evidence_artifact_id` e o link autenticado de download quando houver artefato.
- Wire da coluna Comunicação na UI SITFIS aos endpoints genéricos `/fiscal/sitfis/clients/{id}/communication-*` (padrão DCTFWeb), removendo toasts de stub.
- Tipar respostas `show`/`refresh` no cliente fiscal web.
- Registrar cenários Trial SITFIS em `config/serpro.php` e validar fixtures solicit/emit para o driver fixture.
- Cobrir com testes unitários/feature o `SitfisFlowService`, `SitfisReportParser`, `SitfisSituationController` e smoke de comunicação SITFIS.

Non-goals: reescrever o fluxo async; inventar `idServico`; ligar providers de e-mail/WhatsApp (permanecem fail-closed); smoke live produção; mutações fiscais; flags ON; mei no Compose; ops backup/restore.

## Capabilities

### New Capabilities

- `sitfis-monitoring-surface`: contrato da carteira SITFIS operacional — comunicação wired, evidência no show, tipagem client, Trial/fixtures e cobertura de testes do caminho Integra.

### Modified Capabilities

- `sitfis-protocol-persist`: estender o contrato de `publicView`/refresh para incluir evidência downloadável quando o snapshot tiver artefato (sem alterar cursor/protocolo).

## Impact

- API: `SitfisSnapshotService::publicView`, `config/serpro.php` (Trial scenarios), fixtures SERPRO, testes em `apps/api/tests`.
- Web: `createFiscalApi.ts`, `sitfis.vue`, `types/fiscal-modules.ts`, testes unitários SITFIS.
- Specs: nova `sitfis-monitoring-surface`; delta em `sitfis-protocol-persist`.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs `sitfis-protocol-persist`, `monitoring-portfolio-columns`, `monitoring-communication-send-guards`
- Depende de: nenhuma
- Capability/contrato: `sitfis-monitoring-surface`, `sitfis-protocol-persist`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: implementação da carteira SITFIS completa no painel
- Paralelismo: pode rodar em paralelo com changes que não toquem `SitfisSnapshotService`/`sitfis.vue`
