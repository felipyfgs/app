## ADDED Requirements

### Requirement: Parser RBT12 aceita layout tabular do extrato DAS

O sistema SHALL extrair RBT12 (total e, quando presentes, mercado interno e externo) do texto do extrato DAS PGDAS-D obtido via `CONSEXTRATO16`, inclusive quando o rótulo RBT12 estiver no cabeçalho e os valores em linhas seguintes. O parser MUST permanecer fail-closed: MUST NOT inventar valores ausentes ou ambíguos.

#### Scenario: Layout tabular multi-linha

- **WHEN** o texto contém cabeçalho com RBT12 (ou equivalente inequívoco) seguido de colunas Mercado Interno / Mercado Externo / Total e uma linha com três valores monetários compatíveis
- **THEN** o parser retorna status `PARSED` com `total_cents` e composição interna/externa correspondentes

#### Scenario: Linha única compatível

- **WHEN** uma linha contém RBT12 e os valores de mercado/total na mesma linha de forma inequívoca
- **THEN** o parser retorna `PARSED` como na versão anterior

#### Scenario: Valor ausente ou ambíguo

- **WHEN** não há total inequívoco ou há totais conflitantes
- **THEN** o parser retorna `NOT_FOUND` ou `AMBIGUOUS` sem preencher `total_cents`

### Requirement: Coluna RBT12 detalha no popover sem confundir com RPA

A coluna RBT12 da carteira PGDAS-D SHALL exibir o total parseado quando `PARSED` e SHALL abrir um painel/popover (não um tooltip de parágrafo) com lista dos campos disponíveis (total, mercado interno/externo, RPA quando presente, origem/período). O copy MUST distinguir RBT12 (receita bruta acumulada dos 12 meses anteriores ao PA) de RPA (receita do período de apuração) e MUST NOT apresentar RBT12 como sublimite anual.

#### Scenario: Popover com valor parseado

- **WHEN** o usuário aciona o chip RBT12 de um cliente com status `PARSED` e total
- **THEN** a UI abre um painel com lista de rótulo/valor incluindo o total RBT12 e a composição disponível

#### Scenario: Popover quando indisponível

- **WHEN** RBT12 não está `PARSED` e o usuário aciona o chip
- **THEN** o chip permanece sem valor inventado e o painel explica a indisponibilidade em linguagem compreensível (sem inventar montante)
