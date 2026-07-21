## Why

A review Bugbot nas alterações não commitadas encontrou regressões de comportamento: comunicação automática PGDAS-D disparada por serviços Simples/MEI errados, envio (manual/automático) ignorando a guarda de documentos locais, lista office-wide de guias carregando o dataset inteiro em memória, e ordenação da coluna RBT12 desalinhada do valor exibido na carteira.

## What Changes

- Restringir o hook pós-consulta agendada para enfileirar comunicação PGDAS-D só quando o `service_code` for PGDASD (e PGMEI só para PGMEI); demais serviços Simples/MEI (DEFIS, CCMEI, DASN, etc.) MUST NOT enfileirar PGDAS-D.
- Alinhar `requestSend` e o envio automático ao mesmo critério de `can_send` da prévia: para PGDAS-D, exigir artefatos locais antes de enfileirar.
- Manter a lista unificada de guias (tax_guides + DAS + DARF), mas paginar/agregar sem materializar o universo inteiro do office em memória.
- Fazer a ordenação por RBT12 da carteira PGDASD usar a mesma precedência de seleção do valor exibido em `portfolioDetails`.

Non-goals: ligar provider externo de comunicação; SERPRO live; parecer jurídico; mutações fiscais novas; flags ON por default; canais SEFAZ; serviços mei no Compose; ops backup/restore.

## Capabilities

### New Capabilities

- `monitoring-communication-send-guards`: contrato de roteamento e elegibilidade (documentos locais) para envio manual/automático de comunicação template nos submódulos Simples/MEI.
- `monitoring-guides-portfolio-consistency`: contrato de lista unificada de guias com paginação segura e ordenação RBT12 alinhada ao valor de carteira.

### Modified Capabilities

- (nenhuma — `openspec/specs/` está vazio; contratos vivem como capabilities novas desta change)

## Impact

- API: `FiscalMonitoringRunService`, `PgdasdCommunicationService`, `ClientGuidesQueryService` / `TaxGuideController`, `ModulePortfolioQueryService`.
- Testes Feature/Unit na área de comunicação, guias e portfolio PGDASD.
- Frontend: sem mudança de contrato de UI esperada (mesmos payloads; send passa a rejeitar 422 sem documentos).

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs vazias; comportamento de referência em changes arquiváveis `standardize-monitoring-portfolio-columns` (comunicação) e `fix-monitoring-guides-central-empty` (lista unificada).
- Depende de: nenhuma
- Capability/contrato: `monitoring-communication-send-guards`, `monitoring-guides-portfolio-consistency`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: correção das regressões Bugbot no working tree
- Paralelismo: pode seguir em paralelo com changes de UX PGDASD que não toquem os mesmos métodos de send/guias/sort RBT12
