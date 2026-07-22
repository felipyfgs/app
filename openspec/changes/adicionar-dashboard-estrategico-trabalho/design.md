## Context

O módulo Work já possui lista mestre–detalhe, processos, calendário, modelos e o read model tenant-scoped `GET /api/v1/work/kpis`. A rota raiz `/work` ainda monta `WorkQueueWorkspace`, enquanto o bloco de Trabalho na Home apresenta apenas um resumo compacto. A nova superfície precisa adicionar hierarquia estratégica sem redesenhar o shell autenticado nem inventar séries temporais que o backend não fornece.

## Goals / Non-Goals

**Goals:**

- Fazer de `/work` o ponto de entrada estratégico do módulo.
- Expor volume, risco, execução por departamento e exceções acionáveis com dados locais do escritório corrente.
- Preservar a fila completa, filtros, mestre–detalhe e deep links em `/work/tasks` e `/work/tasks/{id}`.
- Oferecer loading, erro com retry, dado anterior marcado como desatualizado e responsividade alinhados ao shell Nuxt UI.

**Non-Goals:**

- Alterar `OperationalKpiQuery`, banco, autorização ou formato da API.
- Exibir tendência histórica, produtividade por período ou SLA sem read model temporal.
- Fazer mutação operacional diretamente nos cards estratégicos.
- Provocar consultas SERPRO/SEFAZ/MEI ao abrir o dashboard.

## Decisions

1. **Separação entre estratégia e execução por rota.** `/work` será a visão geral; `/work/tasks` será a fila sem seleção; `/work/tasks/{id}` continuará sendo o detalhe canônico. Alternativa de empilhar dashboard e fila na mesma página foi rejeitada porque mistura leitura executiva e operação mestre–detalhe, reduzindo a densidade de ambas.
2. **Compatibilidade de URL fail-safe.** A página `/work` detectará somente as chaves reconhecidas da fila (`tab`, `q`, filtros, paginação, `view`, ordenação e `task`) e redirecionará com `replace` para a nova rota. `/work` sem essas chaves permanece no dashboard. `task` válido seleciona `/work/tasks/{id}` e é removido da query.
3. **Read model existente como fonte única.** A UI consumirá `api.work.kpis()` e a lista local de departamentos em paralelo. KPIs, riscos e processos sem responsável vêm do snapshot local autorizado; nenhum zero será inventado antes de uma resposta válida.
4. **Composição visual no shell canônico.** A página usará `ShellPagePanel`, `ShellPageNavbar`, `ShellKpiStrip`, `ShellSectionCard`, `UProgress`, `UBadge` e cores semânticas. O primeiro nível mostra seis KPIs acionáveis; abaixo, execução por departamento recebe maior área e prioridades/processos sem responsável formam a coluna de exceções.
5. **Estado resiliente e sessão.** Loading inicial usa skeleton; falha sem cache exibe alerta com retry; falha após sucesso mantém o último snapshot com aviso de desatualização. Mudança de `sessionEpoch` limpa dados do escritório anterior e recarrega, evitando vazamento visual entre contextos.
6. **Testabilidade por transformações puras.** Mapeamento dos KPIs, progresso geral, nomes/links de departamento e migração de query ficam em utilitário testável; um gate de página confirma a composição, a fonte real e os caminhos canônicos.
7. **Hierarquia executiva inspirada na referência.** O primeiro nível combinará um painel amplo de desempenho geral — avanço e situação das tarefas — com um painel lateral de nível operacional. O segundo nível mostrará desempenho por departamento em tabela compacta no desktop e cards deliberados no mobile, acompanhado de acessos rápidos e resumo operacional. Prioridades e processos sem responsável permanecem como exceções abaixo. A referência visual não autoriza um segundo shell, filtro mensal sem série temporal, métrica por pessoa sem nomes autorizados ou modo escuro forçado.
8. **Nível operacional como leitura derivada, não score histórico.** A faixa textual deriva exclusivamente da porcentagem de conclusão consolidada e dos riscos presentes no snapshot. O componente explicará a unidade, manterá resumo textual e usará cor semântica apenas como reforço.

## Risks / Trade-offs

- [Contagem de concluídas não possui janela temporal] → rotular como estoque consolidado e não apresentar tendência ou velocidade.
- [Referência apresenta período e desempenho individual inexistentes no contrato] → adaptar para a posição atual e desempenho por departamento, deixando explícito que os números representam o snapshot consolidado.
- [URLs antigas de `/work` abrem o dashboard sem query] → comportamento intencional; URLs com qualquer filtro reconhecido migram automaticamente para a fila.
- [Mudança concorrente no cockpit Home ou na fila] → limitar alterações compartilhadas a deep links e path-base, preservando o restante dos diffs existentes e rodando o gate web completo.
- [Vazamento entre offices durante troca de sessão] → invalidar snapshot e departamentos pelo `sessionEpoch`; a API continua resolvida por `CurrentOffice`.
- [Bilhetagem/egress acidental] → usar apenas `/api/v1/work/kpis` e `/api/v1/work/departments`, ambos locais; nenhum provider fiscal é chamado.

## Mapa de dependências

- `adicionar-visao-lista-tarefas` (`apply`) → fornece as duas visões da fila e filtros serializáveis; ownership compartilhado em `useWorkQueueFilters` e `WorkQueueWorkspace` limita-se à troca de `/work` por `/work/tasks`.
- `restaurar-fila-tarefas-mestre-detalhe` (`apply`) → fornece auto-seleção e painel lateral; a change atual preserva esse comportamento na nova rota-base.
- Dashboard, utilitários/testes e ajustes de navegação podem ser implementados no mesmo nível após os artefatos; gates integrados rodam por último.
- Rollout é somente frontend estático. Rollback restaura `/work` como fila, remove `/work/tasks/index.vue` e reverte os deep links; API e dados permanecem compatíveis.

## Open Questions

- Nenhuma; gráficos temporais ficam explicitamente adiados até existir série histórica confiável.
