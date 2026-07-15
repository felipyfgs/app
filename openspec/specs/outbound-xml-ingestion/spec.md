# Outbound XML Ingestion

## Purpose

Importação de XML de saídas (NF-e/NFC-e e afins) para vault e projeção no catálogo, sem emissão SEFAZ.

## Requirements

### Requirement: Importação de XML de saída
O sistema SHALL permitir que OPERATOR/ADMIN importe um ou mais XML (ou ZIP de XML) de documentos emitidos (saídas), persistindo bytes no vault e projeção no catálogo com direction OUT.

#### Scenario: Import procNFe
- **WHEN** um ZIP contém procNFe bem-formado da NF-e emitida pelo CNPJ do cliente
- **THEN** o sistema grava dfe_document imutável, projeção NFE com direction OUT e disponibiliza download

#### Scenario: Duplicata
- **WHEN** o mesmo SHA-256 já existe no office
- **THEN** o import é idempotente (não duplica vault; reporta skipped/duplicate)

#### Scenario: VIEWER
- **WHEN** VIEWER tenta importar
- **THEN** 403

### Requirement: Kinds suportados no import MVP
O sistema SHALL aceitar no MVP import de NF-e (55) e NFC-e (65); MAY aceitar outros kinds conhecidos com parse tolerante.

#### Scenario: NFC-e de saída
- **WHEN** o XML é NFC-e emitida pelo estabelecimento
- **THEN** kind=NFCE, direction=OUT

### Requirement: Sem emissão
O sistema MUST NOT autorizar ou transmitir nota à SEFAZ no fluxo de import — apenas armazena XML já autorizado.

#### Scenario: Import não chama SEFAZ
- **WHEN** o import conclui com sucesso
- **THEN** nenhuma chamada a web service de autorização/emissão SEFAZ é efetuada
