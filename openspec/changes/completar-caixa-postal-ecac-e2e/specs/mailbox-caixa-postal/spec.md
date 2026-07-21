## ADDED Requirements

### Requirement: Sync LISTAR enfileira DETALHE para mensagens sem corpo
Após um sync LISTAR bem-sucedido para um cliente, o sistema SHALL enfileirar até N runs da operação DETALHE (`caixa_postal.detalhe`) para mensagens do mesmo office/cliente com `has_body = false`, ordenadas priorizando não lidas oficiais e mais recentes. N SHALL ser configurável (`fiscal_monitoring.mailbox.max_detail_fetches_per_sync`, default 10). O sistema MUST NOT enfileirar quando o módulo/flag mailbox estiver desabilitado. O sistema MUST NOT criar run duplicada se já existir run pendente ou em execução para o mesmo `external_id`/`isn`.

#### Scenario: LISTAR bem-sucedido enfileira DETALHE limitado
- **WHEN** um LISTAR conclui com sucesso e existem mais de N mensagens sem corpo
- **THEN** no máximo N runs DETALHE são enfileiradas
- **AND** as selecionadas são as de maior prioridade (não lidas / mais recentes)

#### Scenario: Fail-closed sem módulo
- **WHEN** o módulo mailbox está desabilitado para o office
- **THEN** nenhum DETALHE é enfileirado após LISTAR

#### Scenario: Idempotência por mensagem
- **WHEN** já existe run DETALHE pendente para o mesmo `external_id` do cliente
- **THEN** o sistema não cria outra run para essa mensagem

### Requirement: API de leitura, triagem e corpo preserva invariantes
A API tenant-scoped em `/api/v1/fiscal/mailbox/*` SHALL expor listagem paginada, detalhe, triagem interna (`NEW`|`IN_REVIEW`|`RESOLVED`), download de corpo/anexos existentes, state DTE por `client_id` e alerts sanitizados. Triagem interna MUST NOT alterar `official_read_indicator`. Conteúdo de corpo MUST NOT aparecer no JSON de listagem. Leitura exige permissão de operations view e módulo habilitado; triagem exige permissão de triage e mutação habilitada.

#### Scenario: Triagem não altera leitura oficial
- **WHEN** um OPERATOR aplica triagem `RESOLVED` em uma mensagem
- **THEN** a resposta reflete o novo `triage_status`
- **AND** `official_read_indicator` permanece inalterado

#### Scenario: Body stream para preview
- **WHEN** um usuário autorizado solicita `GET .../messages/{id}/body` e a mensagem tem corpo no vault
- **THEN** o sistema retorna stream com Content-Type adequado (texto)
- **AND** registra evento de acesso de download/view conforme política existente

#### Scenario: State exige client_id
- **WHEN** `GET /fiscal/mailbox/state` é chamado sem `client_id` válido
- **THEN** a API rejeita a requisição com erro de validação

### Requirement: UI inbox com preview, alerts e deep-link de cliente
A superfície `/monitoring/mailbox` SHALL exibir lista + detalhe (arquétipo Inbox), preview autenticado do corpo quando `has_body` for verdadeiro, mensagem clara quando o corpo ainda não estiver sincronizado, labels de triagem em pt-BR, e uma faixa compacta de alerts ativos. Deep-link a partir da aba mailbox do cliente SHALL aplicar filtro de cliente via handoff em memória e MUST NOT serializar filtros na query string da URL Nuxt (path permanece `/monitoring/mailbox` ou `/monitoring/mailbox/{id}`).

#### Scenario: Preview do corpo
- **WHEN** o operador abre uma mensagem com `has_body=true`
- **THEN** o painel de detalhe exibe o texto do corpo obtido via fetch autenticado
- **AND** o botão de download permanece disponível

#### Scenario: Corpo pendente de sync
- **WHEN** o operador abre uma mensagem com `has_body=false`
- **THEN** o painel informa que o corpo ainda não foi sincronizado

#### Scenario: Deep-link da aba cliente
- **WHEN** o operador clica em Abrir caixas postais na aba mailbox de um cliente
- **THEN** a navegação vai para `/monitoring/mailbox` sem query de filtro
- **AND** a lista aplica o filtro daquele `client_id` via handoff

#### Scenario: Alerts no topo
- **WHEN** existem alerts ativos de mailbox para o office
- **THEN** a página mailbox exibe uma faixa/lista compacta alimentada por `GET /fiscal/mailbox/alerts`
