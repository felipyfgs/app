## MODIFIED Requirements

### Requirement: Labels da Situação PGDAS-D na carteira

A UI da carteira Simples/MEI (submódulo PGDASD) SHALL representar a entrega do PA esperado na coluna **Declaração** (badge MM/YYYY colorido), não na coluna Situação. Cores canônicas: verde = declaração localizada; amarelo = ainda no prazo sem declaração; vermelho = prazo passou sem entrega; neutro = sem evidência / consulta pendente. Labels e tooltips MUST ser pt_BR humanos (ex.: No prazo, Atrasado) e MUST NOT exibir códigos de máquina. A precedência visual “Sem procuração” MAY sobrescrever a badge da coluna Situação (pagamento) quando o cliente não tem procuração e-CAC, sem alterar o estado persistido de declaração.

#### Scenario: Declaração no prazo
- **WHEN** a linha da carteira tem entrega ainda dentro do prazo e procuração não está ausente
- **THEN** a badge Declaração MUST usar cor de alerta (amarelo) no período MM/YYYY
- **AND** o tooltip/aria MUST comunicar atraso relativo ao prazo em linguagem humana (ex. “No prazo”)

#### Scenario: Declaração atrasada
- **WHEN** a linha da carteira tem ausência confirmada após o prazo e procuração não está ausente
- **THEN** a badge Declaração MUST usar cor de erro (vermelho) no período MM/YYYY
- **AND** o tooltip/aria MUST comunicar “Atrasado” (ou equivalente humano)

#### Scenario: Situação não mostra entrega
- **WHEN** a linha tem declaração localizada (entrega em dia) e DAS em aberto
- **THEN** a coluna Declaração MUST indicar entrega ok (verde)
- **AND** a coluna Situação MUST indicar Pendências (pagamento), não Em dia de entrega
