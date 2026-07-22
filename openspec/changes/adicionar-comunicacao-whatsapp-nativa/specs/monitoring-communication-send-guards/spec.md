## MODIFIED Requirements

### Requirement: Roteamento de comunicação automática por service_code
O sistema SHALL materializar comunicação automática somente para o módulo/submódulo correspondente à política e ao `service_code` da competência: PGMEI → PGMEI; PGDASD → PGDAS-D; DCTFWeb → DCTFWeb; FGTS → avaliação FGTS. O sistema MUST NOT criar envio PGDAS-D para DEFIS, CCMEI, DASN ou serviços diferentes. A conclusão da consulta MUST NOT enviar imediatamente: o dispatch aguarda o cutoff configurado por Office+módulo.

#### Scenario: Sucesso PGDASD agenda PGDAS-D
- **WHEN** uma competência PGDASD possui política ativa, cliente elegível e destinatários WhatsApp
- **THEN** o sistema materializa dispatch(es) `SCHEDULED` no submódulo `pgdasd` para o cutoff configurado

#### Scenario: Sucesso DEFIS não agenda PGDAS-D
- **WHEN** uma run `simples_mei` possui `service_code` de DEFIS ou outro serviço não PGDASD/PGMEI
- **THEN** o sistema MUST NOT criar dispatch `submodule_key=pgdasd` ou `pgmei` por essa run

#### Scenario: Sucesso PGMEI agenda PGMEI
- **WHEN** a competência PGMEI possui política ativa e cliente elegível
- **THEN** o sistema materializa dispatch(es) `SCHEDULED` no submódulo `pgmei`

#### Scenario: DCTFWeb usa política própria
- **WHEN** competência DCTFWeb possui política ativa e cliente elegível
- **THEN** dispatch(es) DCTFWeb são agendados sem depender da política PGDAS/PGMEI

### Requirement: Envio fiscal exige documento canônico da competência
No cutoff, PGDAS-D, PGMEI e DCTFWeb SHALL enviar somente o artefato canônico armazenado para o mesmo Office, cliente, módulo e competência do dispatch. A existência de documento de outra competência MUST NOT habilitar envio. Ausência do artefato exato SHALL resultar em `SKIPPED_NO_DOCUMENT`, sem fallback, tarefa ou reativação automática tardia. FGTS SHALL permanecer `SKIPPED_NO_DOCUMENT` enquanto guia local for `UNSUPPORTED`. Provider/gateway permanece fail-closed por default.

#### Scenario: PGDAS sem documento da competência
- **WHEN** há artefato PGDAS histórico, mas nenhum DAS canônico para a competência do dispatch
- **THEN** o dispatch vira `SKIPPED_NO_DOCUMENT` e nenhum comando WhatsApp é criado

#### Scenario: PGDAS com documento exato
- **WHEN** existe `PgdasdArtifact` DAS canônico da mesma competência no cutoff
- **THEN** seu ID/digest é congelado no dispatch e uma mensagem com o documento é enfileirada

#### Scenario: PGMEI e DCTFWeb resolvem versões canônicas
- **WHEN** existe versão confirmada com bytes para a competência PGMEI ou DARF/evidência DCTFWeb correspondente
- **THEN** o resolver específico congela essa versão e não usa outra guia mais nova de período diferente

#### Scenario: Documento chega depois do cutoff
- **WHEN** dispatch já foi `SKIPPED_NO_DOCUMENT` e o documento aparece posteriormente
- **THEN** o automático não é reaberto; apenas um envio manual novo pode transmitir o documento

#### Scenario: FGTS sem suporte de guia
- **WHEN** política FGTS e switch do cliente estão ativos, mas `guide_status=UNSUPPORTED`
- **THEN** o sistema registra `SKIPPED_NO_DOCUMENT` e não envia texto nem anexo

### Requirement: Idempotência e dedupe do envio automático
Dispatches automáticos SHALL usar chave de no máximo 64 caracteres, estável por Office+cliente+módulo+submódulo+competência+canal+inbox+identidade+versão de template. Cada destinatário elegível SHALL possuir dispatch independente. Nova execução da mesma política MUST NOT duplicar dispatch ou mensagem. Envio manual SHALL permanecer reenviável com chave única curta.

#### Scenario: Dois destinatários recebem dispatches independentes
- **WHEN** preferência `ALL_ELIGIBLE` resolve dois telefones WhatsApp válidos
- **THEN** o sistema cria dois dispatches idempotentes e duas mensagens, cada um ligado à sua identidade

#### Scenario: Segunda execução não duplica destinatário
- **WHEN** scheduler reprocessa a mesma competência, política, inbox e identidade
- **THEN** nenhum dispatch ou mensagem adicional é criado para esse destinatário

#### Scenario: Alteração de destinatário não duplica anteriores
- **WHEN** seleção muda de um para dois destinatários antes do cutoff
- **THEN** somente o destinatário ainda ausente recebe novo dispatch; o existente é preservado

#### Scenario: Send manual permanece reenviável
- **WHEN** operador envia manualmente duas vezes com permissão e documento válido
- **THEN** ambos os envios criam chaves distintas de até 64 caracteres

## ADDED Requirements

### Requirement: Política de automação é explícita e fail-closed
O sistema SHALL separar horário de consulta de horário de envio por Office+módulo. Automação somente será efetiva com política ativa, `automatic_requested`, `whatsapp_enabled`, inbox geral válida e destinatário elegível. Ausência de qualquer condição MUST resultar em nenhum envio.

#### Scenario: Switch desligado
- **WHEN** cliente possui contatos e política ativa, mas `automatic_requested=false`
- **THEN** nenhum dispatch automático é materializado

#### Scenario: Política ausente
- **WHEN** cliente solicita automático, mas não há política de envio do módulo no Office
- **THEN** `automatic_effective=false` e nenhum dispatch é criado

#### Scenario: Inbox geral indisponível
- **WHEN** cutoff vence com inbox padrão ausente, desabilitada ou revogada
- **THEN** envio não é executado e a razão fail-closed é auditada sem usar outra inbox silenciosamente

### Requirement: Automação integra a conversa compartilhada
Mensagem fiscal automática SHALL criar ou reutilizar conversa ativa da inbox+identidade e deixá-la `PENDING`. Resposta inbound SHALL colocar a conversa `OPEN` na fila da inbox, mantendo dispatch, cliente e documento como contexto da timeline.

#### Scenario: Dispatch cria timeline
- **WHEN** dispatch fiscal passa para envio
- **THEN** mensagem automática aparece na conversa com módulo, competência e anexo associados

#### Scenario: Cliente responde documento
- **WHEN** destinatário responde à mensagem fiscal
- **THEN** a mesma conversa fica `OPEN`, preserva vínculo ao cliente e pode ser atribuída a um membro da fila
