## Context

Snapshots SITFIS já são versionados em `fiscal_snapshots` (`system_code`/`service_code` configuráveis, default `INTEGRA_SITFIS`/`SITFIS`), com `observed_at`, `situation`, `version`, `is_current` e `evidence_artifact_id` opcional. O download autenticado `GET /api/v1/fiscal/evidence/{id}/download` já existe. A carteira DCTFWeb já tem o padrão UI/API de histórico local (`DctfwebHistoryModal` + `GET /fiscal/dctfweb/clients/{id}/history`) que **não** dispara SERPRO ao abrir.

Hoje a carteira `/monitoring/sitfis` e o painel SITFIS em `/monitoring/clients/:id` só expõem o estado corrente. O operador não tem controle operacional sobre buscas anteriores nem download de PDFs históricos.

## Goals / Non-Goals

**Goals:**

- Endpoint dedicado de histórico SITFIS por cliente, escopo office, read-only, sem side-effect SERPRO.
- Painel "Histórico de Busca" incorporado à seção SITFIS da empresa, com header (razão social + CNPJ) e tabela Data da Busca | Arquivo.
- Entrada no menu Ações ⋮ da carteira (label "Histórico de busca") navegando para o detalhe e caminho inverso "Abrir carteira SITFIS".
- Download só quando `evidence_artifact_id` existir; linhas sem evidência ainda listam a data.
- Testes API + web na mesma change.

**Non-Goals:**

- Parse/extração de texto do PDF; inventar "Em dia" ou mudar badges.
- Endpoint genérico de snapshots com filtro no client.
- Coluna Histórico na grade (`monitoring-portfolio-columns` mantém histórico só no ⋮).
- Flags SERPRO/MEI ON, mei no Compose, mutações fiscais, parecer jurídico, ops backup/restore.

## Decisions

1. **Endpoint dedicado (não snapshots genéricos)**  
   `GET /api/v1/fiscal/sitfis/clients/{client}/history` retorna:
   ```json
   {
     "data": {
       "client": { "id": 1, "legal_name": "...", "cnpj_masked": "..." },
       "searches": [
         {
           "id": 10,
           "observed_at": "2026-07-21T12:00:00+00:00",
           "situation": "ATTENTION",
           "version": 3,
           "is_current": true,
           "evidence_artifact_id": 99,
           "links": { "evidence_download": "/api/v1/fiscal/evidence/99/download" }
         }
       ]
     }
   }
   ```
   Ordenação: `observed_at` desc (desempate `version` desc / `id` desc). Snapshots com o mesmo `run_id` são consolidados e a versão mais alta/mais recente fornece os metadados, pois reprocessamento local não representa nova consulta.  
   **Alternativa rejeitada:** reutilizar listagem genérica de snapshots + filtro no front — vaza contrato de domínio e dificulta auth/shape estável.

2. **Critério de inclusão das linhas**  
   Incluir todas as consultas SITFIS concluídas do cliente no office que tenham snapshot persistido para o par system/service SITFIS. Consolidar snapshots pelo `run_id`; snapshots sem `run_id` permanecem linhas independentes. Não inventar linhas a partir de runs em voo sem snapshot. Download habilitado **somente** se `evidence_artifact_id` presente; caso contrário `evidence_artifact_id`/`links.evidence_download` null e a UI mostra "Arquivo indisponível", mantendo a data.

3. **Auth e tenancy**  
   Mesmo guard de leitura fiscal dos outros endpoints SITFIS/DCTFWeb: Sanctum + office context (`CurrentOffice`) + `TenantAuthorization` / permissão de leitura fiscal. Cliente MUST pertencer ao office atual; rejeitar `office_id` do body/query. PLATFORM_ADMIN sem office fiscal implícito.

4. **UI incorporada ao detalhe da empresa**  
   - Componente `SitfisHistoryView.vue`, título "Histórico de Busca", card/header com `legal_name` + `cnpj_masked`, tabela colunas "Data da Busca" | "Arquivo" (ícone download), diretamente abaixo do resumo do snapshot atual.  
   - Composable `useSitfisMonitoring().fetchHistory(clientId)` + tipos em `fiscal-modules.ts`.  
   - Carteira: item no menu ⋮ via `sitfis-table.ts` navega para `/monitoring/clients/:id/sitfis` — **não** coluna `history` e **não** modal.  
   - Detalhe: mantém "Abrir carteira SITFIS" para navegação inversa.  
   - Abrir a seção MUST NOT chamar refresh/SERPRO — apenas `show` e o GET history local.

5. **Camada API**  
   Preferir query service dedicado (ex. método em serviço SITFIS existente ou `SitfisHistoryQueryService`) + action no `SitfisSituationController` (ou controller de monitoring SITFIS se já houver padrão local). Reusar `FiscalEvidenceStore`/rota de download existente — não duplicar stream.

6. **Copy e locale**  
   Labels pt_BR: "Histórico de Busca" / "Histórico de busca" no menu; "Data da Busca"; "Arquivo". Sem copy jurídica.

## Risks / Trade-offs

- [Bilhetagem SERPRO acidental] → Mitigação: history/read-only; testes garantem que abrir histórico não enfileira run nem chama executor; fail-closed das flags permanece.
- [Vazamento entre offices] → Mitigação: filtro estrito `office_id` + client do office; Feature test cross-tenant 404.
- [Linhas sem PDF] → Mitigação: listar data; desabilitar/omitir download; não inventar artefato.
- [Conflito com changes SITFIS ativas (integração/replay)] → Mitigação: ownership limitado a history endpoint + UI incorporada; não editar FlowService/parser/replay além de rota/wiring necessários.
- [Reprocessamento duplica relatório] → Mitigação: consolidar por `run_id` e selecionar o snapshot canônico mais recente da consulta.
- [Volume de versões] → Mitigação: ordenação desc e conteúdo no scroll do painel; paginação fora de escopo nesta change (pode evoluir depois se necessário).

## Mapa de dependências

- Change **C0**; sem upstream ativo bloqueante.
- Changes SITFIS ativas (`corrigir-sitfis-*`) são coordenadas em ownership: esta change não altera replay/parser; aquelas não devem tocar o contrato de history.
- Rollout: deploy API+web juntos (endpoint novo + UI). Rollback: reverter rota/UI; dados de snapshot permanecem.
- Gates: API (pint + Feature history) e Web (lint/typecheck/test do painel/menu) + `openspec validate --change sitfis-historico-busca --strict`.
