# serpro-installments-monitoring Specification

## Purpose
TBD - created by archiving change monitorar-todos-parcelamentos-serpro. Update Purpose after archive.
## Requirements
### Requirement: Catálogo completo e honesto de parcelamentos SERPRO

O produto SHALL expor as oito modalidades produtivas `PARCSN`, `PARCSN-ESP`, `PERTSN`, `RELPSN`, `PARCMEI`, `PARCMEI-ESP`, `PERTMEI` e `RELPMEI` como monitoráveis. O produto SHALL também tornar `PARC-PAEX` e `PARC-SIPADE` visíveis como `PROSPECTION`, mas MUST bloquear execução enquanto o catálogo oficial não as marcar como produtivas e executáveis.

#### Scenario: Modalidades produtivas disponíveis

- **WHEN** um usuário autorizado consulta o catálogo de Parcelamentos
- **THEN** a resposta lista as oito modalidades produtivas com `monitoring_supported=true`
- **AND** informa label, regime, estado oficial e disponibilidade de execução

#### Scenario: Modalidades em prospecção são fail-closed

- **WHEN** o catálogo ou a UI apresenta `PARC-PAEX` ou `PARC-SIPADE`
- **THEN** elas aparecem como “Em prospecção” e não podem ser enfileiradas
- **AND** o backend rejeita tentativa direta sem criar run nem chamar SERPRO

### Requirement: Monitoramento abrange todas as modalidades produtivas

O produto SHALL permitir solicitar o monitoramento de todas as modalidades produtivas para um ou mais clientes do escritório ativo. O backend MUST criar uma run independente e idempotente por cliente + modalidade, de modo que bloqueio ou falha parcial não impeça as demais modalidades.

#### Scenario: Consulta completa de um cliente

- **WHEN** Admin ou Operator confirma “Consultar todos” para um cliente elegível
- **THEN** o backend enfileira até oito runs `MONITOR`, uma para cada modalidade produtiva
- **AND** retorna o resultado individual por modalidade e contagens consolidadas

#### Scenario: Viewer não enfileira

- **WHEN** um Viewer tenta solicitar monitoramento
- **THEN** a API responde `403`
- **AND** nenhuma run é criada

#### Scenario: Cliente de outro tenant é rejeitado

- **WHEN** o request referencia cliente que não pertence ao `CurrentOffice`
- **THEN** a API responde sem expor dados do outro escritório
- **AND** nenhuma run ou chamada externa é criada

### Requirement: Orquestração segue os contratos oficiais

Para cada modalidade, o monitor automático SHALL consultar `PEDIDOSPARC` com payload vazio, consultar `OBTERPARC` por pedido limitado e consultar `PARCELASPARAGERAR` uma única vez com payload vazio. A projeção SHALL usar `demonstrativoPagamentos` para o estado resumido de pagamentos e MUST NOT executar `DETPAGTOPARC` em N+1 durante todo ciclo automático.

#### Scenario: Payload de parcelas para gerar é vazio

- **WHEN** uma run `MONITOR` executa uma modalidade produtiva
- **THEN** `PARCELASPARAGERAR` recebe `businessData` vazio
- **AND** não recebe `numeroParcelamento`

#### Scenario: Detalhe de pagamento permanece consultável

- **WHEN** uma consulta explícita `CONSULTAR_PAGAMENTO` informa pedido e `anoMesParcela`
- **THEN** o backend usa o `operation_key` `*.detpagtoparc` da modalidade
- **AND** projeta o pagamento apenas no pedido/parcela correspondentes

### Requirement: Normalização e persistência preservam vínculos

O backend SHALL normalizar `parcelamentos`, `listaParcela`/`listaParcelas`, `consolidacaoOriginal`, `alteracoesDivida` e `demonstrativoPagamentos` em pedidos, parcelas e pagamentos canônicos. Cada registro MUST permanecer isolado por `office_id`, `client_id`, modalidade, pedido externo e chave da parcela; valores monetários e datas compactas MUST ser convertidos de forma determinística.

#### Scenario: Dois pedidos não compartilham parcelas

- **WHEN** a SERPRO retorna dois pedidos com demonstrativos distintos
- **THEN** cada parcela e pagamento é persistido somente sob seu pedido de origem
- **AND** nenhuma parcela do primeiro pedido aparece no detalhe do segundo

#### Scenario: Variações da lista de parcelas são aceitas

- **WHEN** `PARCELASPARAGERAR` retorna `listaParcelas` ou `listaParcela`
- **THEN** o codec produz a mesma coleção canônica de parcelas disponíveis

#### Scenario: Consulta sem pedidos não inventa regularidade

- **WHEN** a modalidade retorna lista de pedidos vazia
- **THEN** a run conclui com situação `UNKNOWN` e finding informativo
- **AND** o sistema MUST NOT marcar o cliente como “Em dia” por ausência de dados

### Requirement: APIs de leitura são locais e tenant-scoped

Os endpoints de pedidos, parcelas e guias SHALL ler apenas projeções persistidas do `CurrentOffice`. Abrir um detalhe na UI MUST NOT disparar consulta SERPRO, e filtros de modalidade MUST ser aplicados tanto à carteira quanto ao detalhe agregado retornado.

#### Scenario: Abrir detalhe sem egress

- **WHEN** o usuário abre o slideover de um pedido
- **THEN** a UI carrega pedido, parcelas e pagamentos pelos endpoints locais
- **AND** nenhuma run fiscal é criada

#### Scenario: Filtro e enrichment usam a mesma modalidade

- **WHEN** a carteira é filtrada por `RELPSN`
- **THEN** as linhas e seus resumos consideram somente pedidos `RELPSN`
- **AND** não exibem como principal um pedido de outra modalidade

### Requirement: Carteira exibe todas as modalidades sem esconder agregação

A página `/monitoring/installments` SHALL manter o shell canônico do dashboard, oferecer filtro/tabs para as oito modalidades produtivas, apresentar PAEX/SIPADE como indisponíveis e permitir “Consultar todos” com confirmação de custo. Na visão “Todas”, o resumo do cliente SHALL informar modalidades e totais agregados; ao filtrar uma modalidade, SHALL exibir o pedido e as parcelas daquela modalidade.

#### Scenario: Portfólio completo por cliente

- **WHEN** um cliente possui pedidos em mais de uma modalidade
- **THEN** a visão “Todas” indica todas as modalidades encontradas e totais agregados
- **AND** o operador pode filtrar cada modalidade sem perder a navegação por cliente

#### Scenario: Confirmação antes do lote faturável

- **WHEN** o operador inicia “Consultar todos”
- **THEN** a UI informa quantidade de clientes e que cada cliente pode gerar até oito consultas compostas
- **AND** nenhuma chamada é enfileirada antes da confirmação

#### Scenario: Situação e histórico honestos

- **WHEN** a carteira exibe situação, atraso, próxima parcela ou pagamento
- **THEN** os valores vêm das projeções locais e da evidência oficial normalizada
- **AND** a UI não afirma inadimplência definitiva apenas por vencimento sem confirmação da fonte
