## MODIFIED Requirements

### Requirement: Ledger e proveniência por chamada
Cada chamada técnica SHALL registrar office, cliente, operação, rota, status, tag, latência, faturamento e proveniência sem armazenar segredo ou payload fiscal em log. Quando originada por uma consulta de monitor, a chamada SHALL carregar correlação com o lançamento comercial, sem assumir equivalência entre quantidade de chamadas técnicas e unidades comerciais.

#### Scenario: Resultado simulado
- **WHEN** uma capacidade simulated for usada fora de produção
- **THEN** o resultado SHALL ser marcado como simulado e MUST NOT contar como evidência produtiva

#### Scenario: Consulta produz polling técnico
- **WHEN** uma única consulta comercial gerar solicitação e múltiplos pollings
- **THEN** todas as chamadas SHALL apontar para a mesma correlação comercial e preservar sua classificação técnica individual

### Requirement: Mutações fail-closed
Operações mutantes produtivas SHALL permanecer desligadas por padrão e exigir flag, allowlist, assinatura writable, ator com capacidade efetiva de ADMIN, desafio de autenticação recente aplicável ao ator, confirmação, elegibilidade, idempotência, orçamento, contrato saudável e kill switch aberto. Para `PLATFORM_ADMIN` em contexto privilegiado, o desafio MUST ser reconfirmação recente de senha e MUST NOT ser substituído por bypass; os requisitos de autenticação vigentes para administradores do escritório permanecem aplicáveis.

#### Scenario: Scheduler encontra mutação pendente
- **WHEN** um ciclo automático de monitoramento identificar uma ação mutante possível
- **THEN** ele MUST NOT criar nem executar a intenção mutante

#### Scenario: Gate ausente
- **WHEN** qualquer gate mutante estiver ausente
- **THEN** o transporte externo SHALL ser bloqueado antes da chamada

#### Scenario: Plataforma tenta mutação sem reconfirmar senha
- **WHEN** um `PLATFORM_ADMIN` privilegiado solicitar mutação fiscal sem confirmação recente
- **THEN** a operação SHALL ser bloqueada antes do transporte mesmo que os demais gates estejam válidos

### Requirement: Execução assíncrona controlada
Refreshes e polling SHALL usar Horizon, locks por office/cliente/operação e reagendamento orientado por espera oficial. Execuções mensais de monitor MUST também usar itens idempotentes por período e admitir continuidade nos dias seguintes sem repetir a solicitação nem o consumo comercial.

#### Scenario: Resposta ainda processando
- **WHEN** o SERPRO retornar estado pendente
- **THEN** o job SHALL persistir o protocolo e reagendar sem duplicar a solicitação inicial

#### Scenario: Item mensal atravessa o dia
- **WHEN** rate limit ou backpressure adiar um item automático para o dia seguinte
- **THEN** o mesmo item SHALL continuar sob o mesmo lock e correlação sem novo consumo

## ADDED Requirements

### Requirement: Procuração sincronizada e aplicada por operação
O monitoramento SHALL sincronizar do SERPRO os poderes já concedidos no e-CAC e normalizar por cliente os estados `Autorizada`, `Sem procuração`, `Vencida` e `Não verificada`, com validade e última verificação. Uma operação MUST ser bloqueada somente quando seu metadado oficial exigir o poder ausente ou inválido; criação, importação e override manual MUST ser proibidos.

#### Scenario: Cliente sem procuração usa operação independente
- **WHEN** o cliente estiver sem procuração e a `operation_key` não exigir poder
- **THEN** a operação SHALL poder prosseguir pelos demais gates

#### Scenario: Poder obrigatório está vencido
- **WHEN** a `operation_key` exigir poder e a sincronização indicar procuração vencida
- **THEN** o monitor SHALL bloquear somente essa operação antes do transporte

