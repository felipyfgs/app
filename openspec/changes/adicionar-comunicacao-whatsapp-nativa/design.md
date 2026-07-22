## Context

O backend já possui `ClientCommunicationPreference`, `ClientCommunicationDispatch` e `ClientCommunicationEvent`, mas eles registram apenas intents `TEMPLATE_ONLY`. O frontend exibe preferências e histórico local por módulo, sem caixa de atendimento. O monorepo usa Laravel/Horizon para regras de negócio e jobs, Nuxt para o painel e Docker Compose para todos os processos. Conexões WhatsApp Web são long-lived, stateful e numerosas; colocá-las em workers PHP misturaria sessão de protocolo com tenancy, documentos fiscais e domínio de atendimento.

O resultado cruza API, Web, um novo binário Go, Postgres, Redis/Reverb e Compose. A mudança mantém `Office` como tenant, defaults OFF, anexos privados e nenhuma dependência de UAZAPI, Chatwoot ou Whaticket em produção. O risco de protocolo WhatsApp não oficial foi aceito, mas não autoriza campanhas, spam, processador externo ou exposição pública do gateway.

## Goals / Non-Goals

**Goals:**

- Entregar atendimento WhatsApp 1:1 nativo, multi-inbox e multiusuário, com filas, atribuição, notas, etiquetas, busca e mídia.
- Manter Laravel como fonte de verdade e Go/WhatsMeow como transporte substituível.
- Garantir isolamento por Office, idempotência ponta a ponta, eventos at-least-once, status monotônico e recuperação após restart.
- Usar o mesmo histórico para mensagens humanas e automações fiscais.
- Enviar somente o documento canônico da competência agendada para contatos explicitamente elegíveis.
- Preparar milhares de sessões lógicas por leases e capacidade configurável, sem exigir outro orquestrador além do Compose atual.

**Non-Goals:**

- Incorporar runtime/UI de Chatwoot ou Whaticket; consumir UAZAPI; oferecer Meta Cloud API nesta entrega.
- Grupos, campanhas, broadcast, newsletter, chatbot, IA ou email.
- Guardar histórico de negócio no gateway ou expor sua porta ao usuário final.
- Habilitar produção, SERPRO live, mutações fiscais, canais SEFAZ ou qualquer flag sensível por default.
- Adicionar `mei`/`mei-worker`, restaurar `services/mei` ou criar operações de backup/restore indisponíveis.

## Decisions

### 1. Separar domínio Laravel e transporte Go

`apps/api` será dono de inboxes, contatos, conversas, mensagens, anexos, permissões, outbox, automações e auditoria. `apps/whatsapp-gateway` será dono apenas de device store WhatsMeow, conexões, pairing, comandos, leases, recibos e entrega de eventos. O gateway implementará um contrato interno versionado; Laravel dependerá de `CommunicationTransport`, não de tipos WhatsMeow.

Alternativas rejeitadas: manter sessões em Laravel/Horizon, por inadequação a WebSockets permanentes e milhares de goroutines equivalentes; incorporar Chatwoot, por duplicar tenancy/domínio/UI; usar UAZAPI, por introduzir processador externo, lock-in e retenção fora do hub.

### 2. Modelo office-scoped com identidade separada de cliente

Criar inboxes e membros por Office; contatos de comunicação e identidades normalizadas por canal; vínculos muitos-para-muitos com `Client` e `ClientContact`; conversas, contexto de clientes, mensagens, anexos, labels, canned responses e eventos. Telefone desconhecido cria contato provisório dentro do Office. Uma constraint parcial permite no máximo uma conversa não resolvida por inbox+identidade; nova mensagem após resolução cria outra conversa.

`WorkDepartment` será a fila. `OfficeMembership` será a unidade de acesso/atribuição. Administradores do Office enxergam todas as inboxes; demais usuários somente inboxes das quais são membros. Nenhuma rota aceita `office_id` como autoridade.

Alternativa rejeitada: usar `ClientContact` como identidade global. O mesmo telefone pode representar uma pessoa ligada a vários clientes e mensagens podem chegar antes de qualquer vínculo cadastral.

### 3. Outbox Laravel e comandos idempotentes no gateway

Uma transação Laravel cria/atualiza conversa e mensagem, grava um evento de domínio e insere `communication_outbox_entries`. Um job Horizon envia o comando ao gateway e só marca aceito após HTTP 202. `command_id` e `provider_message_id` são definidos pelo Laravel e únicos; o adapter WhatsMeow usa o mesmo ID em qualquer retry. Resultado ambíguo permanece `UNKNOWN` até receipt/reconciliação, sem regressão de status.

O gateway persiste o comando antes do 202. Eventos ficam em outbox própria e são reenviados até 2xx. O Laravel deduplica por `gateway_event_id`; mensagens também por inbox+provider ID. A ordem de status é monotônica (`QUEUED < ACCEPTED < SENT < DELIVERED < READ`) e `FAILED|UNKNOWN` não apagam evidência posterior válida.

Alternativa rejeitada: HTTP síncrono direto do controller para WhatsApp. Falhas entre envio remoto e commit local produziriam duplicação ou perda não reconciliável.

### 4. Contrato interno HMAC e rede privada

O gateway expõe `POST /internal/v1/commands`, `GET /internal/v1/sessions/{id}`, health e metrics apenas na rede Compose. O Laravel recebe `POST /api/internal/v1/whatsapp/events` fora do middleware de Office, mas resolve o tenant exclusivamente pelo `session_id` persistido.

Cada requisição leva key ID, timestamp, nonce e assinatura HMAC-SHA256 sobre método, path, timestamp, nonce e SHA-256 do corpo. A janela aceita cinco minutos, nonces são únicos por dez minutos em Redis/DB e duas chaves podem coexistir durante rotação. Corpo, QR, telefone e segredos nunca entram em logs.

Alternativa rejeitada: confiar apenas na rede Docker ou usar o token da inbox no payload. Uma SSRF ou exposição acidental da rede não pode virar autenticação.

### 5. Ownership de sessão por lease

O schema dedicado do gateway armazena sessions, leases, commands e event outbox. Cada réplica possui `replica_id`, anuncia heartbeat e reivindica sessões com lock/lease até sua capacidade. Apenas o lease válido inicia o client WhatsMeow e consome comandos daquela sessão. Expiração libera a sessão para outra réplica; `LISTEN/NOTIFY` reduz polling, com polling periódico como fallback.

O primeiro Compose inicia uma réplica, mas o algoritmo e testes simulam múltiplas réplicas e 5.000 sessões lógicas. Credenciais WhatsMeow ficam restritas ao role do gateway e ao storage cifrado do ambiente, nunca no Laravel ou na API pública.

Alternativa rejeitada: shard fixo por índice de container. Escala ou restart mudaria índices e poderia conectar a mesma sessão duas vezes.

### 6. Mídia privada com streaming e spool recuperável

Anexos permanentes entram por um novo `CommunicationMediaStore` com envelope cifrado e API de stream, preservando a semântica do vault sem carregar vídeo/documento inteiro em memória. Download é same-origin, autenticado, tenant-scoped, auditado e com `Content-Disposition`; metadados incluem MIME detectado, tamanho e SHA-256.

No inbound, o gateway mantém arquivo cifrado em volume persistente até o Laravel confirmar o multipart. No outbound, o gateway busca o anexo por endpoint interno associado ao `command_id`; não recebe URL pública nem acesso geral ao vault. Spool é apagado somente após ACK ou expurgo terminal auditado.

Alternativas rejeitadas: base64 em JSON, URLs públicas e montar o vault fiscal inteiro no container Go.

### 7. Realtime como projeção, não fonte de verdade

Laravel Reverb transmite eventos em canais privados por Office/inbox; policies repetem as regras de membership. Toda resposta REST inclui cursor do último evento. O Nuxt aplica deltas durante a conexão e chama `/api/v1/communication/sync?after=` após reconnect; REST/Postgres continua sendo a fonte de verdade.

A tela usa o padrão de painéis redimensionáveis do arquétipo: filtros/lista, timeline/composer e contexto; mobile usa `USlideover`. Busca cobre contato, cliente, telefone exato e texto, sempre dentro do Office.

Alternativa rejeitada: usar WebSocket como armazenamento ou confiar que não haverá eventos perdidos durante reconexão.

### 8. Ciclo de conversa e roteamento

Mensagens automáticas criam ou reutilizam a conversa ativa e a deixam `PENDING`. Inbound coloca a conversa em `OPEN`, sem assignee quando ainda não houver responsável, e a encaminha ao `WorkDepartment` da inbox. Notas internas não saem do Laravel. `SNOOZED` volta a `OPEN` no inbound e volta a `PENDING` ao vencer sem inbound. Mudanças concorrentes usam `lock_version`.

As permissões serão `communication.view`, `communication.reply`, `communication.assign`, `communication.manage_inboxes`, `communication.manage_automations` e `communication.purge`. Canned responses e labels pertencem ao Office.

### 9. Automação fiscal por cutoff e artefato exato

`OfficeMonitorSchedulePolicy` continua determinando a consulta. Uma `CommunicationAutomationPolicy` separada define módulo/submódulo, dia/hora de envio, inbox geral, template e flag OFF. O scheduler materializa um dispatch por destinatário e competência. Elegibilidade exige `automatic_requested`, `whatsapp_enabled`, política ativa, inbox conectável e contato ativo/WhatsApp/recebedor.

Modos de destinatário: `PRIMARY`, `ALL_ELIGIBLE` e `SELECTED`. Cada telefone produz dispatch/mensagem/idempotency próprios. A chave automática é estável por office, cliente, módulo, competência, inbox, identidade e versão de template; manual continua reenviável.

Resolvers canônicos:

- PGDAS: `PgdasdArtifact` tipo DAS da competência exata.
- PGMEI: `TaxGuideVersion` atual, confirmada, com bytes, para guia PGMEI da competência.
- DCTFWeb: DARF/evidência canônica com bytes da competência.
- FGTS: o modelo atual marca guia como `UNSUPPORTED`; o dispatch fica `SKIPPED_NO_DOCUMENT` e nada é enviado.

No cutoff, ausência do artefato exato gera `SKIPPED_NO_DOCUMENT`. Não se usa período anterior, não se cria tarefa e um artefato tardio não reativa o envio. Operador pode enviar manualmente depois.

Alternativa rejeitada: o hook atual pós-consulta, que testa qualquer documento histórico e escolhe somente o primeiro contato.

### 10. Retenção, expurgo e rollout fail-closed

Não haverá expiração automática. Exportação administrativa gera arquivo auditado; expurgo elimina corpos, endereços reversíveis e blobs, conservando tombstone com IDs, hashes, estados e timestamps. Offboarding de inbox faz logout WhatsMeow e elimina device store depois de confirmação explícita.

Flags global, Office e inbox iniciam OFF. Deploy cria schema/tabelas e processos sem conectar número nem enviar mensagem. O rollout usa número controlado, depois habilitação individual; rollback desliga flags e processos, preservando ledger para diagnóstico.

## Mapa de dependências

```text
N0 contratos/modelos/segurança
 ├── N1 gateway Go + infra
 └── N1 domínio Laravel + outbox
          ├── N2 APIs/realtime
          └── N2 automações/artefatos
                    └── N3 UI Nuxt
                              └── N4 gates integrados
```

- Bases estáveis: `CurrentOffice`, RBAC, `WorkDepartment`, vault, Horizon, scheduler e modelos fiscais existentes.
- Não há upstream lógico bloqueante. O worktree contém changes ativas que compartilham `routes/api.php`, `routes/console.php`, `AppServiceProvider`, `FiscalMonitoringScheduler`, navegação e Compose; nesses arquivos a implementação será aditiva e validará o conteúdo já presente antes de cada patch.
- Ownership desta change: novo namespace `Communication`, `apps/whatsapp-gateway`, rotas `/communication`, serviço Reverb e deltas da capability de guards.
- Código novo e testes unitários podem avançar independentemente; migrations/contratos precedem API, gateway e automações; gates integrados são o último nível.
- A exceção de três capabilities é necessária porque nenhum dos três contratos isolado produz fluxo implantável: a inbox precisa do gateway, e a entrega fiscal precisa da inbox/outbox.

## Risks / Trade-offs

- [Banimento ou quebra do protocolo não oficial] → provider boundary, kill switches, reconexão observável, rate limiting e possibilidade futura de Meta Cloud API.
- [Vazamento entre Offices] → `CurrentOffice`, scopes, lookup de sessão server-side, composite uniques e testes negativos em toda rota/evento.
- [Mensagem duplicada em timeout] → command/provider IDs estáveis, unique constraints, outboxes e status `UNKNOWN` reconciliável.
- [Perda de evento ou mídia] → persistir antes do ACK, retry at-least-once, spool cifrado e sync por cursor.
- [Duas réplicas conectarem a mesma sessão] → lease transacional, heartbeat, fencing token e teste de takeover.
- [Segredos/PII em logs/API] → HMAC, roles separados, redaction, QR efêmero no Redis e nenhum payload bruto em eventos públicos.
- [Documento fiscal incorreto] → resolver por competência/tipo/run, congelar artifact ID/digest e falhar `SKIPPED_NO_DOCUMENT` sem fallback.
- [Backlog Horizon/Reverb] → métricas de idade da fila, retry limitado, DLQ auditável e REST sync como recuperação.
- [Worktree concorrente] → não restaurar arquivos, patches mínimos e gates de regressão das changes já presentes.
- [Serviço proibido no Compose] → adicionar somente `whatsapp-gateway` e `reverb`; gate confirma ausência de `mei`/`mei-worker`.

## Migration Plan

1. Criar OpenSpec, glossário/ADR, migrations aditivas, contratos e flags OFF.
2. Implantar gateway, schema dedicado, Reverb e healthchecks sem sessões conectadas.
3. Implantar domínio, outbox, APIs e UI; validar com gateway fake e número controlado.
4. Ligar uma inbox por Office de validação, testar inbound/outbound, recibos, restart e mídia.
5. Configurar políticas/recipients e validar PGDAS, PGMEI e DCTFWeb; confirmar skip FGTS e documento ausente.
6. Disponibilizar criação de inboxes aos demais Offices mantendo flags individuais.

Rollback: desligar flags, parar `whatsapp-gateway`/`reverb` e manter tabelas/eventos. Comandos ainda não aceitos ficam cancelados; mensagens com resultado ambíguo permanecem `UNKNOWN`. Não apagar dados nem desfazer migrations destrutivamente.

## Open Questions

- Nenhuma decisão de produto pendente para o apply. Capacidade por réplica, limites de mídia e tempos de retry serão configurações com defaults conservadores e testes, sem habilitar produção automaticamente.
