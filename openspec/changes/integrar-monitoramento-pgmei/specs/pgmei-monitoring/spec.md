## ADDED Requirements

### Requirement: Área Simples/MEI possui somente duas cápsulas
O sistema SHALL manter `/monitoring/simples-mei` como rota canônica e SHALL renderizar somente `Simples Nacional` com identificação `PGDAS-D` e `MEI` com identificação `PGMEI`, usando estado local e PGDAS-D como padrão.

#### Scenario: Abertura da rota canônica
- **WHEN** o usuário abre `/monitoring/simples-mei`
- **THEN** o sistema mostra somente as duas cápsulas e ativa `Simples Nacional`

#### Scenario: Troca rápida de cápsulas
- **WHEN** o usuário alterna entre PGDAS-D e PGMEI antes de uma consulta anterior terminar
- **THEN** o sistema reinicia paginação e filtros exclusivos e descarta a resposta obsoleta

### Requirement: Regime e DASN-SIMEI não são superfícies públicas
O sistema SHALL remover `Regime` e `DASN-SIMEI` das cápsulas, do registro de superfícies, da matriz frontend e dos monitores automáticos, mas MUST preservar `RegimeApplicabilityService`, o domínio declaratório DASN-SIMEI e dados históricos.

#### Scenario: Submódulo público removido
- **WHEN** a API de portfólio recebe `submodule=REGIME` ou `submodule=DASN_SIMEI`
- **THEN** o sistema responde `422` sem retornar dados de outro submódulo

#### Scenario: Deep link legado
- **WHEN** o usuário abre `/monitoring/simples-mei/regime` ou `/monitoring/simples-mei/dasn-simei`
- **THEN** o sistema redireciona para `/monitoring/simples-mei` com PGDAS-D ativo

#### Scenario: Validação interna de aplicabilidade
- **WHEN** um cliente MEI seria consultado como PGDAS-D ou um cliente do Simples como PGMEI
- **THEN** `RegimeApplicabilityService` continua impedindo a consulta incompatível

### Requirement: Tabela PGMEI é especializada em dívida ativa
O sistema SHALL apresentar, nesta ordem e com estes rótulos, as colunas de negócio: Situação, Ações, Enviar, Cliente, Rastreio de envio, Última Busca e Histórico de Busca. A projeção operacional da carteira PGMEI SHALL usar o ano-calendário corrente de forma fixa (sem seletor de ano na interface). O shell SHALL acrescentar seleção antes de Situação somente para ADMIN e OPERATOR, sem contá-la como coluna de negócio. O sistema MUST NOT criar colunas independentes para Dívida ativa, Total inscrito, Últ. Declaração, Sublimite (RBT12), Automático ou Detalhes — dívida, total e frescor alimentam Situação e o tooltip.

#### Scenario: Dívida ativa encontrada
- **WHEN** a última consulta válida do ano contém ao menos um item
- **THEN** Situação mostra Dívida ativa (ou rótulo operacional equivalente) e seu tooltip informa ano, quantidade, soma exata em centavos, frescor e última consulta

#### Scenario: Nenhuma dívida no ano
- **WHEN** a última consulta válida não contém itens
- **THEN** Situação mostra Sem dívida no ano em verde e seu tooltip explicita o ano consultado

#### Scenario: Estado não verificado
- **WHEN** inexiste consulta produtiva válida ou a resposta não pode ser interpretada
- **THEN** a tabela mostra `UNVERIFIED` em cinza sem afirmar inexistência de dívida

#### Scenario: Cliente identificado
- **WHEN** uma linha PGMEI é renderizada
- **THEN** Cliente mostra razão social em destaque e CNPJ abaixo

#### Scenario: Ações e envio automático
- **WHEN** ADMIN ou OPERATOR interage com a linha
- **THEN** Ações oferece ícone de prévia e menu contextual, Enviar controla `automatic_requested` por linha (ou atalho de configuração quando canal/contato falta) e o switch do cabeçalho de Enviar aplica a intenção em massa somente aos clientes selecionados

#### Scenario: Rastreio compacto
- **WHEN** a linha é renderizada
- **THEN** Rastreio de envio reúne status, anexo local (quando houver) e acesso ao modal de rastreio

#### Scenario: Última busca e histórico
- **WHEN** existe consulta válida do serviço 24 para o ano selecionado
- **THEN** Última Busca mostra data compacta com data e hora completas no tooltip e Histórico de Busca abre o modal local
### Requirement: Frescor não oculta dívida observada
O sistema SHALL retornar `freshness_state=CURRENT` até sete dias após a última consulta válida e `OUTDATED` depois desse período, preservando o estado e total observados.

#### Scenario: Dívida desatualizada
- **WHEN** uma projeção com dívida tem mais de sete dias
- **THEN** a interface mostra aviso `OUTDATED` e continua exibindo a dívida e o total

### Requirement: Monitor PGMEI usa somente DIVIDAATIVA24
O sistema SHALL mapear o monitor PGMEI exclusivamente para `PGMEI/DIVIDAATIVA24/1.0`, rota `Consultar`, com exatamente um `anoCalendario`, e SHALL remover o mapeamento fictício `CONSULTAR_DAS`.

#### Scenario: Construção do payload anual
- **WHEN** o scheduler ou a consulta manual seleciona o ano 2026
- **THEN** o envelope de dados contém `{ "anoCalendario": "2026" }`

#### Scenario: Navegação observacional
- **WHEN** o usuário troca de cápsula, abre detalhes, prévia ou rastreio
- **THEN** nenhuma operação `GERARDASPDF21`, `GERARDASCODBARRA22` ou `ATUBENEFICIO23` é chamada

### Requirement: Dívida ativa possui projeção e histórico tenant-scoped
O sistema SHALL persistir uma projeção por escritório, cliente e ano, observações válidas imutáveis e itens normalizados com PA, tributo, valor em centavos, ente federado e situação original.

#### Scenario: Resposta válida com múltiplos itens
- **WHEN** `DIVIDAATIVA24` retorna itens para um cliente e ano
- **THEN** o sistema cria uma observação, seus itens e atualiza atomicamente a projeção com contagem e soma exata

#### Scenario: Falha ou simulação
- **WHEN** a execução falha, é simulada ou tem resposta ambígua
- **THEN** o sistema não promove a execução como última consulta válida nem substitui uma projeção confiável

#### Scenario: Isolamento entre escritórios
- **WHEN** dois escritórios possuem clientes ou anos coincidentes
- **THEN** nenhuma listagem, histórico ou mutação de um escritório acessa registros do outro

### Requirement: Cobertura anual é controlada
O sistema SHALL alternar deterministicamente um dos cinco anos mais recentes em ciclos diários, limitando o monitor automático a uma chamada por cliente por ciclo, e SHALL limitar a consulta manual confirmada a 100 clientes.

#### Scenario: Ciclos sucessivos
- **WHEN** cinco ciclos diários completos são executados
- **THEN** cada um dos cinco anos mais recentes é selecionado uma vez por cliente elegível

#### Scenario: Lote manual acima do limite
- **WHEN** uma consulta manual contém mais de 100 clientes
- **THEN** a API responde `422` antes de criar qualquer execução

### Requirement: APIs PGMEI usam apenas estado local ao visualizar
O sistema SHALL incluir `detail.pgmei` na listagem e SHALL oferecer histórico local por cliente/ano e consulta manual explícita; leitura de histórico e DAS já existentes não poderá disparar chamada SERPRO.

#### Scenario: Leitura dos detalhes
- **WHEN** o usuário abre o modal PGMEI
- **THEN** a API retorna itens, histórico anual e referências locais da Central de Guias sem chamar a SERPRO

#### Scenario: Office informado pelo cliente
- **WHEN** qualquer endpoint recebe `office_id` em query ou body
- **THEN** o sistema rejeita o campo e resolve o tenant exclusivamente por `CurrentOffice`

### Requirement: Comunicação PGMEI permanece em modo template
O sistema SHALL reutilizar preferências, prévia e rastreamento com contexto isolado `PGMEI`, retornando `execution_mode=TEMPLATE_ONLY`, `automatic_effective=false` e sem criar entrega real.

#### Scenario: Preferências independentes
- **WHEN** um usuário altera o switch automático do PGMEI
- **THEN** a preferência PGDAS-D do mesmo cliente permanece inalterada

#### Scenario: Tentativa de envio
- **WHEN** o usuário abre a prévia de envio PGMEI
- **THEN** destinatários aparecem mascarados, `can_send=false` e nenhum mail, job, provider, dispatch ou evento de entrega é criado
