## Context

As nove listas padronizadas de Monitoramento já compartilham `MonitoringModuleTable`, paginação server-side e um `MonitoringFilterValue` backend-facing. A toolbar atual, porém, separa situação rápida e campos avançados em uma faixa recolhível. O backend aceita apenas igualdade sobre campos fixos, e Guias não aplica competência. A implementação precisa continuar SPA estática, local ao Office corrente e fiel à lista `customers.vue` do template fixado.

## Goals / Non-Goals

**Goals:**

- Criar um componente controlado e reutilizável para filtros estruturados de igualdade.
- Separar rascunho visual de estado aplicado, emitindo uma única alteração por confirmação, remoção ou limpeza.
- Preservar busca dedicada, paginação e ordenação server-side, seleção segura e rotas canônicas.
- Declarar em cada lista somente os campos aceitos por sua API e limpar todo estado visual na troca de Office.
- Entregar interação acessível e responsiva com componentes nativos do Nuxt UI 4.9.

**Non-Goals:**

- Alterar Laravel, contratos HTTP, permissões, bulk, dependências ou feature flags.
- Adicionar operadores além de igualdade, intervalos, múltiplos valores, faceting, URL state ou filtros salvos.
- Migrar outras tabelas autenticadas nesta change.

## Decisions

### Modelo discriminado e adaptador do Monitoramento

O núcleo usará `DataTableFilterDefinition` discriminado em `option`, `month` e `client`, e `DataTableFilterModel` com chave, operador `eq`, valor bruto e rótulo visual opcional. `MonitoringFilterValue` continua sendo a estrutura enviada aos loaders; conversores puros traduzem entre os dois contratos e descartam defaults vazios (`all`, `''` e `null`).

Alternativa considerada: tornar o modelo genérico o contrato das páginas. Isso propagaria tipos de UI até as queries e facilitaria o envio acidental de campos não aceitos.

### Estado controlado com rascunho interno

`useDataTableFilters` normaliza, deduplica e ordena o `modelValue` conforme `definitions`, mantém o rascunho e o cache de rótulos, e oferece add/edit/remove/clear sem consultar APIs. O componente só emite `update:modelValue` ao confirmar ou remover e emite `clear` separadamente para permitir que a toolbar limpe busca e chips na mesma transação. Fechar overlay ou editor apenas descarta o rascunho.

Alternativa considerada: `v-model` direto nos editores. Foi rejeitada porque cada tecla poderia provocar consulta e porque fechar sem confirmar deixaria estado parcial.

### Composição responsiva nativa

No desktop, o seletor/editor usa `UPopover` e `UCommandPalette`; no mobile, `UDrawer`. Chips usam `UFieldGroup` e `UButton`, com rótulos acessíveis para editar e remover. Opções e competência têm editores nativos; cliente usa slot customizado preenchido pela toolbar com `FiscalClientPicker`.

Alternativa considerada: copiar o pacote Bazza. O comportamento é referência de interação, mas uma dependência externa aumentaria superfície e desalinharia o tema Nuxt UI existente.

### Uma transação por alteração aplicada

A busca preserva debounce de 320 ms e Enter imediato. Chips confirmados são convertidos para um novo `MonitoringFilterValue` e enviados uma vez por `apply-filters`; remoção e limpeza seguem o mesmo caminho. O composable de paginação reinicia para página 1 e a assinatura de filtros invalida a seleção. KPI cria ou atualiza o modelo `situation` pelo mesmo adaptador.

### Isolamento por Office

O `sessionEpoch` continua identificando troca de Office. Cada lista limpa o estado aplicado antes de carregar o novo contexto; o componente também recebe uma chave de reset para descartar rascunho e cache de rótulos. Nenhum contrato introduz `office_id` em query, body ou URL.

## Risks / Trade-offs

- [Overlay desktop/mobile divergir em comportamento] → compartilhar o mesmo corpo do editor, handlers e testes Nuxt; variar apenas o contêiner.
- [Emissões duplicadas por watchers e eventos] → concentrar a transação no evento confirmado e testar contagem exata de cargas/emissões.
- [Rótulo de cliente ficar associado ao Office anterior] → cache local keyed pelo reset tenant-aware e limpeza antes da nova carga.
- [Página oferecer filtro ignorado pela API] → configuração explícita por lista e testes de superfície; Guias não declara competência.
- [Competência inválida chegar ao backend] → validar `YYYY-MM` e desabilitar confirmação até um mês válido.

## Migration Plan

1. Introduzir tipos, helpers, composable e componente com testes isolados.
2. Evoluir `MonitoringFilterConfig` para `fields` ordenados e adaptar a toolbar.
3. Migrar as nove páginas e adicionar limpeza por Office.
4. Executar gates completos, validar OpenSpec estrito e arquivar com sync das duas capabilities.

Rollback é somente frontend: reverter o commit restaura a toolbar anterior sem migração de dados ou alteração de API.

## Open Questions

Nenhuma. O escopo, os campos por página e o operador único foram confirmados no plano aprovado.
