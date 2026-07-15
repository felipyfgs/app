## ADDED Requirements

### Requirement: Categorias e vínculos por escritório
O sistema SHALL manter catálogo tipado de categorias fiscais e vínculos de clientes por `office_id`, com estado, cobertura, agenda e data de ativação, sem associar automaticamente categoria não comprovada.

#### Scenario: Associar clientes ao Simples Nacional
- **WHEN** operador autorizado vincula clientes elegíveis à categoria
- **THEN** o sistema cria vínculos somente no tenant ativo e agenda a primeira execução conforme cobertura oficial

### Requirement: Execuções idempotentes e rastreáveis
O sistema MUST identificar execução por tenant, contribuinte, sistema, serviço, competência e gatilho/evento, impedindo duplicidade concorrente da mesma operação lógica.

#### Scenario: Evento duplicado
- **WHEN** o mesmo evento oficial é recebido novamente
- **THEN** o sistema reutiliza ou ignora a execução existente e não duplica snapshot, pendência ou consumo

#### Scenario: Execução é reencaminhada após limite
- **WHEN** um job alcança limite de páginas, itens ou tempo
- **THEN** ele persiste progresso confirmado e reencaminha continuação com a mesma identidade lógica segura

### Requirement: Snapshot e evidência imutáveis
O sistema MUST preservar resposta/evidência oficial com hash, origem, versão e horário antes de atualizar projeções; snapshots finalizados MUST NOT ser alterados silenciosamente.

#### Scenario: Nova consulta muda a situação
- **WHEN** uma execução posterior retorna estado diferente
- **THEN** o sistema cria novo snapshot, encerra ou atualiza a projeção corrente e mantém a evidência anterior auditável

### Requirement: Vocabulário honesto de situação e cobertura
O sistema SHALL distinguir `UP_TO_DATE`, `PENDING`, `PROCESSING`, `ATTENTION`, `ERROR`, `NOT_APPLICABLE`, `UNKNOWN`, `UNSUPPORTED` e `BLOCKED` e MUST NOT converter ausência de dado ou fonte em “em dia”.

#### Scenario: Fonte não suportada
- **WHEN** não existe API oficial para uma informação solicitada
- **THEN** o sistema apresenta `UNSUPPORTED` com explicação de cobertura e não gera pendência fiscal fictícia

#### Scenario: Consulta falha sem evidência atual
- **WHEN** a fonte falha e não há snapshot ainda válido
- **THEN** a situação permanece `UNKNOWN` ou `ERROR`, nunca `UP_TO_DATE`

### Requirement: Scheduler tenant-aware e elegível
O scheduler MUST revalidar tenant, plano, autorização, procuração, cobertura, orçamento, rate limit e kill switch imediatamente antes da chamada e SHALL espalhar execuções de forma justa entre escritórios.

#### Scenario: Tenant suspenso após enqueue
- **WHEN** o escritório é suspenso depois do job ser enfileirado
- **THEN** o job encerra antes da chamada externa e registra motivo sanitizado

### Requirement: Evidência e projeção permanecem isoladas
Queries, cache keys, locks, storage paths, exports e jobs MUST incluir o contexto persistido de `office_id` para todo artefato fiscal do tenant.

#### Scenario: Chave de cache compartilhada incorretamente
- **WHEN** dois escritórios consultam o mesmo CNPJ contribuinte
- **THEN** respostas autorizadas, evidências e projeções permanecem separadas por tenant, salvo cache público explicitamente não fiscal

