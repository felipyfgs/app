## ADDED Requirements

### Requirement: Situação cadastral CCMEI sanitizada
O sistema SHALL processar `CCMEISITCADASTRAL123` apenas por uma allowlist de
situação cadastral e indicador de enquadramento, sem persistir ou devolver CNPJ,
CPF ou payload bruto.

#### Scenario: Retorno com situação válida
- **WHEN** a SERPRO retorna uma lista válida de situação cadastral CCMEI
- **THEN** o monitor projeta somente o resumo permitido no escritório do cliente

### Requirement: Consulta CCMEI confirmada e tenant-scoped
O sistema SHALL exigir confirmação explícita e autorização fiscal para enfileirar
a consulta 123, e SHALL servir o histórico apenas no `CurrentOffice`.

#### Scenario: Tentativa com escritório externo
- **WHEN** a requisição usa cliente ou parâmetro `office_id` de outro escritório
- **THEN** o sistema rejeita a requisição sem enfileirar chamada externa

### Requirement: Interface sem identificadores fiscais
A interface SHALL apresentar o histórico local e a confirmação de cobrança
potencial sem renderizar CNPJ, CPF ou resposta bruta da SERPRO.

#### Scenario: Abertura do painel
- **WHEN** o usuário abre a situação cadastral CCMEI
- **THEN** a interface lê apenas a projeção local e não consulta a SERPRO implicitamente
