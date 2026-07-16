## ADDED Requirements

### Requirement: Layout estrito e versionado do Termo
O sistema SHALL gerar e validar o Termo com raiz `termoDeAutorizacao`, filho `dados`, `sistema` com `id="API Integra Contador"`, textos legais oficiais, `dataAssinatura/@data`, `vigencia/@data`, `destinatario` e `assinadoPor` com os atributos oficiais. O schema local MUST ser descrito como derivado da documentação, conter versão, URL, data e hash da fonte e MUST NOT ser apresentado como XSD oficial do SERPRO.

#### Scenario: Termo conforme à versão fixada
- **WHEN** o gerador recebe identidades, nomes e vigência válidos
- **THEN** produz XML determinístico conforme a versão documental fixada e preserva os textos legais sem alteração semântica

#### Scenario: Layout legado permissivo
- **WHEN** o XML usa `TermoAutorizacao`, omite campos obrigatórios ou depende de `xs:any`
- **THEN** a validação estrita rejeita o documento antes de qualquer envio

### Requirement: Assinatura XMLDSig conforme o padrão oficial
O assinador MUST aplicar assinatura XMLDSig Enveloped sobre o documento autorizado, RSA-SHA256, digest SHA-256, C14N, certificado X.509 v3 ICP-Brasil A1 ou A3 e `KeyInfo` com somente o certificado final. O XML MUST ser assinado em sua forma final e MUST NOT ser alterado após a assinatura.

#### Scenario: A1 gerenciado
- **WHEN** o `ADMIN` autoriza expressamente o uso de um A1 válido custodiado no vault
- **THEN** um job dedicado assina o Termo em memória/arquivo temporário protegido, apaga o temporário e persiste somente o XML cifrado e metadados sanitizados

#### Scenario: A3 ou assinatura externa
- **WHEN** o autor utiliza A3 ou assinador externo
- **THEN** o sistema fornece o Termo não assinado para fluxo externo e aceita o XML final somente após todas as validações locais

### Requirement: Validação vinculada ao conteúdo assinado
O validador MUST desabilitar entidades externas, localizar uma única assinatura esperada, validar referências/transforms/digest/assinatura e extrair sistema, destinatário, signatário e vigência exclusivamente do nó coberto pela referência assinada. Ele MUST rejeitar wrapping, nós duplicados, referência externa, algoritmo divergente, certificado fora da validade ou identidade incompatível.

#### Scenario: XML signature wrapping
- **WHEN** um atacante insere identidade não assinada fora do nó referenciado ou duplica elementos críticos
- **THEN** o validador rejeita o Termo e não usa dados obtidos por XPath global

#### Scenario: Identidades coerentes
- **WHEN** o certificado, `assinadoPor`, autor configurado e destinatário do contrato coincidem e a vigência é válida
- **THEN** o estado alcança no máximo `LOCAL_VALIDATED`, ainda sem equivaler a aceite remoto

### Requirement: Certificação local e aceite remoto distintos
O estado MUST distinguir `DRAFT`, `SIGNED`, `LOCAL_VALIDATED`, `SERPRO_ACCEPTED`, `EXPIRED`, `REVOKED` e `REJECTED`. Somente resposta válida do serviço real `AUTENTICAPROCURADOR/ENVIOXMLASSINADO81` pode produzir `SERPRO_ACCEPTED`; Trial, fixture e validação criptográfica local MUST NOT produzi-lo para produção.

#### Scenario: Validação criptográfica local aprovada
- **WHEN** o Termo passa schema, identidade, vigência e XMLDSig localmente
- **THEN** o sistema registra `LOCAL_VALIDATED` e continua bloqueando operações que exigem token produtivo

#### Scenario: Aceite do SERPRO
- **WHEN** o `/Apoiar` real devolve token válido para o Termo e contexto enviados
- **THEN** o sistema registra `SERPRO_ACCEPTED` com hash, versão, ETag e validade, sem persistir segredo fora do vault

### Requirement: Envelope correto do ENVIOXMLASSINADO81
O cliente MUST enviar pela rota `/Apoiar` o envelope padrão com `idSistema=AUTENTICAPROCURADOR`, `idServico=ENVIOXMLASSINADO81`, `versaoSistema=1.0` e `pedidoDados.dados` como JSON string contendo a chave `xml` cujo valor é o XML assinado codificado em Base64. O XML cru e a chave legada `xmlAssinado` MUST NOT ser enviados.

#### Scenario: Codec de ida e volta
- **WHEN** o pedido é montado a partir do XML assinado
- **THEN** decodificar Base64 do campo `xml` reproduz byte a byte o documento validado e o teste contratual coincide com o exemplo oficial

#### Scenario: Campo incompatível
- **WHEN** o payload contém XML cru, `xmlAssinado` ou `dados` que não seja JSON string escapada
- **THEN** o cliente bloqueia o envio como erro de contrato local

### Requirement: Cache seguro de token, ETag e expiração
O token de procurador e qualquer ETag que possa carregá-lo MUST permanecer cifrado no vault. A chave de cache MUST incluir ambiente, contrato, `Office`, autor e hash do Termo. O cliente SHALL usar `If-None-Match`, aceitar `304` apenas com cache íntegro e vigente, priorizar `Expires`/data de expiração válida e, na ausência de metadado, limitar a validade à meia-noite do dia seguinte no fuso de Brasília.

#### Scenario: Resposta 304 com cache válido
- **WHEN** o SERPRO retorna `304` e o contexto/hash possui token cifrado ainda vigente
- **THEN** o sistema reutiliza o token e atualiza a evidência sem expô-lo em Redis, banco ou API

#### Scenario: Resposta 304 sem cache íntegro
- **WHEN** falta token, contexto diverge, Termo mudou ou a validade expirou
- **THEN** o sistema falha fechado e não inventa nem reutiliza token

### Requirement: Imutabilidade e revogação do Termo
Cada assinatura SHALL criar uma versão imutável do XML canônico no vault, identificada por SHA-256 e ligada ao contrato, autor, escritório, documento fonte e período de vigência. Alterar identidade, texto, destinatário, certificado ou vigência MUST criar novo Termo e invalidar token/cache anterior.

#### Scenario: Renovação do Termo
- **WHEN** a vigência é alterada ou um novo certificado assina o documento
- **THEN** uma nova versão é criada e a versão anterior permanece apenas como evidência revogada/expirada

#### Scenario: Exclusão operacional
- **WHEN** o escritório revoga a autorização
- **THEN** o token é invalidado, o uso é imediatamente bloqueado e o XML permanece retido apenas conforme política legal/auditável

### Requirement: Fixtures oficiais sem segredo real
A suíte MUST incluir fixtures sintéticas e, quando permitido, materiais oficiais de demonstração/homologação para validar schema, Base64, XMLDSig e respostas 200/304/erro sem usar certificados ou identidades de clientes reais. Testes live MUST ser opt-in e MUST NOT executar em CI.

#### Scenario: Execução da suíte padrão
- **WHEN** testes unitários e de integração rodam em CI
- **THEN** todas as validações do Termo são exercitadas sem rede, PFX real, segredo produtivo ou chamada faturável

