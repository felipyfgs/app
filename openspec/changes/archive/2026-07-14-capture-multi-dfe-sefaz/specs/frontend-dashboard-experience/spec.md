## MODIFIED Requirements

### Requirement: Filtro e indicação de tipo DF-e
O sistema SHALL permitir filtrar o catálogo por tipo e exibir o tipo de cada linha. Tipos com captura habilitada MUST listar dados reais; tipos sem captura MUST manter empty state informativo.

#### Scenario: NF-e com captura
- **WHEN** a captura DistDFe está habilitada e há documentos
- **THEN** o filtro NF-e mostra linhas com badge NFE e não exibe “em breve” como único estado

## ADDED Requirements

### Requirement: Manifestação no detalhe do documento
O sistema SHALL oferecer no detalhe de NF-e (quando pendente de manifestação) ações de ciência/confirmação/desconhecimento/não realizada para perfis autorizados, com confirmação e feedback de sucesso/erro sanitizado.

#### Scenario: Operador manifesta ciência
- **WHEN** o operador confirma ciência em uma NF-e resumo
- **THEN** a UI envia a ação, atualiza o estado e não exibe material de certificado

### Requirement: Sincronizações multi-canal
O sistema SHALL apresentar status de cursors SEFAZ (DistDFe, CT-e, MDF-e) nas telas de sincronização/saúde de forma distinguível dos cursors ADN.

#### Scenario: Cursor DistDFe bloqueado
- **WHEN** o cursor DistDFe está BLOCKED
- **THEN** a UI de sync/health mostra o canal e severidade sem dump SOAP bruto
