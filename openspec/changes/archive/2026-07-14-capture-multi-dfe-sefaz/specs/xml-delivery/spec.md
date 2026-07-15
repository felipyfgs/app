## MODIFIED Requirements

### Requirement: Download e export no catálogo de documentos
O sistema SHALL permitir download de XML e exportação ZIP a partir da identidade do documento no catálogo, para kinds capturados (NFS-e, NF-e, CT-e, MDF-e, e NFC-e se habilitada).

#### Scenario: Download XML de NF-e
- **WHEN** um usuário autorizado solicita o XML de uma NF-e persistida
- **THEN** o sistema entrega os bytes do vault (procNFe ou resumo, conforme o que estiver armazenado como principal) sem expor certificado

## ADDED Requirements

### Requirement: Export multi-tipo
O sistema SHALL suportar filtros de export por `kind` e, quando múltiplos kinds forem selecionados, organizar o ZIP de forma que cada arquivo seja identificável por kind e chave (prefixo de pasta ou nome de arquivo).

#### Scenario: Export só NFE
- **WHEN** o operador exporta com `kind=NFE` e chaves existentes
- **THEN** o ZIP contém apenas XML desse kind

#### Scenario: Export misto
- **WHEN** o operador exporta sem filtro de kind com NFS-e e NF-e no escopo
- **THEN** o ZIP inclui ambos sem colisão de nomes
