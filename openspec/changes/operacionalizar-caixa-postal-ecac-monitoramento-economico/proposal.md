## Why

A Caixa Postal já consegue persistir e exibir mensagens quando uma consulta é executada, mas não possui monitoramento recorrente do portfólio nem um acionamento visível na própria inbox; por isso a UI permanece vazia até uma operação manual escondida. A SERPRO oferece o evento gratuito E0601 para reduzir consultas faturadas, permitindo uma busca diária quase gratuita sem prometer uma caixa completa usando somente sinais.

## What Changes

- Adicionar monitoramento econômico por escritório: consultar diariamente o E0601 em lote pelo caminho gratuito `/Monitorar` e disparar `caixa_postal.lista` somente para contribuintes com data de evento ainda não reconciliada.
- Persistir integralmente, de forma segura e retryable, a matriz one-shot obtida da SERPRO antes de marcar o resultado remoto como consumido; distinguir recebimento remoto de processamento local.
- Fazer bootstrap pago de todos os clientes elegíveis na ativação e reconciliação completa periódica configurável, com padrão mensal no modo econômico.
- Oferecer modos `ECONOMICO` e `DIARIO_COMPLETO`, orçamento/caps e estimativa baseada na tabela de preços vigente; preço shadow MUST NOT ser apresentado como preço oficial.
- No modo econômico, buscar DETALHE sob demanda ou por política explícita com cap, em vez de pré-buscar até dez corpos automaticamente.
- Tornar “Atualizar agora” e o estado do monitoramento visíveis na página `/monitoring/mailbox`, incluindo última verificação gratuita, última sincronização paga, próxima execução, cobertura, bloqueios de procuração e gasto estimado.
- Implementar corretamente ou ocultar a ação `caixa_postal.indicador` enquanto não houver adapter; quando disponível, tratá-la apenas como diagnóstico de mensagens ainda não abertas, nunca como garantia de completude.
- Adicionar testes Unit, Feature e Vitest para contrato SERPRO, one-shot, idempotência, scheduler, orçamento, tenancy e estados operacionais da UI.

Non-goals: ligar flags SERPRO de produção; executar canário live ou chamadas faturadas sem autorização operacional explícita; oferecer garantia jurídica de ciência; marcar leitura oficial na RFB; baixar anexos não documentados; realizar mutações fiscais; alterar canais SEFAZ; adicionar `mei`/`mei-worker` ao Compose; restaurar `services/mei`; criar rotinas de backup/restore ainda indisponíveis.

## Capabilities

### New Capabilities

- `mailbox-monitoramento-economico`: contrato do monitoramento E0601 por escritório, processamento durável one-shot, sincronização paga condicional, bootstrap/reconciliação, controles de custo e UX operacional da Caixa Postal.

### Modified Capabilities

- (nenhuma)

## Impact

- API: fluxo `EventosAtualizacao`, scheduler/jobs, resolução de CNPJ por estabelecimento, `MailboxEventService`, política LISTAR/DETALHE, endpoints de estado/sync e registro do indicador.
- Dados: persistência dos itens recebidos por protocolo e do estado de monitoramento/reconciliação por escritório/cliente, sem armazenar payload fiscal bruto ou CNPJ em logs.
- Web: página `/monitoring/mailbox`, ação visível de atualização e estados vazios honestos em linguagem de negócio; custo, execução e diagnóstico técnico permanecem protegidos no backend e fora da tela do contador.
- SERPRO: `SOLICEVENTOSPJ132`, `OBTEREVENTOSPJ134`, E0601 e, opcionalmente, `INNOVAMSG63`; `/Consultar` permanece sujeito a bilhetagem e kill switches fail-closed.
- Testes: PHPUnit Unit/Feature, Vitest e gates completos das áreas API, Web e OpenSpec.

### Dependências entre changes

- Nível: **C1**
- Bases estáveis: infraestrutura de `EVENTOSATUALIZACAO`, ledger/catálogo SERPRO, scheduler fiscal e APIs de mailbox existentes.
- Depende de: `completar-caixa-postal-ecac-e2e` — capability `mailbox-caixa-postal`, marco `apply`, relação `bloqueante` (esta change reutiliza persistência LISTAR→DETALHE, API de leitura e inbox entregues pela upstream).
- Desbloqueia: monitoramento diário operacional da Caixa Postal com custo controlado e UI autoexplicativa.
- Paralelismo: não editar `CaixaPostalListAdapter`, `MailboxDetailEnqueueService`, `mailbox.vue`, `MailboxMail.vue` nem o contrato `mailbox-caixa-postal` em paralelo antes de estabilizar a upstream; trabalhos SERPRO/SITFIS sem esses ownerships podem seguir em paralelo.
