# serpro-cadastro-processos-ui

## Purpose

APIs e páginas do painel para Cadastro/Vínculos e Processos fiscais (e-Processo), tenant-scoped e baseadas nos arquétipos do template do dashboard.

## Requirements

### Requirement: APIs tenant-scoped de Cadastro e vínculos
O sistema SHALL oferecer listagem global do office, detalhe por cliente e refresh explícito de Cadastro/Vínculos, sempre derivando o office da sessão.

#### Scenario: Office no request
- **WHEN** o cliente HTTP enviar `office_id` em query ou body
- **THEN** o valor SHALL ser ignorado e somente o `CurrentOffice` SHALL definir o escopo

### Requirement: APIs tenant-scoped de Processos fiscais
O sistema SHALL oferecer listagem global do office, detalhe por cliente e refresh explícito de e-Processo, com isolamento e paginação server-side.

#### Scenario: Processo de outro office
- **WHEN** um usuário solicitar processo que pertence a outro office
- **THEN** a API SHALL responder como recurso inacessível sem revelar sua existência

### Requirement: Páginas do monitoramento baseadas no template
As rotas `/monitoring/registrations` e `/monitoring/tax-processes` SHALL copiar o arquétipo de lista do template fixado, com navegação, filtros, loading, vazio, erro e carregamento server-side.

#### Scenario: Lista sem dados
- **WHEN** a API retornar uma carteira vazia
- **THEN** a página SHALL exibir o estado vazio canônico sem mocks ou dados fabricados

### Requirement: Seções no detalhe do cliente
O detalhe fiscal do cliente SHALL incluir seções Cadastro/Vínculos e Processos fiscais derivadas do arquétipo Settings.

#### Scenario: Navegação para cliente
- **WHEN** o usuário abrir uma linha de qualquer nova carteira
- **THEN** ele SHALL poder acessar a seção correspondente do cliente sem trocar o office da sessão

### Requirement: Ações e dados públicos seguros
A UI MUST NOT exibir ações mutantes bloqueadas, contrato global, PFX, Termo, tokens, XML bruto ou poderes de outros offices.

#### Scenario: Operação mutante desabilitada
- **WHEN** a capacidade mutante estiver desligada
- **THEN** a interface SHALL omitir a ação e a API SHALL permanecer fail-closed
