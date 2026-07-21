## Why

Operadores precisam ver **quando** o SITFIS foi consultado e baixar relatórios históricos, mas a carteira e o detalhe do cliente só expõem o snapshot corrente (e download do artefato atual). Os dados já existem em `fiscal_snapshots` (versões `INTEGRA_SITFIS`/`SITFIS` com `evidence_artifact_id` opcional); falta uma superfície de histórico local, no mesmo espírito do DCTFWeb.

## What Changes

- Novo endpoint dedicado `GET /api/v1/fiscal/sitfis/clients/{client}/history` que lista uma linha por consulta concluída, consolidando versões locais do mesmo `run_id`, com metadados e link de download quando houver evidência — **sem** disparar SERPRO.
- UI `SitfisHistoryView` incorporada à seção SITFIS de `/monitoring/clients/:id/sitfis`: título "Histórico de Busca", card com razão social + CNPJ e tabela "Data da Busca" | "Arquivo" com botão de download por linha.
- Item no menu Ações ⋮ da carteira SITFIS (label "Histórico de busca") navega para a seção SITFIS da empresa — **não** abre modal e **não** adiciona coluna na grade. A página da empresa mantém o retorno "Abrir carteira SITFIS".
- Tipagem web + composable; testes API (Feature) e web (unit) cobrindo contrato, painel incorporado e navegação.
- Reuso do download autenticado existente `GET /api/v1/fiscal/evidence/{id}/download`.

Non-goals:
- Extração/parse de texto do PDF; inventar "Em dia" a partir do PDF; mudar semântica de badges/situação.
- Endpoint genérico de snapshots com filtro client-side.
- Ligar flags SERPRO/MEI, canais SEFAZ, serviços `mei`/`mei-worker` no Compose, ou mutações fiscais.
- Parecer jurídico; targets ops backup/restore indisponíveis; segredos em `.env.example`.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `sitfis-monitoring-surface`: passa a exigir histórico local de buscas SITFIS (API dedicada + painel incorporado ao detalhe do cliente + navegação bidirecional), sem consulta SERPRO ao abrir.

## Impact

- API: rota/controller/query SITFIS history (espelho DCTFWeb), auth TenantAuthorization/office scope, Feature tests.
- Web: `SitfisHistoryView`, wiring em `sitfis-table.ts`, painel SITFIS em `[clientId].vue`, tipos/`useSitfisMonitoring`, testes unitários.
- Dados: leitura de `fiscal_snapshots` existentes; sem migração.
- Compose/OpenSpec: validação da change com delta em `sitfis-monitoring-surface`.

### Dependências entre changes

- Nível: **C0**
- Bases estáveis: `sitfis-monitoring-surface`, `fiscal-authenticated-artifact-download`, `monitoring-portfolio-columns` (histórico só no ⋮)
- Depende de: nenhuma
- Desbloqueia: controle operacional de histórico SITFIS na carteira e no detalhe
- Paralelismo: ownership = superfície SITFIS (API history + painel web); não editar parsers/badges nem changes SITFIS de integração/replay ativas além do necessário para não conflitar
