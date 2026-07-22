## Why

O hub já registra preferências e dispatches de comunicação fiscal, mas ainda opera em modo `TEMPLATE_ONLY`: não há inbox compartilhada, conversa em tempo real nem transporte WhatsApp executável. A mudança cria um módulo de atendimento pertencente ao produto e um gateway Go próprio, mantendo mensagens, documentos, tenancy e automações sob controle do hub.

## What Changes

- Adicionar inboxes WhatsApp por escritório, membros, filas/departamentos, contatos office-scoped, identidades, conversas, mensagens, anexos privados, notas internas, etiquetas, respostas rápidas e busca.
- Entregar uma tela Nuxt nativa de atendimento, alinhada ao arquétipo do dashboard, com estados `OPEN|PENDING|RESOLVED|SNOOZED`, atribuição e atualização em tempo real via Laravel Reverb/Echo.
- Criar `apps/whatsapp-gateway` em Go sobre WhatsMeow, com pareamento, sessões duráveis, ownership por lease, comandos idempotentes, eventos at-least-once, recibos, mídia e reconexão; o gateway será interno e não possuirá o domínio de atendimento.
- Definir contratos internos versionados e autenticados por HMAC entre Laravel e gateway, outbox transacional no Laravel, deduplicação e status monotônico.
- Evoluir preferências/dispatches fiscais para destinatários primário, todos elegíveis ou seleção explícita, inbox geral por office, agendamento separado de consulta e envio e vínculo ao artefato exato da competência.
- Ativar o transporte automático somente para PGDAS, PGMEI e DCTFWeb com documento local canônico; FGTS configurado sem guia local será auditado como `SKIPPED_NO_DOCUMENT`, sem mensagem e sem envio tardio.
- Adicionar exportação/expurgo administrativo auditado, retenção sem expiração automática, kill switches globais/office/inbox e testes API, Web, Go, Compose e OpenSpec.

Non-goals: incorporar Chatwoot, Whaticket ou UAZAPI como runtime; usar processador externo de WhatsApp; grupos, campanhas, newsletters, chatbot ou IA; ligar automaticamente produção; habilitar email; executar chamadas SERPRO live; realizar mutações fiscais; alterar canais SEFAZ; adicionar `mei`/`mei-worker`; restaurar `services/mei`; criar targets ops indisponíveis.

## Capabilities

### New Capabilities

- `communication-inbox`: domínio multi-tenant, APIs, realtime e UX do atendimento compartilhado, incluindo segurança, mídia e ciclo de vida das conversas.
- `whatsapp-native-gateway`: transporte Go/WhatsMeow interno, sessões, sharding por lease, contratos autenticados, idempotência, eventos e operação fail-closed.

### Modified Capabilities

- `monitoring-communication-send-guards`: substituir o gancho imediato `TEMPLATE_ONLY` por dispatch agendado por destinatário e competência, ligado ao documento canônico exato e sem fallback ou envio tardio.

## Impact

- API: novos modelos, migrations, serviços, jobs, eventos broadcast, controllers e rotas `/api/v1/communication`; evolução compatível das preferências e dispatches existentes.
- Gateway/infra: nova aplicação Go em `apps/whatsapp-gateway`, serviços Compose `whatsapp-gateway` e `reverb`, schema/role Postgres dedicado e volumes privados de sessão/spool.
- Web: nova superfície `/communication`, conexão por QR/código, inbox responsiva e administração de membros, filas e automações.
- Dependências: WhatsMeow no gateway; Laravel Reverb no backend; `laravel-echo` e `pusher-js` no frontend.
- Referências: snapshots ignorados em `.local/reference`; nenhum código enterprise/proprietário será incorporado.
- Segurança: nenhum `office_id` confiado do cliente, gateway sem porta pública, mídia sem URL pública, segredos fora do Git e logs sanitizados.

### Dependências entre changes

- Nível: **C0**.
- Bases estáveis: tenancy `Office`/`CurrentOffice`, RBAC canônico, `WorkDepartment`, `SecureObjectStore`, monitoramento fiscal, `ClientCommunicationPreference` e `ClientCommunicationDispatch` existentes.
- Depende de: nenhuma change ativa; capability/contrato: bases estáveis acima; marco: `archive`; relação: `coordenada`.
- Desbloqueia: atendimento WhatsApp nativo e entrega real das comunicações fiscais agendadas.
- Paralelismo: arquivos novos podem avançar em paralelo lógico; patches em `routes/api.php`, `routes/console.php`, `AppServiceProvider`, `FiscalMonitoringScheduler`, navegação e Compose devem preservar as changes ativas presentes no worktree e ser aplicados de forma estritamente aditiva.

Esta change atravessa três capabilities porque o resultado implantável exige, de forma indivisível, o domínio de atendimento, o transporte próprio e a correção do contrato fiscal que hoje cria apenas intents `TEMPLATE_ONLY`; separar o gateway do domínio no apply deixaria uma das partes sem fluxo verificável de ponta a ponta.
