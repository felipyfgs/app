## Why

O gateway nativo usa somente uma fração da API pública do whatsmeow e hoje não possui um inventário que prove quais recursos de conversas 1:1 estão implementados, excluídos ou deliberadamente encapsulados. Sem essa rastreabilidade, presença, recibos, ações sobre mensagens, descoberta de contatos, privacidade, histórico e novos tipos de evento podem ficar silenciosamente fora do produto ou divergir quando a dependência mudar.

## What Changes

- Catalogar os 135 métodos públicos de `*whatsmeow.Client` e os 74 tipos públicos de evento do commit pinado `8b4a8ba0d318`, com classificação, justificativa e vínculo para implementação/teste.
- Auditar o WuzAPI no commit `70642149a0e8a81d49caa640f557217e03e09729` como referência MIT de handlers sobre whatsmeow, registrando o que será adaptado, rejeitado e revalidado contra a versão mais nova pinada pelo hub.
- Excluir explicitamente grupos, communities, newsletters/canais, status/broadcast e primitivas perigosas, deprecated ou exclusivas de protocolo interno; essas entradas permanecem no catálogo para que nenhuma API desapareça da auditoria.
- Completar o gateway para as operações aplicáveis de conversa 1:1: sessão e pareamento, descoberta/perfil de contato, envio e ações sobre mensagens, mídia, presença, recibos, privacidade, bloqueio, mensagens temporárias, histórico e recuperação.
- Ampliar o contrato interno versionado entre Laravel e gateway com comandos, consultas e eventos tipados, idempotentes, autenticados por HMAC e fail-closed, sem expor protobufs ou JIDs crus à API pública.
- Projetar no domínio de atendimento somente os recursos úteis ao operador, preservando Laravel como fonte de verdade e tratando sinais efêmeros separadamente de eventos duráveis.
- Adicionar testes de catálogo que falhem quando a versão pinada ganhar ou perder métodos/eventos sem uma decisão explícita, além de testes unitários, de contrato e de integração para cada família implementada.

Non-goals: grupos, communities, newsletters/canais, status/broadcast, campanhas, chatbot/IA, chamadas de áudio/vídeo, bots/FB/Armadillo, exposição de `DangerousInternals`, API pública genérica de protobuf, habilitar flags em produção, SERPRO live, parecer jurídico, mutações fiscais, canais SEFAZ, serviços `mei`/`mei-worker`, restaurar `services/mei` ou criar operações de backup/restore indisponíveis.

## Capabilities

### New Capabilities

<!-- Nenhuma capability nova. -->

### Modified Capabilities

- `whatsapp-native-gateway`: ampliar e tornar auditável a cobertura do whatsmeow para conversas 1:1 por comandos, consultas, eventos, políticas de exclusão e testes de deriva da API pinada.
- `communication-inbox`: projetar ações e sinais 1:1 suportados pelo gateway no domínio de atendimento sem transformar o transporte em fonte de verdade.

## Impact

- Gateway: `apps/whatsapp-gateway/internal/{protocol,command,httpapi,domain}` e testes Go, mantendo a dependência whatsmeow no commit já pinado.
- Contrato/backend: `apps/api/resources/contracts/whatsapp-gateway.openapi.yaml`, enums/DTOs/transport/ingestor e testes de Comunicação; migrations somente se a projeção exigir estado durável novo.
- Web: apenas superfícies necessárias para ações do operador que tenham contrato de produto; o shell do dashboard não será redesenhado.
- Segurança/operação: gateway continua privado, HMAC/replay protection obrigatórios, payloads allowlisted, grupos/canais rejeitados, flags OFF e nenhuma credencial/PII em logs.
- Documentação: catálogo versionado de métodos/eventos com fonte, commit, disposição, referência WuzAPI, implementação e evidência de teste.

### Dependências entre changes

- Nível: **C1**.
- Bases estáveis: tenancy `Office`/`CurrentOffice`, RBAC, Postgres/Redis/Horizon e contratos de segurança do monorepo.
- Depende de: `adicionar-comunicacao-whatsapp-nativa`; capabilities/contratos `whatsapp-native-gateway` e `communication-inbox`; marco `apply`; relação `bloqueante`, pois esta change amplia o gateway e o domínio criados naquele baseline.
- Desbloqueia: cobertura funcional completa e auditável do whatsmeow 1:1 e upgrades futuros da dependência com detecção explícita de deriva.
- Paralelismo: catalogação e testes de deriva podem avançar sem alterar runtime; comandos, queries e eventos podem evoluir por famílias depois do contrato comum, mas patches no gateway, OpenAPI e enums compartilhados devem permanecer coordenados com o baseline ainda não arquivado.
