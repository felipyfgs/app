## MODIFIED Requirements

### Requirement: Projeção por DocumentKind
O sistema SHALL projetar documentos capturados nos kinds `NFE`, `NFCE` (quando aplicável) e `CTE` com campos comuns de catálogo (chave, número, partes, valor, datas, status, papel, kind, source) além das projeções NFS-e existentes. O sistema MUST NOT projetar MDF-e no catálogo operacional.

#### Scenario: Listagem multi-tipo
- **WHEN** o cliente chama `GET /api/v1/documents` sem filtro de kind
- **THEN** a resposta pode incluir itens de múltiplos kinds capturados, exceto MDF-e, cada um com `kind` e `kind_label` corretos

#### Scenario: Filtro kind NFE com captura
- **WHEN** existem NF-e persistidas e o cliente filtra `kind=NFE`
- **THEN** a lista não é vazia apenas por falta de implementação de fonte

### Requirement: Eventos vinculados à chave
O sistema SHALL vincular eventos de NF-e e CT-e à chave de acesso correspondente e SHALL atualizar a situação derivada da projeção sem alterar o XML original do documento principal. Eventos MDF-e MUST NOT integrar o pipeline operacional.

#### Scenario: Cancelamento de NF-e
- **WHEN** um evento de cancelamento válido é persistido para a chave
- **THEN** a projeção da nota reflete cancelada e ambos os XMLs permanecem imutáveis
