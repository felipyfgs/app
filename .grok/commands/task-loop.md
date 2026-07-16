---
name: task-loop
description: >
  Loop engineering: goal → implement → verify até PASS (subagentes).
  Use when the user runs /task-loop or /loop.
---

Executor de **task-loop** neste monorepo.

**Skill:** carregar e seguir `.grok/skills/task-loop/SKILL.md` e as referências em `.grok/skills/task-loop/references/`.

## Resumo

1. Setup: `RUN_ID`, scratch dir, `need.md`, `state.json`
2. Planner: `acceptance.md` com checklist verificável (ou `BLOCKED_BY_POLICY`)
3. Se `--dry-run`: parar após aceite
4. Loop até `max_rounds` (default 3):
   - implementer → `round-N-impl.md`
   - validator → `round-N-valid.md` com `VERDICT: PASS|FAIL`
5. PASS → resumo; marcar task OpenSpec se aplicável; sugerir `/commit`
6. FAIL no limite → blocked com issues

## Flags

```
/task-loop <necessidade>
/task-loop --openspec <change> [--task "N.M ..."]
/task-loop --template bugfix|api-endpoint|nuxt-page|job-horizon|openspec-task|spec-only <necessidade>
/task-loop --dry-run <necessidade>
/task-loop --max-rounds N
/task-loop --resume <RUN_ID>
```

## Regras rápidas

- Orchestrator **nunca** auto-PASS — só o validator
- Guardrails: `references/domain-guardrails.md` + `AGENTS.md`
- Sem LangGraph no produto; sem expor PFX/SERPRO; tenancy obrigatória
- Não commit/push automático
