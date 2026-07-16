# Templates de aceite (task-loop)

Use `--template <nome>` ou escolha por heurística. Cada template só **pré-preenche** seções de `acceptance.md`; o planner adapta ao pedido.

## openspec-task

**Quando:** mode `--openspec` ou menção a change/task.

Checklist típico:

- [ ] Item de `tasks.md` implementado conforme design/specs da change
- [ ] Comportamento coberto por teste ou evidência executável (quando aplicável)
- [ ] Tenancy / papéis respeitados se houver API ou job
- [ ] Sem vazamento de segredos em logs/respostas
- [ ] Diff limitado ao escopo da task

## bugfix

**Quando:** bug, regressão, erro, “quebra”, falha de teste.

Checklist típico:

- [ ] Repro documentado (passos ou teste que falhava)
- [ ] Causa corrigida na camada correta
- [ ] Teste (ou check) impede regressão
- [ ] Sem refactor colateral fora do bug

## api-endpoint

**Quando:** rota API, controller, resource, policy.

Checklist típico:

- [ ] Rota Sanctum autenticada no grupo correto
- [ ] Isolamento por `office_id` / membership ativa (sem confiar no client)
- [ ] Papéis/autorização adequados
- [ ] Resposta sem segredos / PFX / tokens
- [ ] Feature test cobrindo happy path + negação cross-tenant se aplicável

## nuxt-page

**Quando:** página/painel Vue em `frontend/`.

Checklist típico:

- [ ] Página sob arquétipo do ui-archetype
- [ ] Componentes Nuxt UI (`U*`) — sem reinventar shell
- [ ] Escopo tenant-aware se dados de escritório
- [ ] Sem dados de contrato SERPRO global expostos ao tenant
- [ ] Build/typecheck do frontend ok se o ambiente permitir

## job-horizon

**Quando:** job, queue, scheduler, sync, worker.

Checklist típico:

- [ ] Idempotência / locks adequados (estabelecimento ou office)
- [ ] Nunca mistura tenants
- [ ] Requeue/limites alinhados a AGENTS.md se for sync fiscal
- [ ] Logs sanitizados (sem secrets/XML sensível)
- [ ] Teste unit/feature do caminho crítico se existir harness

## spec-only

**Quando:** só proposal/design/specs/tasks OpenSpec, sem código app.

Checklist típico:

- [ ] Artefatos em pt-BR
- [ ] Alinhados a `openspec/config.yaml` e decisions do design ativo
- [ ] Non-goals e tenancy mencionados onde relevante
- [ ] Sem scaffold de app code ad hoc
- [ ] `openspec validate` / status coerente se CLI disponível

## Heurística rápida

| Pedido contém… | Template |
|-----------------|----------|
| openspec, change, tasks.md | openspec-task |
| bug, erro, falha, regressão | bugfix |
| route, endpoint, API, controller | api-endpoint |
| página, painel, vue, nuxt, UI | nuxt-page |
| job, horizon, queue, scheduler | job-horizon |
| proposal, design, spec only | spec-only |
