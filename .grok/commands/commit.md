---
name: commit
description: Criar commit(s) Git com Conventional Commits em pt-BR. Use when the user runs /commit or /git-commit.
---

Criar commit(s) de alta qualidade neste repositório.

**Skill:** carregar e seguir `.grok/skills/git-commit/SKILL.md` (e a referência em `references/conventional-commits.md` se necessário).

**Passos resumidos**

1. `git status`, `git diff` / `git diff --cached`, `git log --oneline -8`
2. Decidir se um ou vários commits (dividir mudanças não relacionadas)
3. Stage seletivo (paths explícitos ou `git add -p`)
4. Revisar staged: sem segredos, sem debug, sem churn
5. Mensagem Conventional Commits: type/scope em inglês, subject/body em **pt-BR**
6. Commit via HEREDOC; repetir até o working tree desejado
7. Reportar hash + mensagem de cada commit; **não** push salvo pedido explícito

**Segurança:** nunca alterar git config; nunca `--no-verify` / force / reset destrutivo sem pedido; nunca commitar PFX, PEM, `.env` com segredos ou `VAULT_MASTER_KEY`.
