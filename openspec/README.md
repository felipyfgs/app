# OpenSpec — hub fiscal

Fonte de verdade de **capabilities** e **changes**, versionada no git com o monorepo.

Config injetada nos artefatos: [`config.yaml`](./config.yaml). Stack/tenancy/segurança: `AGENTS.md` na raiz.

## Layout

| Path | Papel | Git |
|------|--------|-----|
| `specs/<capability>/spec.md` | Contrato permanente (SHALL **agora**) | Sim |
| `changes/<nome>/` | Change **ativa** | Sim |
| `changes/archive/YYYY-MM-DD-<nome>/` | Histórico pós-archive | Sim |
| `config.yaml` | Context + rules para agents | Sim |

## Ciclo

```text
/opsx-explore (opcional)
    → /opsx-propose   # change pequena; commit docs cedo
    → /opsx-apply     # código + testes
    → verify          # pint/test/lint/verify.sh
    → /opsx-archive   # sync deltas → specs/ + move archive/
    → git commit      # docs(openspec): …  (mesmo dia)
```

### Tamanho de change

- **1** capability principal (máx. **2** se inseparáveis).
- Preferir **≤ ~20** tasks; se estourar, fatiar.
- Non-goals explícitos: live smoke SERPRO, ticket externo, jurídico, mutações, flags ON.

### Tasks honestas

| Marcar `[x]` | Não marcar `[x]` |
|--------------|------------------|
| Teste/CI passou na máquina/CI | “Live ops-gated” sem evidence |
| Evidence preenchida (sem segredo) | Ticket SERPRO / aceite jurídico pendente |
| Scaffold de CLI/runbook (dizer “scaffold”) | Go-live real / canário faturável sem aprovação |

### Archive

1. Sync deltas → `specs/` (ou `--skip-specs` se só tooling/docs).
2. Mover para `changes/archive/YYYY-MM-DD-<nome>/`.
3. **Commitar** specs + archive.
4. Ajustar CI se ainda validava o nome da change ativa.

Não arquivar no disco e “commitar depois quando der”.

## CI

- **Sempre** `openspec validate --specs --strict` (main = source of truth).
- Changes ativas com `specs/**/spec.md` (deltas): `validate <change> --strict`.
- Change só com proposal/design (sem delta): **não** quebra CI; complete os artefatos antes do apply.
- Nunca validar path em `archive/`.

```bash
openspec list
openspec list --specs
openspec validate --specs --strict
openspec validate <change-ativa> --type change --strict
```

## Specs atuais

Ver `openspec list --specs` ou o diretório `specs/`. Antes de criar capability nova, reutilizar com `## MODIFIED Requirements`.

## Skills

| Skill / slash | Uso |
|---------------|-----|
| `openspec-explore` / `/opsx-explore` | Explorar sem comprometer escopo |
| `openspec-propose` / `/opsx-propose` | Criar change + artefatos |
| `openspec-apply-change` / `/opsx-apply` | Implementar tasks |
| `openspec-sync-specs` / `/opsx-sync` | Só sync main specs |
| `openspec-archive-change` / `/opsx-archive` | Sync + archive |

Engines (`.grok/`, `.codex/`, `.opencode/`) são **locais** (gitignored); o contrato do produto é `openspec/` + código.
