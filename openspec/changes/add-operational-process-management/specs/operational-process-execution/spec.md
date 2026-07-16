## ADDED Requirements

### Requirement: Processo operacional manual por cliente e competência
O sistema SHALL permitir que `ADMIN` e `OPERATOR` criem processo manual para cliente ativo do escritório, com título, competência, descrição, prazo, prazo-meta, indicador de multa, departamento, responsável e tarefas, e MUST derivar o tenant da sessão.

#### Scenario: Criação manual válida
- **WHEN** um usuário autorizado cria processo para cliente ativo do escritório com competência válida
- **THEN** o sistema persiste processo e tarefas no mesmo `office_id`, registra o criador e retorna o recurso completo autorizado

#### Scenario: Office forjado no payload
- **WHEN** o payload inclui `office_id` diferente do escritório da sessão
- **THEN** o sistema ignora ou rejeita o campo e não grava qualquer linha no tenant indicado pelo cliente

#### Scenario: Cliente de outro escritório
- **WHEN** o identificador informado pertence a cliente de outro escritório
- **THEN** o sistema responde como recurso não encontrado e não revela sua existência

### Requirement: Estrutura editável antes da execução
O sistema SHALL permitir que usuário autorizado ordene, crie e altere tarefas de processo ainda não iniciado, mas MUST restringir mudança estrutural após início a `ADMIN` com justificativa e auditoria.

#### Scenario: Operador ajusta tarefa não iniciada
- **WHEN** um `OPERATOR` autorizado altera título ou ordem de tarefa em processo ainda `A_FAZER`
- **THEN** o sistema salva a estrutura e registra a alteração

#### Scenario: Operador altera estrutura em execução
- **WHEN** um `OPERATOR` tenta remover ou reordenar tarefa de processo já iniciado
- **THEN** o sistema rejeita a alteração sem afetar o progresso existente

### Requirement: Atribuição vinculada à membership
O sistema MUST aceitar como responsável apenas membership ativa do mesmo escritório e SHALL aplicar o responsável da tarefa antes do responsável do processo como atribuição efetiva.

#### Scenario: Responsável válido
- **WHEN** um `ADMIN` atribui tarefa a membership ativa do escritório
- **THEN** a tarefa passa a aparecer na fila dessa membership e a mudança é auditada

#### Scenario: Membership inativa ou externa
- **WHEN** uma atribuição referencia membership inativa ou pertencente a outro escritório
- **THEN** o sistema rejeita a alteração sem revelar identidade externa

#### Scenario: Operador assume tarefa livre
- **WHEN** um `OPERATOR` assume tarefa sem responsável do seu departamento
- **THEN** a própria membership torna-se responsável e nenhum terceiro é reatribuído

### Requirement: Lifecycle controlado de tarefas
O sistema MUST manter tarefas nos estados `A_FAZER`, `EM_PROGRESSO`, `IMPEDIDA`, `CONCLUIDA` ou `DISPENSADA` e SHALL executar transições exclusivamente pelo serviço de domínio autorizado.

#### Scenario: Início de tarefa
- **WHEN** responsável autorizado inicia tarefa `A_FAZER`
- **THEN** o estado muda para `EM_PROGRESSO`, o início e o ator são registrados e o processo é recalculado

#### Scenario: Impedimento com motivo
- **WHEN** responsável marca tarefa aberta como impedida e informa motivo não vazio
- **THEN** o estado muda para `IMPEDIDA`, o motivo é preservado e a timeline recebe o evento

#### Scenario: Impedimento sem motivo
- **WHEN** uma transição para `IMPEDIDA` omite o motivo
- **THEN** o sistema responde com erro de validação e conserva o estado anterior

#### Scenario: Dispensa administrativa
- **WHEN** `ADMIN` dispensa tarefa aberta com justificativa
- **THEN** a tarefa muda para `DISPENSADA`, registra ator/justificativa e participa da conclusão das obrigatórias

#### Scenario: Operador tenta dispensar
- **WHEN** `OPERATOR` ou `VIEWER` tenta dispensar uma tarefa
- **THEN** o sistema rejeita a ação sem modificar dados

### Requirement: Conclusão condicionada à evidência
O sistema MUST impedir conclusão de tarefa com `requires_evidence=true` enquanto não existir evidência válida e autorizada associada a ela.

#### Scenario: Conclusão com evidência
- **WHEN** responsável conclui tarefa que exige evidência e já existe arquivo válido
- **THEN** o sistema muda o estado para `CONCLUIDA`, registra conclusão/ator e recalcula o processo

#### Scenario: Conclusão sem evidência
- **WHEN** responsável tenta concluir tarefa que exige evidência sem arquivo associado
- **THEN** o sistema rejeita a transição e conserva a tarefa aberta

#### Scenario: Tarefa sem exigência
- **WHEN** responsável conclui tarefa que não exige evidência
- **THEN** a conclusão pode ocorrer sem upload, desde que as demais regras sejam satisfeitas

### Requirement: Estado de processo derivado das tarefas
O sistema SHALL recalcular transacionalmente o processo como `A_FAZER`, `EM_PROGRESSO`, `IMPEDIDO` ou `CONCLUIDO` a partir das tarefas, sem depender de edição livre do cliente.

#### Scenario: Primeira tarefa iniciada
- **WHEN** a primeira tarefa de um processo muda para `EM_PROGRESSO`
- **THEN** o processo muda de `A_FAZER` para `EM_PROGRESSO`

#### Scenario: Tarefa crítica impedida
- **WHEN** existe tarefa crítica em `IMPEDIDA`
- **THEN** o processo assume `IMPEDIDO` até a condição crítica ser resolvida ou dispensada por `ADMIN`

#### Scenario: Todas as obrigatórias encerradas
- **WHEN** todas as tarefas obrigatórias estão `CONCLUIDA` ou `DISPENSADA`
- **THEN** o processo muda para `CONCLUIDO` e registra horário de conclusão

### Requirement: Risco separado do lifecycle
O sistema SHALL calcular `ATRASADA`, `EM_MULTA`, `SEM_PRAZO` e `SEM_RESPONSAVEL` como dimensões combináveis, usando prazo da tarefa com fallback para prazo do processo e a data civil do escritório.

#### Scenario: Tarefa em progresso atrasada
- **WHEN** tarefa `EM_PROGRESSO` possui prazo efetivo anterior a hoje
- **THEN** ela conserva o lifecycle e também recebe risco `ATRASADA`

#### Scenario: Processo sujeito a multa
- **WHEN** tarefa aberta está atrasada em processo com indicador de multa
- **THEN** a fila e os indicadores classificam a tarefa como `EM_MULTA` sem substituir seu estado

#### Scenario: Nenhum prazo disponível
- **WHEN** tarefa e processo não possuem prazo
- **THEN** o sistema classifica o item como `SEM_PRAZO` e não inventa data

### Requirement: Fila diária determinística
O sistema SHALL ordenar tarefas abertas nos buckets em multa, atrasada, vence hoje, vence em até três dias, impedida, sem responsável e demais, com critérios estáveis dentro de cada bucket.

#### Scenario: Itens de riscos diferentes
- **WHEN** a fila contém uma tarefa em multa e outra apenas vencendo hoje
- **THEN** a tarefa em multa aparece primeiro e ambas retornam os motivos allowlisted de prioridade

#### Scenario: Fila padrão de operador
- **WHEN** `OPERATOR` abre “Minha fila”
- **THEN** o sistema lista tarefas atribuídas à sua membership e tarefas livres do seu departamento, sem tarefas concluídas ou dispensadas nas abas abertas

#### Scenario: Aba de concluídas
- **WHEN** usuário seleciona a aba de concluídas
- **THEN** o sistema lista apenas itens encerrados autorizados, paginados e ordenados por conclusão recente

### Requirement: Comentários append-only
O sistema SHALL permitir comentário textual em processo ou tarefa por membership autorizada do escritório e MUST preservar autor, horário e alvo sem edição retroativa.

#### Scenario: Comentário em tarefa
- **WHEN** usuário autorizado comenta uma tarefa do escritório
- **THEN** o comentário é anexado à timeline com autor e horário e não altera o lifecycle

#### Scenario: Comentário de outro tenant
- **WHEN** usuário tenta comentar identificador pertencente a outro escritório
- **THEN** o sistema responde como recurso não encontrado e não grava comentário

### Requirement: Evidências cifradas e privadas
O sistema MUST validar e armazenar evidências de tarefa pelo `SecureObjectStore` com finalidade/AAD tenant-scoped e MUST NOT expor objeto opaco, caminho físico, bytes ou URL pública em recursos, logs, CSV ou auditoria.

#### Scenario: Upload válido
- **WHEN** usuário autorizado envia arquivo permitido dentro do limite
- **THEN** os bytes são cifrados com AAD do escritório/tarefa/hash e somente metadados sanitizados são devolvidos

#### Scenario: Arquivo inválido ou excessivo
- **WHEN** o upload excede o limite ou possui MIME fora da allowlist
- **THEN** o sistema rejeita o arquivo sem criar evidência nem deixar objeto órfão no cofre

#### Scenario: Download autorizado
- **WHEN** membership autorizada solicita evidência da própria tarefa tenant-scoped
- **THEN** o sistema lê o objeto com AAD exata e transmite como attachment autenticado

#### Scenario: Download cruzado
- **WHEN** usuário de outro escritório tenta baixar uma evidência por ID válido
- **THEN** o sistema responde como não encontrado e não abre o objeto do cofre

### Requirement: Remoção controlada de evidência
O sistema SHALL permitir remoção somente a usuário autorizado, com justificativa e auditoria, e MUST impedir que a remoção deixe tarefa concluída em violação à exigência de evidência.

#### Scenario: Única evidência de tarefa concluída
- **WHEN** alguém tenta remover a única evidência de tarefa concluída que a exige
- **THEN** o sistema bloqueia a remoção até reabertura administrativa ou substituição válida

#### Scenario: Remoção autorizada
- **WHEN** uma evidência removível é excluída com justificativa
- **THEN** metadados, objeto cifrado e evento auditável são tratados de forma consistente sem expor o conteúdo

### Requirement: Operações em lote atômicas
O sistema SHALL permitir a `ADMIN` alterar responsável, departamento, prazo ou estado permitido de um lote limitado de tarefas do escritório e MUST validar todo o conjunto antes de efetivar qualquer item.

#### Scenario: Lote válido
- **WHEN** `ADMIN` confirma lote elegível dentro do limite
- **THEN** todas as tarefas são alteradas em uma transação e cada mudança aparece na auditoria com uma correlação comum

#### Scenario: Item externo ou desatualizado
- **WHEN** o lote contém ID de outro escritório ou `lock_version` divergente
- **THEN** toda a operação é rejeitada sem atualização parcial e sem revelar o item externo

### Requirement: Concorrência otimista
O sistema MUST exigir versão corrente em alterações de processo e tarefa para impedir sobrescrita silenciosa.

#### Scenario: Duas abas editam a mesma tarefa
- **WHEN** a segunda aba envia uma alteração baseada em versão anterior
- **THEN** o sistema responde conflito, conserva a primeira alteração e devolve contexto seguro para recarregar

### Requirement: Timeline e auditoria sanitizadas
O sistema MUST registrar criação, transições, prazos, responsáveis, departamentos, dispensas, reaberturas, comentários, evidências e lotes com ator, alvo, resultado, horário e correlação, sem conteúdo de arquivo ou material sensível.

#### Scenario: Reabertura administrativa
- **WHEN** `ADMIN` reabre tarefa concluída ou dispensada com justificativa
- **THEN** a timeline registra estado anterior/novo, ator e justificativa e o processo é recalculado

#### Scenario: Payload da timeline
- **WHEN** cliente consulta histórico autorizado
- **THEN** a API retorna somente eventos e campos allowlisted, sem `vault_object_id`, path, PFX, PEM, token, Termo XML ou bytes de evidência

### Requirement: Execução sem efeito fiscal implícito
O sistema SHALL NOT disparar chamada SERPRO/ADN/SEFAZ, alterar NSU/nNF ou executar mutação fiscal ao mudar tarefa, comentário, evidência ou processo operacional.

#### Scenario: Tarefa fiscal nominalmente concluída
- **WHEN** uma tarefa cujo título menciona obrigação fiscal é concluída
- **THEN** o sistema registra apenas a execução operacional e não presume transmissão, pagamento ou situação oficial

