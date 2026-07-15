# Multi DF-e Catalog Projection

## Purpose

Projeções e parsers multi-tipo (`NFE`, `NFCE`, `CTE`) alimentando o catálogo unificado e exportação, sem MDF-e operacional.

## Requirements

### Requirement: Projeção por DocumentKind
O sistema SHALL projetar documentos capturados nos kinds `NFE`, `NFCE` (quando aplicável) e `CTE` com campos comuns de catálogo (chave, número, partes, valor, datas, status, papel, kind, source) além das projeções NFS-e existentes. O sistema MUST NOT projetar MDF-e no catálogo operacional.

#### Scenario: Listagem multi-tipo
- **WHEN** o cliente chama `GET /api/v1/documents` sem filtro de kind
- **THEN** a resposta pode incluir itens de múltiplos kinds capturados, exceto MDF-e, cada um com `kind` e `kind_label` corretos

#### Scenario: Filtro kind NFE com captura
- **WHEN** existem NF-e persistidas e o cliente filtra `kind=NFE`
- **THEN** a lista não é vazia apenas por falta de implementação de fonte

### Requirement: NFC-e no catálogo
O sistema SHALL tratar NFC-e (`kind=NFCE`, modelo 65) como **fora do pipeline DistDFe de entrada B2B** no MVP: o DistDFe clássico e a MD-e destinam-se à NF-e 55. O sistema MAY capturar NFC-e apenas se surgir canal/caso de negócio real; até lá MUST manter empty state honesto e `capture_available=false`.

#### Scenario: Gap NFC-e documentado
- **WHEN** a captura NFC-e ainda não está habilitada
- **THEN** `kind=NFCE` retorna lista vazia sem erro e a UI indica indisponibilidade (não “em breve” genérico confuso com NF-e)

### Requirement: Eventos vinculados à chave
O sistema SHALL vincular eventos de NF-e e CT-e à chave de acesso correspondente e SHALL atualizar a situação derivada da projeção sem alterar o XML original do documento principal. Eventos MDF-e MUST NOT integrar o pipeline operacional.

#### Scenario: Cancelamento de NF-e
- **WHEN** um evento de cancelamento válido é persistido para a chave
- **THEN** a projeção da nota reflete cancelada e ambos os XMLs permanecem imutáveis

### Requirement: Parser versionado e revisão
O sistema SHALL manter parsers versionados por família e schema; XML bem-formado com XSD desconhecido MUST ser armazenado com alerta de parse e MUST NOT bloquear o avanço seguro do lote por desconhecimento de campo novo.

#### Scenario: Campo novo em schema conhecido
- **WHEN** um `procNFe` (ou equivalente) contém elementos não mapeados pelo parser de catálogo
- **THEN** os campos conhecidos são projetados, o XML completo permanece no vault e o lote DistDFe não é bloqueado por desconhecimento do campo extra
