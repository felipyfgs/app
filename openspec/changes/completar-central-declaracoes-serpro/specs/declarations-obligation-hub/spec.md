## MODIFIED Requirements

### Requirement: Declarações hub exposes obligation tabs

O painel `/monitoring/declarations` SHALL exibir abas locais PGDAS-D, DEFIS, DASN-SIMEI, DCTFWeb, MIT, FGTS Digital e DIRF. A URL SHALL permanecer `/monitoring/declarations` (submódulo não entra no path nem na query de navegação). A aba padrão SHALL ser PGDAS-D. O título da superfície SHALL refletir a aba ativa. A UI MUST distinguir as cinco obrigações do Integra Contador de FGTS Digital e DIRF, que são coberturas externas.

#### Scenario: Default tab is PGDAS

- **WHEN** o usuário abre `/monitoring/declarations`
- **THEN** a aba PGDAS-D está selecionada e a carteira carrega com `submodule=PGDAS`

#### Scenario: Switching tabs stays on the same path

- **WHEN** o usuário seleciona a aba DASN-SIMEI
- **THEN** a URL permanece `/monitoring/declarations`
- **AND** a carteira recarrega com `submodule=DASN_SIMEI` (página, filtros e modais resetados)

#### Scenario: External coverage is not presented as Integra Contador

- **WHEN** o usuário seleciona FGTS Digital ou DIRF
- **THEN** a aba apresenta estado localizado de fonte externa ao Integra Contador
- **AND** o sistema não associa coordenadas nem operações SERPRO a essa aba

### Requirement: Portfolio filters declarations by obligation submodule

A API de portfolio do módulo `declarations` SHALL aceitar `submodule` em `{PGDAS, DEFIS, DASN_SIMEI, DCTFWEB, MIT, FGTS, DIRF}` e MAY aceitar `DECLARACOES` como agregado legado. Overview e lista de clients SHALL aplicar o mesmo filtro de obrigação/origem. Valores não listados em `knownSubmodules()` SHALL ser rejeitados.

#### Scenario: PGDAS filters PGDAS_D projections

- **WHEN** o cliente solicita `GET /api/v1/fiscal/modules/declarations/clients?submodule=PGDAS`
- **THEN** as linhas e detalhes refletem projeções/obrigação `PGDAS_D` sem misturar outra obrigação como próxima

#### Scenario: DASN-SIMEI filters annual MEI projections

- **WHEN** o cliente solicita lista e overview com `submodule=DASN_SIMEI`
- **THEN** ambos usam exclusivamente projeções `DASN_SIMEI` do office autenticado

#### Scenario: MIT filters MIT projections

- **WHEN** o cliente solicita lista e overview com `submodule=MIT`
- **THEN** ambos usam exclusivamente projeções `MIT` e não misturam DCTFWeb

#### Scenario: DIRF returns honest empty unsupported

- **WHEN** o cliente solicita a carteira com `submodule=DIRF`
- **THEN** o sistema NÃO inventa fixtures
- **AND** a UI apresenta estado vazio ou `UNSUPPORTED` honesto

### Requirement: Other obligation tabs reuse existing surfaces

As abas DCTFWeb, DEFIS, DASN-SIMEI e MIT SHALL apresentar lista filtrada e SHALL abrir os respectivos históricos locais já existentes quando o operador solicitar a ação por cliente. FGTS SHALL NÃO inventar status de guia/pagamento produtivos além da cobertura parcial já documentada. DIRF SHALL permanecer sem dados inventados.

#### Scenario: DCTFWeb history from declarations hub

- **WHEN** o usuário está na aba DCTFWeb e solicita histórico de um cliente
- **THEN** o sistema abre o fluxo de histórico DCTFWeb existente sem nova integração SERPRO implícita

#### Scenario: DASN-SIMEI history starts on DASN

- **WHEN** o usuário solicita histórico na aba DASN-SIMEI
- **THEN** o sistema abre o modal de serviços MEI diretamente no serviço DASN-SIMEI para o cliente
- **AND** apenas o histórico local é carregado ao abrir

#### Scenario: MIT history uses apurações 317

- **WHEN** o usuário solicita histórico na aba MIT
- **THEN** o sistema abre o histórico local de apurações MIT 317 para o cliente
- **AND** nenhuma transmissão ou encerramento é executado ao abrir

## ADDED Requirements

### Requirement: Catálogo público exposes sanitized declaration coverage

`GET /api/v1/fiscal/declarations/catalog` SHALL retornar uma matriz de cobertura derivada do snapshot oficial versionado para PGDAS-D, DEFIS, DASN-SIMEI, DCTFWeb, MIT, FGTS Digital e DIRF. Cada entrada SHALL informar fonte, estado de cobertura, capacidade de monitoramento, capacidade de transmissão, contagem de operações, rotas oficiais documentadas e `verified_at`. A resposta MUST NOT expor `operation_key`, `idSistema`, `idServico`, schemas, payloads ou segredos.

#### Scenario: Implemented obligation reports executable coverage

- **WHEN** o catálogo contém operações de leitura `IMPLEMENTED` para PGDAS-D
- **THEN** a matriz marca o monitoramento PGDAS-D como suportado e apresenta somente metadados sanitizados

#### Scenario: Inventoried DASN-SIMEI remains fail-closed

- **WHEN** todas as operações DASN-SIMEI estão `INVENTORIED`
- **THEN** a matriz marca monitoramento e transmissão como indisponíveis
- **AND** a UI explica que o serviço está catalogado, porém não habilitado no hub

#### Scenario: External obligations contain no SERPRO operations

- **WHEN** o catálogo público inclui FGTS Digital e DIRF
- **THEN** ambas as entradas possuem zero operações Integra Contador
- **AND** seus estados são respectivamente `PARTIAL` e `UNSUPPORTED`

### Requirement: Obligation controls match portfolio filter tabs

A central SHALL usar o mesmo componente, tamanho e variante visual das tabs de filtro das demais carteiras. A ação `Operações` SHALL permanecer compacta junto às tabs e abrir a central da obrigação ativa. A página MUST NOT inserir cards descritivos permanentes de cobertura ou operações entre as tabs e a carteira.

#### Scenario: Operator changes declaration through filter tabs

- **WHEN** o usuário alterna entre PGDAS-D, DEFIS, DASN-SIMEI, DCTFWeb e MIT
- **THEN** as tabs mantêm paridade visual com os filtros do painel
- **AND** a ação compacta abre apenas as operações da obrigação ativa

#### Scenario: Catalog load fails independently

- **WHEN** o catálogo de operações falha, mas o portfolio carrega
- **THEN** a tabela permanece utilizável
- **AND** um erro localizado desabilita a ação de operações sem converter a falha em `UNSUPPORTED` ou sucesso

### Requirement: Declaration operation catalog covers the complete official state matrix

O catálogo público SHALL representar exatamente as 33 operações do recorte declarativo oficial: 23 `PRODUCTION` e 10 `PROSPECTION`, agrupadas por PGDAS-D, DEFIS, DASN-SIMEI, DCTFWeb e MIT. Cada operação SHALL ter `action_id` público, rótulo, obrigação, rota, mutabilidade, estado oficial, disponibilidade, parâmetros públicos e estratégia de resultado. A resposta MUST NOT expor `operation_key`, `idSistema`, `idServico`, schema bruto ou coordenadas aceitas do frontend.

#### Scenario: All production operations are end-to-end addressable

- **WHEN** o catálogo é carregado
- **THEN** as 23 operações em produção possuem fluxo `READ` ou `MUTATION` e handler server-side
- **AND** nenhuma delas aparece como `adapter_missing`

#### Scenario: Prospection remains visible and blocked

- **WHEN** a operação oficial está em `PROSPECTION`
- **THEN** o catálogo a apresenta como indisponível para execução
- **AND** qualquer tentativa de executar seu `action_id` termina em `422 OPERATION_NOT_PRODUCTION` antes de egress

#### Scenario: Technical coordinates never come from the browser

- **WHEN** o cliente envia `operation_key`, `idSistema`, `idServico`, `versaoSistema`, identidades técnicas ou `office_id`
- **THEN** a API rejeita o request
- **AND** não executa chamada externa

### Requirement: All productive read actions use the tenant-safe manual flow

As 13 operações produtivas `Consultar`/`Apoiar` SHALL possuir parâmetros curados, handler, validação e execução por ação explícita. A autorização MUST ser revalidada no request e no worker, e o resultado SHALL preservar loading/processamento, sucesso, vazio confirmado, bloqueio e erro sem tratar abertura do modal como chamada remota.

#### Scenario: Read action dispatch is explicit and auditable

- **WHEN** ADMIN ou OPERATOR elegível confirma uma consulta produtiva para cliente do próprio office
- **THEN** o backend resolve o `action_id`, valida os parâmetros e enfileira o adapter correspondente
- **AND** registra run/correlation sem aceitar coordenadas do navegador

#### Scenario: Read action is isolated by office

- **WHEN** o `client_id` pertence a outro office
- **THEN** a API responde como recurso não encontrado
- **AND** nenhuma run é criada

### Requirement: All productive mutating actions use controlled preflight and execution

As 10 operações produtivas `Emitir`/`Declarar` SHALL possuir codec de payload e integração com o fluxo central de mutações. O fluxo MUST exigir preflight válido, ADMIN, confirmação recente de senha, frase de confirmação, idempotência, gates de procuração/plano/orçamento/flags/cohort e autorização tipada emitida somente após revalidação. Defaults SHALL permanecer fail-closed.

#### Scenario: Disabled mutation is explained without egress

- **WHEN** uma operação produtiva está implementada mas a flag/cohort está OFF
- **THEN** o preflight retorna `BLOCKED` com razões acionáveis
- **AND** nenhuma chamada SERPRO ocorre

#### Scenario: Approved mutation passes validated business data

- **WHEN** todos os gates estão válidos, o payload satisfaz o codec oficial e a confirmação corresponde ao preflight
- **THEN** o executor recebe os dados de negócio validados e coordenadas resolvidas no servidor
- **AND** a operação persistida acompanha o resultado com idempotência

#### Scenario: Uncertain response cannot be blindly retried

- **WHEN** uma mutação termina em timeout ou resultado incerto
- **THEN** o estado é `UNCERTAIN`
- **AND** retry direto é bloqueado
- **AND** a reconciliação usa uma consulta segura e nunca repete a mutação original

### Requirement: Declaration operations are usable from the active obligation tab

A página SHALL mostrar as operações da obrigação ativa, permitir filtro por classe/estado, abrir formulário guiado ou importação JSON validada e acompanhar o estado da ação. A central SHALL possuir loading, erro, vazio, bloqueio e sucesso, ser navegável por teclado e preservar tabela, busca, filtros e paginação da carteira.

#### Scenario: Operator finds actions without leaving declarations

- **WHEN** o usuário seleciona DCTFWeb
- **THEN** vê todas as operações DCTFWeb produtivas e em prospecção com status distinto
- **AND** ações disponíveis abrem o fluxo apropriado sem mudar a URL canônica

#### Scenario: Mobile and reference viewport remain usable

- **WHEN** a página é renderizada em mobile ou em 1366×639
- **THEN** abas e ações permitem overflow controlado
- **AND** KPIs, busca e tabela não são ocultados por cards descritivos ou pela central fechada
