## ADDED Requirements

### Requirement: A1 do cliente só quando for desbloquear ou manifestar
O sistema SHALL usar o A1 ACTIVE da raiz do cliente somente para DistDFe (já existente) e, se habilitado, para ciência/MD-e opcional — nunca o certificado do escritório.

#### Scenario: Sem A1
- **WHEN** o operador tenta obter XML completo via ciência e não há A1
- **THEN** a ação falha com motivo claro; o catálogo e o download do que já existe continuam funcionando
