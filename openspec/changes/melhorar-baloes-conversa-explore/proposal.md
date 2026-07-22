## Why

As mensagens da timeline do atendimento já suportam mídia, citação e ações, mas as bolhas atuais diluem a hierarquia entre origem, conteúdo, horário e status e ficam largas demais em telas menores. A referência visual do Explore pede uma leitura mais rápida e compacta sem perder os contratos operacionais já implementados.

## What Changes

- Refinar as bolhas recebidas, enviadas e de nota interna com largura adaptativa, cauda visual discreta, superfície semântica e contraste consistente em light/dark.
- Reorganizar origem, conteúdo, anexos, reações, horário, status e ações em uma hierarquia compacta que continue legível com mensagens curtas ou longas.
- Preservar citação navegável, tipos ricos de WhatsApp, estados de edição/revogação e controles por teclado/foco.
- Cobrir o contrato visual e responsivo dos balões em Vitest e inspecionar desktop/mobile nos dois modos de cor.

## Capabilities

### New Capabilities

- (nenhuma)

### Modified Capabilities

- `communication-workspace-ui`: acrescentar o contrato de bolhas legíveis, compactas, responsivas e semanticamente distintas na timeline do atendimento.

## Impact

- `apps/web/app/components/communication/TimelinePanel.vue` — composição, alinhamento e metadados das bolhas.
- `apps/web/app/components/communication/MessageContent.vue` — apenas ajustes internos indispensáveis para o conteúdo caber na nova superfície, se necessários.
- `apps/web/tests/unit/communication-workspace-ui-gate.test.ts` — cobertura do contrato das bolhas e regressões de responsividade/acessibilidade.
- Sem mudança de API, persistência, gateway, permissões, rotas ou shell do dashboard.

### Non-goals

- Redesenhar lista, composer, contexto ou shell; trocar a timeline por componentes de chat orientados a IA; alterar contratos de mídia ou mensagens.
- SERPRO live, parecer jurídico, mutações fiscais, flags ON, canais SEFAZ, serviços `mei`/`mei-worker` no Compose ou targets ops indisponíveis.

### Dependências entre changes

- Nível: `C4`.
- Bases estáveis: Nuxt Dashboard fixado, Nuxt UI 4 instalado e shell master-detail atual.
- Depende de: `colapsar-contexto-atendimento`, capability `communication-workspace-ui`, marco `apply`, relação `bloqueante`; `evoluir-atendimento-whatsapp-multimidia`, contrato de timeline rica da capability `communication-inbox`, marco `apply`, relação `coordenada`.
- Capability/contrato: `communication-workspace-ui`, bolhas da timeline do atendimento.
- Marco exigido: `apply` das dependências acima.
- Relação: bloqueante para o layout colapsável e coordenada para a timeline rica já materializada.
- Desbloqueia: leitura visual mais rápida das conversas no Explore/Atendimento.
- Paralelismo: pode avançar com áreas fora de `apps/web/app/components/communication/`; não deve ser aplicado em paralelo com mudanças no mesmo `TimelinePanel.vue` ou em seu gate unitário.
