## ADDED Requirements

### Requirement: Módulos consultivos disponíveis por padrão
O sistema SHALL considerar disponíveis os módulos `simples_mei`, `dctfweb`, `parcelamentos`, `situacao_fiscal`, `caixa_postal`, `declaracoes`, `guias`, `fgts`, `cadastros` e `processos_fiscais` quando não existir bloqueio superior e o escritório estiver tecnicamente pronto, sem exigir ativação por escritório.

#### Scenario: Escritório pronto sem controle persistido
- **WHEN** um escritório concluiu o onboarding e não há registro de restrição para o módulo
- **THEN** consultas permitidas pelo perfil ficam disponíveis automaticamente

### Requirement: Precedência única de disponibilidade
O sistema SHALL resolver disponibilidade na ordem kill switch, restrição global, restrição do escritório, política/readiness técnico e elegibilidade do cliente/operação, sem permitir que uma camada inferior habilite algo negado por camada superior.

#### Scenario: Kill switch prevalece
- **WHEN** o kill switch está ligado e nenhum módulo possui restrição persistida
- **THEN** nenhuma nova execução fiscal externa é iniciada

#### Scenario: Restrição de escritório é isolada
- **WHEN** um módulo é restringido para um escritório
- **THEN** novas consultas manuais e automáticas desse escritório são bloqueadas e os demais escritórios não são afetados

### Requirement: Perfil e política de operações
O sistema MUST aceitar somente `dev`, `trial` e `production` em `FISCAL_PROFILE`; `dev` SHALL usar fixtures sem rede, `trial` SHALL usar somente cenários oficiais disponíveis e `production` SHALL permitir somente operações `READ`. Operações `FISCAL_MUTATION` MUST permanecer bloqueadas em todos os perfis.

#### Scenario: Desenvolvimento não acessa rede
- **WHEN** uma operação é executada com `FISCAL_PROFILE=dev`
- **THEN** o resultado vem de fixture e nenhum transporte remoto é chamado

#### Scenario: Produção bloqueia geração
- **WHEN** uma operação `DOCUMENT_GENERATION` é solicitada com `FISCAL_PROFILE=production`
- **THEN** a execução é recusada antes de qualquer chamada externa

### Requirement: Controle administrativo provider-neutral
O sistema SHALL persistir restrições globais e por escritório em `fiscal_module_controls`, tratar ausência de registro como disponível e garantir unicidade por módulo global e por par módulo–escritório.

#### Scenario: Administrador restringe globalmente
- **WHEN** um `PLATFORM_ADMIN` envia motivo válido ao endpoint global de restrição
- **THEN** a restrição entra em vigor imediatamente, registra ator/data/auditoria e alcança todos os escritórios

#### Scenario: Usuário sem privilégio tenta alterar
- **WHEN** um usuário que não é `PLATFORM_ADMIN` tenta alterar uma restrição
- **THEN** o sistema responde sem modificar o controle

#### Scenario: Liberação sem autenticação recente
- **WHEN** um administrador tenta retirar uma restrição sem senha recente validada server-side
- **THEN** a liberação é recusada

### Requirement: API e UI de governança
O sistema SHALL expor os endpoints globais e por escritório definidos em `/api/v1/platform/fiscal/modules` e `/api/v1/platform/tenants/{office}/fiscal/modules`, e a UI SHALL apresentar estado, motivo, responsável, data e jobs bloqueados com ações de restringir e liberar/sincronizar.

#### Scenario: Matriz administrativa
- **WHEN** o administrador pesquisa um escritório na área “Módulos fiscais”
- **THEN** a UI apresenta a matriz escritório × módulo com o estado efetivo e sua causa sem expor credenciais fiscais

### Requirement: Bloqueio preserva histórico e alcança filas
O sistema SHALL manter páginas e dados armazenados legíveis durante uma restrição e SHALL revalidar a decisão no início de todo job fiscal, abortando de forma auditada sem chamada externa quando bloqueado.

#### Scenario: Job foi enfileirado antes da restrição
- **WHEN** o job inicia depois que a restrição entrou em vigor
- **THEN** ele termina sem acessar o provider, registra o motivo e incrementa a contagem de jobs bloqueados

#### Scenario: Consulta de dados durante restrição
- **WHEN** um usuário abre uma página de módulo restringido
- **THEN** os dados existentes continuam visíveis e a UI informa que novas consultas estão pausadas

### Requirement: Recuperação após liberação
O sistema SHALL agendar coleta idempotente de recuperação para os escritórios afetados quando uma restrição for retirada.

#### Scenario: Liberação global
- **WHEN** uma restrição global é retirada com autenticação recente
- **THEN** o sistema agenda em lotes a recuperação dos escritórios elegíveis e mantém auditoria da ação

