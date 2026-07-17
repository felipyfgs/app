## ADDED Requirements

### Requirement: Preferências de comunicação em modo template
O sistema SHALL persistir a intenção de comunicação automática separada por cliente, módulo e submódulo, SHALL manter preferências distintas de e-mail e WhatsApp e SHALL sempre informar `execution_mode=TEMPLATE_ONLY` e `automatic_effective=false` nesta capacidade.

#### Cenário: Ativação individual válida
- **WHEN** ADMIN ou OPERATOR ativa o automático com canal habilitado e contato ativo elegível
- **THEN** `automatic_requested` é persistido, auditado e o lock version é incrementado sem criar envio

#### Cenário: Ativação sem destinatário
- **WHEN** o usuário tenta ativar sem canal e contato elegível
- **THEN** a API rejeita a ativação e a interface abre as configurações de canais

#### Cenário: Concorrência
- **WHEN** a versão enviada não corresponde à versão persistida
- **THEN** a API retorna conflito sem sobrescrever a alteração mais recente

### Requirement: Alteração em massa segura
O sistema SHALL permitir que ADMIN e OPERATOR ativem ou desativem apenas o switch geral de até 100 clientes do escritório por operação atômica.

#### Cenário: Lote inválido
- **WHEN** o lote contém cliente inacessível, mais de 100 itens ou cliente sem configuração elegível para ativação
- **THEN** nenhuma preferência do lote é alterada

#### Cenário: Viewer
- **WHEN** um VIEWER tenta alterar preferência individual ou em massa
- **THEN** a API retorna `403`

### Requirement: Prévia sem envio
O sistema SHALL disponibilizar uma prévia com PA, documentos locais, canais e destinatários mascarados, MUST retornar `can_send=false` e MUST NOT criar dispatch, evento, job ou mensagem.

#### Cenário: Abrir prévia
- **WHEN** o usuário aciona o ícone Enviar
- **THEN** o modal mostra os dados disponíveis e mantém a ação final desabilitada

### Requirement: Rastreio futuro observável
O sistema SHALL expor histórico tenant-scoped por canal com os estados `NOT_CONFIGURED`, `NO_HISTORY`, `QUEUED`, `SENT`, `DELIVERED`, `READ`, `PARTIAL`, `FAILED` e `CANCELED`, sem alterar estado por mera visualização interna.

#### Cenário: Nenhum envio realizado
- **WHEN** não existem dispatches para o cliente
- **THEN** a API e o modal retornam `NOT_CONFIGURED` ou `NO_HISTORY` sem fabricar eventos

#### Cenário: Abrir rastreio
- **WHEN** o usuário abre o modal de rastreio
- **THEN** nenhum registro assume `READ` e nenhum evento é criado

### Requirement: Destinatários protegidos
O sistema SHALL selecionar apenas contatos ativos marcados para receber alertas, SHALL exigir `is_whatsapp` para o canal WhatsApp e SHALL expor destinatários somente mascarados na prévia/rastreio.

#### Cenário: Dado público do CNPJ
- **WHEN** existe e-mail ou telefone obtido apenas de cadastro público
- **THEN** ele não é selecionado automaticamente como destinatário
