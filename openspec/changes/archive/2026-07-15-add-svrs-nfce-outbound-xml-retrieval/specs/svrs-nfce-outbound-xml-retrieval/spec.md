## ADDED Requirements

### Requirement: Elegibilidade restrita ao piloto MA de NFC-e
O sistema MUST recuperar pelo canal SVRS somente documento com chave válida de 44 posições, `cUF=21`, modelo `65`, direção `OUT`, ambiente conhecido, perfil de captura ativo e raiz/estabelecimento allowlisted. O sistema MUST NOT encaminhar NF-e 55, chave de outra UF, documento de entrada ou perfil inativo a esse canal.

#### Scenario: NFC-e MA elegível
- **WHEN** um número de saída possui chave descoberta válida do modelo 65, estabelecimento MA, perfil ativo e A1 relacionado disponível
- **THEN** o sistema o classifica como elegível para recuperação SVRS

#### Scenario: NF-e modelo 55
- **WHEN** uma chave do modelo 55 chega ao avaliador de elegibilidade
- **THEN** o sistema não chama a SVRS e mantém o recovery em outra fonte ou fallback assistido

#### Scenario: Chave de outra UF
- **WHEN** a chave informa `cUF` diferente de 21
- **THEN** o sistema rejeita a elegibilidade nesta change sem tentar o endpoint remoto

### Requirement: Protocolo HTTP mTLS oficial e fechado
O cliente SHALL executar o GET do formulário e o POST de download exclusivamente nos hosts e paths HTTPS allowlisted da SVRS, usando A1 relacionado por mTLS, TLS 1.2 ou superior, verificação de hostname e cookies somente em memória. O sistema MUST NOT aceitar URL, host, cabeçalho, cookie ou certificado arbitrário informado pelo cliente da API.

#### Scenario: Download autenticado bem-sucedido
- **WHEN** a SVRS aceita o A1 no GET e devolve o formulário esperado
- **THEN** o cliente envia o POST com `sistema=Nfce`, origem, ambiente e chave e devolve resposta tipada ao parser

#### Scenario: Redirecionamento externo
- **WHEN** GET ou POST redireciona para host não allowlisted ou para HTTP
- **THEN** o cliente interrompe a operação, registra motivo sanitizado e não envia certificado nem formulário ao destino

#### Scenario: Falha de certificado do servidor
- **WHEN** a cadeia TLS ou o hostname da SVRS não pode ser validado
- **THEN** a operação falha fechada sem desabilitar a verificação TLS

### Requirement: A1 somente em memória
O sistema MUST obter a referência da credencial A1 pela raiz do cliente, materializar PFX e senha somente durante a chamada mTLS em memória e limpar referências após o uso. O sistema MUST NOT gravar PFX, senha, chave privada, PEM ou cookie em arquivo, payload de job, banco, log, trace, auditoria ou resposta de API.

#### Scenario: Execução do job
- **WHEN** o job inicia uma chamada SVRS
- **THEN** o payload contém somente identificadores internos e o transporte recebe o material criptográfico por BLOB em memória

#### Scenario: Inspeção anti-segredo
- **WHEN** testes inspecionam jobs serializados, logs, exceptions, auditoria e respostas da API
- **THEN** nenhum material sensível ou marcador de chave privada aparece

### Requirement: Parser estrito do wrapper HTML/JavaScript
O sistema SHALL extrair o XML somente do marcador e literal de download esperados, usando decoder de escapes JavaScript de gramática mínima e sem executar código. O sistema MUST rejeitar resposta ambígua, concatenação, template string, expressão, múltiplos XML candidatos, conteúdo truncado ou alteração incompatível do wrapper.

#### Scenario: Wrapper conhecido
- **WHEN** a resposta corresponde à fixture versionada e contém um único literal válido do `Blob`
- **THEN** o parser devolve os bytes decodificados sem normalização

#### Scenario: JavaScript executável ou ambíguo
- **WHEN** o suposto argumento contém expressão, concatenação ou mais de um candidato
- **THEN** o parser retorna `RESPONSE_CONTRACT_CHANGED` e não executa JavaScript

#### Scenario: Resposta excede limite
- **WHEN** HTML ou XML extraído excede o limite configurado
- **THEN** a operação é interrompida sem persistir conteúdo parcial

### Requirement: Preservação dos bytes do Blob
O sistema MUST calcular SHA-256 e persistir os bytes resultantes do decoder exatamente como seriam entregues pelo `Blob`, sem pretty-print, reserialização XML, normalização de espaços, conversão de quebra de linha ou troca de encoding.

#### Scenario: XML válido extraído
- **WHEN** o parser devolve um `nfeProc` válido
- **THEN** o hash é calculado sobre os mesmos bytes enviados ao `SecureObjectStore`

### Requirement: Validação fiscal e criptográfica completa
Antes da persistência canônica, o sistema MUST validar XML bem-formado sem DTD/entidades externas, raiz `nfeProc`, chave e DV, `cUF=21`, modelo 65, ambiente, CNPJ do emitente, protocolo para a mesma chave, status de autorização permitido, digest e assinatura XMLDSig com o X.509 embutido.

#### Scenario: Documento íntegro e relacionado
- **WHEN** identidade, protocolo, digest e assinatura correspondem à chave, ao estabelecimento e ao ambiente solicitados
- **THEN** o XML pode seguir para persistência imutável

#### Scenario: Chave ou emitente divergente
- **WHEN** o XML devolvido pertence a outra chave ou a outro emitente
- **THEN** o sistema não o promove a documento canônico, bloqueia o recovery e gera alerta crítico sanitizado

#### Scenario: Assinatura inválida
- **WHEN** digest ou assinatura XMLDSig não pode ser validado
- **THEN** o sistema rejeita a captura, preserva somente evidência sanitizada e abre o circuit breaker aplicável

#### Scenario: Entidade externa
- **WHEN** o XML declara DTD ou tenta resolver entidade externa
- **THEN** o parser XML rejeita o documento sem acesso à rede ou ao filesystem

### Requirement: Respostas e falhas tipadas
O cliente SHALL classificar o resultado em códigos permitidos, incluindo captura, indisponibilidade remota, autenticação proibida, rate limit, falha transitória HTTP, contrato alterado, XML inválido, identidade divergente e assinatura inválida. O sistema MUST NOT propagar HTML, JavaScript ou XML bruto como mensagem de erro.

#### Scenario: Indisponibilidade transitória
- **WHEN** a SVRS responde 429/503 ou ocorre falha de rede recuperável
- **THEN** o resultado tipado informa retry e respeita `Retry-After` válido sem incluir corpo remoto bruto

#### Scenario: Página sem documento
- **WHEN** o portal responde por template reconhecido que a chave não está disponível
- **THEN** o resultado é `REMOTE_NOT_FOUND` e não é confundido com contrato alterado ou captura vazia

### Requirement: Limites e circuit breaker
O sistema SHALL aplicar no início do rollout uma chamada lógica em voo por instância, intervalo mínimo global de cinco segundos, intervalo de trinta segundos por raiz e no máximo vinte chaves por execução. O sistema SHALL abrir circuit breaker global ou por raiz em falhas sistêmicas/recorrentes e MUST impedir novas chamadas enquanto aberto.

#### Scenario: Limite global ocupado
- **WHEN** outra recuperação SVRS já está em voo
- **THEN** o job atual é reagendado sem iniciar GET ou POST concorrente

#### Scenario: Contrato do wrapper alterado
- **WHEN** o parser retorna `RESPONSE_CONTRACT_CHANGED`
- **THEN** o breaker global abre, novos downloads param e a inbox recebe ocorrência crítica

#### Scenario: Half-open
- **WHEN** o período de breaker expira e existe chave allowlisted elegível
- **THEN** somente uma tentativa de prova é permitida antes de fechar ou reabrir o breaker

### Requirement: Flags e kill switch independentes
O canal SHALL permanecer desligado por padrão e só poderá chamar a SVRS quando a flag master, a elegibilidade do perfil e a allowlist forem satisfeitas. A flag de auto-queue SHALL ser independente da recuperação manual, e o kill switch MUST bloquear novas chamadas sem apagar estado ou documentos.

#### Scenario: Master flag desligada
- **WHEN** existe XML pendente elegível mas `SEFAZ_SVRS_NFCE_XML_RETRIEVAL_ENABLED=false`
- **THEN** nenhuma chamada remota ocorre e a pendência permanece disponível ao fallback assistido

#### Scenario: Kill switch durante backlog
- **WHEN** o kill switch é ativado com jobs pendentes
- **THEN** jobs não iniciam novas chamadas e registros, hashes, aquisições e estados existentes são preservados

### Requirement: Gates de liberação
O sistema MUST manter auto-queue desabilitado até existirem testes de fixtures e segurança, smoke mTLS restrito, validação de hash/assinatura, rollback drill e piloto allowlisted observável. A ampliação para novas raízes MUST exigir decisão operacional registrada e métricas do piloto dentro dos limites definidos.

#### Scenario: Build sem certificado real
- **WHEN** a suíte CI é executada
- **THEN** todos os testes usam fakes/fixtures sanitizadas e nenhuma credencial de produção é exigida

#### Scenario: Piloto não aprovado
- **WHEN** o adapter está implantado mas os gates de piloto não foram registrados
- **THEN** auto-queue permanece desligado mesmo que a flag master permita smoke manual restrito

