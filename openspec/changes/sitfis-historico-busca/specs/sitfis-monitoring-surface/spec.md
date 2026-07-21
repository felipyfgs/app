## ADDED Requirements

### Requirement: Histórico local de buscas SITFIS

O hub SHALL expor `GET /api/v1/fiscal/sitfis/clients/{client}/history` autenticado no escopo do office atual, retornando payload sanitizado no formato:

- `client`: `{ id, legal_name, cnpj_masked }`
- `searches`: lista de consultas concluídas do cliente, consolidada por `run_id` para que reprocessamentos locais do mesmo relatório apareçam uma única vez; cada item contém `id`, `observed_at`, `situation`, `version`, `is_current`, `evidence_artifact_id` (nullable) e `links.evidence_download` (path do download autenticado existente quando houver artefato; null/ausente caso contrário)

A lista MUST ser ordenada por `observed_at` descendente. A abertura deste endpoint MUST NOT disparar consulta SERPRO, enqueue de run SITFIS nem mutação de snapshot. Autorização MUST seguir o mesmo TenantAuthorization / office scope das demais leituras fiscais SITFIS; cliente fora do office MUST resultar em 404 (ou equivalente fail-closed), sem vazar dados de outro tenant.

#### Scenario: Lista consultas com e sem evidência

- **WHEN** o operador autenticado no office solicita o histórico de um cliente com múltiplas versões SITFIS, algumas com `evidence_artifact_id` e outras sem
- **THEN** a resposta inclui todas as consultas SITFIS concluídas do cliente no office, ordenadas da mais recente para a mais antiga
- **AND** itens com artefato expõem `links.evidence_download` apontando para `/api/v1/fiscal/evidence/{id}/download`
- **AND** itens sem artefato mantêm a data (`observed_at`) com `evidence_artifact_id` e link de download nulos ou ausentes

#### Scenario: Reprocessamento local não cria busca duplicada

- **WHEN** dois snapshots compartilham o mesmo `run_id` porque um relatório existente foi reprocessado localmente
- **THEN** o histórico MUST retornar uma única linha para a consulta
- **AND** MUST usar a versão de snapshot mais recente como metadado canônico, preservando a data original da busca

#### Scenario: Abrir histórico não chama SERPRO

- **WHEN** a UI ou um cliente HTTP chama apenas `GET .../sitfis/clients/{client}/history`
- **THEN** o sistema MUST NOT iniciar fluxo Integra/SERPRO SITFIS nem criar run de refresh
- **AND** MUST retornar apenas projeção local dos snapshots já persistidos

#### Scenario: Isolamento por office

- **WHEN** um usuário tenta o histórico de um `client` que não pertence ao office atual
- **THEN** a API MUST responder 404 (ou equivalente) sem retornar buscas de outro office

### Requirement: Histórico de Busca SITFIS incorporado ao detalhe da empresa

A UI do painel SHALL oferecer o histórico operacional de SITFIS via:

1. Item do menu Ações ⋮ na carteira `/monitoring/sitfis` (label **Histórico de busca**) que navega para `/monitoring/clients/:id/sitfis` — MUST NOT adicionar coluna Histórico na grade nem abrir modal.
2. Painel **Histórico de Busca** incorporado diretamente à seção SITFIS do detalhe do cliente, abaixo do resumo do snapshot atual.

O painel SHALL exibir cabeçalho com razão social e CNPJ mascarado e tabela com colunas **Data da Busca** e **Arquivo**. Cada linha com evidência MUST permitir download autenticado do PDF/relatório; linhas sem evidência MUST exibir a data e "Arquivo indisponível", sem ação de download. Abrir a seção MUST NOT disparar refresh SERPRO. A UI MUST NOT inventar situação "Em dia" a partir do PDF.

#### Scenario: Menu ⋮ navega para o histórico da empresa

- **WHEN** o operador abre o menu Ações de uma linha na carteira SITFIS e escolhe "Histórico de busca"
- **THEN** a UI navega para `/monitoring/clients/:id/sitfis`, onde o painel "Histórico de Busca" carrega o endpoint de history do cliente
- **AND** a grade da carteira MUST NOT expor coluna dedicada de Histórico
- **AND** a página da empresa MUST oferecer a ação "Abrir carteira SITFIS" para o caminho inverso

#### Scenario: Detalhe do cliente incorpora o histórico

- **WHEN** o operador abre o painel SITFIS do detalhe do cliente
- **THEN** o histórico local daquele cliente é exibido diretamente na página, abaixo do resumo atual
- **AND** MUST NOT chamar `POST .../sitfis/refresh` só por abrir o histórico

#### Scenario: Download só com artefato

- **WHEN** uma linha do histórico não possui `evidence_artifact_id`
- **THEN** a UI exibe a data da busca
- **AND** MUST NOT oferecer download efetivo de arquivo inexistente
