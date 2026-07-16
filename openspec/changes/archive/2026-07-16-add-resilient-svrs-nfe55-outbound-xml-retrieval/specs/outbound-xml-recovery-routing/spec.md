## ADDED Requirements

### Requirement: Ordem de fontes com menor risco
Para cada chave pendente o sistema SHALL consultar primeiro o catálogo/vault, depois aguardar ou reconciliar ingestões por emissão, XML/ZIP, pacote oficial ou `autXML`/DistDFe, usar SVRS somente para lacuna elegível e, por fim, oferecer contingência assistida. O sistema MUST NOT chamar o portal quando uma fonte anterior já satisfez a chave.

#### Scenario: XML chega por autXML antes do job
- **WHEN** um `nfeProc` válido é ingerido por `autXML` enquanto a recuperação SVRS aguarda orçamento
- **THEN** o job remoto é cancelado idempotentemente e a pendência é concluída pela aquisição já existente

### Requirement: SVRS é recuperação pontual
O roteador MUST exigir chave conhecida e MUST NOT transformar o canal SVRS em busca por período, numeração, série ou faixa. Backlog em massa SHALL ser encaminhado preferencialmente a `autXML`, XML/ZIP ou pacote oficial.

#### Scenario: Operador seleciona cem lacunas
- **WHEN** um lote grande de chaves é solicitado e o orçamento SVRS é insuficiente
- **THEN** o sistema não cria rajada, agenda no máximo itens compatíveis com o orçamento e apresenta o caminho de importação em massa

### Requirement: Fallback preserva estado e reconcilia depois
Quando o canal estiver desligado, bloqueado, fora do orçamento, inelegível ou com contrato alterado, o sistema SHALL manter `XML_PENDING`, registrar o motivo tipado e permitir XML/ZIP ou pacote oficial. Ingestão assistida válida MUST encerrar tentativas remotas não iniciadas da mesma chave.

#### Scenario: Breaker global abre
- **WHEN** o governador bloqueia a coorte durante backlog NF-e/NFC-e
- **THEN** todas as chaves permanecem auditáveis e disponíveis ao fallback sem serem marcadas como inexistentes

### Requirement: Idempotência entre fontes
O sistema SHALL manter um único documento canônico por escritório, ambiente e chave e registrar aquisições independentes por origem/hash. Hash divergente para a mesma chave MUST preservar o canônico e bloquear promoção automática para revisão.

#### Scenario: Upload difere do SVRS
- **WHEN** upload válido e resposta SVRS apresentam hashes diferentes para a mesma chave
- **THEN** o documento existente não é sobrescrito e uma divergência crítica é aberta com evidência sanitizada

