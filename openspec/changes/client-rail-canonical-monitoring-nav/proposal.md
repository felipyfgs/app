## Why

O rail do detalhe fiscal por empresa usa labels e itens diferentes do sidebar Monitoramento (ex.: PGDAS-D, SITFIS, Pendências), o que confunde a IA do produto. Precisamos de um menu canônico espelhando os 12 contextos de Monitoramento, com troca de empresa no header do rail.

## What Changes

- Alinhar labels e ordem do rail `/monitoring/clients/:id` aos 12 itens de `MONITORING_NAV_ITEMS` (Dashboard, Simples Nacional, MEI, DCTFWeb, FGTS Digital, Parcelamentos, Situação Fiscal, Caixas Postais, Declarações, Guias, Cadastro e Vínculos, Processos Fiscais).
- Remover do rail itens inventados (Pendências) e seções internas (Execuções, Achados, Renúncias); deep-link → overview.
- Reexibir Cadastro e Vínculos e Processos Fiscais; adicionar seções DCTFWeb e Caixas Postais no detalhe.
- MEI (antes CCMEI) só quando o cliente for MEI.
- Header do sidebar: combobox/select para trocar de empresa preservando a seção quando válida.
- Overview de processos sincronizado com o mesmo catálogo/labels.
- Paths de seção (`/pgdasd`, `/ccmei`, …) permanecem estáveis (sem rename de URL).

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `client-fiscal-rail`: rail canônico espelhando Monitoramento; seções ocultas; MEI regime-gated; seletor de empresa no header.
- `company-monitoring-overview`: cards do overview alinhados ao catálogo canônico (labels/ordem; DCTFWeb e Caixas Postais).

## Impact

- Web: `client-fiscal-detail-navigation.ts`, `client-monitoring-overview.ts`, `ClientFiscalAside.vue`, `pages/monitoring/clients/[clientId].vue`, testes unitários do rail/overview.
- Sem mudança de API/backend; reuso de listagens já filtráveis por `client_id` (mailbox, DCTFWeb/declarações).
- Atalhos CRM Cadastro/Adicionais permanecem.

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: `openspec/specs/client-fiscal-rail`, `openspec/specs/company-monitoring-overview`
- Depende de: nenhuma
- Capability/contrato: `client-fiscal-rail`, `company-monitoring-overview`
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: implementação web do rail canônico
- Paralelismo: pode rodar em paralelo com changes que não toquem `client-fiscal-rail` / overview do cliente

### Non-goals

- Renomear paths de seção (`pgdasd` → `simples`, etc.)
- Alterar o sidebar global Monitoramento
- Remover atalhos CRM Cadastro/Adicionais
- Consultas SERPRO live novas, parecer jurídico, mutações fiscais, ligar flags fail-closed, canais SEFAZ, serviços mei no Compose, ops backup/restore
