## Why

No desktop, o painel **Contexto** do atendimento fica sempre aberto ao lado da timeline, deixando a conversa espremida em viewports comuns. O operador precisa poder fechar e reabrir esse painel sob demanda para ganhar espaço horizontal na mensagem.

## What Changes

- Botão com ícone de usuário na navbar da timeline para abrir/fechar o painel de contexto do contato em qualquer breakpoint.
- No desktop (`lg+`), o painel Contexto deixa de ser coluna permanente: só ocupa espaço quando aberto; a timeline expande ao fechar.
- No mobile/`<lg`, o slideover de contexto continua; o mesmo estado controla abertura/fechamento.
- Botão de fechar também no header do painel Contexto (desktop e mobile).
- Default do painel: fechado ao entrar na conversa (prioriza espaço da timeline).
- Atualizar gate de UI/fidelity do workspace de comunicação para o comportamento colapsável.

## Capabilities

### New Capabilities

- `communication-workspace-ui`: layout master-detail do atendimento (lista, timeline e painel de contexto colapsável) com superfícies Nuxt UI.

### Modified Capabilities

- (nenhuma — `communication-inbox` ainda não está nas main specs; o contrato de layout fica na capability nova)

## Impact

- `apps/web/app/pages/communication.vue` — estado unificado `contextOpen` para coluna desktop e slideover.
- `apps/web/app/components/communication/TimelinePanel.vue` — toggle permanente com ícone de usuário.
- `apps/web/app/components/communication/ContextPanel.vue` — fechar no desktop; sizing resizable opcional.
- `apps/web/tests/unit/communication-workspace-ui-gate.test.ts` — expectativas do painel colapsável.
- Sem mudanças de API, gateway ou permissões.

### Non-goals

- SERPRO live, mutações fiscais, flags ON, canais SEFAZ, serviços `mei`/`mei-worker` no Compose, ops backup/restore.
- Redesign do shell do dashboard ou da lista de conversas.
- Persistência de preferência em cookie/localStorage (pode vir depois).

### Dependências entre changes

- Nível: `C0`
- Bases estáveis: main specs atuais; change arquivável `adicionar-comunicacao-whatsapp-nativa` (superfície já no tree).
- Depende de: nenhuma
- Capability/contrato: `communication-workspace-ui` (nova)
- Marco exigido: n/a
- Relação: n/a
- Desbloqueia: nenhuma change ativa
- Paralelismo: pode rodar em paralelo com `cobrir-whatsmeow-conversas-1x1` (ownership distinto: UI layout vs gateway 1x1).
