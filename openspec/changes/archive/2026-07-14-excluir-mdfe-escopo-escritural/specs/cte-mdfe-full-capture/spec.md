## REMOVED Requirements

### Requirement: Captura MDF-e
**Reason**: MDF-e não integra a necessidade de escrituração deste produto e foi retirado do escopo operacional.

**Migration**: Configurações legadas são ignoradas; nenhum novo MDF-e é capturado. Tabela e dados existentes permanecem inertes até uma decisão explícita de retenção.

## MODIFIED Requirements

### Requirement: Independência de NSU
O cursor CT-e MUST ser independente de NF-e DistDFe e ADN.

#### Scenario: Avanço isolado
- **WHEN** o NSU de DistDFe NF-e avança
- **THEN** o cursor CT-e não é alterado
