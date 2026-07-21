## MODIFIED Requirements

### Requirement: Coluna Situação exibe pagamento DAS com labels humanos

A carteira Simples/MEI submódulo PGDASD SHALL exibir o estado de pagamento DAS na coluna **Situação**, com labels humanos: Em dia, Pendências, Sem movimento. A UI MUST NOT exibir flags de máquina nem o rótulo “Não verificado”; quando não houver evidência de pagamento e a procuração estiver ausente, MUST exibir Sem procuração; nos demais casos sem evidência MUST exibir `—` (ou skeleton de consulta pendente). A entrega do PA MUST NOT aparecer como badge texto nesta coluna — mora na coluna Declaração colorida.

#### Scenario: NO_DAS exibe Sem movimento

- **WHEN** `payment_state` é `NO_DAS`
- **THEN** a coluna Situação MUST exibir o label “Sem movimento” (MUST NOT exibir “Sem DAS” como rótulo principal)
