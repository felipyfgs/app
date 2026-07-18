## ADDED Requirements

### Requirement: Consulta segura de contagem de pagamentos PAGTOWEB
O sistema SHALL oferecer uma consulta confirmada de contagem de documentos de arrecadação pagos para um cliente do escritório atual, usando exclusivamente `PAGTOWEB` / `CONTACONSDOCARRPG73` / versão `1.0` na rota funcional `Consultar` e os mecanismos centrais de capability, OAuth, procuração, poder `00004`, kill switch e classificação de bilhetagem.

#### Scenario: Consulta confirmada para cliente do escritório atual
- **WHEN** um usuário com permissão de sincronizar guias confirma uma consulta com filtro válido para um cliente do `CurrentOffice`
- **THEN** o sistema SHALL enfileirar a operação central com a coordenada oficial e sem confiar em `office_id` fornecido pelo cliente.

#### Scenario: Escritório ou capability não elegível
- **WHEN** o cliente não pertence ao escritório atual, a feature de guias está desligada ou a capability SERPRO está bloqueada
- **THEN** o sistema SHALL negar ou falhar de forma segura sem emitir uma chamada externa.

### Requirement: Filtros e resposta sanitizados da contagem
O sistema SHALL aceitar somente filtros oficiais documentados, exigir pelo menos um filtro, rejeitar chaves e combinações ambíguas e converter a resposta em uma contagem inteira não negativa sem persistir payload de transporte ou números completos de documentos.

#### Scenario: Filtro oficial válido
- **WHEN** a consulta contém um intervalo de arrecadação, faixa de valor, lista de receita/tipo ou documento em formato permitido
- **THEN** o sistema SHALL normalizar o filtro, conservar apenas resumo seguro e enviar `pedidoDados.dados` como string JSON pela infraestrutura central.

#### Scenario: Payload inválido ou resposta ambígua
- **WHEN** a consulta inclui uma chave desconhecida, não contém filtro, combina critérios incompatíveis ou o retorno `dados` não representa inteiro não negativo
- **THEN** o sistema SHALL rejeitar ou registrar falha sanitizada sem criar uma projeção de sucesso.

### Requirement: Histórico e interface de monitoramento de contagem
O sistema SHALL disponibilizar para usuários autorizados um histórico e a última projeção da contagem em uma superfície de guias do cliente, identificando origem e horário sem revelar segredos ou dados de pagamento individuais.

#### Scenario: Exibição da última consulta
- **WHEN** existe uma observação de contagem para o cliente do escritório atual
- **THEN** a interface SHALL mostrar a quantidade, status, origem e horário da última observação e uma lista de histórico segura.

#### Scenario: Aviso de operação potencialmente bilhetável
- **WHEN** o usuário abre o formulário de nova contagem
- **THEN** a interface SHALL solicitar confirmação explícita e informar que consultas na rota `Consultar` podem ser bilhetáveis quando uma capability real estiver autorizada.
