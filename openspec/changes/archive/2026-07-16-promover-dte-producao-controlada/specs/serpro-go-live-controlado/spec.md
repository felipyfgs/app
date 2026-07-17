## MODIFIED Requirements

### Requirement: Canário faturável requer autorização separada
A primeira chamada potencialmente faturável MUST ser opcional e fixada em `dte.consultar`, `idSistema=DTE`, `idServico=CONSULTASITUACAODTE111`, versão `1.0`, rota `/Consultar`, sem parâmetros livres de negócio e com quantidade máxima um. O Proprietário SHALL selecionar server-side um Office Production ativo e um cliente pertencente a ele; a execução MUST NOT aceitar `office_id`, operação ou coordenadas do client.

O canário SHALL exigir aprovação registrada por dois usuários distintos: o Proprietário `PLATFORM_ADMIN` e um `Office ADMIN` do Office piloto, cada um com reconfirmação da própria senha válida por no máximo quinze minutos. TOTP MUST NOT ser exigido. A conta dual MUST NOT satisfazer os dois papéis. O canário MUST NOT fazer parte de CI, setup, deploy, health check ou preflight.

#### Scenario: Usuário deseja testar sem pagar
- **WHEN** não existe aprovação de canário faturável ativa
- **THEN** o processo SHALL encerrar sem executar `/Consultar`, `/Emitir` ou `/Declarar`

#### Scenario: Escopo livre é enviado pelo cliente
- **WHEN** uma requisição contém `office_id`, operação, coordenadas SERPRO ou parâmetros de negócio
- **THEN** o sistema MUST rejeitar o campo e MUST NOT alterar o alvo persistido

#### Scenario: Aprovação incompleta
- **WHEN** falta um dos aprovadores, sua reconfirmação recente, limite quantitativo positivo ou escopo exato
- **THEN** a chamada SHALL permanecer bloqueada

#### Scenario: Conta dual tenta aprovar pelos dois papéis
- **WHEN** a mesma conta dual tenta registrar as aprovações global e do Office
- **THEN** o sistema SHALL aceitar no máximo uma delas e continuar exigindo um segundo usuário autorizado

#### Scenario: Cliente pertence a outro Office
- **WHEN** o cliente selecionado não pertence ao Office piloto persistido
- **THEN** o sistema MUST bloquear o canário sem revelar dados do outro tenant

## ADDED Requirements

### Requirement: Canário DTE exige prontidão global e tenant completa
Imediatamente antes do transporte, o sistema MUST exigir ambiente Production, credencial ativa, OAuth recente da mesma versão, seis gates externos aceitos, A1 do Office válido, Termo aceito, procuração e-CAC válida com poder `00050`, limites globais e do Office positivos, Office classificado Production e kill switches externo, global e DTE abertos. Qualquer regressão MUST bloquear antes do HTTP.

#### Scenario: Procuração não possui poder 00050
- **WHEN** todos os demais gates passam mas o poder `00050` não está válido para o cliente
- **THEN** `dte.consultar` MUST permanecer bloqueado

#### Scenario: Kill switch externo está ligado
- **WHEN** o painel permitir o canário mas `SERPRO_KILL_SWITCH=true`
- **THEN** nenhuma conexão SHALL ser iniciada

### Requirement: Canário DTE possui uma única tentativa idempotente
O canário SHALL criar uma chave idempotente por instalação, ambiente, Office, cliente e operação e MUST permitir exatamente uma reserva/dispatch. Replay SHALL devolver o estado durável sem novo transporte; resultado remoto incerto MUST permanecer `UNCERTAIN` e MUST NOT sofrer retry cego.

#### Scenario: Requisição é repetida após sucesso
- **WHEN** a mesma chave lógica é reapresentada
- **THEN** o sistema SHALL devolver a correlação/estado persistidos sem nova chamada nem novo consumo

#### Scenario: Timeout ocorre após dispatch
- **WHEN** não é possível provar o resultado remoto
- **THEN** a tentativa SHALL permanecer incerta e qualquer repetição MUST ser bloqueada até reconciliação manual

### Requirement: Resultado fiscal é isolado no Office
O resultado fiscal do DTE MUST ser armazenado e exibido somente no contexto do Office piloto. A superfície global SHALL expor apenas status, correlação, timestamps, versão e quantidade consumida, sem payload, mensagem fiscal detalhada, XML ou dados do contribuinte.

#### Scenario: Proprietário sem membership consulta o canário
- **WHEN** o Proprietário abre o histórico global
- **THEN** recebe somente o resumo sanitizado e MUST NOT receber o resultado fiscal

### Requirement: Promoção DTE limitada depende de reconciliação manual
Após um único canário bem-sucedido, o Proprietário MAY promover DTE para `LIMITED` somente depois de registrar referência e resumo da reconciliação com o histórico da Área do Cliente, senha recente, frase exata, motivo e janela vigente. A promoção MUST restringir operação ao mesmo Office piloto e máximo inicial de dez consultas no ciclo; nenhuma outra operação ou Office SHALL ser habilitado.

#### Scenario: Canário passou mas não foi reconciliado
- **WHEN** não existe referência de reconciliação manual válida
- **THEN** DTE MUST permanecer no modo de canário unitário

#### Scenario: Promoção limitada é concluída
- **WHEN** sucesso, reconciliação e confirmação do Proprietário estão válidos
- **THEN** somente `dte.consultar` do mesmo Office SHALL entrar em `LIMITED` com teto dez

#### Scenario: Proprietário desativa DTE
- **WHEN** a desativação auditada é confirmada
- **THEN** novas reservas MUST ser bloqueadas imediatamente, preservando ledger e evidências

