---
name: task-loop
description: >
  Executor de loop engineering (goal → implement → verify até PASS).
  Orquestra subagentes planner/implementer/validator até critérios de
  aceite verificáveis ou esgotar rodadas. Use when the user runs /task-loop,
  /loop, asks for "loop até validar", "loop engineering", "rodar até passar",
  implementer+verifier, closed loop com checklist, ou validar uma necessidade/tarefa
  com subagentes.
license: MIT
metadata:
  author: project
  version: "1.0"
  inspiredBy:
    - check-work (verifier loop)
    - execute-plan (roles implementer/reviewer)
    - loop engineering 2026 (goal + verifier + cap)
---

# Task Loop — loop engineering no monorepo

Skill de **workflow de desenvolvimento** (harness Grok). Não instala LangGraph/CrewAI no produto e não adiciona agente LLM no backend fiscal.

Você é o **orchestrator**. Não marque sucesso sozinho: só o **validator** emite `VERDICT: PASS|FAIL`.

## Invocation

```
/task-loop <necessidade ou tarefa>
/task-loop --openspec <change> [--task "N.M ..."]
/task-loop --template <nome> <necessidade>
/task-loop --dry-run <necessidade>
/task-loop --max-rounds N
/task-loop --resume <RUN_ID>
```

Aliases mentais: `/loop` → esta skill.

| Flag | Default | Efeito |
|------|---------|--------|
| (texto livre) | mode `free` | Necessidade descrita pelo usuário |
| `--openspec` | — | mode `openspec`; carrega change + tasks |
| `--task` | — | Uma checkbox específica de `tasks.md` |
| `--template` | auto | Pré-preenche aceite (`openspec-task`, `bugfix`, `api-endpoint`, `nuxt-page`, `job-horizon`, `spec-only`) |
| `--dry-run` | false | Só planner + `acceptance.md`; não implementa |
| `--max-rounds` | 3 | Cap de implement→validate |
| `--resume` | — | Continua run existente |

Comunicar status ao usuário sempre em **pt-BR**.

## Artefatos e paths

No início de um run novo:

```bash
RUN_ID=$(python3 -c "import uuid; print(uuid.uuid4().hex[:8])")
SCRATCH="${TMPDIR:-/tmp}/grok-$(id -u)/task-loop-${RUN_ID}"
mkdir -p "$SCRATCH" && chmod 700 "${TMPDIR:-/tmp}/grok-$(id -u)" 2>/dev/null || true
echo "$SCRATCH"
```

Arquivos (inline o path absoluto; não depender de env entre shells):

| Arquivo | Conteúdo |
|---------|----------|
| `need.md` | Necessidade original + contexto |
| `acceptance.md` | Goal + checklist verificável + fora de escopo + evidências |
| `state.json` | round, status, verdicts, ids de subagente |
| `round-N-impl.md` | Resumo do implementer |
| `round-N-valid.md` | Relatório do validator + VERDICT |
| `guardrails.md` | Cópia/snippet de `references/domain-guardrails.md` |

Ler uma vez e reusar:

- `AGENTS.md` (raiz do repo)
- `.grok/skills/task-loop/references/domain-guardrails.md`
- `.grok/skills/task-loop/references/templates.md`
- prompts em `references/*-prompt.md`

## state.json (mínimo)

```json
{
  "run_id": "<RUN_ID>",
  "mode": "free|openspec",
  "status": "planning|looping|passed|blocked|dry_run",
  "round": 0,
  "max_rounds": 3,
  "template": null,
  "openspec_change": null,
  "openspec_task": null,
  "acceptance_path": "<SCRATCH>/acceptance.md",
  "last_verdict": null,
  "implementer_id": null,
  "validator_id": null,
  "planner_id": null
}
```

Persistir após cada transição de status.

## Step 0 — Setup

1. Gerar `RUN_ID` + `SCRATCH` (ou restaurar se `--resume`).
2. Parsear flags e texto da necessidade.
3. Escrever `need.md`.
4. Se mode `openspec`:
   ```bash
   openspec status --change "<name>" --json
   openspec instructions apply --change "<name>" --json
   ```
   Ler `contextFiles` e, se `--task`, localizar o item em `tasks.md`.
5. Copiar/injetar guardrails do domínio no run.
6. Anunciar: `run_id`, mode, max_rounds, change/task se houver.

**`--resume`:** ler `state.json` + último `round-*-valid.md`; se `passed`/`blocked`, reportar e parar; senão retomar no Step 2 com issues abertas.

## Step 1 — Definir “pronto” (planner)

Objetivo: `acceptance.md` com **goal verificável** (closed loop). Sem goal fraco, o loop vira slop.

1. Escolher template (`references/templates.md`) se `--template` ou por heurística.
2. Se critérios já estão explícitos e completos no pedido: formalizar em `acceptance.md` sem subagente.
3. Caso contrário, spawn planner:

```
spawn_subagent:
  subagent_type: explore   # ou general-purpose com capability_mode read-only
  description: "[planner] critérios de aceite"
  background: false
  capability_mode: read-only
  prompt: (ler e injetar references/planner-prompt.md)
    + need.md
    + guardrails
    + trechos OpenSpec se mode openspec
    + caminho de saída: <SCRATCH>/acceptance.md
```

4. Orchestrator **lê** `acceptance.md` e confere:
   - checklist com itens observáveis
   - evidências exigidas
   - fora de escopo
   - sem violar non-goals (portal contribuinte, scraping, expor PFX/SERPRO, etc.)

5. Se pedido viola política de domínio → `status: blocked`, `BLOCKED_BY_POLICY`, **não** implementar.

6. Se ambiguidade alta (goal vago e sem OpenSpec) → **uma** pergunta via `ask_user_question` ou pergunta curta; senão seguir com o melhor checklist.

7. Mostrar checklist resumido ao usuário.

8. Se `--dry-run` → `status: dry_run`, parar com path de `acceptance.md`.

## Step 2 — Loop implement → validate

```
round = 1
while round <= max_rounds:
  implementer(round)
  validator(round)
  if VERDICT == PASS: goto Step 3
  if round == max_rounds: goto Step 4
  round += 1  # issues abertas vão para o próximo implementer
```

### 2a. Implementer

```
spawn_subagent:
  subagent_type: general-purpose
  description: "[implementer] round <N>"
  background: false
  prompt: (references/implementer-prompt.md)
    + acceptance.md
    + guardrails / AGENTS.md highlights
    + se N>1: round-(N-1)-valid.md (issues abertas)
    + saída: <SCRATCH>/round-N-impl.md
```

MVP: **workspace compartilhado** (sem `isolation: worktree`). Diff mínimo; só o que fecha o checklist.

Guardar `implementer_id`. Após conclusão, confirmar que `round-N-impl.md` existe.

### 2b. Validator (obrigatório, subagente **separado**)

Não reutilizar o transcript do implementer como juiz.

```
spawn_subagent:
  subagent_type: general-purpose
  description: "[validator] round <N>"
  background: false
  prompt: (references/validator-prompt.md)
    + acceptance.md (única fonte de “done”)
    + guardrails
    + round-N-impl.md
    + saída: <SCRATCH>/round-N-valid.md
```

Validator deve:

- Conferir **cada** item do checklist com evidência (arquivo, comando, teste)
- Rodar checks baratos relevantes (testes filtrados, lint, grep tenancy/secrets se o critério pede)
- Terminar com exatamente uma linha:
  - `VERDICT: PASS` ou `VERDICT: FAIL`

Orchestrator lê o arquivo e o verdict. **Nunca** inventar PASS.

Atualizar `state.json`: `last_verdict`, `round`, `status: looping`.

Reportar ao usuário: round N/M, PASS/FAIL, issues principais.

## Step 3 — Passed

1. `status: passed`
2. Resumo em pt-BR: goal, rounds, evidências, arquivos tocados
3. Mode OpenSpec + task identificada: marcar `- [ ]` → `- [x]` **somente** nesse item de `tasks.md` (e só após PASS)
4. Se houver diff: sugerir `/commit` — **não** commitar sozinho
5. Paths: `SCRATCH`, `acceptance.md`, último valid

## Step 4 — Blocked (fail após max rounds ou policy)

1. `status: blocked`
2. Listar issues remanescentes + o que falta no checklist
3. Opções: `--resume <RUN_ID>`, afrouxar critério (usuário edita), design OpenSpec, parar
4. Não esconder falha

## Relação com outras skills

| Skill | Relação |
|-------|---------|
| `/opsx-apply` | Várias tasks OpenSpec em sequência; **task-loop** = uma necessidade com verifier rígido |
| `/check-work` | Verifier genérico de sessão; task-loop ancora em `acceptance.md` e loopa implementer |
| `/execute-plan` | Stack de PRs/worktrees — não usar aqui |
| `/commit` | Após PASS, se o usuário quiser |
| `panel-ui` | Implementer de UI deve seguir se o goal tocar `frontend/` |

## Guardrails do orchestrator

- pt-BR com o usuário
- **Verifier é o bottleneck**: sem PASS do validator, não há sucesso
- max_rounds sempre honrado
- Diff mínimo; sem refactors fora do checklist
- Sem expor PFX, senhas, Consumer Secret, Termo, PEM, `VAULT_MASTER_KEY`
- Sem portal de contribuinte, scraping, CAPTCHA, Gov.br
- Stack fixa: Laravel 13 / Nuxt 4 / Nginx same-origin — não inventar
- Tenancy: `office_id` nunca confiar no client
- Não push, não force, não `--no-verify`
- Não rodar agent loop LLM no runtime fiscal sem change OpenSpec explícita

## Anti-padrões

| Evitar | Preferir |
|--------|----------|
| “Parece pronto” sem VERDICT | Só PASS do validator |
| Goal vago (“melhorar a tela”) | Checklist mensurável em `acceptance.md` |
| Mesmo subagente implementa e valida | Papéis separados |
| Loop sem cap | `--max-rounds` default 3 |
| Implementar non-goal | BLOCKED_BY_POLICY no planner |
| Marcar várias tasks OpenSpec | Uma task por PASS |

## Referências

- [domain-guardrails.md](references/domain-guardrails.md)
- [templates.md](references/templates.md)
- [planner-prompt.md](references/planner-prompt.md)
- [implementer-prompt.md](references/implementer-prompt.md)
- [validator-prompt.md](references/validator-prompt.md)
