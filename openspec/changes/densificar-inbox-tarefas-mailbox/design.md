## Context

Tarefas (`WorkQueueWorkspace`) e Mailbox (`pages/monitoring/mailbox.vue`) compartilham o arquétipo inbox do dashboard (lista `UDashboardPanel` resizable + detalhe adjacente / slideover mobile). Em desktop, Tarefas auto-seleciona a primeira item e Mailbox abre detalhe via rota — ambos mantêm duas colunas permanentes quando há seleção. O Atendimento acabou de adotar detalhe/contexto sob demanda (`colapsar-contexto-atendimento`); o operador pediu o mesmo ganho de espaço nessas duas superfícies.

Exceção transversal: uma capability cobre Tarefas+Mailbox porque o contrato é o **mesmo padrão de densidade**, não dois produtos distintos. Ownership de arquivos é separado (work vs monitoring) e as tasks espelham isso.

## Goals / Non-Goals

**Goals:**

- Lista prioritária em desktop quando o detalhe está fechado.
- Toggle explícito (ícone) para abrir/fechar detalhe, alinhado ao padrão do Atendimento.
- Tarefas Fila: sem auto-select.
- Mailbox: chrome de monitoramento recolhível (altura).
- Gates de UI atualizados.

**Non-Goals:**

- `view=lista` / `WorkTaskListView` (`adicionar-visao-lista-tarefas`).
- Extrair terceira coluna de “contexto” do corpo do detalhe.
- Mudanças de API, triagem, sync SERPRO/eCAC, flags ON.

## Decisions

1. **Estado `detailOpen` (ou equivalente) por superfície** — espelha `contextOpen` do Atendimento.
   - Tarefas: fechar detalhe = `clearTask()` (volta a `/work`) **ou** manter `selectedTaskId` na URL e só esconder o painel?
   - **Decisão:** fechar esconde o painel mas **mantém** a seleção na URL (`/work/tasks/:id`) quando o usuário só “colapsa”; X / Escape limpa seleção e fecha. Toggle na lista reabre se houver `selectedTaskId`, ou fica disabled sem seleção.
   - Alternativa rejeitada: fechar sempre limpa URL — pior para deep-link e setas ↑↓.

2. **Selecionar item abre detalhe** — click na lista / setas define seleção **e** `detailOpen = true` (como abrir contexto não era automático no Atendimento, mas aqui o detalhe *é* o conteúdo principal da seleção; fechar depois é opcional).

3. **Remover auto-select** em `WorkQueueWorkspace.loadQueue` no ramo desktop `!current && items[0]`.

4. **Mailbox:** `detailOpen` independente; rota `/monitoring/mailbox/:id` continua canônica ao selecionar. Fechar painel pode manter rota ou `replace` para `/monitoring/mailbox` — **Decisão:** fechar (X/toggle) navega para `/monitoring/mailbox` (já existe `closeDetail`); toggle “abrir” sem seleção é no-op. Ao selecionar, abre. Se usuário colapsa sem limpar… com navegação atual close limpa. Para colapsar sem perder deep-link: manter `:id` e `v-if="selectedId && detailOpen"`. Toggle fecha com `detailOpen=false` sem `router.push`; X chama `closeDetail()`.

5. **Chrome mailbox:** `UCollapsible` (ou botão “Configuração / sync”) envolvendo `MonitoringMailboxMonitoringCard` (+ banner se fizer sentido), default **recolhido** após primeiro paint quando há mensagens, ou default expandido só se monitoring OFF/erro — **Decisão simples:** default **recolhido**, header compacto “Monitoramento e sync” com chevron; expandir sob demanda. Não remover o card.

6. **Ícones:** Tarefas `i-lucide-panel-right` / `panel-right-open`; Mailbox `i-lucide-mail` ou `panel-right` — consistência com Atendimento (`i-lucide-user` era domínio contato). Preferir `i-lucide-panel-right` em ambas para padrão shell.

7. **Não compartilhar componente** entre work e mailbox nesta change — só o contrato mental; evita acoplamento e conflito com `adicionar-visao-lista-tarefas`.

## Mapa de dependências

```
colapsar-contexto-atendimento (completa, referência) ──► receita UI
adicionar-visao-lista-tarefas (ativa) ←── coordenada: mesmo WorkQueueWorkspace
operacionalizar-caixa-postal-… (ativa) ←── coordenada: mailbox.vue / card
densificar-inbox-tarefas-mailbox (esta) C0
```

- Arquivos compartilhados de risco: `WorkQueueWorkspace.vue`, `mailbox.vue`.
- Não editar artefatos OpenSpec das outras changes.
- Gates: atualizar só asserts desta change; se Lista pousar depois, ela deve preservar `detailOpen`/sem auto-select da Fila.

## Risks / Trade-offs

- [Merge conflict com Lista] → Mitigação: tocar só ramos Fila / auto-select / toggle; deixar slots para toggle Fila|Lista intactos quando existirem.
- [Operador espera detalhe sempre aberto] → Mitigação: seleção ainda abre; só o default inicial e o fechar mudam.
- [Deep-link `/work/tasks/5` sem painel] → Mitigação: se path tem id, `detailOpen` inicia `true` no mount.
- [Mailbox chrome esconde sync crítico] → Mitigação: chip/badge de erro no header colapsado quando `monitoringError` ou sync falho.

## Migration Plan

- Deploy só frontend.
- Rollback: reverter Vue + gates.

## Open Questions

- Nenhuma bloqueante; persistência de preferência (cookie) fica fora.
