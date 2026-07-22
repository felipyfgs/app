## Context

A timeline de `/communication` já vive no arquétipo mestre–detalhe, usa `UDashboardPanel`, preserva o detalhe em `USlideover` abaixo de `lg` e renderiza conteúdo rico por `CommunicationMessageContent`. As bolhas são construídas diretamente em `TimelinePanel.vue`, o que permite representar notas, receipts, ações e mídia do WhatsApp que não cabem no contrato de chat orientado a IA do `UChatMessage`.

A referência visual do Explore reforça três atributos úteis para o produto: alinhamento inequívoco por direção, largura ditada pelo conteúdo e metadados discretos dentro da própria bolha. O produto, porém, precisa manter contraste acessível, tema green/zinc, light/dark, ações por teclado e todos os estados ricos já existentes.

## Goals / Non-Goals

**Goals:**

- Tornar a leitura da timeline mais rápida com origem, corpo, anexos, reações, horário e receipt em ordem visual previsível.
- Fazer mensagens curtas ocuparem apenas a largura necessária e mensagens longas respeitarem limites responsivos sem overflow.
- Distinguir inbound, outbound e nota interna por alinhamento, texto/ícone e superfície semântica, sem depender só da cor.
- Preservar todas as ações e estados atuais, com controles sempre alcançáveis em touch e por foco de teclado.

**Non-Goals:**

- Trocar o shell, a lista, o composer, o painel de contexto ou o contrato de dados.
- Adotar `UChatMessages`/`UChatMessage`, pois esses componentes priorizam mensagens de IA e não representam diretamente notas internas, receipts WhatsApp, votação e recuperação de mídia.
- Alterar API, tenancy, autorização, gateway, armazenamento ou flags.

## Decisions

### 1. Manter a composição semântica local no `TimelinePanel`

Cada mensagem continuará como `<article>` com `data-message-id`, preservando scroll de citação, destaque e ações. A bolha será um contêiner `relative inline-block w-fit` limitado por largura responsiva; nenhuma nova casca transversal será criada para um único consumidor.

Alternativa rejeitada: migrar para `UChatMessage`. Apesar de fornecer side/variant, a abstração instalada é voltada ao fluxo AI e exigiria overrides extensos ou perda dos contratos específicos de atendimento.

### 2. Usar tokens semânticos e uma cauda discreta

Outbound usará `bg-primary text-inverted`; inbound usará `bg-default`, `border-default` e texto padrão; nota interna usará `warning` com rótulo e ícone. A cauda será puramente decorativa, herdará a cor semântica da superfície e terá `aria-hidden`, sem servir como único indicador de direção.

Alternativa rejeitada: copiar cores absolutas do screenshot. Isso quebraria o tema green/zinc e o contraste entre light/dark.

### 3. Separar cabeçalho, conteúdo e rodapé sem aumentar a bolha

O rótulo de origem ficará em uma linha compacta acima do conteúdo, com ícone quando automação ou nota. Horário, estado editado e receipt formarão um rodapé único; ações ficarão no mesmo agrupamento, mas visíveis em touch e ao foco e progressivas no desktop com hover. Quotes e conteúdo rico permanecem no corpo.

Alternativa rejeitada: posicionar horário fora da bolha, pois mensagens curtas e anexos passariam a desalinhá-lo e o alvo de leitura ficaria fragmentado.

### 4. Validar por contrato de fonte e inspeção real

O gate unitário do workspace verificará largura adaptativa, variantes semânticas, rótulos e visibilidade de ações por foco. Depois serão executados todos os gates Web e uma inspeção real em desktop/mobile e light/dark.

## Mapa de dependências

```text
colapsar-contexto-atendimento (C0/apply) ─┐
                                         ├─ melhorar-baloes-conversa-explore (C4)
evoluir-atendimento-whatsapp-multimidia (C3/apply) ─┘
```

- Ownership desta change: `TimelinePanel.vue` e o gate unitário correspondente; `MessageContent.vue` somente se uma incompatibilidade interna for comprovada.
- A change não edita artefatos das upstreams. Ela consome o painel colapsável já aplicado e a timeline rica já materializada.
- O marco de `colapsar-contexto-atendimento` é bloqueante porque define a largura disponível; `evoluir-atendimento-whatsapp-multimidia` é coordenada porque define o conteúdo que precisa caber na bolha.
- Trabalho fora dos componentes de Comunicação pode ocorrer em paralelo. Mudanças concorrentes no `TimelinePanel.vue` devem ser serializadas.
- Rollout é somente frontend e aditivo; rollback restaura o markup/classes anteriores sem migração de dados.

## Risks / Trade-offs

- [Cauda ou sombra criar artefato em dark mode] → usar tokens semânticos, manter borda discreta e inspecionar ambos os modos.
- [Controles ocultos ficarem inacessíveis em dispositivos touch] → manter visibilidade padrão e ocultar progressivamente apenas sob media query de hover, restaurando em `group-focus-within`.
- [Mídia larga estourar a viewport] → preservar `min-w-0`, limites percentuais mobile/desktop e conteúdo com quebra de palavra, sem `min-width` artificial.
- [Mudança concorrente da timeline rica] → depender do marco `apply`, limitar ownership e executar gate coordenado de Comunicação.
- [Regressão de dados, Office ou segredos] → não alterar data flow, payloads, APIs nem logs; a mudança opera somente sobre a projeção já autorizada.

## Migration Plan

1. Atualizar a composição das bolhas e o teste de contrato no mesmo nível de execução.
2. Rodar gates Web completos e validação OpenSpec estrita.
3. Inspecionar desktop/mobile em light/dark com mensagens curtas, longas, internas e com anexo.
4. Em caso de regressão visual, reverter apenas classes/markup da bolha; nenhuma migração ou limpeza de dados é necessária.

## Open Questions

- Nenhuma decisão bloqueante. Agrupamento temporal de mensagens consecutivas fica fora desta change para não introduzir regra de domínio visual sem necessidade.
