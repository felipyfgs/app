## 1. N0 — Vocabulário, contratos e dados

- [x] 1.1 Criar `CONTEXT.md`, ADR da separação Laravel↔Go e referências pinadas em `.local/reference`, registrando origem/licença sem incorporar runtime de terceiros; validar o ADR e os commits/snapshot documentados
- [x] 1.2 Adicionar enums, contratos DTO/OpenAPI e configuração fail-closed de Comunicação/HMAC, incluindo testes unitários de normalização, assinatura, janela temporal, nonce e transições monotônicas
- [x] 1.3 Criar migrations/models/relations para inboxes, memberships, contatos/identidades/vínculos, conversas/contextos, mensagens/anexos, labels, canned responses, eventos, outbox e políticas; evoluir preferences/dispatches de forma aditiva e testar constraints/casts/tenant scopes

## 2. N1 — Transporte e núcleo Laravel

- [x] 2.1 Criar `apps/whatsapp-gateway` em Go com config, servidor interno, autenticação HMAC, persistência de commands/events e adapter WhatsMeow testável; cobrir idempotência, conflito de digest, replay e health sem PII
  - Depende de: 1.2
- [x] 2.2 Implementar sessões, pairing, leases/fencing, capacidade, reconexão, event dispatcher e spool cifrado no gateway; testar disputa/takeover, restart, retry at-least-once, mídia e 5.000 sessões lógicas
  - Depende de: 1.2, 1.3
- [x] 2.3 Integrar Dockerfile, Compose dev/prod, schema/role Postgres, volumes, healthchecks, Makefile e Laravel Reverb/Echo com defaults OFF; validar configs Compose e ausência de `mei`/`mei-worker`
  - Depende de: 1.2
- [x] 2.4 Implementar no Laravel `CommunicationTransport`, cliente HMAC, outbox/jobs, ingestão idempotente de eventos/receipts e `CommunicationMediaStore` com streaming cifrado; testar falhas antes/depois do ACK, retry, status fora de ordem e download privado
  - Depende de: 1.2, 1.3

## 3. N2 — APIs, realtime e automações

- [x] 3.1 Implementar serviços/controllers/requests/resources e rotas office-scoped para inboxes, pairing, membros, contatos, identidades e vínculos com clientes; adicionar Feature tests de RBAC e isolamento entre Offices
  - Depende de: 2.3, 2.4
- [x] 3.2 Implementar conversas, atribuição/fila/snooze, mensagens/notas/mídia, labels, canned responses e busca; testar conversa ativa única, provisional contact, conflito de versão, composer e dedupe inbound/outbound
  - Depende de: 2.4
- [x] 3.3 Implementar eventos append-only, canais privados Reverb, sync por cursor, exportação e expurgo/tombstone auditados; testar autorização de broadcast, recuperação de lacuna e exclusão real de blobs
  - Depende de: 2.3, 2.4
- [x] 3.4 Implementar política de envio por Office+módulo, modos `PRIMARY|ALL_ELIGIBLE|SELECTED`, scheduler/cutoff e resolvers exatos para PGDAS, PGMEI e DCTFWeb, com FGTS `SKIPPED_NO_DOCUMENT`; testar fanout, idempotência, período errado, ausência e documento tardio
  - Depende de: 2.4

## 4. N3 — Superfície Nuxt

- [x] 4.1 Adicionar tipos, cliente REST, stores/composables e plugin Echo com sync por cursor; testar autorização, reconexão, merge idempotente e estados de carregamento/erro
  - Depende de: 3.1, 3.2, 3.3
- [x] 4.2 Criar `/communication` e componentes de lista, timeline, contexto e composer responsivos no padrão do arquétipo; cobrir status, fila, assignee, snooze, notas, labels, canned responses, citações e mídia em Vitest/fidelity
  - Depende de: 3.1, 3.2, 3.3
- [x] 4.3 Criar administração de inbox/pairing/membros e políticas/destinatários, integrar navegação e atualizar superfícies fiscais PGDAS/PGMEI/DCTFWeb/FGTS sem redesenhar o shell; testar switches fail-closed e mensagens `SKIPPED_NO_DOCUMENT`
  - Depende de: 3.1, 3.4

## 5. N4 — Gates integrados e prontidão

- [x] 5.1 Rodar testes Go e gates API (`composer validate`, Pint e PHPUnit), corrigindo todas as falhas introduzidas e registrando cobertura dos contratos de comunicação/gateway
  - Depende de: 2.1, 2.2, 3.1, 3.2, 3.3, 3.4
- [x] 5.2 Rodar gates Web (`lint`, `typecheck`, `generate`, Vitest, fidelity e artifacts), corrigindo todas as falhas introduzidas
  - Depende de: 4.1, 4.2, 4.3
- [x] 5.3 Validar Compose dev/prod, OpenSpec strict, inventário de rotas/artefatos, secrets/redaction, kill switches OFF e fluxo fake end-to-end inbound→reply→receipt→automação sem egress live
  - Depende de: 5.1, 5.2
