## ADDED Requirements

### Requirement: Proveniência de recuperação SVRS NF-e 55
O pipeline SHALL aceitar a origem `SVRS_NFE55_DOWNLOAD_XML_DFE` somente após validação integral e registrar chave, ambiente, hash, horário e correlação da tentativa sem persistir o HTML wrapper. A transição para `XML_CAPTURED` MUST ocorrer na mesma unidade idempotente da ingestão concluída.

#### Scenario: Ingestão falha após download
- **WHEN** o XML passa validação remota mas a persistência canônica não conclui
- **THEN** a pendência não muda para `XML_CAPTURED` e a tentativa permanece reexecutável sem novo download quando os bytes seguros já estiverem no vault

### Requirement: Tenancy e imutabilidade entre aquisições
Todos os registros de solicitação, tentativa, aquisição e documento SHALL conter `office_id` derivado do contexto autenticado. O sistema MUST NOT sobrescrever bytes canônicos por resposta posterior nem permitir correlação cruzada entre escritórios.

#### Scenario: Mesma chave aparece em outro escritório
- **WHEN** uma tentativa de um escritório encontra correlação pertencente a outro tenant
- **THEN** nenhuma informação é reutilizada ou exposta e a tentativa é bloqueada como violação de isolamento

