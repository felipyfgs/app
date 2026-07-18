## ADDED Requirements

### Requirement: Apoio de receita SICALC sanitizado

O sistema SHALL consultar `SICALC/CONSULTAAPOIORECEITAS52` somente com um
`codigoReceita` válido e projetar apenas descrição e atributos permitidos da
receita, sem persistir ou devolver identificadores fiscais ou payload bruto.

#### Scenario: Resposta válida de receita

- **WHEN** o SERPRO retorna uma receita com extensões estruturadas
- **THEN** o monitor registra o resumo sanitizado no escritório e cliente da execução

#### Scenario: Resposta ambígua

- **WHEN** a resposta não contém receita, código correspondente ou extensões estruturadas
- **THEN** a execução falha fechada e não cria projeção

### Requirement: Consulta explícita tenant-scoped

O sistema SHALL exigir confirmação, `CurrentOffice` e autorização fiscal para
enfileirar a consulta, e SHALL servir histórico somente ao escritório atual.

#### Scenario: Tentativa com office_id ou cliente externo

- **WHEN** a requisição envia `office_id` ou aponta para cliente de outro escritório
- **THEN** a API rejeita a tentativa sem consultar a SERPRO

### Requirement: Painel de atributos de receita

O sistema SHALL mostrar apenas a projeção local no detalhe do cliente, com
loading, vazio e erro, sem disparar consulta externa automaticamente.

#### Scenario: Abertura do painel

- **WHEN** o usuário abre o painel de apoio de receita
- **THEN** a interface não renderiza CNPJ, CPF, tokens ou o payload bruto
