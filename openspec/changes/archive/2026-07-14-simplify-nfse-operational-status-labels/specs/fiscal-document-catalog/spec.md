## ADDED Requirements

### Requirement: Labels operacionais da situação da NFS-e
O sistema SHALL expor a situação da nota com **dois níveis de vocabulário**: (1) valor de domínio `status` estável e granular (`ACTIVE`, `SUBSTITUTE`, `CANCELLED`, `SUPERSEDED`, `JUDICIAL`, `UNKNOWN`); (2) **label operacional** de apresentação em pt-BR limitado a **Autorizada**, **Cancelada** e **Em revisão**, conforme o agrupamento definido abaixo. O sistema MUST NOT alterar o mapeamento cStat→enum nem a atualização por eventos já estabelecidos.

Agrupamento obrigatório do label operacional:

| Label | Valores de `status` |
|-------|---------------------|
| Autorizada | `ACTIVE`, `SUBSTITUTE`, `JUDICIAL` |
| Cancelada | `CANCELLED`, `SUPERSEDED` |
| Em revisão | `UNKNOWN` |

#### Scenario: Nota gerada e substituta
- **WHEN** uma nota tem `status=ACTIVE` ou `status=SUBSTITUTE`
- **THEN** o label operacional apresentado ou retornado para UI é **Autorizada**

#### Scenario: Nota cancelada ou substituída
- **WHEN** uma nota tem `status=CANCELLED` ou `status=SUPERSEDED`
- **THEN** o label operacional é **Cancelada**

#### Scenario: Nota em revisão
- **WHEN** uma nota tem `status=UNKNOWN`
- **THEN** o label operacional é **Em revisão**

#### Scenario: Enums permanecem na API
- **WHEN** o cliente consulta listagem ou detalhe
- **THEN** o payload continua incluindo o `status` granular e o `official_status_code` quando conhecido, permitindo auditoria e detalhe além do label operacional

### Requirement: Filtro de situação por grupo operacional
O sistema SHALL permitir filtrar o catálogo e a exportação de notas pelo **grupo operacional** (Autorizada, Cancelada, Em revisão), expandindo internamente para o conjunto de valores de `status` do grupo. Filtro por valor de enum único MAY permanecer para compatibilidade, mas a UI principal do produto usa o grupo.

#### Scenario: Filtrar autorizadas
- **WHEN** o usuário ou cliente filtra por situação operacional Autorizada
- **THEN** o resultado inclui notas com `status` em `ACTIVE`, `SUBSTITUTE` e `JUDICIAL` e exclui canceladas e em revisão

#### Scenario: Filtrar canceladas
- **WHEN** o usuário filtra por situação operacional Cancelada
- **THEN** o resultado inclui `CANCELLED` e `SUPERSEDED`

### Requirement: Detalhe preserva situação oficial e eventos
O sistema SHALL, no detalhe da nota, permitir apresentar em conjunto: label operacional, `status` granular, `official_status_code` (cStat) com descrição oficial curta quando aplicável, e indicação de eventos de cancelamento ou substituição que justifiquem o grupo Cancelada (ex.: substituída por outra chave), sem apagar o XML original.

#### Scenario: Detalhe de cStat 101
- **WHEN** o usuário abre o detalhe de uma nota com `official_status_code=101` e `status=SUBSTITUTE`
- **THEN** a resposta/UI permite mostrar label **Autorizada** e situação oficial de substituição gerada (cStat 101)

#### Scenario: Detalhe de nota supersedida
- **WHEN** o usuário abre o detalhe de uma nota com `status=SUPERSEDED`
- **THEN** a resposta/UI permite mostrar label **Cancelada** e texto de que a nota foi substituída (não apenas “cancelamento genérico”), quando a projeção/eventos contiverem essa informação

## MODIFIED Requirements

### Requirement: Consulta paginada e filtrável
O sistema SHALL listar notas com paginação por cursor e filtros combináveis por cliente, estabelecimento, papel, situação (incluindo **grupo operacional** Autorizada/Cancelada/Em revisão), competência e data de emissão.

#### Scenario: Competência diferente da emissão
- **WHEN** o usuário filtra por competência sem informar data de emissão
- **THEN** o sistema aplica somente o período de competência e não confunde os dois campos

#### Scenario: Situação operacional na listagem
- **WHEN** o usuário filtra por situação operacional Autorizada, Cancelada ou Em revisão
- **THEN** o sistema aplica o conjunto de `status` do grupo e mantém paginação por cursor com os demais filtros combináveis
