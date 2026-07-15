# SEFAZ DistDFe Sync

## Purpose

Distribuição NF-e DistDFe (NFeDistribuicaoDFe): cursor, rate limit, decode docZip, mTLS próprio.

## Requirements

### Requirement: Cliente oficial DistDFe com mTLS
O sistema MUST consultar o serviço SEFAZ de distribuição de DF-e de interesse (`NFeDistribuicaoDFe` / `nfeDistDFeInteresse`, SOAP 1.2, Ambiente Nacional) usando o certificado e-CNPJ A1 da raiz do estabelecimento, com PFX apenas em memória (BLOB), TLS 1.2 ou superior e verificação de hostname **e** cadeia (peer) habilitadas. Endpoints de referência: produção `…/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx` e homologação `hom.nfe.fazenda.gov.br` (URLs confirmáveis na relação oficial de web services).

#### Scenario: Consulta autenticada
- **WHEN** um job de captura DistDFe é executado para um estabelecimento com A1 ativo
- **THEN** a requisição SOAP usa o PFX em memória (BLOB) e o CNPJ de consulta compatível com a base do certificado

#### Scenario: Falha de verificação TLS
- **WHEN** a cadeia ou o hostname do endpoint SEFAZ não pode ser validado
- **THEN** o sistema encerra a chamada, não avança o cursor e não desabilita a verificação TLS

### Requirement: Cursor NSU por estabelecimento e canal
O sistema SHALL manter o último NSU processado por estabelecimento, ambiente e canal `NFE_DISTDFE`, iniciando em zero, e SHALL consultar lotes até `ultNSU` alcançar `maxNSU` ou o limite do job.

#### Scenario: Primeira sincronização
- **WHEN** o estabelecimento ainda não possui cursor DistDFe
- **THEN** a primeira consulta usa NSU zero

#### Scenario: Documentos localizados (cStat 138)
- **WHEN** a SEFAZ responde cStat 138 com lote de até 50 docZip
- **THEN** o sistema decodifica cada item (Base64+GZip), classifica o schema (resNFe, procNFe, resEvento, procEventoNFe, …) e só avança o cursor após persistir o lote

#### Scenario: Nenhum documento (cStat 137)
- **WHEN** a SEFAZ responde que nenhum documento foi localizado
- **THEN** o sistema preserva o cursor e agenda a próxima consulta com intervalo mínimo de uma hora

#### Scenario: Fim de fila ultNSU igual maxNSU
- **WHEN** o retorno traz ultNSU igual a maxNSU
- **THEN** o sistema trata como fila esgotada no momento e aplica o mesmo quiet de pelo menos uma hora

### Requirement: Persistência atômica do lote
O sistema MUST persistir todos os documentos válidos do lote (XML descompactado + metadados) antes de avançar o NSU e MUST ser idempotente no reprocessamento do mesmo NSU.

#### Scenario: Falha parcial de banco
- **WHEN** qualquer persistência do lote falha antes do commit
- **THEN** o cursor não avança e o lote pode ser reprocessado

#### Scenario: Documento já persistido
- **WHEN** o mesmo office, SHA-256 ou (access_key, kind) já existe
- **THEN** o sistema não duplica o XML e conclui o item sem erro fatal

### Requirement: Decodificação docZip
O sistema SHALL decodificar Base64 e GZip de cada `docZip`, preservar bytes XML sem normalização e classificar o schema (`resNFe`, `procNFe`, `resEvento`, `procEventoNFe`, etc.).

#### Scenario: Payload corrompido
- **WHEN** um item não decodifica como Base64/GZip
- **THEN** o sistema não avança o cursor do lote, registra falha sanitizada e agenda retry

### Requirement: Rate limit e limite de páginas
O sistema SHALL esperar no mínimo dois segundos entre chamadas DistDFe no mesmo job, limitar o número de iterações por execução e reenfileirar se ainda houver NSU pendente.

#### Scenario: Consumo indevido (cStat 656)
- **WHEN** a SEFAZ responde consumo indevido
- **THEN** o cursor entra em estado bloqueado ou backoff longo e um item de inbox operacional é gerado sem corpo remoto bruto

### Requirement: Interface de domínio isolada
O sistema SHALL expor a captura DistDFe via interface de domínio (ex.: `SefazDistDfeClient`) e MUST NOT depender de biblioteca comunitária de emissão NF-e como transporte de produção.

#### Scenario: Implementação substituível
- **WHEN** testes de contrato usam fixtures SOAP sanitizadas
- **THEN** o job layer consome apenas DTOs internos, não o XML de rede cru acoplado à lib externa
