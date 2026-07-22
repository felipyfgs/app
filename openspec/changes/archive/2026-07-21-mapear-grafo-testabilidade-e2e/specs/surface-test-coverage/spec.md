## ADDED Requirements

### Requirement: Grafo canônico de casos de uso é completo e regenerável
O sistema SHALL manter um grafo versionado que classifique todas as rotas Laravel e páginas Nuxt atuais em casos de uso e registre atores, handlers, integrações e evidências de teste. O grafo MUST ser regenerável de forma determinística a partir do catálogo e dos inventários de superfície.

#### Scenario: Toda superfície pertence a uma jornada
- **WHEN** o gate de testabilidade compara o grafo com `route:list` e `app/pages/**/*.vue`
- **THEN** toda rota e toda página aparecem exatamente uma vez no inventário e estão ligadas a ao menos um caso de uso, sem nós ou referências órfãs

#### Scenario: Snapshots API e web são equivalentes
- **WHEN** as fixtures do grafo nas duas aplicações são verificadas
- **THEN** ambas possuem o mesmo digest, contagens e catálogo de jornadas

### Requirement: Matriz diferencia níveis reais de evidência
O sistema SHALL publicar uma matriz por caso de uso com evidências `L0` inventário, `L1` contrato HTTP/auth/tenant, `L2` domínio/behavioral e `L3` navegador. Uma evidência MUST apontar para teste existente e MUST NOT ser promovida de nível apenas por conter referência textual ao código ou à rota.

#### Scenario: Lacuna permanece explícita
- **WHEN** um caso de uso não crítico não possui evidência em algum nível
- **THEN** o relatório o marca como lacuna sem inventar cobertura e sem remover a superfície do grafo

#### Scenario: Evidência inválida falha o gate
- **WHEN** uma jornada referencia arquivo ausente, nível inválido ou âncora inexistente
- **THEN** o gate falha com a jornada e a evidência responsáveis pela divergência

### Requirement: Jornadas críticas possuem testabilidade ponta a ponta
As jornadas críticas de identidade/tenant, catálogo de clientes, trabalho operacional e monitoramento fiscal SHALL possuir evidência automatizada nos níveis L1, L2 e L3. Os testes MUST validar o contexto de ator, isolamento de escritório e permissões mutáveis aplicáveis.

#### Scenario: Tenant não vaza dados
- **WHEN** operador ou viewer alterna ou acessa um escritório no teste da jornada
- **THEN** API e UI exibem somente dados do `CurrentOffice` selecionado e negam dados cross-office

#### Scenario: Papel read-only não recebe mutação
- **WHEN** o viewer percorre uma jornada crítica no navegador
- **THEN** controles de criação, sincronização ou alteração restritos não estão disponíveis

### Requirement: E2E local é determinístico e fail-closed
O harness Playwright SHALL usar Docker Compose e seed exclusivo de `APP_ENV=testing`, SHALL bloquear hosts externos e MUST manter SERPRO, Integra, SEFAZ e providers de comunicação fail-closed. Playwright MUST permanecer fora do gate CI Frontend.

#### Scenario: Navegador não realiza egress
- **WHEN** qualquer spec Playwright da matriz crítica executa
- **THEN** requisições para host diferente de `localhost` ou `127.0.0.1` são abortadas e nenhum canal fiscal externo é acionado

#### Scenario: Gate CI continua determinístico
- **WHEN** o workflow Frontend é executado
- **THEN** Vitest valida catálogo, grafo e evidências sem iniciar navegador ou stack E2E

### Requirement: Inventários verificam conjuntos exatos
Os gates de superfície SHALL comparar o conjunto completo de chaves `método + URI` da API e de arquivos/rotas das páginas, além das contagens agregadas. Trocar uma superfície por outra sem alterar o total MUST ser detectado.

#### Scenario: Substituição com mesma contagem falha
- **WHEN** uma rota ou página é removida e outra é adicionada mantendo a contagem total
- **THEN** o gate identifica as chaves ausente e excedente e exige regeneração consciente do inventário e do grafo
