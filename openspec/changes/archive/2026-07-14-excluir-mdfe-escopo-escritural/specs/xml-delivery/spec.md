## MODIFIED Requirements

### Requirement: Download e export no catálogo de documentos
O sistema SHALL permitir download de XML e exportação ZIP a partir da identidade do documento no catálogo somente para kinds capturados no escopo escritural (NFS-e, NF-e, CT-e e NFC-e se habilitada). O sistema MUST NOT baixar nem exportar MDF-e.

#### Scenario: Download XML via documents
- **WHEN** um usuário autorizado solicita o XML de um documento existente via API de documents
- **THEN** o sistema entrega o XML original do vault sem expor material de certificado

#### Scenario: Download XML de NF-e
- **WHEN** um usuário autorizado solicita o XML de uma NF-e persistida
- **THEN** o sistema entrega os bytes do vault (procNFe ou resumo, conforme o que estiver armazenado como principal) sem expor certificado

#### Scenario: Tentativa de download MDF-e
- **WHEN** um cliente antigo solicita download usando uma identidade MDF-e
- **THEN** o sistema não localiza o documento no catálogo operacional e não acessa o vault
