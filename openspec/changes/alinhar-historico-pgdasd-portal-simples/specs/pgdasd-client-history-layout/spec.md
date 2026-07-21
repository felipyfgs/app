## MODIFIED Requirements

### Requirement: Histórico PGDAS-D agrupado por período de apuração

Na superfície de histórico local PGDAS-D do detalhe do cliente (`/monitoring/clients/:id` aba PGDAS-D), a UI SHALL organizar os registros por período de apuração (PA), com um bloco distinto por PA, em ordem decrescente de `period_key`. Cada bloco MUST exibir uma faixa de cabeçalho com o rótulo do período formatado (ex.: `PA 06/2026`).

No desktop, o layout principal de cada PA SHALL ser uma grade no espírito do portal oficial “Consultar Declarações”: cabeçalhos agrupados **Declaração** e **DAS**, com uma linha por operação (Declaração Original, Declaração Retificadora, Geração de DAS). Células não aplicáveis à operação da linha MAY permanecer vazias (`—`); a UI MUST NOT usar `rowspan` misturando declaração e DAS na mesma linha.

Em viewports estreitas, a UI SHALL apresentar as mesmas operações como cards compactos por linha (sem depender de uma tabela horizontal densa como único layout).

A grade SHALL permanecer como superfície canônica no detalhe do cliente. A UI MUST NOT manter um modal aninhado de histórico de declarações dentro do histórico DAS que duplique essa grade.

#### Scenario: Vários PAs no histórico local

- **WHEN** o histórico local contém mais de um período com declarações e/ou DAS
- **THEN** cada PA aparece como bloco próprio com o rótulo do período formatado (ex.: `PA 06/2026`)
- **AND** os blocos seguem ordem do PA mais recente para o mais antigo

#### Scenario: Grade oficial por PA no desktop

- **WHEN** um PA possui ao menos uma declaração e ao menos um DAS e a viewport é desktop
- **THEN** o bloco exibe uma grade com colunas de operação, grupo Declaração e grupo DAS
- **AND** cada declaração e cada DAS aparecem como linhas distintas
- **AND** a leitura não depende de `rowspan` misturando os dois tipos na mesma linha

#### Scenario: PA sem registros

- **WHEN** um período existe no payload sem declarações nem DAS
- **THEN** o bloco do PA permanece visível
- **AND** a UI comunica ausência de registros naquele PA de forma explícita

#### Scenario: Histórico acessado sem superfície duplicada

- **WHEN** o operador consulta o histórico DAS em outra superfície do painel
- **THEN** a UI não oferece um modal aninhado que replique a grade de declarações
- **AND** a grade oficial permanece disponível na aba PGDAS-D do detalhe do cliente

## ADDED Requirements

### Requirement: Associação de artefatos às linhas da grade

Na grade/cards do histórico PGDAS-D, a UI SHALL associar downloads autenticados à linha correta:

1. Recibo / Declaração / MAED → artefatos com `declaration_number` igual ao da linha de declaração.
2. Extrato (e DAS PDF quando aplicável) → artefatos com `das_number` igual ao da linha de DAS.
3. Artefatos do PA sem vínculo de número MUST aparecer em “Outros documentos” no bloco do PA e MUST NOT ser repetidos falsamente em todas as linhas.

A UI MUST NOT disparar SERPRO ao apenas renderizar ícones de download.

#### Scenario: Recibo vinculado à declaração

- **WHEN** o PA tem artefato `RECIBO` com `declaration_number` de uma declaração presente
- **THEN** o download de Recibo fica disponível na linha daquela declaração
- **AND** MUST NOT aparecer automaticamente em linhas de outras declarações ou DAS sem o mesmo vínculo

#### Scenario: Artefato sem vínculo

- **WHEN** o PA tem artefato sem `declaration_number` nem `das_number` utilizável
- **THEN** o artefato aparece em “Outros documentos” do bloco do PA
- **AND** MUST NOT ser inventado como coluna preenchida em todas as linhas

## REMOVED Requirements

### Requirement: Seções Declarações e DAS dentro de cada PA

**Reason**: Substituído pela grade oficial do portal (cabeçalhos agrupados Declaração/DAS + linhas por operação) no requirement “Histórico PGDAS-D agrupado por período de apuração”.

**Migration**: Usar a grade oficial por PA no desktop; no mobile, cards por operação com os mesmos campos e associação de artefatos.
