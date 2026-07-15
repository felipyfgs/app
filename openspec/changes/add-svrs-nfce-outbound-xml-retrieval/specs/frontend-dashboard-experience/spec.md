## ADDED Requirements

### Requirement: Card de recovery NFC-e via SVRS
O detalhe de Sincronização do estabelecimento SHALL apresentar card “XML NFC-e via SVRS” com estado da integração, elegibilidade, modo manual/automático, backlog, última tentativa/captura, próximo retry, breaker e motivo sanitizado, seguindo o template de dashboard fixado.

#### Scenario: Perfil elegível e saudável
- **WHEN** o estabelecimento MA possui perfil 65 ativo, A1 válido e canal habilitado
- **THEN** o card mostra integração disponível, progresso real e ações autorizadas sem exibir A1 ou chave completa

#### Scenario: Modelo 55
- **WHEN** o usuário visualiza uma série NF-e 55
- **THEN** a interface não oferece download SVRS e explica que este canal atende somente NFC-e 65

### Requirement: Lista de pendências e tentativas
A interface SHALL oferecer lista server-side de recoveries com filtros de estado/motivo, paginação, estado vazio, erro, deep-link para estabelecimento e ações por registro coerentes com a API.

#### Scenario: Pendência em retry
- **WHEN** uma recuperação está `RETRY_SCHEDULED`
- **THEN** a linha mostra tentativa, próximo horário e ação permitida sem simular captura concluída

#### Scenario: Falha ao carregar
- **WHEN** a API de recoveries falha
- **THEN** a UI mantém dados válidos anteriores, informa erro sanitizado e oferece tentar novamente

### Requirement: Ações por papel e 2FA
A UI MUST ocultar e bloquear controles administrativos de flag, allowlist, kill switch e breaker para quem não é ADMIN com 2FA recente. OPERATOR SHALL receber retry e fallback somente quando elegíveis; VIEWER MUST permanecer somente leitura.

#### Scenario: Operator com fallback disponível
- **WHEN** o canal está bloqueado e o OPERATOR visualiza a pendência
- **THEN** a interface oferece upload XML/ZIP existente e não oferece resetar breaker

#### Scenario: Admin sem 2FA recente
- **WHEN** ADMIN tenta alterar allowlist ou kill switch sem segundo fator vigente
- **THEN** a interface conduz ao desafio e não envia a mutação

### Requirement: Estados honestos de descoberta e captura
A interface MUST distinguir `Chave descoberta`, `XML pendente`, `Em recuperação`, `XML capturado`, `Fallback necessário` e `Bloqueado`, sem usar sucesso de consulta de protocolo como sucesso de XML.

#### Scenario: Chave descoberta sem XML
- **WHEN** o número possui `KEY_DISCOVERED` e recovery ainda não concluído
- **THEN** a UI mostra XML pendente e não habilita download do documento inexistente

### Requirement: Conteúdo remoto e segredos nunca renderizados
A interface MUST NOT receber ou renderizar HTML/JavaScript remoto, XML fiscal bruto, PFX, senha, PEM, cookie, token, `vault_object_id` ou mensagem remota não sanitizada. A chave, quando necessária à identificação autorizada, SHALL usar formato mascarado conforme política existente.

#### Scenario: Parser retorna contrato alterado
- **WHEN** a API informa `RESPONSE_CONTRACT_CHANGED`
- **THEN** a UI exibe texto local sanitizado e não injeta o corpo retornado pela SVRS no DOM

### Requirement: Fallback assistido integrado
A experiência SHALL ligar uma recuperação indisponível ao upload em massa XML/ZIP existente, preservar o contexto do cliente/estabelecimento e atualizar o estado quando a mesma chave for ingerida.

#### Scenario: Upload fecha pendência
- **WHEN** o operador conclui upload válido da chave pendente
- **THEN** o card e a lista atualizam para XML capturado por fallback, mantendo a proveniência correta

