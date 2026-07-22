## Context

O repositório já contém `TaxInstallmentOrder`, `TaxInstallmentParcel`, `TaxInstallmentPayment`, uma carteira Nuxt e um adapter genérico. A estrutura é aproveitável, porém o fluxo atual não corresponde ao contrato oficial: `PEDIDOSPARC` retorna `parcelamentos`; `PARCELASPARAGERAR` recebe `dados` vazio e retorna `listaParcelas` (com variação catalogada `listaParcela`); `OBTERPARC` retorna consolidação e `demonstrativoPagamentos`; `DETPAGTOPARC` recebe pedido + competência da parcela. Além disso, o consolidado atual replica uma lista global de parcelas em cada pedido e a ação genérica da UI consulta somente `PARCSN`.

O catálogo oficial vigente contém oito sistemas em produção, cada qual com os mesmos cinco tipos de operação: `PARCSN`, `PARCSN-ESP`, `PERTSN`, `RELPSN`, `PARCMEI`, `PARCMEI-ESP`, `PERTMEI` e `RELPMEI`. `PARC-PAEX` e `PARC-SIPADE` possuem somente entradas em prospecção, sem schema documentado, fixture ou poder conhecido; não são executáveis pelo `OperationCoordinateResolver`.

A mudança cruza integração SERPRO, persistência/consulta Laravel e a carteira Nuxt, mas preserva as fronteiras existentes: domínio chama `SerproOperationExecutor`, jobs usam o pipeline `FiscalMonitoringRun`/Horizon, tenant vem de `CurrentOffice` e a UI mantém o shell do arquétipo.

## Goals / Non-Goals

**Goals:**

- consultar todas as oito modalidades produtivas, manual e agendadamente, em runs independentes e idempotentes;
- decodificar respostas oficiais e persistir pedidos, parcelas e pagamentos sem cruzar pedido, modalidade ou `Office`;
- oferecer leitura consolidada por cliente e modalidade, detalhe local e estados de disponibilidade do catálogo;
- tornar PAEX/SIPADE visíveis e inequivocamente indisponíveis enquanto estiverem em prospecção;
- manter consultas faturáveis explícitas, confirmadas e limitadas, com testes determinísticos via fixtures.

**Non-Goals:**

- ativar egress real, kill switches ou flags em produção;
- adesão, reparcelamento, desistência ou qualquer mutação fiscal;
- promover operações em prospecção ou inventar schema/poder para PAEX/SIPADE;
- consultar `DETPAGTOPARC` para todo o histórico em cada ciclo automático;
- redesenhar o shell, criar serviço Compose, sidecar MEI ou alterar infraestrutura de deploy.

## Decisions

### 1. Catálogo de produto deriva do catálogo oficial, com disponibilidade explícita

`ParcelamentoServiceCatalog` continuará oferecendo as oito modalidades executáveis e passará a expor também PAEX/SIPADE como registros `PROSPECTION`, `monitoring_supported=false` e `executable=false`. O enqueue aceitará apenas modalidades executáveis. Coordenadas SERPRO serão resolvidas exclusivamente pelo `operation_key`; o domínio não enviará `idSistema`/`idServico` manualmente.

Alternativa descartada: adicionar PAEX/SIPADE ao enum executável. Isso permitiria runs sem contrato e violaria o fail-closed do catálogo.

### 2. Codec oficial isolado da projeção

Será criado um codec puro para:

- aceitar `dados` já decodificado pelo executor e normalizar `parcelamentos`;
- aceitar `listaParcelas` e a variante `listaParcela`;
- combinar `consolidacaoOriginal`, `alteracoesDivida` e `demonstrativoPagamentos` no pedido correto;
- converter datas compactas `AAAAMMDD`/`AAAAMMDDHHMMSS` e valores decimais da SERPRO sem usar `float` como fonte de centavos;
- produzir estrutura canônica indexada por `external_order_id`, com parcelas/pagamentos também indexados por pedido + competência.

A projeção consumirá apenas essa estrutura canônica. Assim, variantes de payload e regras de persistência ficam testáveis separadamente.

Alternativa descartada: ampliar condicionais diretamente em `ParcelamentoProjectionService`; manteria o acoplamento que hoje causa mistura entre pedidos.

### 3. Monitoramento automático evita explosão de chamadas

Cada run de modalidade fará:

1. uma chamada `PEDIDOSPARC` com payload vazio;
2. uma chamada `OBTERPARC` por pedido retornado, limitada por configuração conservadora;
3. uma chamada `PARCELASPARAGERAR` com payload vazio por modalidade.

O `demonstrativoPagamentos` de `OBTERPARC` já sustenta a projeção de pagamentos. `DETPAGTOPARC` será mantido para consulta explícita de uma parcela e não será multiplicado pelo histórico no ciclo automático. A lista de parcelas disponíveis, que não informa número do pedido, será associada somente ao pedido corrente mais recente da modalidade; a associação ficará registrada em metadata. Sem pedido elegível, nenhuma parcela órfã será persistida e um finding informativo será gerado.

Alternativa descartada: executar `DETPAGTOPARC` para cada parcela paga. O custo cresce com todo o histórico, apesar de não ser necessário para situação e valor pagos.

### 4. “Consultar todos” cria oito runs isoladas

Um serviço de aplicação receberá `Office` + cliente e enfileirará `MONITOR` para cada modalidade produtiva com idempotência do pipeline existente. O endpoint office-scoped aceitará um ou vários clientes dentro do limite de lote e retornará contagens/resultados por modalidade. Uma falha ou bloqueio de procuração em uma modalidade não impedirá as demais.

O agendador comercial reutilizará o mesmo catálogo de modalidades em vez de gerar o par inválido `INSTALLMENTS/INSTALLMENTS`. A UI chamará o endpoint específico, substituindo o default atual que só usa `PARCSN`.

Alternativa descartada: uma run monolítica com as oito modalidades. Ela perderia rastreabilidade, retry e consumo por operação/modalidade.

### 5. Carteira permanece por cliente e explicita agregação/modalidade

`/monitoring/installments` continuará sobre `MonitoringModuleTable`. Na visão “Todas”, o detalhe do cliente agregará modalidades, quantidade de pedidos, saldo/parcelas e próximo vencimento sem esconder que há múltiplos contratos. Ao filtrar uma modalidade, o backend aplicará o mesmo filtro tanto à seleção do cliente quanto ao enrichment do detalhe. O slideover continuará lendo somente projeção local e permitirá navegar pelos pedidos/modalidades retornados, sem egress ao abrir.

As tabs usarão labels humanos, grupo/regime e disponibilidade; PAEX/SIPADE aparecerão desabilitadas com indicação “Em prospecção”. A coluna Situação seguirá o contrato existente e não anunciará sort que a API não implementa.

Alternativa descartada: trocar a página por uma tabela global de pedidos. Isso quebraria a navegação/seleção por cliente que o shell de monitoramento usa nas demais carteiras.

### 6. Segurança, tenancy e evidência

Endpoints usam `CurrentOffice`, validam o cliente no mesmo tenant e mantêm viewer somente leitura. Admin/Operator podem enfileirar; nenhum `office_id` do body será aceito. O executor central continua responsável por contrato, procuração, budget, kill switch, rate limit e circuit breaker. Payloads/evidências persistidas não incluirão segredos, certificados ou tokens.

## Mapa de dependências

- DAG externo: change `C0`, sem upstream ativo bloqueante; usa como bases estáveis o catálogo oficial versionado, `SerproOperationExecutor`, main specs de monitoramento e o shell Nuxt.
- Ownership backend: `Services/Integra/Parcelamento`, controller/rotas de installments, detalhes de `ModulePortfolioQueryService` e testes próprios.
- Ownership web: página, tipos, client e utilitários de installments; não altera o shell compartilhado salvo correção estritamente necessária e coberta.
- Arquivos compartilhados com a change de Caixa Postal atualmente suja: `routes/api.php`, `AppServiceProvider` e inventários gerados. Edições serão pontuais, preservando linhas existentes; artefatos gerados só serão atualizados no gate final.
- Rollout: codec/projeção e testes podem avançar em paralelo com catálogo/orquestração; UI depende do contrato HTTP consolidado. O último nível contém apenas gates integrados.
- Rollback: reverter os arquivos desta change remove o endpoint/codec/UI adicionais; não há migração destrutiva nem alteração de schema necessária.

## Risks / Trade-offs

- [Bilhetagem multiplicada por oito modalidades] → confirmação explícita na UI, limite de lote/pedidos, runs isoladas e reutilização do budget/rate limit central.
- [Resposta oficial varia entre `listaParcela` e `listaParcelas`] → codec tolerante às duas chaves e testes de contrato.
- [Parcela disponível não identifica pedido] → associar apenas ao pedido corrente mais recente, guardar provenance e nunca inventar pedido.
- [Vazamento entre offices] → toda query e upsert inclui `office_id`; testes cruzados de API/projeção.
- [Mistura de pedidos/modalidades] → estrutura canônica composta por modalidade + pedido + parcela e chaves lógicas correspondentes.
- [Segredo ou PDF em JSON/log] → não expor body bruto; documentos seguem `FiscalEvidenceStore`/download autenticado.
- [PAEX/SIPADE mudarem de estado] → disponibilidade vem do catálogo; promoção futura exigirá nova change com schemas, fixtures, poderes e coverage.
- [Conflito com worktree suja] → patches mínimos nos arquivos compartilhados e verificação do diff por path antes de cada gate.

## Migration Plan

1. Introduzir codec e corrigir projeção/orquestração com testes unitários.
2. Expor catálogo e bulk enqueue tenant-scoped; cobrir API e agendador.
3. Ajustar enrichment da carteira e o client/UI tipados.
4. Rodar testes focados e depois todos os gates API, web e OpenSpec.
5. Não alterar flags; deploy usa defaults atuais fail-closed. Rollback é somente de código, sem reversão de dados.

## Open Questions

- Nenhuma para esta entrega. Quando PAEX/SIPADE passarem a produção, seus contratos e poderes precisarão ser reavaliados em change própria.
