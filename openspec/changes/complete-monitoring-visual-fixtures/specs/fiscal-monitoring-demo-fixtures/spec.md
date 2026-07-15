## ADDED Requirements

### Requirement: Seeder fiscal restrito ao ambiente demonstrativo
O sistema MUST fornecer uma carga fiscal demonstrativa executável somente em `local` ou `testing` e somente para o office explicitamente configurado como tenant demo. A validação de ambiente e tenant MUST ocorrer antes de qualquer mutação no banco.

#### Scenario: Carga no tenant demo local
- **WHEN** o seeder é executado em `local` para o office configurado com slug `demo`
- **THEN** o sistema cria o conjunto fiscal sintético dentro de uma transação e associa todo dado de negócio ao `office_id` desse tenant

#### Scenario: Tentativa em produção
- **WHEN** o seeder é invocado em `production`, ainda que exista um office chamado demo
- **THEN** o sistema recusa a operação antes de inserir, atualizar ou remover qualquer registro

#### Scenario: Office não autorizado
- **WHEN** a carga recebe um office diferente do tenant demo configurado
- **THEN** o sistema recusa a operação e não usa o identificador fornecido para popular dados fiscais

### Requirement: Dataset determinístico e idempotente
O sistema SHALL manter um manifesto versionado, data-âncora explícita e chaves sintéticas estáveis para que a reexecução produza o mesmo cenário lógico sem duplicar categorias, vínculos, competências, execuções ou projeções.

#### Scenario: Reexecução da mesma versão
- **WHEN** o seeder da mesma versão é executado duas vezes com a mesma data-âncora
- **THEN** contagens, relações, estados, valores e identificadores lógicos permanecem equivalentes e nenhum registro demonstrativo é duplicado

#### Scenario: Atualização da versão da fixture
- **WHEN** o manifesto muda de versão
- **THEN** somente registros demonstrativos identificados do office demo são substituídos e dados de outros tenants permanecem intactos

#### Scenario: Relógio reproduzível
- **WHEN** testes e screenshots usam a data-âncora configurada
- **THEN** competências, vencimentos, idades, agendas e timestamps relativos produzem o mesmo resultado esperado

### Requirement: Cobertura funcional coerente entre módulos
O dataset SHALL popular núcleo fiscal, Simples/MEI, DCTFWeb/MIT, Parcelamentos, SITFIS, Caixa Postal, Declarações, Guias, FGTS/eSocial e consumo com relações coerentes para a mesma carteira sintética.

#### Scenario: Carteira com todos os estados
- **WHEN** o tenant demo consulta as carteiras fiscais
- **THEN** existem cenários representativos para `UP_TO_DATE`, `PENDING`, `PROCESSING`, `ATTENTION`, `ERROR`, `NOT_APPLICABLE`, `UNKNOWN`, `UNSUPPORTED` e `BLOCKED`

#### Scenario: KPI consistente com a lista
- **WHEN** o overview e a carteira do mesmo módulo são consultados com filtros equivalentes
- **THEN** os totais por situação correspondem ao conjunto completo da consulta e não somente à página retornada

#### Scenario: Relações navegáveis
- **WHEN** uma linha demonstrativa abre cliente, execução, mensagem, declaração, guia, parcela ou competência
- **THEN** o detalhe relacionado existe no mesmo office e apresenta dados semanticamente compatíveis com a linha de origem

#### Scenario: Cursor bloqueado por falha de decodificação
- **WHEN** a fixture representa cinco falhas consecutivas de Base64/GZip em um canal documental
- **THEN** o cursor aparece `BLOCKED`, conserva o último NSU persistido e não simula avanço ou salto silencioso

### Requirement: Isolamento multi-escritório das fixtures
Toda consulta, agregação, download e ação interna sobre dados demonstrativos MUST aplicar o office da membership ativa, sem aceitar `office_id` livre do cliente.

#### Scenario: Mesmo CNPJ em dois offices
- **WHEN** a fixture sentinela contém o mesmo CNPJ em outro office e o usuário está autenticado no tenant demo principal
- **THEN** listas, KPIs, busca, detalhe e exportação mostram somente registros do office ativo

#### Scenario: Manipulação de office_id
- **WHEN** o cliente envia `office_id` de outro tenant em query ou body não autorizado
- **THEN** o valor é rejeitado ou ignorado e não altera o escopo resolvido pela membership

#### Scenario: Troca autorizada de tenant
- **WHEN** o usuário troca para outra membership válida
- **THEN** cache, seleção, totais e dados demonstrativos do office anterior são invalidados antes da nova consulta

### Requirement: Proveniência demonstrativa explícita
As APIs de overview, carteira e detalhe SHALL informar origem `DEMO`, `SIMULATED` ou `LIVE`, e a interface MUST exibir “Dados demonstrativos” sempre que o conjunto ativo não representar fonte fiscal produtiva.

#### Scenario: Consulta no office demo
- **WHEN** uma API fiscal retorna dados criados pelo seeder
- **THEN** a resposta inclui proveniência sintética sanitizada e a UI apresenta selo textual visível sem depender somente de cor

#### Scenario: API produtiva sem dados
- **WHEN** uma API em produção retorna uma lista vazia
- **THEN** o sistema mantém o estado vazio real e MUST NOT substituir a resposta por fixture, exemplo ou fallback sintético

#### Scenario: Falha da API produtiva
- **WHEN** uma consulta em produção falha
- **THEN** a UI apresenta erro e opção de nova tentativa, sem reaproveitar dados demonstrativos para ocultar a falha

### Requirement: Fixtures sanitizadas e sem material sensível
O sistema MUST NOT criar ou expor PFX, senha, chave privada, PEM, Consumer Secret, token SERPRO, Termo assinado, cookie, XML fiscal real ou identificador recuperável de cofre nas fixtures, APIs, logs e artefatos de teste.

#### Scenario: Arquivo necessário ao fluxo visual
- **WHEN** Caixa Postal, evidência ou Guia necessita de corpo, anexo ou download demonstrativo
- **THEN** o sistema armazena conteúdo inofensivo pelo `SecureObjectStore`, marca-o “DEMONSTRAÇÃO — SEM VALIDADE FISCAL” e entrega somente pela rota autorizada

#### Scenario: Varredura de segurança
- **WHEN** seed, testes, screenshots, traces e relatórios são gerados
- **THEN** a varredura rejeita padrões de material criptográfico, segredo, XML fiscal real, cookie e token

#### Scenario: Metadado de certificado
- **WHEN** a interface necessita mostrar estado de certificado para um cliente demo
- **THEN** somente metadados públicos sintéticos são usados e nenhuma rota de recuperação de certificado é criada

### Requirement: Demonstração permanece somente leitura para efeitos fiscais externos
O perfil demo MUST manter transmissões, emissões externas, adesões e demais mutações fiscais de alto risco bloqueadas, ainda que clients fake estejam registrados no container.

#### Scenario: Tentativa de transmissão demo
- **WHEN** um administrador abre a confirmação de transmissão em dado demonstrativo
- **THEN** o preflight identifica o modo demonstração/somente leitura e impede execução externa sem registrar sucesso fiscal fictício

#### Scenario: Ação interna segura
- **WHEN** um operador filtra, associa categoria, atualiza triagem interna ou navega entre detalhes do office demo
- **THEN** a ação funciona conforme as permissões e permanece identificada como operação interna sobre dados demonstrativos

#### Scenario: Scheduler local
- **WHEN** o perfil local de demonstração é habilitado
- **THEN** o scheduler e chamadas externas permanecem desligados até ativação operacional separada e explícita

### Requirement: Ausência de dependência demo no runtime produtivo
O build e o runtime de produção MUST funcionar sem carregar manifestos, seeders, interceptadores E2E ou clients de fixture como fallback do produto.

#### Scenario: Build de produção
- **WHEN** o frontend e o backend são preparados para produção
- **THEN** não existe rota mock Nuxt, bundle de dataset demo, processo Node adicional ou condição de fallback sintético nas páginas

#### Scenario: Configuração demo ativada indevidamente
- **WHEN** uma variável de demo é configurada em produção
- **THEN** o guard de ambiente prevalece, registra falha sanitizada e não habilita carga nem origem demonstrativa

