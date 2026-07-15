## ADDED Requirements

### Requirement: Autenticação contratante emite os dois tokens oficiais
O sistema MUST autenticar o contrato global com mTLS e OAuth `client_credentials` no endpoint oficial, processar `access_token` e `jwt_token` e renovar o par de forma coordenada antes da expiração.

#### Scenario: Autenticação bem-sucedida
- **WHEN** o endpoint oficial devolve `access_token`, `jwt_token`, tipo e expiração
- **THEN** o sistema guarda os tokens somente no cofre/cache protegido e disponibiliza um contexto autenticado sem expor seus valores

#### Scenario: Resposta sem JWT
- **WHEN** a autenticação não contém `jwt_token` válido
- **THEN** o contrato é marcado como indisponível e nenhuma chamada de negócio é enviada

### Requirement: Pedido oficial é montado por operação catalogada
O sistema MUST resolver a `operation_key` para rota, sistema, serviço e versão oficiais, montar Contratante, Autor e Contribuinte a partir de registros persistidos e serializar `pedidoDados.dados` exatamente uma vez como string.

#### Scenario: Operação SITFIS de emissão
- **WHEN** um job solicita a operação interna de emissão SITFIS com um protocolo
- **THEN** o cliente envia POST em `/Emitir` com `idSistema=SITFIS`, `idServico=RELATORIOSITFIS92`, `versaoSistema=2.0` e o protocolo dentro da string JSON de `dados`

#### Scenario: Coordenadas fornecidas pelo cliente HTTP
- **WHEN** body, query ou header do frontend tenta definir identidade ou coordenadas SERPRO
- **THEN** o sistema ignora esses valores e usa exclusivamente tenant, contrato e catálogo persistidos

### Requirement: Chamada de negócio envia headers oficiais e sanitizados
O sistema SHALL enviar Bearer, `jwt_token`, token do procurador quando exigido e `X-Request-Tag` de até 32 caracteres, sem registrar tokens ou payload fiscal em logs, métricas ou auditoria.

#### Scenario: Chamada representada
- **WHEN** uma operação exige Autor do Pedido e existe token do procurador aceito
- **THEN** o header de representação é enviado e somente correlação, operação e resultado sanitizados são auditados

### Requirement: Driver é selecionado por capacidade sem fallback
O sistema MUST selecionar `disabled`, `simulated` ou `real` por capacidade e MUST rejeitar driver simulado em produção.

#### Scenario: Simulador configurado em produção
- **WHEN** o ambiente de produção inicia com SITFIS em modo `simulated`
- **THEN** o preflight falha e nenhum worker ou scheduler dessa capacidade é iniciado

#### Scenario: Cliente real indisponível
- **WHEN** a capacidade usa `real` e autenticação ou representação falha
- **THEN** a operação falha fechada sem recorrer ao simulador ou ao cliente legado

### Requirement: Termo possui validação local criptográfica e estado externo distinto
O sistema MUST validar estrutura oficial, atributos, XSD, digest, XMLDSig RSA-SHA256/C14N, certificado, identidade, destinatário e vigência, e MUST distinguir validação local, aceitação SERPRO, simulação e rejeição.

#### Scenario: Termo válido apenas localmente
- **WHEN** o XML passa em todas as validações locais mas ainda não foi enviado ao SERPRO
- **THEN** o estado é `LOCAL_VALIDATED` e chamadas reais que exigem aceite permanecem bloqueadas

#### Scenario: Assinatura apenas presente
- **WHEN** o XML contém `SignatureValue` mas o digest ou a assinatura não confere
- **THEN** o Termo é rejeitado e nenhum token de procurador é solicitado

