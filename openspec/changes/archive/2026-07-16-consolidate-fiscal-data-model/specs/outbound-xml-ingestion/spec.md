## ADDED Requirements

### Requirement: Proveniência outbound por caso e tentativa
Toda necessidade de recuperação outbound SHALL possuir um caso com prazo e identidade fiscal, enquanto cada consulta, upload, pacote ou fonte automática SHALL registrar tentativa própria; somente uma aquisição canônica validada MUST satisfazer o caso.

#### Scenario: Duas fontes concorrentes
- **WHEN** upload e recuperação oficial entregam o mesmo XML válido para o mesmo caso
- **THEN** o documento é único, ambas as aquisições são preservadas e o caso registra de forma determinística qual aquisição o satisfez primeiro

#### Scenario: Tentativa falha antes de outra fonte concluir
- **WHEN** uma fonte falha e outra captura o XML válido no prazo
- **THEN** a falha continua auditável, o caso é concluído pela aquisição válida e tentativas pendentes são canceladas de forma idempotente quando aplicável

### Requirement: Tentativas e pacotes não alteram o documento canônico
Requests, responses sanitizadas, roteamento e solicitações de pacote MUST permanecer subordinados ao caso/tentativa e MUST NOT substituir bytes, hash, identidade ou projeção do documento canônico.

#### Scenario: Pacote contém documento divergente
- **WHEN** um pacote oficial contém mesma chave com hash diferente do canônico
- **THEN** o artefato fica em custódia, a tentativa registra divergência e o caso não é marcado como satisfeito

#### Scenario: Resultado de captura
- **WHEN** uma aquisição válida satisfaz o caso
- **THEN** o estado do caso passa a concluído sem gravar “capturado” como faixa de urgência nem como estado de uma tentativa futura

### Requirement: Migração outbound preserva prazos e histórico
A consolidação MUST preservar `due_at`, `captured_at`, fonte, decisões de roteamento, request tags, tentativas, pacotes, divergências e resultado de prazo das solicitações legadas.

#### Scenario: Solicitação legada com múltiplas trocas
- **WHEN** uma solicitação antiga contém histórico de mais de uma fonte ou exchange
- **THEN** o backfill cria um caso e tentativas ordenadas sem colapsar falhas ou atribuir sucesso à fonte errada

#### Scenario: Histórico insuficiente
- **WHEN** uma solicitação legada não comprova qual fonte satisfez o prazo
- **THEN** o sistema preserva os dados disponíveis, marca a origem como indeterminada e impede que a linha seja usada como evidência positiva falsa
