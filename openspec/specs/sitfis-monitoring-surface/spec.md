## Purpose

Capability `sitfis-monitoring-surface` — requisitos sincronizados das changes OpenSpec.

## Requirements

### Requirement: Comunicação SITFIS wired na carteira
A página `/monitoring/sitfis` SHALL expor a coluna Comunicação com preferência, prévia, rastreio e envio via endpoints `/api/v1/fiscal/sitfis/clients/{client}/communication-*`. A UI MUST NOT exibir toasts de “indisponível” quando o enrichment `detail.communication` estiver presente. Controles MUST permanecer desabilitados (fail-closed) quando `communication` estiver ausente. O envio efetivo via provider MUST permanecer fail-closed (desligado por default).

#### Scenario: Switch de automático persiste preferência
- **WHEN** o operador alterna o switch de envio automático numa linha com `communication` enriquecido
- **THEN** a UI MUST chamar `PATCH .../communication-preference` com `automatic_requested` e `lock_version`
- **AND** MUST NÃO exibir toast de “Preferência indisponível”

#### Scenario: Sem enrichment desabilita controles
- **WHEN** a linha do portfolio não inclui `detail.communication`
- **THEN** send, switch e rastreio MUST estar desabilitados ou sem ação efetiva
- **AND** MUST NÃO afirmar sucesso de envio

### Requirement: Tipagem show e refresh SITFIS no cliente web
O cliente fiscal web SHALL tipar `GET /api/v1/fiscal/sitfis` e `POST /api/v1/fiscal/sitfis/refresh` com DTOs dedicados (não `Record<string, unknown>`), incluindo campos de situação, TTL/`can_refresh`/`block_reason`, e links de evidência quando presentes.

#### Scenario: Show tipado no slideover
- **WHEN** o operador abre o detalhe SITFIS de um cliente
- **THEN** a resposta de `sitfis.show` MUST ser consumida via tipo `SitfisShowResponse` (ou equivalente nomeado)
- **AND** campos `can_refresh`, `block_reason` e `links.evidence_download` MUST ser acessíveis tipados

### Requirement: Download de evidência no detalhe SITFIS
Quando o snapshot SITFIS tiver artefato de evidência, o `publicView` e a UI do slideover SHALL permitir download autenticado do PDF/relatório via o link exposto (ou `FiscalDocumentAction` do portfolio). Ausência de artefato MUST NÃO inventar link.

#### Scenario: Snapshot com artefato oferece download
- **WHEN** o snapshot ativo possui `evidence_artifact_id`
- **THEN** `GET /fiscal/sitfis` MUST incluir `evidence_artifact_id` e `links.evidence_download` apontando para `/api/v1/fiscal/evidence/{id}/download`
- **AND** o slideover MUST expor ação de download quando o link ou `document` do portfolio estiver disponível

#### Scenario: Sem artefato não inventa link
- **WHEN** o snapshot não tem evidência
- **THEN** `links.evidence_download` MUST ser null ou ausente
- **AND** a UI MUST NÃO oferecer download de relatório inexistente

### Requirement: Cenários Trial e fixtures SITFIS
O hub SHALL registrar em `config/serpro.php` cenários Trial para `sitfis.solicitar_protocolo` e `sitfis.emitir_relatorio` com identidades mock da documentação oficial (contratante/autor `00000000000000` tipo 2; contribuinte Trial documentado). Fixtures do driver `fixture` MUST cobrir resposta de solicitação (protocolo + tempoEspera) e emissão (pdf/`dados`). O sistema MUST NOT colocar bearer Trial real em `.env.example` ou commits.

#### Scenario: Config lista cenários SITFIS
- **WHEN** a configuração Trial é carregada
- **THEN** `environments.TRIAL.scenarios` MUST conter entradas para as operation keys SITFIS com `source_url` da doc oficial de cenários SITFIS

### Requirement: Cobertura de testes do caminho Integra SITFIS
A suíte API SHALL incluir testes que exercitem: (1) fases do `SitfisFlowService` (solicit → espera → emit processamento → emit sucesso/parse); (2) `SitfisReportParser` com layout conhecido e desconhecido (nunca afirmar certidão negativa); (3) `SitfisSituationController` show/refresh (`WITHIN_TTL`, `ERROR` re-enqueue, `force`); (4) smoke HTTP de communication preference para `module=sitfis` fail-closed no provider.

#### Scenario: FlowService avança fases com double
- **WHEN** um teste unitário executa o fluxo com executor/fixture controlado
- **THEN** a primeira execução MUST solicitar protocolo e requeue com espera
- **AND** após o protocolo e o prazo mínimo, a emissão MUST consumir o protocolo de `progress.protocol`

#### Scenario: Parser nunca afirma certidão negativa
- **WHEN** o parser recebe layout vazio ou desconhecido
- **THEN** o resultado MUST NÃO marcar certidão negativa
- **AND** MUST sinalizar atenção/layout desconhecido conforme contrato do parser
