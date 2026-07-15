## MODIFIED Requirements

### Requirement: Projeção NF-e orientada a entrega
O sistema SHALL expor em listagem/detalhe de NF-e se o XML completo está disponível (`is_summary` / `has_full_xml`), status de obtenção do full e, se houver, status de manifestação opcional — sem exigir conclusiva para a nota ser “válida” no catálogo.

#### Scenario: Lista NFE
- **WHEN** GET `/documents?kind=NFE`
- **THEN** cada item indica se é resumo ou full e se o download completo é possível
