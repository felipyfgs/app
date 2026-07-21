## ADDED Requirements

### Requirement: Histórico PGDAS-D agrupado por período de apuração

Na superfície de histórico local PGDAS-D do detalhe do cliente (`/monitoring/clients/:id` aba PGDAS-D), a UI SHALL organizar os registros por período de apuração (PA), com um bloco distinto por PA, em ordem decrescente de `period_key`. A UI MUST NOT apresentar declarações e DAS numa única tabela plana com células cruzadas vazias como layout principal.

#### Scenario: Vários PAs no histórico local

- **WHEN** o histórico local contém mais de um período com declarações e/ou DAS
- **THEN** cada PA aparece como bloco próprio com o rótulo do período formatado (ex.: `PA 06/2026`)
- **AND** os blocos seguem ordem do PA mais recente para o mais antigo

### Requirement: Seções Declarações e DAS dentro de cada PA

Dentro de cada bloco de PA, a UI SHALL separar visualmente a lista de **Declarações** (operação, número, transmissão, malha) da lista de **Geração de DAS** (número, emissão, situação de pagamento), refletindo a hierarquia do portal oficial PGDAS-D. Campos não aplicáveis a um tipo de registro MUST NOT ocupar colunas cruzadas com placeholder denso no layout principal.

#### Scenario: PA com declaração original e gerações de DAS

- **WHEN** um PA possui ao menos uma declaração e ao menos um DAS
- **THEN** o bloco exibe a seção de declarações com os dados da(s) declaração(ões)
- **AND** o mesmo bloco exibe a seção de DAS com número, emissão e indicação de pagamento localizado quando disponível
- **AND** a leitura não depende de `rowspan` misturando os dois tipos na mesma grade

#### Scenario: PA sem registros

- **WHEN** um período existe no payload sem declarações nem DAS
- **THEN** o bloco do PA permanece visível
- **AND** a UI comunica ausência de registros naquele PA de forma explícita

### Requirement: Resumo, documentos e coleta explícita preservados

A superfície SHALL manter o resumo de situação da declaração, PA esperado e última consulta válida; downloads de artefatos locais autenticados; e a coleta de documentos apenas mediante confirmação explícita do usuário (sem disparar SERPRO ao apenas carregar ou expandir o histórico).

#### Scenario: Abrir o histórico não consulta SERPRO

- **WHEN** o usuário abre a aba PGDAS-D do cliente e o histórico local carrega
- **THEN** a UI exibe apenas dados já persistidos
- **AND** nenhuma coleta de documentos é enfileirada sem a confirmação do modal de solicitação

#### Scenario: Buscar documentos do PA

- **WHEN** o usuário com permissão aciona buscar documentos em um PA e confirma no modal
- **THEN** a solicitação é enfileirada para aquele período
- **AND** artefatos já disponíveis no bloco continuam baixáveis via download autenticado
