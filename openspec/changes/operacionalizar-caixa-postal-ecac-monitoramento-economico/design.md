## Context

O fluxo atual possui catálogo e transporte para `EVENTOSATUALIZACAO`, persistência de protocolo/ETA/TTL, `MailboxEventService`, LISTAR/DETALHE e inbox. Porém, PJ é bloqueado por divergência documental (`evento` versus `eventValue`), o `OBTER` descarta a matriz e guarda apenas um resumo, não há scheduler por escritório nem configuração operacional, `caixa_postal.indicador` é anunciado sem adapter correspondente e `/monitoring/mailbox` apenas lê dados já persistidos.

A SERPRO define E0601 como sinal de nova mensagem, retornado por `/Monitorar` em lote de até 1.000 CNPJs. O retorno é assíncrono, tem TTL informado pelo servidor e é apagado na primeira obtenção HTTP 200. A matriz fornece somente data `AAMMDD`, vazio ou `x`; logo ela não é uma inbox nem possui granularidade para distinguir várias chegadas no mesmo dia. LISTAR e DETALHE usam `/Consultar` e podem ser faturados. O desenho atravessa API, jobs Horizon, Postgres, vault, ledger de custo e Nuxt, justificando uma única capability transversal centrada no resultado implantável “monitoramento econômico da mailbox”.

## Goals / Non-Goals

**Goals:**

- Fazer uma verificação diária gratuita por escritório e condicionar consultas pagas a eventos ou reconciliações devidas.
- Nunca perder o resultado remoto one-shot por falha entre HTTP 200 e processamento local.
- Manter isolamento por `Office`, idempotência, limites oficiais, kill switches e orçamento antes de egress.
- Entregar bootstrap, reconciliação, detalhe sob demanda e uma UX que explique cobertura, custo e bloqueios.
- Suportar CNPJ alfanumérico e procuração negada por cliente sem falhar o lote inteiro.

**Non-Goals:**

- Habilitar produção, executar canário live ou autorizar gasto SERPRO durante a implementação.
- Prometer tempo real, webhook, completude gratuita, ciência jurídica ou marcar leitura oficial.
- Armazenar anexos não documentados, payload fiscal bruto em banco/log ou segredos fora do vault.
- Alterar shell do dashboard, canais SEFAZ, Compose, sidecar MEI ou rotinas ops indisponíveis.

## Decisions

### 1. Agendamento por escritório e não por cliente

Criar configuração única por `office_id`, inicialmente `enabled=false`, com `mode`, horário/fuso, intervalo de reconciliação, política de detalhe e orçamento. Um comando scheduler executado a cada minuto seleciona configurações devidas e despacha jobs Horizon com lock por escritório/evento/lote. Os clientes elegíveis são resolvidos no momento do job e agrupados deterministicamente em lotes de até 1.000 CNPJs completos obtidos de `establishments.cnpj`.

Alternativa rejeitada: reutilizar uma schedule paga por cliente a cada 24 horas. Ela transforma 14 clientes em pelo menos 420 LISTAR/mês e ignora o mecanismo gratuito criado pela SERPRO.

### 2. Codec PJ versionado, sem fallback automático

Extrair o envelope de SOLICITAR/OBTER para codec testável. A versão inicial usará `evento`, que aparece no exemplo completo de request e no OBTER, mas manterá o nome do campo explícito em configuração/metadata de contrato. Configuração ausente ou inválida falha antes do egress. `eventValue` pode ser habilitado somente após evidência controlada; não haverá retry automático com variante porque outra solicitação pode consumir limite ou ser faturada.

Alternativa rejeitada: remover o bloqueio PJ e tentar ambos os campos. Isso troca uma divergência documental por egress duplicado e comportamento não auditável.

### 3. Caixa de entrada durável para o resultado one-shot

Ao receber HTTP 200 no OBTER, o job grava `dados` em artefato privado determinístico no `SecureObjectStore`, com digest e referência no run, antes de qualquer parsing. Em seguida, uma transação bloqueia o run, normaliza as linhas em `serpro_eventos_run_items` e marca `remote_result_received_at`. `local_processing_status` (`PENDING`, `PROCESSING`, `SUCCEEDED`, `FAILED`) é separado de `one_shot_complete`; o segundo significa somente que o remoto foi consumido.

Cada item persiste `office_id`, `run_id`, `client_id` nullable, HMAC do NI, classificação, data do evento, estado de processamento, run direcionada e erro sanitizado. CNPJ cru não entra em logs nem permanece na tabela. Linhas não mapeadas conservam somente fingerprint. O artefato criptografado fica retido até processamento e janela de auditoria concluídos.

Alternativa rejeitada: persistir apenas contagens/digest, como hoje. Um crash depois do 200 torna impossível reconstruir quais clientes devem ser consultados.

### 4. Fechamento diário antes do LISTAR econômico

E0601 informa apenas a data da atualização mais recente. No modo econômico, uma data igual ao dia corrente do escritório é guardada como pendente; LISTAR é disparado quando essa data já pertence a um dia encerrado e é maior que `last_reconciled_event_date`. Assim, uma única lista captura todas as mensagens daquele dia sem repetir chamadas a cada poll. A data só avança após LISTAR bem-sucedido.

Isso produz SLA típico próximo de 24 horas, acrescido do atraso de sincronização da fonte, e será comunicado na UI. “Atualizar agora” permite antecipar a consulta mediante preview/confirmação de custo.

Alternativa rejeitada: disparar imediatamente para qualquer data nova e marcá-la reconciliada. Uma segunda mensagem no mesmo dia manteria o mesmo `AAMMDD` e poderia ser perdida até a reconciliação completa.

### 5. Bootstrap e reconciliação como rede de segurança

Criar `mailbox_client_sync_states` por office/client com estado de bootstrap, data E0601 observada/pendente/reconciliada, última LISTAR, última reconciliação completa e bloqueio. Ao ativar, a API oferece preview e bootstrap explícito de todos os clientes elegíveis. O modo `ECONOMICO` usa reconciliação completa padrão a cada 30 dias; `DIARIO_COMPLETO` exige opt-in explícito e orçamento antes de LISTAR todos os dias.

Alternativa rejeitada: depender somente dos últimos 60 dias de E0601. Clientes novos, períodos longos de indisponibilidade ou falhas do produtor deixariam a inbox incompleta sem diagnóstico.

### 6. Orçamento antes do egress e DETALHE sob demanda

Toda LISTAR/DETALHE passa pelo ledger/eligibilidade e por um guard de orçamento usando a versão de preço vigente. Versões shadow retornam valor com fonte `SHADOW` e aviso; falta de preço retorna `UNKNOWN`, nunca zero. O preview soma chamadas mínimas, deixando explícito que paginação e quantidade de detalhes podem aumentar o custo.

No modo econômico, `max_detail_fetches_per_sync` efetivo será zero. O usuário solicita DETALHE ao abrir mensagem sem corpo, após preview, ou uma política futura opt-in pode habilitar cap baixo. O modo diário completo também mantém cap configurável, não herda silenciosamente dez detalhes.

Alternativa rejeitada: pré-buscar dez corpos por LISTAR como default global. Isso pode custar mais que a própria descoberta das mensagens.

### 7. APIs office-scoped e UX na inbox

Adicionar endpoints Sanctum no contexto `CurrentOffice` para consultar/alterar configuração, obter estado, gerar preview e confirmar sync. Nenhum recebe `office_id`; referências a clientes são revalidadas no office. O POST de confirmação usa idempotency key e retorna runs enfileiradas, nunca aguarda a SERPRO.

`/monitoring/mailbox` ganha card compacto de estado, ação “Atualizar agora”, modo, cobertura, última verificação gratuita/paga, próxima execução, gasto estimado e clientes com `x`. Empty states distinguem nunca sincronizada, vazia após sucesso, bloqueada, atrasada e falha. O shell do dashboard permanece o do arquétipo.

### 8. INNOVAMSG63 como diagnóstico secundário

Implementar e registrar `CaixaPostalIndicatorAdapter` para que uma ação anunciada seja executável. Seu resultado é armazenado como observação e rotulado “mensagens ainda não abertas”; zero não altera datas reconciliadas e o scheduler econômico não depende dele.

Alternativa rejeitada: usar o indicador como gatilho principal. A SERPRO deixa de considerar a mensagem nova quando qualquer pessoa a abre, inclusive fora do produto.

### 9. Resiliência assíncrona e observabilidade sanitizada

SOLICITAR persiste protocolo, ETA e TTL. OBTER é reagendado por Horizon após `TempoEsperaMedioEmMs`, sem `sleep` bloqueante. HTTP 429 abre cooldown até a janela seguinte; TTL expirado gera falha recuperável para uma nova execução futura, sem loop. Métricas usam office/run, quantidades, operação, fonte do preço e status, nunca NI, corpo, token, PFX ou payload bruto.

## Mapa de dependências

```text
completar-caixa-postal-ecac-e2e (C0, apply)
                 │ bloqueante: LISTAR/DETALHE/API/inbox
                 ▼
operacionalizar-caixa-postal-ecac-monitoramento-economico (C1)
        ├── API/dados/eventos e scheduler
        ├── política de custo e sync
        └── estado/ações da UI
```

- Ownership upstream compartilhado: `CaixaPostalListAdapter`, `MailboxDetailEnqueueService`, `mailbox.vue`, `MailboxMail.vue` e delta `mailbox-caixa-postal`; aplicar esta change somente sobre a versão estabilizada da upstream.
- Ownership desta change: codec/processor E0601, settings/itens/estado de sync, jobs do monitoramento, endpoints de monitoramento, adapter INNOVAMSG63 e card/ação operacional da inbox.
- API/dados precisa preceder a UI; parser/codec e migrations podem avançar em paralelo após o contrato. Gates integrados rodam somente depois de API e Web convergirem.
- Rollout mantém `enabled=false` e produção OFF. Rollback desabilita o scheduler, preserva runs/itens/mensagens para auditoria e não reverte migrations destrutivamente.

## Risks / Trade-offs

- [Documentação PJ contraditória] → codec versionado, `evento` explícito, testes de envelope e nenhuma tentativa automática com variante; canário somente com autorização separada.
- [Perda do one-shot após HTTP 200] → artefato privado determinístico antes do parsing, estados remoto/local separados e retry exclusivamente local.
- [Mais de uma mensagem no mesmo dia] → fechamento diário antes de reconciliar a data e reconciliação completa periódica.
- [Atraso de até cerca de 24 horas no econômico] → SLA exibido na UI e atualização manual paga com preview; `DIARIO_COMPLETO` para quem prioriza completude diária.
- [Bilhetagem acidental ou preço impreciso] → budget guard antes de `/Consultar`, flags OFF, fonte `SHADOW/OFFICIAL/UNKNOWN` e sem fallback de custo desconhecido para zero.
- [Vazamento entre offices] → `CurrentOffice`, chaves compostas office/client, revalidação em jobs e testes negativos multi-tenant.
- [Segredos/PII em logs ou API] → vault para artefato bruto, HMAC de NI, sanitização e retenção limitada.
- [Procuração D-1 ausente] → `x` vira bloqueio isolado do cliente, sem abortar o lote e sem LISTAR faturável para ele.
- [Locks ou backlog Horizon atrasarem a rotina] → locks com TTL, due-at persistido, métricas de atraso e retomada idempotente.
- [Serviço MEI aparecer no Compose] → nenhuma alteração de Compose/`services/mei`; gate existente continua obrigatório.

## Migration Plan

1. Aplicar migrations aditivas para settings, estado por cliente, itens one-shot e referências do artefato; manter monitoramento desabilitado.
2. Implantar codec/processor/jobs e executar testes locais com fixtures, sem egress live.
3. Implantar APIs e UI; mostrar preço shadow como estimativa e contratos não reconciliados como bloqueio.
4. Em ambiente autorizado, reconciliar o campo PJ com Trial/canário controlado e registrar a versão de contrato; isso não faz parte do apply automático.
5. Habilitar `ECONOMICO` por escritório, apresentar preview e executar bootstrap confirmado.
6. Observar primeira rotina diária, one-shot, custos e reconciliação; somente então avaliar `DIARIO_COMPLETO`.

Rollback: desabilitar setting/kill switch e cancelar apenas jobs ainda não iniciados; runs, itens, artefatos e mensagens já persistidos permanecem legíveis. Não apagar dados nem tentar “desconsumir” protocolo SERPRO.

## Open Questions

- Confirmar no contrato/Loja SERPRO o preço oficial por LISTAR, página e DETALHE antes de apresentar valores oficiais; até lá a UI usa `SHADOW` ou `UNKNOWN`.
- Confirmar em ensaio autorizado se `SOLICEVENTOSPJ132` aceita `evento`, apesar da tabela documental citar `eventValue`; a implementação não executará fallback automático.
- Definir a permissão exata que pode alterar modo/orçamento; por padrão será reutilizada a permissão administrativa fiscal mais restritiva já existente no office.
