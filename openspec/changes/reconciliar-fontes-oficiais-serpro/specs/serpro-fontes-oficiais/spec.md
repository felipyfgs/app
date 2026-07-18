## ADDED Requirements

### Requirement: Snapshot canônico usa conteúdo oficial real
O sistema SHALL manter um manifesto versionado no qual toda fonte canônica de conteúdo HTTP tenha URL HTTPS oficial, data de captura, status HTTP 200 e SHA-256 calculado sobre o corpo efetivamente recuperado, sem valores placeholder ou hashes fabricados.

#### Scenario: Fonte oficial capturada com sucesso
- **WHEN** uma fonte canônica responde HTTP 200 dentro dos limites de tempo e tamanho
- **THEN** o snapshot registra o SHA-256 real do corpo, a data de captura e a proveniência oficial

#### Scenario: Conteúdo indisponível ou inválido
- **WHEN** a fonte falha, redireciona para host não permitido, excede limites ou não retorna HTTP 200
- **THEN** a captura falha fechada e não publica nem preserva um hash como se fosse evidência vigente

### Requirement: Tipos de evidência não são confundidos
O sistema MUST distinguir conteúdo HTTP estável, referência oficial dinâmica, evidência de transporte/TLS e referência histórica sem conteúdo recuperável; somente conteúdo HTTP estável capturado pode preencher `content_sha256`.

#### Scenario: Referência oficial com corpo dinâmico
- **WHEN** uma página oficial mantém URL e semântica documental, mas seu corpo bruto varia entre capturas da mesma rodada
- **THEN** ela é registrada como referência não canônica, sem `content_sha256`, e não satisfaz gates automáticos de conteúdo

#### Scenario: Referência histórica sem URL
- **WHEN** o registro representa uma divergência histórica ou ticket ainda sem documento oficial recuperável
- **THEN** ele permanece não canônico, sem `content_sha256` fabricado e sem satisfazer gates de proveniência

#### Scenario: Evidência de transporte
- **WHEN** um endpoint é usado apenas para handshake ou readiness e não fornece documento HTTP canônico
- **THEN** seu resultado é classificado separadamente e não é armazenado como hash de conteúdo oficial

### Requirement: Coerência entre catálogo, procurações e registro de fontes
O sistema SHALL validar que os hashes do catálogo oficial e da matriz de serviços × procurações coincidem entre o catálogo canônico, o manifesto de fontes e a matriz derivada aprovada.

#### Scenario: Manifestos coerentes
- **WHEN** catálogo, registro de fontes e matriz de poderes são carregados
- **THEN** URL e SHA-256 da fonte comum coincidem e as contagens oficiais permanecem 119 operações, sendo 98 produtivas e 21 não produtivas

#### Scenario: Hash divergente
- **WHEN** qualquer consumidor encontra hash ausente, placeholder ou divergente para uma fonte comum
- **THEN** a validação falha fechada e a matriz não é tratada como aprovada para elegibilidade real

### Requirement: Verificação vigente é explícita, read-only e sanitizada
O sistema SHALL oferecer uma verificação explícita que recupere somente as fontes oficiais allowlisted, compare os hashes vigentes e retorne apenas status, chave da fonte, HTTP e hashes, sem persistir corpo de documento nem executar endpoint fiscal de negócio.

#### Scenario: Conteúdo vigente permanece igual
- **WHEN** o operador executa a verificação e todas as fontes canônicas retornam os hashes esperados
- **THEN** a verificação termina com sucesso e relata correspondência sem expor o corpo recuperado

#### Scenario: Fonte oficial mudou
- **WHEN** ao menos uma fonte canônica retorna hash diferente
- **THEN** a verificação termina com código diferente de zero, marca `REVIEW_REQUIRED` e não altera automaticamente catálogo, matriz ou estado operacional

### Requirement: Testes offline rejeitam proveniência sintética
O sistema MUST cobrir offline a estrutura do manifesto, a ausência de hashes placeholder e a coerência entre recursos, sem depender de internet nos gates comuns.

#### Scenario: Manifesto contém padrão sintético
- **WHEN** um teste carrega hash sequencial, repetitivo, nulo em fonte canônica ou incompatível com outro recurso
- **THEN** o validator rejeita o manifesto com erro acionável e sanitizado

### Requirement: Ledger separa evidência real de histórico insuficiente
O ledger SHALL registrar 25 mutações entre as 98 operações produtivas, 33 mutações no catálogo total e SHALL recusar `PASS_BUSINESS`, HTTP 304 de fluxo não-cacheável, Trial, 4xx/5xx ou `BLOCKED_HUB` como evidência `PASS_REAL_*` de `PRODUCTION_CANARY`.

#### Scenario: Evidência histórica é reclassificada
- **WHEN** uma execução antiga não comprova endpoint contratado, payload semanticamente válido e classificação `PASS_REAL_*`
- **THEN** a linha permanece `BLOCKED` ou `FAIL_REAL`, com a pendência explícita, sem ser promovida a `READY_PRODUCTION`
