## ADDED Requirements

### Requirement: Exemplos versionados não contêm chaves operacionais

O sistema MUST manter `APP_KEY`, `VAULT_MASTER_KEY`, segredos HMAC, data keys e chaves de APIs externas vazios nos arquivos `.env.example` versionados e SHALL falhar o gate quando uma dessas chaves receber valor.

#### Scenario: Chave criptográfica foi preenchida no exemplo

- **WHEN** um arquivo de ambiente de exemplo contém valor não vazio para uma chave operacional protegida
- **THEN** o gate de material sensível falha antes do commit ou build de entrega

### Requirement: Subprocesso RPA recebe ambiente mínimo

O sistema MUST iniciar o worker FGTS Digital com uma allowlist explícita de variáveis de runtime e MUST NOT propagar credenciais ou segredos do processo Horizon.

#### Scenario: Horizon possui segredo não relacionado ao RPA

- **WHEN** o processo pai contém uma variável sensível que não pertence à allowlist
- **THEN** o worker filho não recebe a variável e a execução mantém o contrato JSON esperado

### Requirement: Evidência explícita de não pagamento não vira quitação

O parser FGTS Digital MUST classificar textos explícitos de não pagamento como `NOT_CONFIRMED` e MUST dar precedência a pagamento parcial sobre estados genéricos.

#### Scenario: Linha informa não pago

- **WHEN** uma linha de guia contém “não pago” ou outro marcador inequívoco de pendência
- **THEN** o parser retorna `NOT_CONFIRMED` e nunca `CONFIRMED`

### Requirement: Entrega compila com contratos tipados não ambíguos

O frontend SHALL concluir lint, typecheck e geração sem erro de payload e MUST manter uma única fonte auto-importada para cada tipo canônico.

#### Scenario: Ação bulk monta mudanças dinâmicas

- **WHEN** a UI prepara um payload bulk de processos ou tarefas
- **THEN** `changes.action` permanece obrigatório no tipo enviado e o typecheck conclui sem auto-import duplicado

### Requirement: CI exercita todas as stacks introduzidas

O CI SHALL executar os gates Laravel e Nuxt existentes, SHALL testar e vetar o gateway Go e MUST executar os testes de contrato Python na imagem Horizon RPA construída pelo Compose.

#### Scenario: Regressão existe somente no parser Python ou gateway Go

- **WHEN** uma mudança quebra um teste Python/RPA, um teste Go ou `go vet`
- **THEN** o CI falha mesmo que Laravel e Nuxt continuem aprovados

### Requirement: Superfícies novas permanecem classificadas

O sistema SHALL manter os inventários de páginas/rotas, o grafo de testabilidade e a matriz de fidelidade sincronizados com todas as páginas Nuxt e rotas Laravel versionadas.

#### Scenario: Uma nova página entra no painel

- **WHEN** uma página Nuxt é adicionada ao worktree
- **THEN** o inventário registra arquivo, rota e seção e o grafo regenerado a associa exatamente a uma jornada

#### Scenario: Uma nova rota entra na API

- **WHEN** uma rota Laravel é adicionada ao worktree
- **THEN** o inventário registra método, URI, grupo e handler e o grafo regenerado a associa exatamente a uma jornada
