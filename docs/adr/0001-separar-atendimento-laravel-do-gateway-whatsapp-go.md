# ADR 0001: Separar o domínio de atendimento do gateway WhatsApp

- Status: Aceita
- Data: 2026-07-22

## Contexto

O hub precisa oferecer atendimento WhatsApp multiusuário e enviar documentos fiscais com isolamento por escritório, auditoria e idempotência. O protocolo WhatsApp Web mantém conexões long-lived e estado de dispositivo, enquanto o Laravel já concentra tenancy, RBAC, clientes, documentos fiscais, scheduler e histórico de comunicação. Chatwoot e Whaticket trazem domínios e interfaces paralelos; UAZAPI transfere a sessão e parte do processamento a um terceiro; executar as conexões em workers PHP mistura dois ciclos de vida distintos.

## Decisão

O Laravel será a fonte de verdade para inboxes, contatos, conversas, mensagens, anexos, permissões, outbox e automações fiscais. Um gateway interno em Go, baseado em WhatsMeow, cuidará somente de pairing, device store, conexões, envio, receipts, mídia temporária, leases e entrega durável de eventos. A integração usará contrato HTTP interno versionado, HMAC com proteção contra replay, IDs idempotentes definidos pelo Laravel e eventos entregues pelo menos uma vez. O gateway não será exposto pelo proxy público nem armazenará clientes, templates, conversas ou regras fiscais.

## Alternativas consideradas

- Incorporar Chatwoot ou Whaticket: rejeitada porque duplicaria tenancy, autorização, timeline e UI já pertencentes ao hub.
- Consumir UAZAPI: rejeitada como base do produto por lock-in, processamento externo e menor controle de sessão e retenção; sua documentação permanece apenas como referência comparativa.
- Implementar a conexão no Laravel/Horizon: rejeitada porque conexões stateful e numerosas têm ciclo operacional mais adequado a goroutines e a um processo dedicado.
- Usar somente a API oficial da Meta: permanece uma alternativa futura de transporte; não substitui o domínio de atendimento e não faz parte desta entrega.

## Consequências

O produto mantém uma única fonte de verdade e pode trocar o transporte sem migrar o histórico. Em contrapartida, passa a operar um serviço Go stateful e assume o risco de compatibilidade e bloqueio inerente ao protocolo não oficial. Kill switches começam desligados, campanhas e grupos ficam fora do escopo e leases, observabilidade, testes de idempotência e spool durável tornam-se requisitos operacionais.
