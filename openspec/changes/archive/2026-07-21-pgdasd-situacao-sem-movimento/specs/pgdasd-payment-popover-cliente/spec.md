## MODIFIED Requirements

### Requirement: Popover/tooltip Situação para estados simples

Quando o estado for Sem movimento (`NO_DAS`), a UI MUST oferecer detalhe limpo (tooltip e/ou cartão curto no popover) com a descrição humana correspondente. MUST NOT exigir lista de competências e MUST NOT apresentar o detalhe como pares rotulados Situação | Detalhe.

#### Scenario: Sem movimento com tooltip limpo

- **WHEN** a linha está `NO_DAS` e o operador inspeciona o detalhe de Situação
- **THEN** a UI MUST exibir o estado “Sem movimento” com descrição humana curta e MUST NOT exigir lista de competências nem linhas Situação/Detalhe
