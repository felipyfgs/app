## ADDED Requirements

### Requirement: Export por direção
O sistema SHALL permitir export ZIP filtrando por direction e kind, organizando arquivos de forma identificável (ex.: pastas entrada/ e saida/ ou prefixo no nome).

#### Scenario: Export só entradas
- **WHEN** o operador exporta direction=IN
- **THEN** o ZIP não inclui documentos OUT do mesmo filtro de kind
